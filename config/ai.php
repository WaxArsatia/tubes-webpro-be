<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the AI provider used for document summarization and
    | quiz generation. Supported providers: "gemini", "openai"
    |
    */

    'provider' => env('AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Gemini Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration specific to the Google Gemini provider.
    |
    */

    'gemini' => [
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration specific to the OpenAI provider.
    |
    */

    'openai' => [
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

];
