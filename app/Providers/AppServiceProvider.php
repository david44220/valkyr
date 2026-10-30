<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Tournament;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! $this->app->isProduction());
        Model::automaticallyEagerLoadRelationships();

        // Flexible binding supporting both slug (/tournaments/valkyr-sovereign-series-s04) and numeric ID (/api/tournaments/1)
        Route::bind('tournament', function (string $value): Tournament {
            if (ctype_digit($value)) {
                return Tournament::whereKey((int) $value)->firstOrFail();
            }

            return Tournament::where('slug', $value)->firstOrFail();
        });

        RateLimiter::for('auth', function (Request $request): Limit {
            $email = strtolower(trim((string) $request->input('email', 'guest')));

            return Limit::perMinute(10)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('financial', function (Request $request): Limit {
            $userId = (string) ($request->user()?->id ?? $request->ip());

            return Limit::perMinute(60)->by('fin|'.$userId);
        });
    }
}
