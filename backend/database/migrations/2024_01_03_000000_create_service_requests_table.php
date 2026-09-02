<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('service_type');
            $table->text('description');
            $table->string('status')->default('new');
            // new -> analyzing -> ready_for_assignment -> assigned -> scheduled
            // -> in_progress -> completed -> reviewed -> closed | cancelled
            $table->string('category')->nullable();
            $table->string('priority')->nullable(); // low|medium|high|critical
            $table->string('required_skill')->nullable();
            $table->integer('estimated_duration_minutes')->nullable();
            $table->date('preferred_date')->nullable();
            $table->time('preferred_time')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('address');
            $table->timestamps();

            $table->index(['status']);
            $table->index(['priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
