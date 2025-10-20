<?php

namespace App\Filament\Resources\PdfUploadResource\Pages;

use App\Filament\Resources\PdfUploadResource;
use App\Models\VendaCliente;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Smalot\PdfParser\Parser;

class CreatePdfUpload extends CreateRecord
{
    protected static string $resource = PdfUploadResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by'] = Auth::id();
        
        // Se o arquivo foi carregado, extrair informações
        if (isset($data['file_path'])) {
            $filePath = $data['file_path'];
            if (is_string($filePath)) {
                // O arquivo já foi processado pelo Filament
                $fullPath = storage_path('app/public/' . $filePath);
                if (file_exists($fullPath)) {
                    $data['file_size'] = filesize($fullPath);
                    $data['file_name'] = basename($filePath);
                    $data['mime_type'] = mime_content_type($fullPath) ?: 'application/pdf';
                    
                    if (!isset($data['original_name'])) {
                        $data['original_name'] = $data['file_name'];
                    }
                }
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->extrairApenasTextoPdf();
    }

    private function extrairApenasTextoPdf(): void
    {
        try {
            $pdfUpload = $this->record;
            $fullPath = storage_path('app/public/' . $pdfUpload->file_path);
            
            if (!file_exists($fullPath)) {
                return;
            }

            $parser = new Parser();
            $pdf = $parser->parseFile($fullPath);
            $text = $pdf->getText();
            
            // Salvar apenas o texto extraído para uso futuro
            $pdfUpload->update(['text_extrated' => $text]);
            
        } catch (\Exception $e) {
            // Log do erro se necessário
            \Log::error('Erro ao extrair texto do PDF: ' . $e->getMessage());
        }
    }

    // Remover as funções de processamento automático
    // private function extrairDadosTabela() - REMOVIDA
    // private function parsearLinhaDados() - REMOVIDA
    // private function converterData() - REMOVIDA

    private function extrairDadosTabela(string $text, int $pdfUploadId): void
    {
        // Dividir o texto em linhas
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Pular linhas vazias ou cabeçalhos
            if (empty($line) || 
                strpos($line, 'Cliente') !== false || 
                strpos($line, 'Primeira Venda') !== false) {
                continue;
            }
            
            // Tentar extrair dados da linha
            $dados = $this->parsearLinhaDados($line);
            
            if ($dados && !empty($dados['cliente'])) {
                VendaCliente::create([
                    'pdf_upload_id' => $pdfUploadId,
                    'cliente' => $dados['cliente'],
                    'primeira_venda' => $dados['primeira_venda'],
                    'ultima_venda' => $dados['ultima_venda'],
                    'valor_venda_medio' => $dados['valor_venda_medio'],
                    'quantidade_total_venda' => $dados['quantidade_total_venda'],
                    'custo_venda_total' => $dados['custo_venda_total'],
                    'total_devolucao' => $dados['total_devolucao'],
                    'custo_dev_total' => $dados['custo_dev_total'],
                    'total_custo' => $dados['total_custo'],
                    'lucro_reais' => $dados['lucro_reais'],
                    'lucro_percentual' => $dados['lucro_percentual'],
                ]);
            }
        }
    }

    private function parsearLinhaDados(string $line): ?array
    {
        // Esta função precisa ser ajustada baseada no formato exato do seu PDF
        // Exemplo básico de parsing - você pode precisar ajustar
        
        // Remover espaços extras
        $line = preg_replace('/\s+/', ' ', $line);
        
        // Dividir por espaços (pode precisar de ajuste baseado no formato)
        $parts = explode(' ', $line);
        
        if (count($parts) < 3) {
            return null;
        }
        
        // Tentar identificar padrões de data (dd/mm/yyyy)
        $datePattern = '/\d{2}\/\d{2}\/\d{4}/';
        $numberPattern = '/[\d,\.]+/';
        
        $dados = [
            'cliente' => '',
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
        
        // Extrair nome do cliente (geralmente a primeira parte)
        $dados['cliente'] = $parts[0];
        
        // Extrair datas
        preg_match_all($datePattern, $line, $dates);
        if (count($dates[0]) >= 1) {
            $dados['primeira_venda'] = $this->converterData($dates[0][0]);
        }
        if (count($dates[0]) >= 2) {
            $dados['ultima_venda'] = $this->converterData($dates[0][1]);
        }
        
        // Extrair números (valores monetários e quantidades)
        preg_match_all($numberPattern, $line, $numbers);
        if (count($numbers[0]) > 0) {
            $nums = array_map(function($n) {
                return floatval(str_replace(',', '.', str_replace('.', '', $n)));
            }, $numbers[0]);
            
            // Mapear números para campos (ajustar conforme necessário)
            if (isset($nums[0])) $dados['valor_venda_medio'] = $nums[0];
            if (isset($nums[1])) $dados['quantidade_total_venda'] = intval($nums[1]);
            if (isset($nums[2])) $dados['custo_venda_total'] = $nums[2];
            // ... continuar mapeamento conforme estrutura do PDF
        }
        
        return $dados;
    }

    private function converterData(string $data): ?string
    {
        try {
            $date = \DateTime::createFromFormat('d/m/Y', $data);
            return $date ? $date->format('Y-m-d') : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}