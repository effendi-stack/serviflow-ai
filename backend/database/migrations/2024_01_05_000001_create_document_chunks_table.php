<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->integer('chunk_index');
            $table->integer('page_number')->nullable();
            $table->text('content');
            // pgvector column added via raw SQL below (embedding vector(768))
            $table->timestamps();
        });

        // nomic-embed-text produces 768-dim embeddings; adjust to match your embedding model.
        DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(768)');
        DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
