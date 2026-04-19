<?php

return [
    // Serveur Ollama utilisé pour la proposition IA de qualification
    'ollama' => [
        'url' => rtrim(getenv('OLLAMA_URL') ?: 'https://ollama.kazflow.com', '/'),
        'model' => getenv('OLLAMA_MODEL') ?: 'qwen3.6',
        'api_key' => getenv('OLLAMA_API_KEY') ?: '',
        'cf_client_id' => getenv('OLLAMA_CF_CLIENT_ID') ?: '',
        'cf_client_secret' => getenv('OLLAMA_CF_CLIENT_SECRET') ?: '',
        'timeout' => 300,
    ],
    // Nombre max d'opérations déjà qualifiées envoyées en contexte
    'history_limit' => 15,
];
