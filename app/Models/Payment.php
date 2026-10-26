<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['amount', 'paid_on', 'request_token', 'note'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_on' => 'immutable_date'];
    }
}
