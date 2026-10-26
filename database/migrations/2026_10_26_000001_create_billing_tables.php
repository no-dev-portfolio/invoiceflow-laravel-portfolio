<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('name', 80);
            $t->string('email')->nullable();
            $t->string('contact_name', 80)->nullable();
            $t->timestamps();
            $t->index(['user_id', 'name']);
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('number')->unique();
            $t->string('title', 120);
            $t->date('issue_date');
            $t->date('due_date');
            $t->unsignedInteger('tax_rate');
            $t->unsignedBigInteger('subtotal');
            $t->unsignedBigInteger('tax_amount');
            $t->unsignedBigInteger('total_amount');
            $t->unsignedBigInteger('paid_amount')->default(0);
            $t->string('status', 16)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->index(['user_id', 'status', 'due_date']);
        });
        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->string('description', 120);
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('unit_price');
            $t->unsignedBigInteger('amount');
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('amount');
            $t->date('paid_on');
            $t->uuid('request_token');
            $t->string('note', 120)->nullable();
            $t->timestamps();
            $t->unique(['invoice_id', 'request_token']);
        });
        Schema::create('audit_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('action', 40);
            $t->json('metadata');
            $t->timestamp('created_at');
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['audit_entries', 'payments', 'invoice_items', 'invoices', 'customers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
