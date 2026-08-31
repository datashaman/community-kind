<?php

namespace App\Providers;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Cryptography\ContactBlindIndexer;
use App\Cryptography\VersionedKeyRing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            ClassifiedDataEncrypter::class,
            fn () => new ClassifiedDataEncrypter(new VersionedKeyRing('classified_data.encryption')),
        );
        $this->app->bind(
            ContactBlindIndexer::class,
            fn () => new ContactBlindIndexer(new VersionedKeyRing('classified_data.contact_index')),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureProductionReleaseIsIdentified();
        $this->configureDefaults();
    }

    /**
     * Refuse to run a production interface without corresponding source metadata.
     */
    private function ensureProductionReleaseIsIdentified(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        $release = config('source.release');
        $repository = config('source.repository');

        if (! is_string($release) || $release === '' || $release === 'development') {
            throw new LogicException('APP_RELEASE must identify the deployed commit or release.');
        }

        if (! is_string($repository) || filter_var($repository, FILTER_VALIDATE_URL) === false) {
            throw new LogicException('APP_SOURCE_REPOSITORY must be a valid corresponding-source URL.');
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(function (): Password {
            $rule = Password::min(14);

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
