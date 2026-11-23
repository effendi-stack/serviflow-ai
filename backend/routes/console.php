<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ai:warm', function (\App\AI\Contracts\AIProviderInterface $provider) {
    if (! $provider instanceof \App\AI\Providers\OllamaProvider) {
        $this->info('The configured AI provider does not need warming.');

        return 0;
    }

    $failed = false;

    foreach ($provider->configuredModels() as $model) {
        $start = microtime(true);

        try {
            $provider->warm($model);
            $this->info(sprintf('Loaded %s in %.1fs', $model, microtime(true) - $start));
        } catch (\Throwable $e) {
            $failed = true;
            $this->error("Could not load {$model}: {$e->getMessage()}");
            $this->line("  Pull it first with: ollama pull {$model}");
        }
    }

    return $failed ? 1 : 0;
})->purpose('Preload the configured Ollama models so the first AI request is not slow');
