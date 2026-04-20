<?php

namespace App\Providers;

use App\Models\Collection;
use App\Observers\CollectionObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()
            : null
        );

        $this->registerObservers();
    }

    protected function registerObservers(): void
    {
        if (class_exists(CollectionObserver::class)) {
            Collection::observe(CollectionObserver::class);
        }
    }
}
