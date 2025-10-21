<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PdfTextConverterResource\Pages;
use App\Models\PdfTextConverter;
use App\Models\VendaCliente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use PrinsFrank\PdfParser\PdfParser;

class PdfTextConverterResource extends Resource
{
    protected static ?string $model = PdfTextConverter::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Conversor PDF → Texto';

    protected static ?string $modelLabel = 'Conversor PDF';

    protected static ?string $pluralModelLabel = 'Conversores PDF';

    protected static ?string $navigationGroup = 'Ferramentas';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações do Arquivo')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Digite um título para identificar este PDF'),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->placeholder('Descrição opcional do conteúdo do PDF')
                            ->rows(3),

                        Forms\Components\FileUpload::make('file_path')
                            ->label('Arquivo PDF')
                            ->required()
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(50 * 1024) // 50MB
                            ->directory('pdf-text-converter')
                            ->preserveFilenames()
                            ->downloadable()
                            ->openable()
                            ->previewable(false)
                            ->helperText('Selecione um arquivo PDF para converter em texto (máximo 50MB)'),
                    ]),

                Forms\Components\Section::make('Configurações de Extração')
                    ->schema([
                        Forms\Components\Select::make('extraction_method')
                            ->label('Método de Extração')
                            ->options([
                                'prinsfrank' => 'PrinsFrank Parser (Recomendado)',
                                'smalot' => 'Smalot Parser (Compatibilidade)',
                            ])
                            ->default('prinsfrank')
                            ->required()
                            ->helperText('PrinsFrank é mais rápido e usa menos memória'),
                    ])
                    ->collapsible()
                    ->collapsed(),
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

                Tables\Columns\TextColumn::make('original_name')
                    ->label('Arquivo Original')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                Tables\Columns\TextColumn::make('formatted_file_size')
                    ->label('Tamanho')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('file_size', $direction);
                    }),
              
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pendente',
                        'processing' => 'Processando',
                        'completed' => 'Concluído',
                        'failed' => 'Falhou',
                    ]),

                Tables\Filters\SelectFilter::make('extraction_method')
                    ->label('Método de Extração')
                    ->options([
                        'prinsfrank' => 'PrinsFrank',
                        'smalot' => 'Smalot',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('processar')
                    ->label('Processar')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn(PdfTextConverter $record) => $record->status === 'pending' || $record->status === 'failed')
                    ->action(function (PdfTextConverter $record) {
                        return static::processarPdf($record);
                    }),

                Tables\Actions\Action::make('ver_texto')
                    ->label('Ver Texto')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->visible(fn(PdfTextConverter $record) => $record->status === 'completed' && !empty($record->extracted_text))
                    ->modalHeading('Texto Extraído do PDF')
                    ->modalDescription(fn(PdfTextConverter $record) => "Arquivo: {$record->original_name}")
                    ->modalContent(fn(PdfTextConverter $record) => view('filament.modals.texto-extraido', [
                        'texto' => $record->extracted_text,
                        'totalLinhas' => substr_count($record->extracted_text, "\n") + 1,
                        'totalCaracteres' => strlen($record->extracted_text),
                        'metodo' => $record->extraction_method,
                        'tempoProcessamento' => $record->processing_time
                    ]))
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),

                Tables\Actions\Action::make('refatorar_texto')
                    ->label('Refatorar Texto')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->visible(fn(PdfTextConverter $record) => $record->status === 'completed' && !empty($record->extracted_text))
                    ->requiresConfirmation()
                    ->modalHeading('Refatorar Texto Extraído')
                    ->modalDescription('Remove “Página X de Y” e cabeçalhos da planilha do texto salvo.')
                    ->action(function (PdfTextConverter $record) {
                        return static::refatorarTextoBanco($record);
                    }),
              
                Tables\Actions\Action::make('salvar_vendas_clientes')
                    ->label('Salvar Vendas Clientes')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->visible(fn(PdfTextConverter $record) => $record->status === 'completed' && !empty($record->extracted_text) && $record->vendas()->count() === 0)
                    ->requiresConfirmation()
                    ->modalHeading('Salvar Vendas Clientes')
                    ->modalDescription('Salva em vendas_clientes na ordem: Cliente, Primeira, Última, Vl Médio, Qtd Total, Custo Venda, Devolução, Custo Dev. Total, Total Custo, Lucro (R$), Lucro (%)')
                    ->action(function (PdfTextConverter $record) {
                        return static::salvarVendasClientesOrdenado($record);
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('processar_selecionados')
                        ->label('Processar Selecionados')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->action(function ($records) {
                            $processados = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'pending' || $record->status === 'failed') {
                                    static::processarPdf($record);
                                    $processados++;
                                }
                            }

                            Notification::make()
                                ->title('Processamento iniciado')
                                ->body("{$processados} arquivo(s) foram processados.")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPdfTextConverters::route('/'),
            'create' => Pages\CreatePdfTextConverter::route('/create'),
            'view' => Pages\ViewPdfTextConverter::route('/{record}'),
            'edit' => Pages\EditPdfTextConverter::route('/{record}/edit'),
        ];
    }

    public static function processarPdf(PdfTextConverter $record)
    {
        try {
            // Atualizar status para processando
            $record->update(['status' => 'processing']);

            $startTime = microtime(true);
            $fullPath = storage_path('app/public/' . $record->file_path);

            if (!file_exists($fullPath)) {
                $record->update(['status' => 'failed']);

                Notification::make()
                    ->title('Erro')
                    ->body('Arquivo PDF não encontrado.')
                    ->danger()
                    ->send();
                return;
            }

            $text = '';

            // Usar o método selecionado para extração
            if ($record->extraction_method === 'prinsfrank') {
                $document = (new PdfParser())->parseFile($fullPath);
                $text = $document->getText();
            } else {
                // Fallback para smalot
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($fullPath);
                $text = $pdf->getText();
            }

            $endTime = microtime(true);
            $processingTime = $endTime - $startTime;

            // Salvar o texto extraído
            $record->update([
                'extracted_text' => $text,
                'processing_time' => $processingTime,
                'status' => 'completed'
            ]);

            Notification::make()
                ->title('Processamento concluído!')
                ->body("Texto extraído com sucesso em " . number_format($processingTime, 2) . " segundos.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            $record->update(['status' => 'failed']);

            \Log::error('Erro ao processar PDF: ' . $e->getMessage());

            Notification::make()
                ->title('Erro no processamento')
                ->body('Ocorreu um erro ao processar o PDF: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    // public static function salvarDadosNoBanco(PdfTextConverter $record)
    // {
    //     try {
    //         if (empty($record->extracted_text)) {
    //             Notification::make()
    //                 ->title('Erro')
    //                 ->body('Nenhum texto extraído disponível.')
    //                 ->danger()
    //                 ->send();
    //             return;
    //         }

    //         // Verificar se já existem dados salvos
    //         if ($record->vendas()->count() > 0) {
    //             Notification::make()
    //                 ->title('Aviso')
    //                 ->body('Já existem dados salvos para este PDF.')
    //                 ->warning()
    //                 ->send();
    //             return;
    //         }

    //         // Usar a mesma lógica de extração de dados do PdfUploadResource
    //         $dadosExtraidos = static::extrairDadosPlanilhaPdf($record->extracted_text);

    //         if (empty($dadosExtraidos)) {
    //             Notification::make()
    //                 ->title('Aviso')
    //                 ->body('Nenhum dado de vendas foi encontrado no texto extraído.')
    //                 ->warning()
    //                 ->send();
    //             return;
    //         }

    //         // Salvar os dados no banco
    //         foreach ($dadosExtraidos as $dados) {
    //             VendaCliente::create([
    //                 'pdf_text_converter_id' => $record->id,
    //                 'pdf_upload_id' => null, // Não vem de um PdfUpload
    //                 ...$dados
    //             ]);
    //         }

    //         Notification::make()
    //             ->title('Sucesso!')
    //             ->body(count($dadosExtraidos) . ' registros de vendas foram salvos no banco de dados.')
    //             ->success()
    //             ->send();
    //     } catch (\Exception $e) {
    //         \Log::error('Erro ao salvar dados no banco: ' . $e->getMessage());

    //         Notification::make()
    //             ->title('Erro ao salvar dados')
    //             ->body('Ocorreu um erro ao processar e salvar os dados: ' . $e->getMessage())
    //             ->danger()
    //             ->send();
    //     }
    // }

    private static function extrairDadosPlanilhaPdf(string $text): array
    {
        $dados = [];
        $lines = preg_split("/\r\n|\n|\r/", $text);

        \Log::info("Processando planilha PDF - Total de linhas: " . count($lines));

        $headerFound = false;
        $columnMapping = [];
        $buffer = '';

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            \Log::info("Linha {$index}: " . $line);

            if (!$headerFound && static::detectarCabecalhoEMapearColunas($line, $columnMapping)) {
                $headerFound = true;
                \Log::info("Cabeçalho encontrado na linha {$index}. Mapeamento: " . json_encode($columnMapping));
                continue;
            }

            // Ignora linhas sem dado (página, separadores, período, etc.)
            if (static::isLinhaIgnoravel($line)) {
                continue;
            }

            // Acumula linhas até formar uma linha completa (evita quebrar o cliente)
            $candidate = $buffer === '' ? $line : ($buffer . ' ' . $line);

            if (static::isLinhaCompleta($candidate)) {
                $dadosLinha = static::parsearLinhaPlanilhaComMapeamento($candidate, $columnMapping);
                if ($dadosLinha && static::validarDadosCliente($dadosLinha)) {
                    \Log::info("Dados válidos extraídos (agregado): " . json_encode($dadosLinha));
                    $dados[] = $dadosLinha;
                }
                $buffer = '';
            } else {
                $buffer = $candidate;
            }
        }

        // Processa buffer remanescente se estiver completo
        if ($buffer !== '' && static::isLinhaCompleta($buffer)) {
            $dadosLinha = static::parsearLinhaPlanilhaComMapeamento($buffer, $columnMapping);
            if ($dadosLinha && static::validarDadosCliente($dadosLinha)) {
                $dados[] = $dadosLinha;
            }
        }

        \Log::info("Total de registros extraídos da planilha: " . count($dados));
        return $dados;
    }
    
    private static function isLinhaIgnoravel(string $line): bool
    {
        $l = trim($line);
        if ($l === '') return true;

        $padroes = [
            '/^(página|page)\b/i',
            '/^\s*-+\s*$/',
            '/^\s*SENNACAR\s*-\s*Venda\s*X\s*Cliente.*$/iu',
            '/^\s*Per[íi]odo\s*:/iu',
            '/\bTOTAIS\s*:/iu',
        ];

        foreach ($padroes as $p) {
            if (preg_match($p, $l)) {
                return true;
            }
        }

        return false;
    }

    private static function isLinhaCompleta(string $line): bool
    {
        $dates = preg_match_all('/\d{1,2}\/\d{1,2}\/\d{4}/', $line);
        $nums = preg_match_all('/[\d.,]+/', $line);

        // Linha de dados deve ter duas datas e ao menos 7 números (inclui % como número)
        return $dates >= 2 && $nums >= 7;
    }

    private static function detectarCabecalhoEMapearColunas(string $line, array &$columnMapping): bool
    {
        $original = trim($line);
        $normalized = preg_replace('/\s+/', ' ', $original);

        $tokens = [
            'cliente' => 'Cliente',
            'primeira_venda' => 'Primeira Venda',
            'ultima_venda' => 'Última Venda',
            'vl_vnd_medio' => 'Vl Vnd Médio',
            'qtd' => 'Qtd. Total Venda',
            'custo_venda' => 'Custo Venda Total',
            'total_devolucao' => 'Devolução',
            'custo_dev' => 'Custo Dev. Total',
            'total_custo' => 'Total Custo',
            'lucro_reais' => 'Lucro (R$)',
            'lucro_percentual' => 'Lucro (%)',
        ];

        $found = 0;
        foreach ($tokens as $key => $label) {
            if (stripos($normalized, $label) !== false) {
                $columnMapping[$key] = $label;
                $found++;
            }
        }

        // Considera cabeçalho quando encontra a maioria dos rótulos
        return $found >= 8;
    }

    private static function isLinhaValidaPlanilha(string $line): bool
    {
        $padroesInvalidos = [
            '/^(total|subtotal|soma)/i',
            '/^(página|page)/i',
            '/^\d+\/\d+\/\d+\s*$/i',
            '/^(cliente|nome|data|valor|período)$/i',
            '/^(per[íi]odo)\s*:/i',
            '/^(relat[óo]rio)\s*:/i',
            '/^\s*-+\s*$/i',
            '/^(quinta-feira|segunda-feira|terça-feira|quarta-feira|sexta-feira|sábado|domingo)/i',
            '/^(de|à|até|para|em|com|por|sem|sobre)$/i',
            '/^R\$[\d,\.]+\s*$/i',
            '/^\d+\s*$/i',
            '/^[\d,\.]+%\s*$/i',
        ];

        foreach ($padroesInvalidos as $padrao) {
            if (preg_match($padrao, $line)) {
                return false;
            }
        }

        // Deve ter pelo menos 3 elementos e pelo menos um número (garante linha de dados)
        $parts = preg_split('/[\s\t]+/', trim($line));
        if (count($parts) < 3) {
            return false;
        }

        // Exige presença de pelo menos um dígito para considerar linha com dados
        if (!preg_match('/\d/', $line)) {
            return false;
        }

        return true;
    }

    private static function parsearLinhaPlanilhaComMapeamento(string $line, array $columnMapping): ?array
    {
        $line = trim($line);

        // Captura datas e números com offsets para delimitar corretamente o nome do cliente
        $datePattern = '/\d{1,2}\/\d{1,2}\/\d{4}/';
        preg_match_all($datePattern, $line, $dateMatches, PREG_OFFSET_CAPTURE);
        preg_match_all('/[\d.,]+(?:\s*%)?/', $line, $numMatches, PREG_OFFSET_CAPTURE);

        $firstDateOffset = !empty($dateMatches[0]) ? $dateMatches[0][0][1] : null;
        $firstNumberOffset = !empty($numMatches[0]) ? $numMatches[0][0][1] : null;

        // Determina a fronteira do nome: antes da primeira data ou do primeiro número (o que vier primeiro)
        $clientEndOffset = null;
        if ($firstDateOffset !== null && $firstNumberOffset !== null) {
            $clientEndOffset = min($firstDateOffset, $firstNumberOffset);
        } elseif ($firstDateOffset !== null) {
            $clientEndOffset = $firstDateOffset;
        } elseif ($firstNumberOffset !== null) {
            $clientEndOffset = $firstNumberOffset;
        }

        $cliente = null;
        if ($clientEndOffset !== null) {
            $cliente = preg_replace('/\s+/', ' ', trim(substr($line, 0, $clientEndOffset)));
        } else {
            // Fallback: tenta pelos 'parts' caso a linha não tenha números nem datas
            $parts = preg_split('/\s{2,}|\t+/', $line);
            if (count($parts) < 3) {
                $parts = preg_split('/\s+/', $line);
            }
            for ($i = 0; $i < count($parts); $i++) {
                $possibleClient = trim($parts[$i]);
                if (static::isValidClientName($possibleClient)) {
                    $cliente = $possibleClient;
                    break;
                }
            }
        }

        if (!$cliente) {
            return null;
        }

        // Inicializa com cliente já correto, sem números anexados
        $dados = [
            'cliente' => $cliente,
            'primeira_venda' => null,
            'ultima_venda' => null,
            'vl_vnd_medio' => null,
            'qtd' => null,
            'total_venda' => null,
            'custo_venda' => null,
            'total_devolucao' => null,
            'custo_dev' => null,
            'total' => null,
            'total_custo' => null,
            'lucro_reais' => null,
            'lucro_percentual' => null,
        ];

        // Usando os matches já capturados com conteúdo e offset
        if (count($dateMatches[0]) >= 1) {
            $dados['primeira_venda'] = static::converterData($dateMatches[0][0][0]);
        }
        if (count($dateMatches[0]) >= 2) {
            $dados['ultima_venda'] = static::converterData($dateMatches[0][1][0]);
        }

        // Ranges de datas para excluir seus números
        $dateRanges = [];
        foreach ($dateMatches[0] as $dm) {
            $start = $dm[1];
            $length = strlen($dm[0]);
            $dateRanges[] = [$start, $start + $length];
        }

        // Percentual com offset
        $percentRange = null;
        if (preg_match('/[\d.,]+\s*%/', $line, $pm, PREG_OFFSET_CAPTURE)) {
            $dados['lucro_percentual'] = static::converterDecimal(str_replace('%', '', $pm[0][0]));
            $percentRange = [$pm[0][1], $pm[0][1] + strlen($pm[0][0])];
        }

        // Números com offset; exclui os que pertencem às datas e ao percentual
        // ATUALIZAÇÃO: Captura números com sinal para pegar devoluções negativas
        preg_match_all('/-?[\d.,]+/', $line, $numbers, PREG_OFFSET_CAPTURE);
        $valores = [];
        foreach ($numbers[0] as $numMatch) {
            $numStr = $numMatch[0];
            $pos = $numMatch[1];
        
            // dentro de uma data?
            $inDate = false;
            foreach ($dateRanges as [$s, $e]) {
                if ($pos >= $s && $pos < $e) {
                    $inDate = true;
                    break;
                }
            }
            if ($inDate) continue;
        
            // dentro do percentual?
            if ($percentRange && $pos >= $percentRange[0] && $pos < $percentRange[1]) {
                continue;
            }
        
            $valor = static::converterDecimal($numStr);
            if ($valor !== null) {
                $valores[] = $valor;
            }
        }

        // PRIORIDADE: identificar devoluções/custos negativos primeiro
        $negativos = array_values(array_filter($valores, fn($v) => $v < 0));
        if (isset($negativos[0])) {
            // total_devolucao deve ser positivo no banco, usar valor absoluto
            $dados['total_devolucao'] = abs($negativos[0]);
        }
        if (isset($negativos[1])) {
            // custo_dev também como valor positivo
            $dados['custo_dev'] = abs($negativos[1]);
        }

        // Ordem EXATA do cabeçalho informado (fallback), sem sobrescrever já preenchidos
        if (count($valores) >= 1) $dados['lucro_reais'] = $dados['lucro_reais'] ?? $valores[0];
        if (count($valores) >= 3 && $dados['custo_dev'] === null) $dados['custo_dev'] = $valores[2];
        if (count($valores) >= 4) $dados['total'] = $dados['total'] ?? $valores[3];
        if (count($valores) >= 5) $dados['total_custo'] = $dados['total_custo'] ?? $valores[4];
        if (count($valores) >= 6) $dados['vl_vnd_medio'] = $dados['vl_vnd_medio'] ?? $valores[5];
        if (count($valores) >= 7) $dados['qtd'] = $dados['qtd'] ?? (int) $valores[6];
        if (count($valores) >= 8) $dados['total_venda'] = $dados['total_venda'] ?? $valores[7];
        if (count($valores) >= 9) $dados['custo_venda'] = $dados['custo_venda'] ?? $valores[8];
        if (count($valores) >= 10 && $dados['total_devolucao'] === null) $dados['total_devolucao'] = $valores[9];
        if (count($valores) >= 11) $dados['lucro_percentual'] = $dados['lucro_percentual'] ?? $valores[10];

        return $dados;
    }

    private static function isValidClientName(string $name): bool
    {
        // Verificar se é um nome de cliente válido
        $name = trim($name);

        // Deve ter pelo menos 2 caracteres
        if (strlen($name) < 2) {
            return false;
        }

        // Não deve ser apenas números
        if (is_numeric($name)) {
            return false;
        }

        // Não deve ser uma data
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $name)) {
            return false;
        }

        // Não deve ser um valor monetário
        if (preg_match('/^R\$/', $name)) {
            return false;
        }

        // Não deve ser apenas símbolos
        if (preg_match('/^[^a-zA-Z0-9]+$/', $name)) {
            return false;
        }

        return true;
    }

    private static function validarDadosCliente(array $dados): bool
    {
        // Validar se os dados do cliente são válidos
        return !empty($dados['cliente']) && strlen($dados['cliente']) >= 2;
    }

    private static function converterData(?string $data): ?string
    {
        if (empty($data)) return null;

        try {
            $data = trim($data);

            // Verificar se já está no formato correto (YYYY-MM-DD)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
                return $data;
            }

            // Converter de DD/MM/YYYY para YYYY-MM-DD
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $data, $matches)) {
                $dia = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $mes = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $ano = $matches[3];

                return "{$ano}-{$mes}-{$dia}";
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function converterDecimal(?string $valor): ?float
    {
        if (empty($valor)) return null;

        $valor = trim($valor);
        $valor = str_replace(['R$', ' '], '', $valor);

        // Converter formato brasileiro (1.234,56) para formato padrão (1234.56)
        if (strpos($valor, ',') !== false) {
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

    private static function refatorarTextoBanco(PdfTextConverter $record): void
    {
        try {
            $texto = $record->extracted_text ?? '';
            if (empty($texto)) {
                Notification::make()
                    ->title('Sem texto para refatorar')
                    ->body('Este registro não possui texto extraído.')
                    ->warning()
                    ->send();
                return;
            }

            // Dividir por linhas para remoções precisas
            $linhas = preg_split("/\r\n|\n|\r/", $texto);

            $tokensCabecalho = [
                'Cliente',
                'Primeira Venda',
                'Última Venda',
                'Vl Vnd Médio',
                'Qtd. Total Venda',
                'Custo Venda Total',
                'Devolução Total',
                'Total Custo',
                'Lucro (R$)',
                'Lucro (%)',
                'Custo Dev.',
            ];

            $linhasFiltradas = array_values(array_filter($linhas, function ($linha) use ($tokensCabecalho) {
                $l = trim($linha);
                if ($l === '') {
                    return true; // manter linhas vazias para não colar parágrafos
                }
                // Remover paginação "Página X de Y"
                if (preg_match('/^Página\s+\d+\s+de\s+\d+$/iu', $l)) {
                    return false;
                }

                // Remover linha isolada "Custo Dev." (com variações)
                if (preg_match('/^Custo\s+Dev\.?$/iu', $l)) {
                    return false;
                }

                // Remover título do relatório: "SENNACAR - Venda X Cliente ..."
                if (preg_match('/^\s*SENNACAR\s*-\s*Venda\s*X\s*Cliente.*$/iu', $l)) {
                    return false;
                }

                // Remover linha de período: "Período: de DD/MM/YYYY à DD/MM/YYYY"
                if (preg_match('/^\s*Per[íi]odo\s*:\s*de\s*\d{1,2}\/\d{1,2}\/\d{4}\s*[aà]\s*\d{1,2}\/\d{1,2}\/\d{4}\s*$/iu', $l)) {
                    return false;
                }

                if (preg_match('/\bTOTAIS\s*:/iu', $l)) {
                    return false;
                }

                $l = trim($linha);

                $l = preg_replace('/\bDev\.\s*/iu', '', $l);

                // $l = preg_replace('/Custo[\s\-_\.]*Dev\.?\s*/iu', '', $l);

                // Remover cabeçalho da planilha (contém vários tokens característicos)
                $count = 0;
                foreach ($tokensCabecalho as $tk) {
                    if (stripos($l, $tk) !== false) {
                        $count++;
                    }
                }
                if ($count >= 4) {
                    return false;
                }

                return true;
            }));


            $limpo = implode(PHP_EOL, $linhasFiltradas);
            $alterado = $limpo !== $texto;

            $record->extracted_text = $limpo;
            $record->save();

            Notification::make()
                ->title($alterado ? 'Texto refatorado' : 'Nada para remover')
                ->body($alterado
                    ? 'Paginação e cabeçalhos removidos. Texto atualizado com sucesso.'
                    : 'Nenhuma linha de paginação/cabeçalho foi encontrada.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            \Log::error('Erro ao refatorar texto: ' . $e->getMessage());
            Notification::make()
                ->title('Erro ao refatorar')
                ->body('Ocorreu um erro ao refatorar o texto: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function salvarVendasClientesOrdenado(PdfTextConverter $record)
    {
        try {
            if (empty($record->extracted_text)) {
                Notification::make()
                    ->title('Erro')
                    ->body('Nenhum texto extraído disponível.')
                    ->danger()
                    ->send();
                return;
            }

            if ($record->vendas()->count() > 0) {
                Notification::make()
                    ->title('Aviso')
                    ->body('Já existem dados salvos para este PDF.')
                    ->warning()
                    ->send();
                return;
            }

            // Extrair linhas da planilha a partir do texto salvo em extracted_text
            $dadosExtraidos = static::extrairDadosPlanilhaPdf($record->extracted_text);

            if (empty($dadosExtraidos)) {
                Notification::make()
                    ->title('Aviso')
                    ->body('Nenhum dado foi encontrado para salvar na tabela vendas_clientes.')
                    ->warning()
                    ->send();
                return;
            }

            $total = 0;

            foreach ($dadosExtraidos as $dados) {
                // Monta payload na ordem do cabeçalho solicitado
                $payloadOrdenado = [
                    'cliente' => $dados['cliente'] ?? null,
                    'primeira_venda' => $dados['primeira_venda'] ?? null,
                    'ultima_venda' => $dados['ultima_venda'] ?? null,
                    'vl_vnd_medio' => $dados['vl_vnd_medio'] ?? null,
                    'qtd' => $dados['qtd'] ?? null,
                    'total_venda' => $dados['total_venda'] ?? null,
                    'custo_venda' => $dados['custo_venda'] ?? null,
                    'total_devolucao' => $dados['total_devolucao'] ?? null,
                    'custo_dev' => $dados['custo_dev'] ?? null,
                    'total' => $dados['total'] ?? null,
                    'total_custo' => $dados['total_custo'] ?? null,
                    'lucro_reais' => $dados['lucro_reais'] ?? null,
                    'lucro_percentual' => $dados['lucro_percentual'] ?? null,
                ];

                VendaCliente::create([
                    'pdf_text_converter_id' => $record->id,
                    'pdf_upload_id' => null,
                    // payload com chaves exatamente como na migration/model
                    ...$payloadOrdenado,
                ]);

                $total++;
            }

            Notification::make()
                ->title('Sucesso!')
                ->body("{$total} registros foram salvos em vendas_clientes na ordem especificada.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            \Log::error('Erro ao salvar vendas_clientes (ordenado): ' . $e->getMessage());
            Notification::make()
                ->title('Erro')
                ->body('Falha ao salvar dados em vendas_clientes: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
