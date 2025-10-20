<?php

namespace App\Filament\Resources\PdfUploadResource\Pages;

use App\Filament\Resources\PdfUploadResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPdfUpload extends ViewRecord
{
    protected static string $resource = PdfUploadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}