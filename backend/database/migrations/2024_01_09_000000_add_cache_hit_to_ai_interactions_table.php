<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_interactions', function (Blueprint $table) {
            // True when the answer came from the result cache instead of a
            // model call -- keeps the audit trail complete and lets latency
            // reports separate cached from live calls.
            $table->boolean('cache_hit')->default(false)->after('succeeded');
        });
    }

    public function down(): void
    {
        Schema::table('ai_interactions', function (Blueprint $table) {
            $table->dropColumn('cache_hit');
        });
    }
};
