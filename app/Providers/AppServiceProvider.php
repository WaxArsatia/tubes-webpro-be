<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Contracts\AIServiceInterface::class,
            function ($app) {
                $provider = config('ai.provider', 'gemini');

                return match ($provider) {
                    'openai' => new \App\Services\OpenAIService,
                    default => new \App\Services\GeminiService,
                };
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
