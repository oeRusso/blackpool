<?php

namespace App\Http\Middleware;

use App\Enums\PermissionName;
use App\Models\Category;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\Models\Role;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Signed-in areas gain nothing from SSR (no crawlers, no SEO), so they skip
     * the round trip to the Node server.
     *
     * @var array<int, string>
     */
    protected $withoutSsr = ['admin', 'admin/*', 'dashboard', 'settings/*'];

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'appUrl' => rtrim((string) config('app.url'), '/'),
            'locale' => app()->getLocale(),
            'locales' => config('app.supported_locales'),
            'repositoryUrl' => config('app.repository_url'),
            'demo' => config('demo.enabled') ? [
                'password' => config('demo.password'),
                'accounts' => config('demo.accounts'),
            ] : null,
            'auth' => fn (): array => $this->auth($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The authenticated user plus a map of UI abilities computed on the server.
     * The UI only uses it to show or hide controls; every endpoint authorizes again.
     *
     * @return array<string, mixed>
     */
    protected function auth(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return ['user' => null, 'roles' => [], 'can' => (object) []];
        }

        $can = [
            'accessAdmin' => $user->can('access-admin'),
            'manageCategories' => $user->can('viewAny', Category::class),
            'manageLessons' => $user->can('viewAny', Lesson::class),
            'publishLessons' => $user->can(PermissionName::PublishLessons->value),
            'manageUsers' => $user->can('viewAny', User::class),
            'manageRoles' => $user->can('viewAny', Role::class),
            'viewPremiumLessons' => $user->can(PermissionName::ViewPremiumLessons->value),
        ];

        return [
            'user' => $user->withoutRelations(),
            'roles' => $user->getRoleNames(),
            'can' => $can,
        ];
    }
}
