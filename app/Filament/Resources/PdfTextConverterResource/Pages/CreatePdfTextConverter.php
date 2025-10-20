<?php

namespace App\Filament\Resources\PdfTextConverterResource\Pages;

use App\Filament\Resources\PdfTextConverterResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePdfTextConverter extends CreateRecord
{
    protected static string $resource = PdfTextConverterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Adicionar informações do arquivo
        if (isset($data['file_path'])) {
            $filePath = $data['file_path'];
            $fullPath = storage_path('app/public/' . $filePath);
            
            if (file_exists($fullPath)) {
                $data['file_name'] = basename($filePath);
                $data['original_name'] = basename($filePath);
                $data['file_size'] = filesize($fullPath);
                $data['mime_type'] = mime_content_type($fullPath) ?: 'application/pdf';
            }
        }
        
        // Adicionar usuário atual
        $data['uploaded_by'] = Auth::id();
        $data['status'] = 'pending';
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Processar automaticamente após criar
        PdfTextConverterResource::processarPdf($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}