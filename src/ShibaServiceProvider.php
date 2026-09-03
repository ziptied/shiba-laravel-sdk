<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel;

use Bentonow\ShibaLaravel\Http\InboundWebhook;
use Bentonow\ShibaLaravel\Http\ShibaClient;
use Bentonow\ShibaLaravel\Http\WebhookSignature;
use Bentonow\ShibaLaravel\Mail\ShibaMessagePayload;
use Bentonow\ShibaLaravel\Mail\ShibaTransport;
use Bentonow\ShibaLaravel\Queue\ReleaseRateLimitedJob;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

final class ShibaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/shiba.php', 'shiba');

        $this->app['config']->set('mail.mailers.shiba', array_merge(
            ['transport' => 'shiba'],
            (array) config('mail.mailers.shiba', []),
        ));

        $this->app->singleton(ShibaClient::class);
        $this->app->singleton(ShibaMessagePayload::class);
        $this->app->singleton(WebhookSignature::class);
        $this->app->singleton(InboundWebhook::class);
    }

    public function boot(Dispatcher $events): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/shiba.php' => config_path('shiba.php'),
            ], 'shiba-config');
        }

        Mail::extend('shiba', fn (): ShibaTransport => new ShibaTransport(
            $this->app->make(ShibaClient::class),
            $this->app->make(ShibaMessagePayload::class),
        ));

        $events->listen(JobExceptionOccurred::class, ReleaseRateLimitedJob::class);

        if ((bool) config('shiba.webhook.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');
        }
    }
}
