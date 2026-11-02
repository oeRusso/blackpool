<?php

namespace App\Providers;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use App\Policies\RolePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

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
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Register policies that cannot be auto-discovered and the admin-area gate.
     */
    protected function configureAuthorization(): void
    {
        Gate::policy(Role::class, RolePolicy::class);

        Gate::define('access-admin', fn (User $user): bool => $user->can(PermissionName::AccessAdmin->value));

        // Laravel Pulse dashboard (/pulse) is restricted to administrators.
        Gate::define('viewPulse', fn (User $user): bool => $user->hasRole(RoleName::Admin->value));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
