<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['user_id', 'name', 'email', 'contact_name'];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
