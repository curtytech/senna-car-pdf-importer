<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PrinsFrank\PdfParser\PdfParser;

class TestePdfConverterCommand extends Command
{
    protected $signature = 'pdf:teste-converter {arquivo}';
    protected $description = 'Testa a conversão de PDF para texto usando PrinsFrank';

    public function handle()
    {
        $arquivo = $this->argument('arquivo');
        
        if (!file_exists($arquivo)) {
            $this->error("Arquivo não encontrado: {$arquivo}");
            return 1;
        }
        
        $this->info("Testando conversão do arquivo: {$arquivo}");
        
        try {
            $startTime = microtime(true);
            
            // Teste com PrinsFrank
            $this->info("🔄 Testando com PrinsFrank Parser...");
            $document = (new PdfParser())->parseFile($arquivo);
            $textPrinsFrank = $document->getText();
            $timePrinsFrank = microtime(true) - $startTime;
            
            $this->info("✅ PrinsFrank concluído em " . number_format($timePrinsFrank, 2) . " segundos");
            $this->info("📄 Caracteres extraídos: " . number_format(strlen($textPrinsFrank)));
            $this->info("📝 Linhas extraídas: " . number_format(substr_count($textPrinsFrank, "\n") + 1));
            
            // Teste com Smalot para comparação
            $this->info("\n🔄 Testando com Smalot Parser para comparação...");
            $startTime = microtime(true);
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($arquivo);
            $textSmalot = $pdf->getText();
            $timeSmalot = microtime(true) - $startTime;
            
            $this->info("✅ Smalot concluído em " . number_format($timeSmalot, 2) . " segundos");
            $this->info("📄 Caracteres extraídos: " . number_format(strlen($textSmalot)));
            $this->info("📝 Linhas extraídas: " . number_format(substr_count($textSmalot, "\n") + 1));
            
            // Comparação
            $this->info("\n📊 COMPARAÇÃO:");
            $this->info("PrinsFrank: " . number_format($timePrinsFrank, 2) . "s | " . number_format(strlen($textPrinsFrank)) . " chars");
            $this->info("Smalot:     " . number_format($timeSmalot, 2) . "s | " . number_format(strlen($textSmalot)) . " chars");
            
            $speedup = $timeSmalot / $timePrinsFrank;
            $this->info("🚀 PrinsFrank é " . number_format($speedup, 1) . "x mais rápido");
            
            // Mostrar preview do texto
            if ($this->confirm('Deseja ver um preview do texto extraído?')) {
                $preview = substr($textPrinsFrank, 0, 500);
                $this->info("\n📖 PREVIEW (primeiros 500 caracteres):");
                $this->line($preview);
                if (strlen($textPrinsFrank) > 500) {
                    $this->info("... (texto truncado)");
                }
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Erro ao processar PDF: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}