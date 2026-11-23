<?php

return [
    'provider' => env('AI_PROVIDER', 'ollama'),

    'ollama' => [
        // On macOS, run Ollama natively (brew install ollama) so it can use
        // the GPU via Metal -- Docker on macOS has no GPU access and is
        // several times slower. Containers reach the host through
        // host.docker.internal. On a Linux GPU host, start the compose
        // "ollama" profile and point this at http://ollama:11434 instead.
        'base_url' => env('OLLAMA_BASE_URL', 'http://host.docker.internal:11434'),

        // Larger model for long-form generation (service reports, RAG
        // answers, analytics narration).
        'chat_model' => env('OLLAMA_CHAT_MODEL', 'llama3.1:8b'),

        // Small model for short structured tasks (classification, ranking).
        // A 3B model answers these in a fraction of the time with
        // comparable accuracy, because the output is a tiny JSON object.
        'fast_model' => env('OLLAMA_FAST_MODEL', 'llama3.2:3b'),

        'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),

        // How long Ollama keeps a model in memory after a request. Keeping
        // models warm avoids paying several seconds of load time on the
        // first request after an idle period. "-1" keeps it loaded forever.
        'keep_alive' => env('OLLAMA_KEEP_ALIVE', '30m'),
    ],

    // Per-feature generation settings. "model" is "fast" or "chat" (mapped
    // to the models above); max_tokens caps output length, which is the
    // main driver of generation time.
    'features' => [
        'ticket_classification' => ['model' => 'fast', 'temperature' => 0.0, 'max_tokens' => 200],
        'technician_recommendation' => ['model' => 'fast', 'temperature' => 0.0, 'max_tokens' => 300],
        'service_report' => ['model' => 'chat', 'temperature' => 0.3, 'max_tokens' => 600],
        'knowledge_answer' => ['model' => 'chat', 'temperature' => 0.2, 'max_tokens' => 500],
        'analytics_explanation' => ['model' => 'chat', 'temperature' => 0.3, 'max_tokens' => 400],
    ],

    'request_timeout' => (int) env('AI_REQUEST_TIMEOUT', 60),
    'max_retries' => (int) env('AI_MAX_RETRIES', 2),

    'rag' => [
        'chunk_size' => (int) env('RAG_CHUNK_SIZE', 800),
        'chunk_overlap' => (int) env('RAG_CHUNK_OVERLAP', 120),
        'top_k' => (int) env('RAG_TOP_K', 5),
    ],
];
