<?php

namespace App\Filament\Resources\VendaClienteResource\Pages;

use App\Filament\Resources\VendaClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVendaCliente extends EditRecord
{
    protected static string $resource = VendaClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}