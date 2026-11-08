<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('justm:status', function () {
    $this->info('Just-M Rebuild V2 is bootstrapped.');
})->purpose('Check the Just-M application bootstrap');
