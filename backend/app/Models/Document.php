<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'category', 'file_path', 'status', 'page_count',
        'uploaded_by', 'processing_error',
    ];

    public function chunks()
    {
        return $this->hasMany(DocumentChunk::class);
    }
}
