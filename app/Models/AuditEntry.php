<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEntry extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'invoice_id', 'action', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
