<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec v2 section 10.4. PostgreSQL does not index foreign-key columns
 * automatically, so every relation lookup and ownership-scoped list was a
 * sequential scan. These cover the lists, filters, relation loads and the
 * scheduling conflict check.
 */
return new class extends Migration
{
    private array $indexes = [
        'service_requests' => [['customer_id', 'created_at'], ['status', 'created_at'], ['scheduled_at']],
        'assignments' => [['technician_id', 'status']],
        'service_notes' => [['service_request_id'], ['technician_id']],
        'status_history' => [['service_request_id', 'created_at']],
        'ai_analyses' => [['service_request_id']],
        'invoices' => [['service_request_id'], ['status']],
        'reviews' => [['service_request_id'], ['technician_id']],
        'technicians' => [['user_id']],
        'customers' => [['user_id']],
        'documents' => [['status']],
        'ai_interactions' => [['feature', 'created_at'], ['created_at']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes) {
                foreach ($indexes as $columns) {
                    $blueprint->index($columns);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes) {
                foreach ($indexes as $columns) {
                    $blueprint->dropIndex($columns);
                }
            });
        }
    }
};
