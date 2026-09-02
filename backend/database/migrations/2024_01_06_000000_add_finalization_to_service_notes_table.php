<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_notes', function (Blueprint $table) {
            $table->foreignId('finalized_by')->nullable()->after('final_report')->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->after('finalized_by');
        });
    }

    public function down(): void
    {
        Schema::table('service_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropColumn('finalized_at');
        });
    }
};
