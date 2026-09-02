<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('assigned'); // assigned | started | completed | cancelled
            $table->decimal('ai_rank_score', 5, 2)->nullable();
            $table->text('ai_rationale')->nullable();
            $table->boolean('was_ai_recommended')->default(false);
            $table->timestamps();

            $table->unique(['service_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
