<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Square\SquareClient;

class SquareServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(SquareClient::class, function ($app) {
            return new SquareClient([
                'accessToken' => config('square.access_token'),
                'environment' => config('square.environment'),
            ]);
        });
    }
}
