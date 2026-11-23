<?php

// AI provider settings (T1). Every provider here speaks the OpenAI-compatible
// Chat Completions API, so switching from Groq to a local llama.cpp/Ollama server
// is a configuration change, not a code change. Keys stay in the server's .env.

return [
    'enabled' => (bool) env('AI_ENABLED', true),

    'timeout' => (int) env('AI_TIMEOUT_SECONDS', 20),

    // Tried in order; the second is used only when the first fails (T2).
    'providers' => array_values(array_filter([
        [
            'name' => env('AI_PROVIDER_NAME', 'groq'),
            'base_url' => env('AI_BASE_URL'),
            'model' => env('AI_MODEL'),
            'api_key' => env('AI_API_KEY'),
        ],
        [
            'name' => env('AI_FALLBACK_PROVIDER_NAME', 'openrouter'),
            'base_url' => env('AI_FALLBACK_BASE_URL'),
            'model' => env('AI_FALLBACK_MODEL'),
            'api_key' => env('AI_FALLBACK_API_KEY'),
        ],
    ], fn (array $p) => ! empty($p['base_url']) && ! empty($p['model']))),

    'prompt_version' => 'classify.v1',

    // Stay under the free-tier limit (Groq: about 30 requests per minute).
    'max_requests_per_minute' => (int) env('AI_MAX_REQUESTS_PER_MINUTE', 25),
];
