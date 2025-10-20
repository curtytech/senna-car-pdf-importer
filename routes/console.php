<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\TestePdfConverterCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Registrar comando de teste do PDF converter
Artisan::command('pdf:teste-converter {arquivo}', function ($arquivo) {
    $command = new TestePdfConverterCommand();
    $command->setLaravel($this->getLaravel());
    return $command->handle();
})->purpose('Testa a conversão de PDF para texto usando PrinsFrank');
