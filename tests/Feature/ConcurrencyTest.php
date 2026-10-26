<?php

namespace Tests\Feature;

use App\Models\AuditEntry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    private string $database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(12, 0));
        $this->database = tempnam(sys_get_temp_dir(), 'invoiceflow-');
        config(['database.connections.sqlite.database' => $this->database]);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        @unlink($this->database);
        parent::tearDown();
    }

    private function invoice(): array
    {
        $u = User::factory()->create();
        $c = app(BillingService::class)->createCustomer($u, ['name' => '並行試験']);
        $i = app(BillingService::class)->saveDraft($u, ['customer_id' => $c->id, 'title' => '同時入金', 'issue_date' => '2026-10-01', 'due_date' => '2026-10-31', 'tax_rate' => 0, 'items' => [['description' => '試験', 'quantity' => 1, 'unit_price' => 1000]]]);
        $i = app(BillingService::class)->issue($u, $i->id, $i->version);

        return [$u, $i];
    }

    private function worker(User $u, Invoice $i, int $amount, string $token): Process
    {
        return new Process([PHP_BINARY, base_path('tests/Support/payment_worker.php'), $this->database, (string) $u->id, (string) $i->id, (string) $amount, $token], base_path(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->database], null, 20);
    }

    public function test_two_real_processes_cannot_overpay(): void
    {
        [$u,$i] = $this->invoice();
        $a = $this->worker($u, $i, 700, Str::uuid()->toString());
        $b = $this->worker($u, $i, 700, Str::uuid()->toString());
        $a->start();
        $b->start();
        $a->wait();
        $b->wait();
        $codes = [$a->getExitCode(), $b->getExitCode()];
        sort($codes);
        $this->assertSame([0, 2], $codes, $a->getErrorOutput().$b->getErrorOutput());
        $this->assertSame(700, $i->fresh()->paid_amount);
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, AuditEntry::where('action', 'payment.recorded')->count());
    }

    public function test_two_real_processes_with_same_token_record_once(): void
    {
        [$u,$i] = $this->invoice();
        $token = Str::uuid()->toString();
        $a = $this->worker($u, $i, 1000, $token);
        $b = $this->worker($u, $i, 1000, $token);
        $a->start();
        $b->start();
        $a->wait();
        $b->wait();
        $this->assertSame(0, $a->getExitCode(), $a->getErrorOutput());
        $this->assertSame(0, $b->getExitCode(), $b->getErrorOutput());
        $this->assertSame(1, Payment::count());
        $this->assertSame(1000, $i->fresh()->paid_amount);
        $this->assertSame('paid', $i->fresh()->status);
        $this->assertSame(1, AuditEntry::where('action', 'payment.recorded')->count());
    }
}
