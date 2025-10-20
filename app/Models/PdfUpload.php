<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class PdfUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'text_extrated',
        'file_name',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    /**
     * Relacionamento com o usuário que fez o upload
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Relacionamento com os dados de vendas extraídos do PDF
     */
    public function vendasClientes(): HasMany
    {
        return $this->hasMany(VendaCliente::class);
    }

    /**
     * Accessor para obter o tamanho do arquivo formatado
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Accessor para obter a URL do arquivo
     */
    public function getFileUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }

    /**
     * Método para deletar o arquivo físico quando o modelo for deletado
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($pdfUpload) {
            if (Storage::exists($pdfUpload->file_path)) {
                Storage::delete($pdfUpload->file_path);
            }
        });
    }
}