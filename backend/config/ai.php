<?php

return [
    'provider' => env('AI_PROVIDER', 'ollama'),

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://ollama:11434'),
        'chat_model' => env('OLLAMA_CHAT_MODEL', 'llama3.1'),
        'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
    ],

    'request_timeout' => (int) env('AI_REQUEST_TIMEOUT', 60),
    'max_retries' => (int) env('AI_MAX_RETRIES', 2),

    'rag' => [
        'chunk_size' => (int) env('RAG_CHUNK_SIZE', 800),
        'chunk_overlap' => (int) env('RAG_CHUNK_OVERLAP', 120),
        'top_k' => (int) env('RAG_TOP_K', 5),
    ],
];
