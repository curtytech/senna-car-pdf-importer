<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendas_clientes', function (Blueprint $table) {
            $table->id();

            // Torna nullable para suportar inserções sem PdfUpload
            $table->foreignId('pdf_upload_id')
                ->nullable()
                ->constrained('pdf_uploads')
                ->onDelete('cascade');

            // Mantém nullable para PdfTextConverter
            $table->foreignId('pdf_text_converter_id')
                ->nullable()
                ->constrained('pdf_text_converters')
                ->nullOnDelete();

            $table->string('cliente');
            $table->date('primeira_venda')->nullable();
            $table->date('ultima_venda')->nullable();
            $table->decimal('vl_vnd_medio', 10, 2)->nullable();
            $table->integer('qtd')->nullable();
            $table->decimal('total_venda', 10, 2)->nullable();
            $table->decimal('custo_venda', 10, 2)->nullable();
            $table->decimal('total_devolucao', 10, 2)->nullable();
            $table->decimal('custo_dev', 10, 2)->nullable();
            $table->decimal('total', 10, 2)->nullable();
            $table->decimal('total_custo', 10, 2)->nullable();
            $table->decimal('lucro_reais', 10, 2)->nullable();
            $table->decimal('lucro_percentual', 5, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendas_clientes');
    }
};