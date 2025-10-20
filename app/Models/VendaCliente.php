<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendaCliente extends Model
{
    use HasFactory;

    protected $table = 'vendas_clientes';

    protected $fillable = [
        'pdf_upload_id',
        'pdf_text_converter_id',
        'cliente',
        'primeira_venda',
        'ultima_venda',
        'vl_vnd_medio',
        'qtd',
        'total_venda',
        'custo_venda',
        'total_devolucao',
        'custo_dev',
        'total',
        'total_custo',
        'lucro_reais',
        'lucro_percentual',
    ];

    protected $casts = [
        'primeira_venda' => 'date',
        'ultima_venda' => 'date',
        'vl_vnd_medio' => 'decimal:2',
        'qtd' => 'integer',
        'total_venda' => 'decimal:2',
        'custo_venda' => 'decimal:2',
        'total_devolucao' => 'decimal:2',
        'custo_dev' => 'decimal:2',
        'total' => 'decimal:2',
        'total_custo' => 'decimal:2',
        'lucro_reais' => 'decimal:2',
        'lucro_percentual' => 'decimal:2',
    ];

    public function pdfUpload(): BelongsTo
    {
        return $this->belongsTo(PdfUpload::class);
    }

    public function pdfTextConverter(): BelongsTo
    {
        return $this->belongsTo(PdfTextConverter::class);
    }
}