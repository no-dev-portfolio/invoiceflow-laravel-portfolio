<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\BillingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('デモデータはlocal/testing環境のみ作成できます。');
        }
        if (User::where('email', 'demo@invoiceflow.test')->exists()) {
            return;
        }
        $u = User::create(['name' => 'デモ担当者', 'email' => 'demo@invoiceflow.test', 'password' => Hash::make('DemoReview!2026')]);
        User::create(['name' => '別担当者', 'email' => 'other@invoiceflow.test', 'password' => Hash::make('DemoReview!2026')]);
        $s = app(BillingService::class);
        $customers = [];
        foreach ([['株式会社ブルーム', '経理部', 'bloom@example.test'], ['合同会社ノース', '管理部', 'north@example.test'], ['株式会社リーフ', '制作部', 'leaf@example.test']] as [$name,$person,$email]) {
            $customers[] = $s->createCustomer($u, ['name' => $name, 'contact_name' => $person, 'email' => $email]);
        }
        $definitions = [['Webサイト保守・運用', 180000, 1, 0], ['業務システム機能追加', 320000, 0, 120000], ['データ整備・移行支援', 145000, 2, 159500], ['管理画面のUI改修', 95000, 1, 0]];
        foreach ($definitions as $n => [$title,$price,$customer,$paid]) {
            $issue = today()->subDays(25)->format('Y-m-d');
            $due = today()->addDays($n === 1 ? -3 : 10 + $n)->format('Y-m-d');
            $i = $s->saveDraft($u, ['customer_id' => $customers[$customer]->id, 'title' => $title, 'issue_date' => $issue, 'due_date' => $due, 'tax_rate' => 10, 'items' => [['description' => $title, 'quantity' => 1, 'unit_price' => $price]]]);
            if ($n < 3) {
                $i = $s->issue($u, $i->id, $i->version);
            }
            if ($paid > 0) {
                $s->pay($u, $i->id, ['amount' => $paid, 'paid_on' => today()->subDay()->format('Y-m-d'), 'request_token' => (string) Str::uuid(), 'note' => '架空の入金記録']);
            }
        }
    }
}
