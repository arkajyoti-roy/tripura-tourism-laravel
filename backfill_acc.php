<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$accs = \App\Models\Accommodation::whereIn('type', [2, 4])->whereNull('is_active')->get();
foreach ($accs as $a) {
    // Computes date-based active status (true/false) and writes 1 or 0 to DB
    $a->is_active = $a->is_active;
    $a->save();
}

echo "BACKFILLED_" . count($accs) . "_ACCOMMODATIONS\n";

