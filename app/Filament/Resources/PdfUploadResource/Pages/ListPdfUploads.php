<?php

namespace App\Filament\Resources\PdfUploadResource\Pages;

use App\Filament\Resources\PdfUploadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPdfUploads extends ListRecords
{
    protected static string $resource = PdfUploadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Novo Upload'),
        ];
    }
}