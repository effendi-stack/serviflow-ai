<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\KnowledgeController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ServiceNoteController;
use App\Http\Controllers\Api\ServiceRequestController;
use App\Http\Controllers\Api\TechnicianController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::apiResource('service-requests', ServiceRequestController::class)
        ->only(['index', 'store', 'show']);

    Route::prefix('service-requests/{serviceRequest}')->group(function () {
        Route::get('/recommended-technicians', [ServiceRequestController::class, 'recommendedTechnicians']);
        Route::post('/triage', [ServiceRequestController::class, 'reviewAiAnalysis']);
        Route::post('/cancel', [ServiceRequestController::class, 'cancel']);
        Route::post('/assign', [ServiceRequestController::class, 'assign']);
        Route::post('/schedule', [ServiceRequestController::class, 'schedule']);
        Route::post('/start', [ServiceRequestController::class, 'start']);
        Route::post('/complete', [ServiceRequestController::class, 'complete']);

        Route::post('/notes', [ServiceNoteController::class, 'store']);
        Route::post('/generate-report', [ServiceNoteController::class, 'generateReport']);
        Route::post('/finalize-report', [ServiceNoteController::class, 'finalize']);
        Route::post('/review', [ReviewController::class, 'store']);

        Route::post('/invoice', [InvoiceController::class, 'store']);
        Route::post('/invoice/issue', [InvoiceController::class, 'issue']);
        Route::post('/invoice/pay', [InvoiceController::class, 'pay']);
        Route::post('/invoice/cancel', [InvoiceController::class, 'cancel']);
    });

    Route::get('/invoices', [InvoiceController::class, 'index']);

    Route::get('/technicians', [TechnicianController::class, 'index']);
    Route::get('/technicians/{technician}', [TechnicianController::class, 'show']);
    Route::patch('/technicians/{technician}/status', [TechnicianController::class, 'updateStatus']);

    Route::get('/customers', [CustomerController::class, 'index'])->middleware('role:admin');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('role:admin');

    Route::middleware('throttle:30,1')->group(function () {
        Route::post('/knowledge/documents', [KnowledgeController::class, 'uploadDocument']);
        Route::get('/knowledge/documents', [KnowledgeController::class, 'documents']);
        Route::delete('/knowledge/documents/{document}', [KnowledgeController::class, 'destroy']);
        Route::post('/knowledge/documents/{document}/reindex', [KnowledgeController::class, 'reindex']);
        Route::post('/knowledge/ask', [KnowledgeController::class, 'ask']);
    });

    Route::get('/analytics/summary', [AnalyticsController::class, 'summary']);
    Route::post('/analytics/explain', [AnalyticsController::class, 'explain']);
});
