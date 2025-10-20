<?php

namespace App\Filament\Resources\PdfTextConverterResource\Pages;

use App\Filament\Resources\PdfTextConverterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPdfTextConverter extends EditRecord
{
    protected static string $resource = PdfTextConverterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}