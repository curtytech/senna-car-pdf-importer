<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PdfUploadResource\Pages;
use App\Models\PdfUpload;
use App\Models\VendaCliente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Smalot\PdfParser\Parser;

class PdfUploadResource extends Resource
{
    protected static ?string $model = PdfUpload::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';
    
    protected static ?string $navigationLabel = 'Upload de PDFs';
    
    protected static ?string $modelLabel = 'PDF Upload';
    
    protected static ?string $pluralModelLabel = 'PDF Uploads';
    
    protected static ?string $navigationGroup = 'Documentos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações do Documento')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Digite um título para o documento'),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->maxLength(500)
                            ->placeholder('Descrição opcional do documento')
                            ->rows(3),
                    ])
                    ->columns(1),
                
                Forms\Components\Section::make('Upload do Arquivo')
                    ->schema([
                        Forms\Components\FileUpload::make('file_path')
                            ->label('Arquivo PDF')
                            ->required()
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240) // 10MB
                            ->directory('pdf-uploads')
                            ->preserveFilenames()
                            ->downloadable()
                            ->openable()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $set('original_name', $state->getClientOriginalName());
                                }
                            }),
                    ])
                    ->columns(1),
                
                Forms\Components\Section::make('Metadados do Arquivo')
                    ->schema([
                        Forms\Components\TextInput::make('original_name')
                            ->label('Nome Original')
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\TextInput::make('file_size')
                            ->label('Tamanho do Arquivo')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($state) => $state ? number_format($state / 1024, 2) . ' KB' : ''),
                        
                        Forms\Components\TextInput::make('mime_type')
                            ->label('Tipo MIME')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->limit(50)
                    ->searchable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('original_name')
                    ->label('Nome do Arquivo')
                    ->searchable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('formatted_file_size')
                    ->label('Tamanho')
                    ->sortable('file_size')
                    ->toggleable(),
                                          
                Tables\Columns\TextColumn::make('vendasClientes_count')
                    ->label('Registros Extraídos')
                    ->counts('vendasClientes')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data de Upload')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('uploaded_by')
                    ->label('Enviado por')
                    ->relationship('uploader', 'name')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\Filter::make('created_at')
                    ->label('Data de Upload')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('De'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                
                Tables\Filters\Filter::make('processado')
                    ->label('Status de Processamento')
                    ->query(fn (Builder $query): Builder => $query->has('vendasClientes'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('processar')
                    ->label('Processar PDF')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('warning')
                    ->visible(fn (PdfUpload $record) => $record->vendasClientes()->count() === 0)
                    ->action(function (PdfUpload $record) {
                        try {
                            $fullPath = storage_path('app/public/' . $record->file_path);
                            
                            if (!file_exists($fullPath)) {
                                Notification::make()
                                    ->title('Erro')
                                    ->body('Arquivo PDF não encontrado.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $parser = new Parser();
                            $pdfDocument = $parser->parseFile($fullPath);
                            $text = $pdfDocument->getText();
                            
                            $dadosExtraidos = self::extrairDadosRelatorioSennacar($text);
                            
                            if (empty($dadosExtraidos)) {
                                Notification::make()
                                    ->title('Aviso')
                                    ->body('Nenhum dado de vendas foi encontrado no PDF.')
                                    ->warning()
                                    ->send();
                                return;
                            }
                            
                            foreach ($dadosExtraidos as $dados) {
                                VendaCliente::create([
                                    'pdf_upload_id' => $record->id,
                                    ...$dados
                                ]);
                            }
                            
                            Notification::make()
                                ->title('Sucesso!')
                                ->body("PDF processado com sucesso! " . count($dadosExtraidos) . " registros de vendas foram extraídos.")
                                ->success()
                                ->send();
                                
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Erro ao processar PDF')
                                ->body('Erro: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Processar PDF')
                    ->modalDescription('Deseja extrair os dados de vendas deste PDF?'),
                
                Tables\Actions\Action::make('processar_planilha')
                    ->label('Processar Planilha PDF')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->visible(fn (PdfUpload $record) => $record->vendasClientes()->count() === 0)
                    ->action(function (PdfUpload $record) {
                        try {
                            // Verificar se já temos o texto extraído
                            $text = $record->text_extrated;
                            
                            if (empty($text)) {
                                // Se não temos o texto, extrair do arquivo
                                $fullPath = storage_path('app/public/' . $record->file_path);
                                
                                if (!file_exists($fullPath)) {
                                    Notification::make()
                                        ->title('Erro')
                                        ->body('Arquivo PDF não encontrado.')
                                        ->danger()
                                        ->send();
                                    return;
                                }

                                $parser = new Parser();
                                $pdfDocument = $parser->parseFile($fullPath);
                                $text = $pdfDocument->getText();
                                
                                // Salvar o texto extraído para uso futuro
                                $record->update(['text_extrated' => $text]);
                            }
                            
                            $dadosExtraidos = self::extrairDadosPlanilhaPdf($text);
                            
                            if (empty($dadosExtraidos)) {
                                Notification::make()
                                    ->title('Aviso')
                                    ->body('Nenhum dado foi encontrado na planilha PDF.')
                                    ->warning()
                                    ->send();
                                return;
                            }
                            
                            foreach ($dadosExtraidos as $dados) {
                                VendaCliente::create([
                                    'pdf_upload_id' => $record->id,
                                    ...$dados
                                ]);
                            }
                            
                            Notification::make()
                                ->title('Sucesso!')
                                ->body("Planilha PDF processada com sucesso! " . count($dadosExtraidos) . " registros foram extraídos.")
                                ->success()
                                ->send();
                                
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Erro ao processar planilha PDF')
                                ->body('Erro: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Processar PDF')
                    ->modalDescription('Deseja extrair os dados de vendas deste PDF?'),
                
                Tables\Actions\Action::make('inserir_dados_manualmente')
                    ->label('Inserir Dados Manualmente')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->form([
                        Forms\Components\Repeater::make('vendas')
                            ->label('Dados de Vendas')
                            ->schema([
                                Forms\Components\TextInput::make('cliente')
                                    ->label('Cliente')
                                    ->required(),
                                
                                Forms\Components\DatePicker::make('primeira_venda')
                                    ->label('Primeira Venda'),
                                
                                Forms\Components\DatePicker::make('ultima_venda')
                                    ->label('Última Venda'),
                                
                                Forms\Components\TextInput::make('valor_venda_medio')
                                    ->label('Valor Venda Médio')
                                    ->numeric()
                                    ->prefix('R$'),
                                
                                Forms\Components\TextInput::make('quantidade_total_venda')
                                    ->label('Qtd. Total Venda')
                                    ->numeric(),
                                
                                Forms\Components\TextInput::make('custo_venda_total')
                                    ->label('Custo Venda Total')
                                    ->numeric()
                                    ->prefix('R$'),
                                
                                Forms\Components\TextInput::make('total_devolucao')
                                    ->label('Total Devolução')
                                    ->numeric()
                                    ->prefix('R$'),
                                
                                Forms\Components\TextInput::make('custo_dev_total')
                                    ->label('Custo Dev. Total')
                                    ->numeric()
                                    ->prefix('R$'),
                                
                                Forms\Components\TextInput::make('total_custo')
                                    ->label('Total Custo')
                                    ->numeric()
                                    ->prefix('R$'),
                                
                                Forms\Components\TextInput::make('lucro_reais')
                                    ->label('Lucro (R$)')
                                    ->numeric()
                                    ->prefix('R$'),
                                
                                Forms\Components\TextInput::make('lucro_percentual')
                                    ->label('Lucro (%)')
                                    ->numeric()
                                    ->suffix('%'),
                            ])
                            ->columns(3)
                            ->addActionLabel('Adicionar Cliente')
                            ->defaultItems(1)
                    ])
                    ->action(function (PdfUpload $record, array $data) {
                        foreach ($data['vendas'] as $venda) {
                            VendaCliente::create([
                                'pdf_upload_id' => $record->id,
                                'cliente' => $venda['cliente'],
                                'primeira_venda' => $venda['primeira_venda'],
                                'ultima_venda' => $venda['ultima_venda'],
                                'valor_venda_medio' => $venda['valor_venda_medio'],
                                'quantidade_total_venda' => $venda['quantidade_total_venda'],
                                'custo_venda_total' => $venda['custo_venda_total'],
                                'total_devolucao' => $venda['total_devolucao'],
                                'custo_dev_total' => $venda['custo_dev_total'],
                                'total_custo' => $venda['total_custo'],
                                'lucro_reais' => $venda['lucro_reais'],
                                'lucro_percentual' => $venda['lucro_percentual'],
                            ]);
                        }
                        
                        Notification::make()
                            ->title('Sucesso!')
                            ->body(count($data['vendas']) . ' registros de vendas foram inseridos.')
                            ->success()
                            ->send();
                    })
                    ->modalWidth('7xl'),
                
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (PdfUpload $record): string => Storage::url($record->file_path))
                    ->openUrlInNewTab(),
                
                Tables\Actions\Action::make('ver_vendas')
                    ->label('Ver Vendas')
                    ->icon('heroicon-o-chart-bar')
                    ->color('success')
                    ->visible(fn (PdfUpload $record) => $record->vendasClientes()->count() > 0)
                    ->url(fn (PdfUpload $record): string => VendaClienteResource::getUrl('index', ['tableFilters[pdf_upload_id][value]' => $record->id])),
                
                Tables\Actions\Action::make('exportar_csv')
                    ->label('Exportar CSV')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->action(function (PdfUpload $record) {
                        return static::processarEExportarCSV($record);
                    }),
                
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('ver_texto')
                    ->label('Ver Texto Extraído')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->visible(fn (PdfUpload $record) => !empty($record->text_extrated))
                    ->modalHeading('Texto Extraído do PDF')
                    ->modalDescription(fn (PdfUpload $record) => "Arquivo: {$record->original_name}")
                    ->modalContent(fn (PdfUpload $record) => view('filament.modals.texto-extraido', [
                        'texto' => $record->text_extrated,
                        'totalLinhas' => substr_count($record->text_extrated, "\n") + 1,
                        'totalCaracteres' => strlen($record->text_extrated)
                    ]))
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),

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
            'index' => Pages\ListPdfUploads::route('/'),
            'create' => Pages\CreatePdfUpload::route('/create'),
            'view' => Pages\ViewPdfUpload::route('/{record}'),
            'edit' => Pages\EditPdfUpload::route('/{record}/edit'),
        ];
    }

    private static function extrairDadosPlanilhaPdf(string $text): array
    {
        $dados = [];
        $lines = explode("\n", $text);
        
        // Log para debug
        \Log::info("Processando planilha PDF - Total de linhas: " . count($lines));
        
        $headerFound = false;
        $columnMapping = [];
        
        foreach ($lines as $index => $line) {
            $line = trim($line);
            
            // Pular linhas vazias
            if (empty($line)) {
                continue;
            }
            
            // Log da linha atual
            \Log::info("Linha {$index}: " . $line);
            
            // Detectar cabeçalho da planilha e mapear colunas
            if (!$headerFound && self::detectarCabecalhoEMapearColunas($line, $columnMapping)) {
                $headerFound = true;
                \Log::info("Cabeçalho encontrado na linha {$index}. Mapeamento: " . json_encode($columnMapping));
                continue;
            }
            
            // Se já encontrou o cabeçalho, processar dados
            if ($headerFound && self::isLinhaValidaPlanilha($line)) {
                \Log::info("Processando linha de dados {$index}");
                $dadosLinha = self::parsearLinhaPlanilhaComMapeamento($line, $columnMapping);
                
                if ($dadosLinha && self::validarDadosCliente($dadosLinha)) {
                    \Log::info("Dados válidos extraídos: " . json_encode($dadosLinha));
                    $dados[] = $dadosLinha;
                }
            }
        }
        
        \Log::info("Total de registros extraídos da planilha: " . count($dados));
        return $dados;
    }

    private static function detectarCabecalhoEMapearColunas(string $line, array &$columnMapping): bool
    {
        // Padrões mais específicos para identificar cada coluna
        $patterns = [
            'cliente' => '/cliente/i',
            'qtd' => '/qtd\.?\s*/i',
            'total_venda' => '/total\s*venda/i',
            'custo_venda' => '/custo\s*venda/i',
            'total_devolucao' => '/total\s*devolu[çc][aã]o/i',
            'custo_dev' => '/custo\s*dev\.?/i',
            'total_custo' => '/total\s*custo/i',
            'vl_vnd_medio' => '/vl\s*vnd\s*m[eé]dio/i',
            'lucro_reais' => '/lucro\s*\(r\$\)/i',
            'lucro_percentual' => '/lucro\s*\(%\)/i',
            'primeira_venda' => '/primeira\s*venda/i',
            'ultima_venda' => '/[uú]ltima\s*venda/i',
        ];
        
        // Verificar se a linha contém pelo menos 6 padrões de cabeçalho
        $matches = 0;
        $tempMapping = [];
        
        $lineForAnalysis = strtolower($line);
        
        foreach ($patterns as $field => $pattern) {
            if (preg_match($pattern, $lineForAnalysis, $match, PREG_OFFSET_CAPTURE)) {
                $matches++;
                $tempMapping[$field] = $match[0][1];
            }
        }
        
        if ($matches >= 6) {
            // Ordenar por posição no texto para criar mapeamento de índices
            asort($tempMapping);
            $columnMapping = [];
            $index = 0;
            
            foreach ($tempMapping as $field => $position) {
                $columnMapping[$index] = $field;
                $index++;
            }
            
            return true;
        }
        
        return false;
    }

    private static function parsearLinhaPlanilhaComMapeamento(string $line, array $columnMapping): ?array
    {
        // Limpar e dividir a linha de forma mais inteligente
        $line = trim($line);
        
        // Primeiro, tentar dividir por múltiplos espaços ou tabs
        $parts = preg_split('/\s{2,}|\t+/', $line);
        
        // Se não funcionou, usar espaços simples
        if (count($parts) < 3) {
            $parts = preg_split('/\s+/', $line);
        }
        
        if (count($parts) < 3) {
            return null;
        }
        
        // Identificar o cliente - pode não estar na primeira posição
        $cliente = null;
        $clienteIndex = 0;
        
        // Procurar por um nome de cliente válido
        for ($i = 0; $i < count($parts); $i++) {
            $possibleClient = trim($parts[$i]);
            
            // Verificar se parece com um nome de cliente
            if (self::isValidClientName($possibleClient)) {
                $cliente = $possibleClient;
                $clienteIndex = $i;
                break;
            }
        }
        
        if (!$cliente) {
            return null;
        }
        
        // Inicializar dados
        $dados = [
            'cliente' => $cliente,
            'primeira_venda' => null,
            'ultima_venda' => null,
            'valor_venda_medio' => null,
            'quantidade_total_venda' => null,
            'custo_venda_total' => null,
            'total_devolucao' => null,
            'custo_dev_total' => null,
            'total_custo' => null,
            'lucro_reais' => null,
            'lucro_percentual' => null,
        ];
        
        // Processar os valores restantes
        foreach ($parts as $i => $value) {
            if ($i === $clienteIndex) {
                continue; // Pular o cliente
            }
            
            $value = trim($value);
            
            if (empty($value) || $value === '-' || $value === '0' || $value === '0,00') {
                continue;
            }
            
            // Identificar o tipo de valor baseado no formato
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value)) {
                // É uma data
                if (!$dados['primeira_venda']) {
                    $dados['primeira_venda'] = self::converterDataBrasil($value);
                } elseif (!$dados['ultima_venda']) {
                    $dados['ultima_venda'] = self::converterDataBrasil($value);
                }
            } elseif (preg_match('/[\d,\.]+\s*%/', $value)) {
                // É um percentual
                $dados['lucro_percentual'] = self::converterPercentual($value);
            } elseif (preg_match('/^-?[\d,\.]+$/', $value)) {
                // É um número
                $numericValue = self::converterValor($value);
                
                if ($numericValue !== null) {
                    // Classificar baseado no valor
                    if ($numericValue < 0) {
                        // Valor negativo - provavelmente devolução
                        if (!$dados['total_devolucao']) {
                            $dados['total_devolucao'] = abs($numericValue);
                        } elseif (!$dados['custo_dev_total']) {
                            $dados['custo_dev_total'] = abs($numericValue);
                        }
                    } elseif ($numericValue < 1000 && $numericValue == intval($numericValue)) {
                        // Número pequeno e inteiro - provavelmente quantidade
                        $dados['quantidade_total_venda'] = intval($numericValue);
                    } elseif ($numericValue < 1000) {
                        // Valor pequeno - provavelmente valor médio
                        $dados['valor_venda_medio'] = $numericValue;
                    } else {
                        // Valor grande - classificar por ordem de aparição
                        if (!$dados['custo_venda_total']) {
                            $dados['custo_venda_total'] = $numericValue;
                        } elseif (!$dados['total_custo']) {
                            $dados['total_custo'] = $numericValue;
                        } elseif (!$dados['lucro_reais']) {
                            $dados['lucro_reais'] = $numericValue;
                        }
                    }
                }
            }
        }
        
        return $dados;
    }

    private static function isValidClientName(string $name): bool
    {
        // Validação do nome do cliente
        if (empty($name) || strlen($name) < 3 || is_numeric($name)) {
            return false;
        }
        
        // Lista de palavras proibidas
        $palavrasProibidas = [
            'quinta-feira', 'segunda-feira', 'terça-feira', 'quarta-feira', 
            'sexta-feira', 'sábado', 'domingo', 'de', 'à', 'até', 'para',
            'período', 'cliente', 'total', 'qtd', 'sennacar', 'venda', 
            'relatório', 'nome', 'data', 'valor', 'em', 'com', 'por',
            'sem', 'sobre', 'subtotal', 'soma', 'página', 'page', 'primeira',
            'última', 'médio', 'custo', 'devolução', 'lucro'
        ];
        
        if (in_array(strtolower($name), $palavrasProibidas)) {
            return false;
        }
        
        // Verificar se é apenas um valor monetário, data ou percentual
        if (preg_match('/^(R\$|[\d,\.]+%?|\d{1,2}\/\d{1,2}\/\d{4})$/', $name)) {
            return false;
        }
        
        // Deve conter pelo menos uma letra
        if (!preg_match('/[a-zA-Z]/', $name)) {
            return false;
        }
        
        return true;
    }

    private static function isLinhaValidaPlanilha(string $line): bool
    {
        // Padrões que indicam linhas inválidas em planilhas
        $padroesInvalidos = [
            '/^(total|subtotal|soma)/i',
            '/^(página|page)/i',
            '/^\d+\/\d+\/\d+\s*$/i', // Apenas data
            '/^(cliente|nome|data|valor|período)$/i', // Cabeçalhos isolados
            '/^\s*-+\s*$/i', // Linhas de separação
            '/^(quinta-feira|segunda-feira|terça-feira|quarta-feira|sexta-feira|sábado|domingo)/i', // Dias da semana
            '/^(de|à|até|para|em|com|por|sem|sobre)$/i', // Preposições isoladas
            '/^R\$[\d,\.]+\s*$/i', // Apenas valores monetários
            '/^\d+\s*$/i', // Apenas números
            '/^[\d,\.]+%\s*$/i', // Apenas percentuais
        ];
        
        foreach ($padroesInvalidos as $padrao) {
            if (preg_match($padrao, $line)) {
                return false;
            }
        }
        
        // Deve ter pelo menos 3 elementos separados por espaços ou tabs
        $parts = preg_split('/[\s\t]+/', trim($line));
        return count($parts) >= 3;
    }

    private static function parsearLinhaRelatorioSennacar(string $line): ?array
    {
        // Limpar espaços extras
        $line = trim($line);
        
        // Dividir por espaços
        $parts = preg_split('/\s+/', $line);
        
        // Verificar se temos pelo menos 2 partes (cliente + dados)
        if (count($parts) < 2) {
            return null;
        }
        
        // Extrair nome do cliente (primeira parte)
        $cliente = trim($parts[0]);
        
        // Validação básica do cliente
        if (empty($cliente) || strlen($cliente) < 3 || is_numeric($cliente)) {
            return null;
        }
        
        // Verificar se não é uma palavra proibida
        $palavrasProibidas = [
            'quinta-feira', 'Quinta-feira', 'segunda-feira', 'terça-feira', 'quarta-feira', 
            'sexta-feira', 'sábado', 'domingo', 'de', 'à', 'período', 
            'cliente', 'total', 'qtd', 'sennacar', 'venda', 'relatório'
        ];
        
        if (in_array(strtolower($cliente), $palavrasProibidas)) {
            return null;
        }
        
        // Por enquanto, retornar apenas o cliente com valores padrão/nulos
        return [
            'cliente' => $cliente,
            'primeira_venda' => null,
            'ultima_venda' => null,
            'valor_venda_medio' => null,
            'quantidade_total_venda' => null,
            'custo_venda_total' => null,
            'total_devolucao' => null,
            'custo_dev_total' => null,
            'total_custo' => null,
            'lucro_reais' => null,
            'lucro_percentual' => null,
        ];
    }

    private static function converterDataBrasil(?string $data): ?string
    {
        if (empty($data)) return null;
        
        try {
            // Formato brasileiro: dd/mm/yyyy
            $date = \DateTime::createFromFormat('d/m/Y', trim($data));
            return $date ? $date->format('Y-m-d') : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function converterValor(?string $valor): ?float
    {
        if (empty($valor)) return null;
        
        // Remover espaços e caracteres especiais, manter apenas números, vírgulas e pontos
        $valor = trim($valor);
        $valor = str_replace([' ', 'R$'], '', $valor);
        
        // Converter formato brasileiro (1.234,56) para formato PHP (1234.56)
        if (strpos($valor, ',') !== false) {
            // Se tem vírgula, assumir formato brasileiro
            $valor = str_replace('.', '', $valor); // Remove pontos de milhares
            $valor = str_replace(',', '.', $valor); // Converte vírgula decimal para ponto
        }
        
        return is_numeric($valor) ? floatval($valor) : null;
    }

    private static function converterInteiro(?string $valor): ?int
    {
        if (empty($valor)) return null;
        
        $valor = trim($valor);
        $valor = str_replace([' ', '.'], '', $valor); // Remove espaços e pontos de milhares
        
        return is_numeric($valor) ? intval($valor) : null;
    }

    private static function converterPercentual(?string $percentual): ?float
    {
        if (empty($percentual)) return null;
        
        $percentual = trim($percentual);
        $percentual = str_replace(['%', ' '], '', $percentual);
        
        // Converter formato brasileiro
        if (strpos($percentual, ',') !== false) {
            $percentual = str_replace(',', '.', $percentual);
        }
        
        return is_numeric($percentual) ? floatval($percentual) : null;
    }

    public static function exportarParaCSV(PdfUpload $record)
    {
        $vendas = $record->vendasClientes()->get();
        
        if ($vendas->isEmpty()) {
            Notification::make()
                ->title('Nenhum dado encontrado')
                ->body('Não há dados de vendas para exportar.')
                ->warning()
                ->send();
            return;
        }

        $filename = 'vendas_' . $record->id . '_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = storage_path('app/public/exports/' . $filename);
        
        // Criar diretório se não existir
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $file = fopen($filepath, 'w');
        
        // Adicionar BOM para UTF-8 (para Excel reconhecer acentos)
        fwrite($file, "\xEF\xBB\xBF");
        
        // Cabeçalho do CSV
        $headers = [
            'Cliente',
            'Primeira Venda',
            'Última Venda',
            'Valor Venda Médio',
            'Quantidade Total Venda',
            'Custo Venda Total',
            'Total Devolução',
            'Custo Dev. Total',
            'Total Custo',
            'Lucro (R$)',
            'Lucro (%)'
        ];
        
        fputcsv($file, $headers, ';');
        
        // Dados
        foreach ($vendas as $venda) {
            $row = [
                $venda->cliente,
                $venda->primeira_venda ? $venda->primeira_venda->format('d/m/Y') : '',
                $venda->ultima_venda ? $venda->ultima_venda->format('d/m/Y') : '',
                $venda->vl_vnd_medio ? number_format($venda->vl_vnd_medio, 2, ',', '.') : '',
                $venda->qtd ?? '',
                $venda->total_venda ? number_format($venda->total_venda, 2, ',', '.') : '',
                $venda->custo_venda ? number_format($venda->custo_venda, 2, ',', '.') : '',
                $venda->total_devolucao ? number_format($venda->total_devolucao, 2, ',', '.') : '',
                $venda->custo_dev ? number_format($venda->custo_dev, 2, ',', '.') : '',
                $venda->total ? number_format($venda->total, 2, ',', '.') : '',
                $venda->total_custo ? number_format($venda->total_custo, 2, ',', '.') : '',
                $venda->lucro_reais ? number_format($venda->lucro_reais, 2, ',', '.') : '',
                $venda->lucro_percentual ? number_format($venda->lucro_percentual, 2, ',', '.') . '%' : ''
            ];
            
            fputcsv($file, $row, ';');
        }
        
        fclose($file);
        
        // Retornar download
        Notification::make()
            ->title('CSV exportado com sucesso!')
            ->body("Arquivo: {$filename}")
            ->success()
            ->send();
            
        return response()->download($filepath, $filename)->deleteFileAfterSend(true);
    }
}