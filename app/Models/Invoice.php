<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = ['user_id', 'customer_id', 'number', 'title', 'issue_date', 'due_date', 'tax_rate', 'subtotal', 'tax_amount', 'total_amount', 'paid_amount', 'status', 'version'];

    protected function casts(): array
    {
        return ['issue_date' => 'immutable_date', 'due_date' => 'immutable_date', 'subtotal' => 'integer', 'tax_amount' => 'integer', 'total_amount' => 'integer', 'paid_amount' => 'integer', 'version' => 'integer', 'tax_rate' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(AuditEntry::class)->latest('id');
    }

    public function balance(): int
    {
        return $this->total_amount - $this->paid_amount;
    }

    public function label(): string
    {
        return ['draft' => '下書き', 'issued' => '請求中', 'paid' => '入金済', 'void' => '取消'][$this->status];
    }

    public function overdue(): bool
    {
        return $this->status === 'issued' && $this->due_date->lt(today()) && $this->balance() > 0;
    }
}
