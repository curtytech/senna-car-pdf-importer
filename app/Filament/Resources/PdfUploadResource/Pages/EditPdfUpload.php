<?php

namespace App\Filament\Resources\PdfUploadResource\Pages;

use App\Filament\Resources\PdfUploadResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPdfUpload extends EditRecord
{
    protected static string $resource = PdfUploadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}