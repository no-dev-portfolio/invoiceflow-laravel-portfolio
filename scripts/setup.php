<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

// Local demo bootstrap only. Re-running preserves the existing APP_KEY and data.
$root = dirname(__DIR__);
chdir($root);
if (! is_file('vendor/autoload.php')) {
    fwrite(STDERR, "先に composer install を実行してください。\n");
    exit(1);
}
if (! is_file('.env')) {
    copy('.env.example', '.env');
}
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment(['local', 'testing'])) {
    fwrite(STDERR, "setup は local/testing 専用です。\n");
    exit(1);
}
if (! config('app.key')) {
    Artisan::call('key:generate', ['--force' => true]);
}
if (! is_file('database/database.sqlite')) {
    touch('database/database.sqlite');
}
Artisan::call('migrate', ['--force' => true]);
echo Artisan::output();
Artisan::call('db:seed', ['--force' => true]);
echo Artisan::output();
echo "準備完了: php artisan serve --host=127.0.0.1\n";
