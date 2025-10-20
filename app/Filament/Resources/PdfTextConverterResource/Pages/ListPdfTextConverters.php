<?php

namespace App\Filament\Resources\PdfTextConverterResource\Pages;

use App\Filament\Resources\PdfTextConverterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPdfTextConverters extends ListRecords
{
    protected static string $resource = PdfTextConverterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Novo Conversor')
                ->icon('heroicon-o-plus'),
        ];
    }
}