<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature'); // ticket_classification | technician_recommendation | service_report | rag_query | analytics_explanation
            $table->string('model');
            $table->text('input');
            $table->text('output')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->integer('latency_ms')->nullable();
            $table->boolean('succeeded')->default(true);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interactions');
    }
};
