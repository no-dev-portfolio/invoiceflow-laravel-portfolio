<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['description', 'quantity', 'unit_price', 'amount'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'integer', 'amount' => 'integer'];
    }
}
