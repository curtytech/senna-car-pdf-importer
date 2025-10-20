<?php

namespace App\Console\Commands;

use App\Models\PdfUpload;
use App\Models\VendaCliente;
use Illuminate\Console\Command;
use Smalot\PdfParser\Parser;

class ProcessarPdfCommand extends Command
{
    protected $signature = 'pdf:processar {pdf_id?}';
    protected $description = 'Processa um PDF e extrai dados de vendas para a tabela vendas_clientes';

    public function handle()
    {
        $pdfId = $this->argument('pdf_id');
        
        if ($pdfId) {
            $pdf = PdfUpload::find($pdfId);
            if (!$pdf) {
                $this->error("PDF com ID {$pdfId} não encontrado.");
                return 1;
            }
            $this->processarPdf($pdf);
        } else {
            // Processar todos os PDFs que ainda não foram processados
            $pdfs = PdfUpload::whereDoesntHave('vendasClientes')->get();
            
            if ($pdfs->isEmpty()) {
                $this->info('Nenhum PDF pendente para processar.');
                return 0;
            }
            
            foreach ($pdfs as $pdf) {
                $this->processarPdf($pdf);
            }
        }
        
        return 0;
    }

    private function processarPdf(PdfUpload $pdf)
    {
        $this->info("Processando PDF: {$pdf->title}");
        
        try {
            $fullPath = storage_path('app/public/' . $pdf->file_path);
            
            if (!file_exists($fullPath)) {
                $this->error("Arquivo não encontrado: {$fullPath}");
                return;
            }

            $parser = new Parser();
            $pdfDocument = $parser->parseFile($fullPath);
            $text = $pdfDocument->getText();
            
            $dadosExtraidos = $this->extrairDadosTabela($text);
            
            if (empty($dadosExtraidos)) {
                $this->warn("Nenhum dado encontrado no PDF: {$pdf->title}");
                return;
            }
            
            foreach ($dadosExtraidos as $dados) {
                VendaCliente::create([
                    'pdf_upload_id' => $pdf->id,
                    ...$dados
                ]);
            }
            
            $this->info("✓ Processado com sucesso! {count($dadosExtraidos)} registros criados.");
            
        } catch (\Exception $e) {
            $this->error("Erro ao processar PDF {$pdf->title}: " . $e->getMessage());
        }
    }

    private function extrairDadosTabela(string $text): array
    {
        $dados = [];
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Pular linhas vazias ou cabeçalhos
            if (empty($line) || 
                stripos($line, 'Cliente') !== false || 
                stripos($line, 'Primeira Venda') !== false ||
                stripos($line, 'Última Venda') !== false) {
                continue;
            }
            
            $dadosLinha = $this->parsearLinhaDados($line);
            
            if ($dadosLinha && !empty($dadosLinha['cliente'])) {
                $dados[] = $dadosLinha;
            }
        }
        
        return $dados;
    }

    private function parsearLinhaDados(string $line): ?array
    {
        // Limpar a linha
        $line = preg_replace('/\s+/', ' ', trim($line));
        
        // Padrões para identificar dados
        $datePattern = '/\d{2}\/\d{2}\/\d{4}/';
        $moneyPattern = '/R?\$?\s*[\d.,]+/';
        $percentPattern = '/[\d.,]+%/';
        
        // Extrair datas
        preg_match_all($datePattern, $line, $dates);
        
        // Extrair valores monetários e percentuais
        preg_match_all($moneyPattern, $line, $money);
        preg_match_all($percentPattern, $line, $percent);
        
        // Remover datas, valores e percentuais da linha para extrair o nome do cliente
        $clienteLine = $line;
        foreach ($dates[0] as $date) {
            $clienteLine = str_replace($date, '', $clienteLine);
        }
        foreach ($money[0] as $value) {
            $clienteLine = str_replace($value, '', $clienteLine);
        }
        foreach ($percent[0] as $perc) {
            $clienteLine = str_replace($perc, '', $clienteLine);
        }
        
        $cliente = trim($clienteLine);
        
        // Se não conseguiu extrair cliente, pular
        if (empty($cliente) || strlen($cliente) < 2) {
            return null;
        }
        
        $dadosCliente = [
            'cliente' => $cliente,
            'primeira_venda' => isset($dates[0][0]) ? $this->converterData($dates[0][0]) : null,
            'ultima_venda' => isset($dates[0][1]) ? $this->converterData($dates[0][1]) : null,
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
        
        // Processar valores monetários
        $valores = [];
        foreach ($money[0] as $value) {
            $cleanValue = preg_replace('/[R$\s]/', '', $value);
            $cleanValue = str_replace(',', '.', $cleanValue);
            $valores[] = floatval($cleanValue);
        }
        
        // Exemplo de bloco onde mapeia $valores para $dados
        if (count($valores) >= 1) $dados['vl_vnd_medio'] = $valores[0];
        if (count($valores) >= 2) $dados['qtd'] = $this->converterInteiro($valores[1]);
        if (count($valores) >= 3) $dados['total_venda'] = $valores[2];
        if (count($valores) >= 4) $dados['custo_venda'] = $valores[3];
        if (count($valores) >= 5) $dados['total_devolucao'] = $valores[4];
        if (count($valores) >= 6) $dados['custo_dev'] = $valores[5];
        if (count($valores) >= 7) $dados['total'] = $valores[6];
        if (count($valores) >= 8) $dados['total_custo'] = $valores[7];
        if (count($valores) >= 9) $dados['lucro_reais'] = $valores[8];
        if (count($valores) >= 10) $dados['lucro_percentual'] = $valores[9];
        
        // Processar percentual
        if (!empty($percent[0])) {
            $percentValue = str_replace('%', '', $percent[0][0]);
            $percentValue = str_replace(',', '.', $percentValue);
            $dados['lucro_percentual'] = floatval($percentValue);
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
}