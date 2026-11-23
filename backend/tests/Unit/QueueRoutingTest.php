<?php

use App\Jobs\GenerateEmbedding;
use App\Jobs\ProcessDocument;
use App\Jobs\ProcessServiceRequest;

// Spec v2 section 10.3: each workload has its own queue so slow document
// imports never delay classification, and every job timeout stays below
// its connection's retry_after so a slow job is never run twice.

it('sends classification to the ai-fast queue with a timeout under retry_after', function () {
    $job = new ProcessServiceRequest(1);

    expect($job->queue)->toBe('ai-fast');
    expect($job->timeout)->toBeLessThan(config('queue.connections.redis.retry_after'));
    expect($job->timeout)->toBeGreaterThan(config('ai.request_timeout') * config('ai.max_retries'));
});

it('sends document processing to the documents queue on the long-running connection', function () {
    config(['queue.default' => 'redis']);

    foreach ([new ProcessDocument(1), new GenerateEmbedding(1, [])] as $job) {
        expect($job->queue)->toBe('documents');
        expect($job->connection)->toBe('redis-long');
        expect($job->timeout)->toBeLessThan(config('queue.connections.redis-long.retry_after'));
    }
});

it('keeps document jobs on the default connection when Redis is not in use', function () {
    config(['queue.default' => 'sync']);

    expect((new ProcessDocument(1))->connection)->toBeNull();
});

it('has a Horizon supervisor for every queue a job is sent to', function () {
    $supervised = collect(config('horizon.defaults'))->pluck('queue')->flatten()->all();

    expect($supervised)->toContain('ai-fast', 'documents', 'default');
});
