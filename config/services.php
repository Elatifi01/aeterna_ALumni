<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'ollama'),
        'max_tool_iterations' => (int) env('AI_MAX_TOOL_ITERATIONS', 3),
        'job_searches_per_conversation' => (int) env('AI_JOB_SEARCHES_PER_CONVERSATION', 5),
        'history_limit' => (int) env('AI_HISTORY_LIMIT', 20),

        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
            'model' => env('OLLAMA_MODEL', 'llama3.2:3b'),
            'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
        ],
    ],

    'parsebot' => [
        'key' => env('PARSEBOT_API_KEY', env('EMPLOIMA_API_KEY')),
        'monthly_budget' => (int) env('PARSEBOT_MONTHLY_BUDGET', env('EMPLOIMA_MONTHLY_BUDGET', 180)),
        'cache_ttl' => (int) env('PARSEBOT_CACHE_TTL', env('EMPLOIMA_CACHE_TTL', 86400)),
    ],

    'emploima' => [
        'enabled' => env('EMPLOIMA_ENABLED', false),
        'base_url' => env('EMPLOIMA_BASE_URL', ''),
        'key' => env('PARSEBOT_API_KEY', env('EMPLOIMA_API_KEY')),
        'monthly_budget' => (int) env('PARSEBOT_MONTHLY_BUDGET', env('EMPLOIMA_MONTHLY_BUDGET', 180)),
        'cache_ttl' => (int) env('PARSEBOT_CACHE_TTL', env('EMPLOIMA_CACHE_TTL', 86400)),
    ],

    'jobs' => [
        'sources' => [
            'linkedin' => [
                'label' => 'LinkedIn',
                'enabled' => env('LINKEDIN_ENABLED', false),
                'base_url' => env('LINKEDIN_BASE_URL', ''),
                'endpoint' => 'search_jobs',
                'jobs_key' => 'jobs',
                'default_parameters' => ['location' => env('LINKEDIN_DEFAULT_LOCATION', 'Morocco')],
                'parameters' => ['keywords' => 'keywords', 'city' => 'location'],
                'fixed_parameters' => [],
                'fields' => [
                    'title' => 'title',
                    'url' => 'job_url',
                    'company_name' => 'company',
                    'company_url' => 'company_url',
                    'location' => 'location',
                    'description_snippet' => 'description',
                ],
            ],
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
