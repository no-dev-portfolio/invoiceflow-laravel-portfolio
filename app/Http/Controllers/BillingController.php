<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Http\Requests\PaymentRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    public function dashboard(Request $r)
    {
        $all = Invoice::where('user_id', $r->user()->id)->with('customer')->get();
        $open = $all->where('status', 'issued');

        return view('dashboard', ['recent' => $all->sortByDesc('id')->take(5), 'openAmount' => $open->sum(fn ($i) => $i->balance()), 'paidAmount' => $all->sum('paid_amount'), 'overdueAmount' => $open->filter->overdue()->sum(fn ($i) => $i->balance()), 'openCount' => $open->count(), 'customerCount' => Customer::where('user_id', $r->user()->id)->count()]);
    }

    public function customers(Request $r)
    {
        return view('customers.index', ['customers' => Customer::where('user_id', $r->user()->id)->latest('id')->paginate(15)]);
    }

    public function storeCustomer(Request $r, BillingService $service)
    {
        $data = $r->validate(['name' => 'required|string|max:80', 'email' => 'nullable|email|max:255', 'contact_name' => 'nullable|string|max:80']);
        $service->createCustomer($r->user(), $data);

        return back()->with('success', '顧客を登録しました。');
    }

    public function index(Request $r)
    {
        $filters = $r->validate(['q' => 'nullable|string|max:80', 'status' => 'nullable|in:draft,issued,paid,void']);
        $query = Invoice::where('user_id', $r->user()->id)->with('customer');
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }
        if ($filters['q'] ?? null) {
            $q = $filters['q'];
            $query->where(fn ($b) => $b->where('number', 'like', '%'.$q.'%')->orWhere('title', 'like', '%'.$q.'%')->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$q.'%')));
        }

        return view('invoices.index', ['invoices' => $query->latest('id')->paginate(12)->withQueryString(), 'filters' => $filters]);
    }

    private function owned(Request $r, int $id): Invoice
    {
        return Invoice::where('user_id', $r->user()->id)->with(['customer', 'items', 'payments', 'audits'])->findOrFail($id);
    }

    public function create(Request $r)
    {
        return view('invoices.form', ['invoice' => null, 'customers' => Customer::where('user_id', $r->user()->id)->orderBy('name')->get()]);
    }

    public function store(InvoiceRequest $r, BillingService $s)
    {
        $i = $s->saveDraft($r->user(), $r->validated());

        return redirect()->route('invoices.show', $i)->with('success', '下書きを作成しました。内容を確認して発行してください。');
    }

    public function show(Request $r, int $invoice)
    {
        return view('invoices.show', ['invoice' => $this->owned($r, $invoice), 'token' => (string) Str::uuid()]);
    }

    public function edit(Request $r, int $invoice)
    {
        $i = $this->owned($r, $invoice);
        abort_unless($i->status === 'draft', 403);

        return view('invoices.form', ['invoice' => $i, 'customers' => Customer::where('user_id', $r->user()->id)->orderBy('name')->get()]);
    }

    public function update(InvoiceRequest $r, int $invoice, BillingService $s)
    {
        $this->owned($r, $invoice);
        $s->saveDraft($r->user(), $r->validated(), $invoice);

        return redirect()->route('invoices.show', $invoice)->with('success', '下書きを更新しました。');
    }

    public function issue(Request $r, int $invoice, BillingService $s)
    {
        $data = $r->validate(['version' => 'required|integer|min:1']);
        $s->issue($r->user(), $invoice, (int) $data['version']);

        return back()->with('success', '請求書を発行しました。');
    }

    public function void(Request $r, int $invoice, BillingService $s)
    {
        $data = $r->validate(['version' => 'required|integer|min:1']);
        $s->void($r->user(), $invoice, (int) $data['version']);

        return back()->with('success', '請求書を取り消しました。');
    }

    public function pay(PaymentRequest $r, int $invoice, BillingService $s)
    {
        $s->pay($r->user(), $invoice, $r->validated());

        return back()->with('success', '入金を記録しました。同じ送信IDの再送は二重登録しません。');
    }
}
