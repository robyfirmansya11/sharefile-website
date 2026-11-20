<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

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
            'locale' => app()->getLocale(),
            'share' => [
                'driver' => config('share.driver'),
                'domain' => config('share.driver') === 'windows' ? config('share.windows.domain') : null,
                'writesEnabled' => config('share.driver') !== 'windows' || (bool) config('share.windows.writes_enabled'),
            ],
            'auth' => [
                'user' => $request->user(),
                'can_manage_staff' => config('share.driver') === 'local' && $request->user() instanceof \App\Models\User && (bool) $request->user()->is_it_admin && $request->user()->is_active,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
