<?php

namespace App\Filament\Resources\PdfTextConverterResource\Pages;

use App\Filament\Resources\PdfTextConverterResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPdfTextConverter extends ViewRecord
{
    protected static string $resource = PdfTextConverterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            
            Actions\Action::make('processar')
                ->label('Processar Novamente')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->record->status !== 'processing')
                ->action(function () {
                    PdfTextConverterResource::processarPdf($this->record);
                }),
        ];
    }
}