<?php

namespace App\Providers;

use App\Models\Institution;
use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Observers\LogbookEntryObserver;
use App\Policies\LogbookEntryPolicy;
use App\Policies\MahasiswaTaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
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
        // Daftarkan policy secara eksplisit agar tidak bergantung pada
        // auto-discovery.
        Gate::policy(LogbookEntry::class, LogbookEntryPolicy::class);
        Gate::policy(MahasiswaTa::class, MahasiswaTaPolicy::class);

        // Invalidate cache health indicator saat entry berubah.
        LogbookEntry::observe(LogbookEntryObserver::class);

        // Paksa skema https bila diakses via reverse-proxy HTTPS.
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        // Terapkan profil institusi (brand dinamis) saat boot.
        try {
            Institution::active()->applyToConfig();
        } catch (\Throwable $e) {
            // tabel belum ada (mis. saat migrate fresh) — abaikan.
        }
    }
}
