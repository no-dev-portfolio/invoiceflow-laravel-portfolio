<?php

use App\Models\User;
use App\Services\BillingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Standalone process used only by the SQLite concurrency integration tests.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing')) {
    fwrite(STDERR, 'testing environment required');
    exit(3);
}
config(['database.connections.sqlite.database' => $argv[1]]);
DB::purge('sqlite');
Date::setTestNow('2026-10-26 12:00:00');
try {
    $user = User::findOrFail((int) $argv[2]);
    $payment = app(BillingService::class)->pay($user, (int) $argv[3], ['amount' => (int) $argv[4], 'paid_on' => '2026-10-26', 'request_token' => $argv[5], 'note' => 'parallel']);
    echo json_encode(['payment_id' => $payment->id, 'amount' => $payment->amount]);
} catch (ValidationException $e) {
    echo json_encode(['rejected' => $e->errors()]);
    exit(2);
}
