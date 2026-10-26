<?php

namespace App\Services;

use App\Models\AuditEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public const MAX_TOTAL = 1_000_000_000;

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function audit(User $user, ?Invoice $invoice, string $action, array $metadata = []): void
    {
        AuditEntry::create(['user_id' => $user->id, 'invoice_id' => $invoice?->id, 'action' => $action, 'metadata' => $metadata, 'created_at' => now()]);
    }

    public function createCustomer(User $user, array $data): Customer
    {
        return DB::transaction(function () use ($user, $data) {
            $c = Customer::create(['user_id' => $user->id, 'name' => $data['name'], 'email' => $data['email'] ?? null, 'contact_name' => $data['contact_name'] ?? null]);
            $this->audit($user, null, 'customer.created', ['customer_id' => $c->id]);

            return $c;
        }, 5);
    }

    /** All currency is integer yen. Tax is a demo rule: floor(subtotal * rate / 100). */
    private function totals(array $data): array
    {
        $items = [];
        $subtotal = 0;
        if (count($data['items']) < 1 || count($data['items']) > 20) {
            $this->fail('items', '明細は1〜20行で指定してください。');
        }
        foreach ($data['items'] as $row) {
            $qty = filter_var($row['quantity'], FILTER_VALIDATE_INT);
            $price = filter_var($row['unit_price'], FILTER_VALIDATE_INT);
            if ($qty === false || $price === false || $qty < 1 || $qty > 10000 || $price < 0 || $price > 1000000) {
                $this->fail('items', '数量または単価が範囲外です。');
            }
            $amount = $qty * $price;
            $subtotal += $amount;
            if ($subtotal > self::MAX_TOTAL) {
                $this->fail('items', '請求総額は10億円以下で指定してください。');
            }
            $items[] = ['description' => $row['description'], 'quantity' => $qty, 'unit_price' => $price, 'amount' => $amount];
        }
        $rate = (int) $data['tax_rate'];
        if (! in_array($rate, [0, 10], true)) {
            $this->fail('tax_rate', 'デモ税率は0%または10%です。');
        }
        $tax = intdiv($subtotal * $rate, 100);
        $total = $subtotal + $tax;
        if ($total < 1 || $total > self::MAX_TOTAL) {
            $this->fail('items', '請求総額は1〜10億円で指定してください。');
        }

        return ['items' => $items, 'subtotal' => $subtotal, 'tax_amount' => $tax, 'total_amount' => $total];
    }

    public function saveDraft(User $user, array $data, ?int $id = null): Invoice
    {
        return DB::transaction(function () use ($user, $data, $id) {
            Customer::where('user_id', $user->id)->findOrFail($data['customer_id']);
            $values = $this->totals($data);
            $invoice = $id ? Invoice::where('user_id', $user->id)->lockForUpdate()->findOrFail($id) : new Invoice(['user_id' => $user->id, 'number' => 'TMP-'.Str::uuid()]);
            if ($id && ($invoice->status !== 'draft' || $invoice->version !== (int) $data['version'])) {
                $this->fail('version', 'この請求書は変更済み、または下書きではありません。再表示してください。');
            }
            $invoice->fill(collect($data)->only(['customer_id', 'title', 'issue_date', 'due_date', 'tax_rate'])->all());
            $invoice->fill(collect($values)->except('items')->all());
            if ($id) {
                $invoice->version++;
            }
            $invoice->save();
            if (! $id) {
                $invoice->number = 'INV-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT);
                $invoice->save();
            }
            $invoice->items()->delete();
            $invoice->items()->createMany($values['items']);
            $this->audit($user, $invoice, $id ? 'invoice.updated' : 'invoice.created', ['total' => $invoice->total_amount]);

            return $invoice->refresh();
        }, 5);
    }

    public function issue(User $user, int $id, int $version): Invoice
    {
        return DB::transaction(function () use ($user, $id, $version) {
            $i = Invoice::where('user_id', $user->id)->lockForUpdate()->findOrFail($id);
            if (in_array($i->status, ['issued', 'paid'], true)) {
                return $i;
            }
            if ($i->status !== 'draft' || $i->version !== $version) {
                $this->fail('version', '発行できるのは最新の下書きだけです。');
            }
            $i->update(['status' => 'issued', 'version' => $i->version + 1]);
            $this->audit($user, $i, 'invoice.issued');

            return $i;
        }, 5);
    }

    public function void(User $user, int $id, int $version): Invoice
    {
        return DB::transaction(function () use ($user, $id, $version) {
            $i = Invoice::where('user_id', $user->id)->lockForUpdate()->findOrFail($id);
            if ($i->status === 'void') {
                return $i;
            }
            if ($i->paid_amount > 0 || $i->version !== $version) {
                $this->fail('version', '入金のある請求書、または変更済みの請求書は取り消せません。');
            }
            $i->update(['status' => 'void', 'version' => $i->version + 1]);
            $this->audit($user, $i, 'invoice.voided');

            return $i;
        }, 5);
    }

    public function pay(User $user, int $id, array $data): Payment
    {
        return DB::transaction(function () use ($user, $id, $data) {
            $i = Invoice::where('user_id', $user->id)->lockForUpdate()->findOrFail($id);
            $existing = $i->payments()->where('request_token', $data['request_token'])->first();
            if ($existing) {
                if ($existing->amount !== (int) $data['amount'] || $existing->paid_on->format('Y-m-d') !== $data['paid_on'] || ($existing->note ?? '') !== ($data['note'] ?? '')) {
                    $this->fail('request_token', '同じ送信IDで異なる入金内容は登録できません。');
                }

                return $existing;
            }
            $amount = filter_var($data['amount'], FILTER_VALIDATE_INT);
            if ($amount === false || $amount < 1 || $amount > self::MAX_TOTAL || $i->status !== 'issued' || $amount > $i->balance()) {
                $this->fail('amount', '請求中の残額を超えない、1円以上の入金額を指定してください。');
            }
            if ($data['paid_on'] < $i->issue_date->format('Y-m-d') || $data['paid_on'] > today()->format('Y-m-d')) {
                $this->fail('paid_on', '入金日は請求日以降かつ今日以前で指定してください。');
            }
            $changed = Invoice::whereKey($i->id)->where('status', 'issued')->where('paid_amount', '<=', $i->total_amount - $amount)->increment('paid_amount', $amount, ['version' => DB::raw('version + 1')]);
            if ($changed !== 1) {
                $this->fail('amount', '残額が変更されています。再表示してください。');
            }
            $p = $i->payments()->create(['amount' => $amount, 'paid_on' => $data['paid_on'], 'request_token' => $data['request_token'], 'note' => $data['note'] ?? null]);
            $i->refresh();
            if ($i->balance() === 0) {
                $i->update(['status' => 'paid']);
            }
            $this->audit($user, $i, 'payment.recorded', ['payment_id' => $p->id, 'amount' => $amount]);

            return $p;
        }, 5);
    }
}
