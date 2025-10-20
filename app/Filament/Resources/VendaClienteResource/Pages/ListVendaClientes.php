<?php

namespace App\Filament\Resources\VendaClienteResource\Pages;

use App\Filament\Resources\VendaClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVendaClientes extends ListRecords
{
    protected static string $resource = VendaClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}