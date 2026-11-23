<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec v2 section 10.4. The original IVFFlat index was created on an empty
 * table, so its cluster centroids were never trained and recall is poor
 * until it is rebuilt with data. HNSW needs no training, works from the
 * first row, and gives better speed/recall for this table size.
 *
 * embedding_model records which model produced each vector, so a later
 * model change can be detected and the affected chunks re-embedded.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS document_chunks_embedding_idx');
        DB::statement('CREATE INDEX document_chunks_embedding_hnsw_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)');

        Schema::table('document_chunks', function ($table) {
            $table->string('embedding_model')->nullable()->after('content');
            $table->index(['document_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::table('document_chunks', function ($table) {
            $table->dropIndex(['document_id', 'chunk_index']);
            $table->dropColumn('embedding_model');
        });

        DB::statement('DROP INDEX IF EXISTS document_chunks_embedding_hnsw_idx');
        DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }
};
