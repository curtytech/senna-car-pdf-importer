<?php

namespace App\Filament\Resources\VendaClienteResource\Pages;

use App\Filament\Resources\VendaClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewVendaCliente extends ViewRecord
{
    protected static string $resource = VendaClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}