<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->string('priority')->nullable();
            $table->string('issue')->nullable();
            $table->integer('estimated_duration_minutes')->nullable();
            $table->string('required_skill')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->json('raw_response')->nullable();
            $table->boolean('accepted_by_admin')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
    }
};
