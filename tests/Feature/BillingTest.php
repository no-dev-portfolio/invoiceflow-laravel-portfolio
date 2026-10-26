<?php

namespace Tests\Feature;

use App\Models\AuditEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $other;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(12, 0));
        $this->owner = User::factory()->create(['email' => 'owner@example.test', 'password' => Hash::make('ValidPassword!123')]);
        $this->other = User::factory()->create();
        $this->customer = app(BillingService::class)->createCustomer($this->owner, ['name' => 'テスト顧客', 'email' => 'customer@example.test']);
    }

    private function payload(array $replace = []): array
    {
        return array_replace(['customer_id' => $this->customer->id, 'title' => 'システム開発', 'issue_date' => '2026-10-01', 'due_date' => '2026-10-31', 'tax_rate' => 10, 'items' => [['description' => '実装作業', 'quantity' => 3, 'unit_price' => 1001]]], $replace);
    }

    private function draft(): Invoice
    {
        return app(BillingService::class)->saveDraft($this->owner, $this->payload());
    }

    private function issued(): Invoice
    {
        $i = $this->draft();

        return app(BillingService::class)->issue($this->owner, $i->id, $i->version);
    }

    private function payment(int $amount, string $token = ''): array
    {
        return ['amount' => $amount, 'paid_on' => '2026-10-26', 'request_token' => $token ?: Str::uuid()->toString(), 'note' => '入金確認'];
    }

    public function test_guest_cannot_read_or_write_billing(): void
    {
        foreach (['/dashboard', '/customers', '/invoices', '/invoices/create'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/invoices', $this->payload())->assertRedirect('/login');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_login_logout_and_session_authentication(): void
    {
        $this->post('/login', ['email' => $this->owner->email, 'password' => 'ValidPassword!123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->owner);
        $this->get('/dashboard')->assertOk()->assertSee('ダッシュボード');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected_and_throttled(): void
    {
        for ($n = 0; $n < 5; $n++) {
            $this->post('/login', ['email' => $this->owner->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $this->owner->email, 'password' => 'ValidPassword!123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_csrf_is_required_outside_testing_bypass(): void
    {
        $this->app->instance('env', 'local');
        $this->post('/login', ['email' => $this->owner->email, 'password' => 'ValidPassword!123'])->assertStatus(419);
    }

    public function test_security_headers_and_authenticated_no_store(): void
    {
        $this->actingAs($this->owner)->get('/dashboard')->assertOk()->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_other_user_cannot_view_edit_issue_void_or_pay_invoice(): void
    {
        $i = $this->draft();
        $this->actingAs($this->other);
        $this->get('/invoices/'.$i->id)->assertNotFound();
        $this->get('/invoices/'.$i->id.'/edit')->assertNotFound();
        foreach (['issue', 'void'] as $path) {
            $this->post('/invoices/'.$i->id.'/'.$path, ['version' => 1])->assertNotFound();
        }
        $this->post('/invoices/'.$i->id.'/payments', $this->payment(1))->assertNotFound();
        $this->assertSame('draft', $i->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cross_owner_customer_cannot_be_assigned(): void
    {
        $c = app(BillingService::class)->createCustomer($this->other, ['name' => '他担当の顧客']);
        $this->actingAs($this->owner)->post('/invoices', $this->payload(['customer_id' => $c->id]))->assertSessionHasErrors('customer_id');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_dashboard_lists_and_search_do_not_leak_other_users_data(): void
    {
        $i = $this->draft();
        $this->actingAs($this->other);
        $this->get('/dashboard')->assertOk()->assertDontSee($i->number);
        $this->get('/invoices?q='.$i->number)->assertOk()->assertDontSee($i->title);
        $this->get('/customers')->assertDontSee($this->customer->name);
    }

    public function test_customer_creation_and_validation(): void
    {
        $this->actingAs($this->owner)->post('/customers', ['name' => '株式会社サンプル', 'email' => 'office@example.test'])->assertRedirect();
        $this->assertDatabaseHas('customers', ['user_id' => $this->owner->id, 'name' => '株式会社サンプル']);
        $this->post('/customers', ['name' => '', 'email' => 'not-email'])->assertSessionHasErrors(['name', 'email']);
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_blade_escapes_untrusted_customer_and_invoice_text(): void
    {
        $name = '<img src=x onerror=alert(1)>';
        $c = app(BillingService::class)->createCustomer($this->owner, ['name' => $name]);
        $i = app(BillingService::class)->saveDraft($this->owner, $this->payload(['customer_id' => $c->id, 'title' => '<script>alert(1)</script>']));
        $response = $this->actingAs($this->owner)->get('/invoices/'.$i->id)->assertOk();
        $response->assertSee(e($name), false)->assertDontSee($name, false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_invoice_create_calculates_integer_totals_and_audit(): void
    {
        $this->actingAs($this->owner)->post('/invoices', $this->payload())->assertRedirect();
        $i = Invoice::firstOrFail();
        $this->assertSame(3003, $i->subtotal);
        $this->assertSame(300, $i->tax_amount);
        $this->assertSame(3303, $i->total_amount);
        $this->assertSame('draft', $i->status);
        $this->assertSame(0, $i->paid_amount);
        $this->assertSame(1, $i->version);
        $this->assertDatabaseCount('invoice_items', 1);
        $this->assertDatabaseHas('audit_entries', ['invoice_id' => $i->id, 'action' => 'invoice.created']);
    }

    public function test_client_supplied_totals_owner_and_status_are_ignored(): void
    {
        $this->actingAs($this->owner)->post('/invoices', $this->payload(['user_id' => $this->other->id, 'total_amount' => 1, 'paid_amount' => 999999, 'status' => 'paid']))->assertRedirect();
        $i = Invoice::firstOrFail();
        $this->assertSame($this->owner->id, $i->user_id);
        $this->assertSame(3303, $i->total_amount);
        $this->assertSame('draft', $i->status);
        $this->assertSame(0, $i->paid_amount);
    }

    public function test_invalid_mixed_items_reject_entire_invoice(): void
    {
        $data = $this->payload();
        $data['items'][] = ['description' => '不正', 'quantity' => 0, 'unit_price' => 100];
        $this->actingAs($this->owner)->post('/invoices', $data)->assertSessionHasErrors('items.1.quantity');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertSame(0, AuditEntry::whereNotNull('invoice_id')->count());
    }

    public function test_amount_cap_is_atomic_and_zero_total_rejected(): void
    {
        foreach ([['quantity' => 10000, 'unit_price' => 1000000], ['quantity' => 1, 'unit_price' => 0]] as $row) {
            $data = $this->payload(['items' => [['description' => '境界'] + $row]]);
            $this->actingAs($this->owner)->post('/invoices', $data)->assertSessionHasErrors('items');
        }$this->assertDatabaseCount('invoices', 0);
    }

    public function test_due_before_issue_is_rejected(): void
    {
        $this->actingAs($this->owner)->post('/invoices', $this->payload(['due_date' => '2026-09-30']))->assertSessionHasErrors('due_date');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_draft_edit_requires_current_version(): void
    {
        $i = $this->draft();
        $data = $this->payload(['version' => 1, 'title' => '変更後']);
        $this->actingAs($this->owner)->put('/invoices/'.$i->id, $data)->assertRedirect();
        $this->assertSame(2, $i->fresh()->version);
        $this->put('/invoices/'.$i->id, $this->payload(['version' => 1, 'title' => '古い更新']))->assertSessionHasErrors('version');
        $this->assertSame('変更後', $i->fresh()->title);
    }

    public function test_issued_invoice_is_immutable_and_issue_is_idempotent(): void
    {
        $i = $this->draft();
        $this->actingAs($this->owner)->post('/invoices/'.$i->id.'/issue', ['version' => 1])->assertRedirect();
        $this->post('/invoices/'.$i->id.'/issue', ['version' => 1])->assertRedirect();
        $this->assertSame(1, AuditEntry::where('action', 'invoice.issued')->count());
        $this->get('/invoices/'.$i->id.'/edit')->assertForbidden();
        $this->put('/invoices/'.$i->id, $this->payload(['version' => 2]))->assertSessionHasErrors('version');
    }

    public function test_partial_then_full_payment_and_audit(): void
    {
        $i = $this->issued();
        $this->actingAs($this->owner)->post('/invoices/'.$i->id.'/payments', $this->payment(1000))->assertRedirect();
        $this->assertSame(2303, $i->fresh()->balance());
        $this->assertSame('issued', $i->fresh()->status);
        $this->post('/invoices/'.$i->id.'/payments', $this->payment(2303))->assertRedirect();
        $this->assertSame('paid', $i->fresh()->status);
        $this->assertSame(0, $i->fresh()->balance());
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(2, AuditEntry::where('action', 'payment.recorded')->count());
    }

    public function test_overpayment_rejected_without_payment_or_audit(): void
    {
        $i = $this->issued();
        $this->actingAs($this->owner)->post('/invoices/'.$i->id.'/payments', $this->payment(3304))->assertSessionHasErrors('amount');
        $this->assertSame(0, $i->fresh()->paid_amount);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(0, AuditEntry::where('action', 'payment.recorded')->count());
    }

    public function test_duplicate_request_token_is_idempotent_after_full_payment(): void
    {
        $i = $this->issued();
        $data = $this->payment(3303);
        $this->actingAs($this->owner);
        for ($n = 0; $n < 2; $n++) {
            $this->post('/invoices/'.$i->id.'/payments', $data)->assertRedirect();
        }
        $this->assertSame(3303, $i->fresh()->paid_amount);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, AuditEntry::where('action', 'payment.recorded')->count());
    }

    public function test_same_token_with_different_content_is_rejected(): void
    {
        $i = $this->issued();
        $data = $this->payment(100);
        $this->actingAs($this->owner)->post('/invoices/'.$i->id.'/payments', $data)->assertRedirect();
        $data['amount'] = 101;
        $this->post('/invoices/'.$i->id.'/payments', $data)->assertSessionHasErrors('request_token');
        $this->assertSame(100, $i->fresh()->paid_amount);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_draft_and_void_invoices_do_not_accept_payments(): void
    {
        $i = $this->draft();
        $this->actingAs($this->owner)->post('/invoices/'.$i->id.'/payments', $this->payment(1))->assertSessionHasErrors('amount');
        $this->post('/invoices/'.$i->id.'/void', ['version' => 1])->assertRedirect();
        $this->post('/invoices/'.$i->id.'/payments', $this->payment(1))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_void_rejects_paid_and_stale_invoices(): void
    {
        $i = $this->issued();
        $this->actingAs($this->owner)->post('/invoices/'.$i->id.'/void', ['version' => 1])->assertSessionHasErrors('version');
        $this->post('/invoices/'.$i->id.'/payments', $this->payment(1))->assertRedirect();
        $this->post('/invoices/'.$i->id.'/void', ['version' => $i->fresh()->version])->assertSessionHasErrors('version');
        $this->assertSame('issued', $i->fresh()->status);
    }

    public function test_payment_date_must_be_between_issue_and_today(): void
    {
        $i = $this->issued();
        foreach (['2026-09-30', '2026-10-27', '2026-02-30'] as $date) {
            $data = $this->payment(1);
            $data['paid_on'] = $date;
            $this->actingAs($this->owner)->post('/invoices/'.$i->id.'/payments', $data)->assertSessionHasErrors('paid_on');
        }$this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_rejects_non_integer_and_missing_token(): void
    {
        $i = $this->issued();
        foreach (['0', '-1', '1.5', '1e2', '１２', '1000000001'] as $amount) {
            $data = $this->payment(1);
            $data['amount'] = $amount;
            $this->actingAs($this->owner)->post('/invoices/'.$i->id.'/payments', $data)->assertSessionHasErrors('amount');
        }
        $data = $this->payment(1);
        unset($data['request_token']);
        $this->post('/invoices/'.$i->id.'/payments', $data)->assertSessionHasErrors('request_token');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_pages_and_printable_detail_render(): void
    {
        $i = $this->issued();
        $this->actingAs($this->owner);
        foreach (['/dashboard', '/customers', '/invoices', '/invoices/create', '/invoices/'.$i->id] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_demo_seeder_is_idempotent_and_blocked_in_production(): void
    {
        $this->seed(DemoSeeder::class);
        $n = Invoice::count();
        $this->seed(DemoSeeder::class);
        $this->assertSame($n, Invoice::count());
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        app(DemoSeeder::class)->run();
    }

    public function test_malformed_old_input_can_be_rendered_safely(): void
    {
        $this->from('/login')->post('/login', ['email' => ['bad'], 'password' => 'x'])->assertSessionHasErrors('email');
        $this->get('/login')->assertOk();
        $this->actingAs($this->owner)->from('/customers')->post('/customers', ['name' => ['bad'], 'email' => ['bad'], 'contact_name' => ['bad']])->assertSessionHasErrors('name');
        $this->get('/customers')->assertOk();
        $this->from('/invoices/create')->post('/invoices', $this->payload(['title' => ['bad'], 'items' => 'invalid']))->assertSessionHasErrors(['title', 'items']);
        $this->get('/invoices/create')->assertOk();
        $this->from('/invoices/create')->post('/invoices', $this->payload(['items' => [['description' => ['bad'], 'quantity' => ['bad'], 'unit_price' => ['bad']]]]))->assertSessionHasErrors();
        $this->get('/invoices/create')->assertOk();
        $this->assertDatabaseCount('invoices', 0);
    }
}
