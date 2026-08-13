<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$url = rtrim(config('services.supabase.url'), '/');
$key = config('services.supabase.key');
$res = Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders(['apikey' => $key, 'Authorization' => 'Bearer ' . $key])->get($url . '/storage/v1/bucket');
echo $res->body();
