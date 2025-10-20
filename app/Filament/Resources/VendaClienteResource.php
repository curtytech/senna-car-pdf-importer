<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendaClienteResource\Pages;
use App\Models\VendaCliente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendaClienteResource extends Resource
{
    protected static ?string $model = VendaCliente::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    
    protected static ?string $navigationLabel = 'Vendas por Cliente';
    
    protected static ?string $modelLabel = 'Venda Cliente';
    
    protected static ?string $pluralModelLabel = 'Vendas Clientes';
    
    protected static ?string $navigationGroup = 'Relatórios';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('pdf_upload_id')
                    ->relationship('pdfUpload', 'title')
                    ->required()
                    ->label('PDF de Origem'),
                    
                Forms\Components\TextInput::make('cliente')
                    ->required()
                    ->maxLength(255)
                    ->label('Cliente'),
                    
                Forms\Components\DatePicker::make('primeira_venda')
                    ->label('Primeira Venda'),
                    
                Forms\Components\DatePicker::make('ultima_venda')
                    ->label('Última Venda'),
                    
                Forms\Components\TextInput::make('valor_venda_medio')
                    ->numeric()
                    ->prefix('R$')
                    ->label('Valor Venda Médio'),
                    
                Forms\Components\TextInput::make('quantidade_total_venda')
                    ->numeric()
                    ->label('Qtd. Total Venda'),
                    
                Forms\Components\TextInput::make('custo_venda_total')
                    ->numeric()
                    ->prefix('R$')
                    ->label('Custo Venda Total'),
                    
                Forms\Components\TextInput::make('total_devolucao')
                    ->numeric()
                    ->prefix('R$')
                    ->label('Total Devolução'),
                    
                Forms\Components\TextInput::make('custo_dev_total')
                    ->numeric()
                    ->prefix('R$')
                    ->label('Custo Dev. Total'),
                    
                Forms\Components\TextInput::make('total_venda')
                    ->numeric()
                    ->prefix('R$')
                    ->label('Total Venda'),
                    
                Forms\Components\TextInput::make('custo_venda')
                    ->numeric()
                    ->prefix('R$')
                    ->label('Custo Venda'),
                    
                Forms\Components\TextInput::make('total_devolucao')
                    ->numeric()
                    ->prefix('R$')
                    ->label('Total Devolução'),
                    
                Forms\Components\TextInput::make('custo_dev')
                    ->numeric()
                    ->prefix('R$')
                    ->label('Custo Dev.'),
                    
                Forms\Components\TextInput::make('total')
                    ->numeric()
                    ->prefix('R$')
                    ->label('Total'),
                    
                Forms\Components\TextInput::make('total_custo')
                    ->numeric()
                    ->prefix('R$')
                    ->label('Total Custo'),
                    
                Forms\Components\TextInput::make('lucro_reais')
                    ->numeric()
                    ->prefix('R$')
                    ->label('Lucro (R$)'),
                    
                Forms\Components\TextInput::make('lucro_percentual')
                    ->numeric()
                    ->suffix('%')
                    ->label('Lucro (%)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pdfUpload.title')
                    ->label('PDF')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('cliente')
                    ->label('Cliente')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('primeira_venda')
                    ->label('Primeira Venda')
                    ->date('d/m/Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('ultima_venda')
                    ->label('Última Venda')
                    ->date('d/m/Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('vl_vnd_medio')
                    ->label('Vl Vnd Médio')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('qtd')
                    ->label('Qtd.')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_venda')
                    ->label('Total Venda')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('custo_venda')
                    ->label('Custo Venda')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_devolucao')
                    ->label('Total Devolução')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('custo_dev')
                    ->label('Custo Dev.')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_custo')
                    ->label('Total Custo')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('lucro_reais')
                    ->label('Lucro (R$)')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('lucro_percentual')
                    ->label('Lucro (%)')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('pdf_upload_id')
                    ->relationship('pdfUpload', 'title')
                    ->label('PDF'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendaClientes::route('/'),
            'create' => Pages\CreateVendaCliente::route('/create'),
            'view' => Pages\ViewVendaCliente::route('/{record}'),
            'edit' => Pages\EditVendaCliente::route('/{record}/edit'),
        ];
    }
}