<?php

namespace App\Http\Middleware;

use App\Models\FacebookPage;
use App\Models\User;
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
        /** @var User|null $user */
        $user = $request->user();
        $activePage = $user?->resolveCurrentPage();

        $connectedPages = $user !== null
            ? $user->facebookPages()
                ->orderBy('name')
                ->get()
                ->map(fn (FacebookPage $page): array => [
                    'id' => $page->id,
                    'page_id' => $page->page_id,
                    'name' => $page->name,
                    'category' => $page->category,
                    'avatar_url' => $page->avatar_url,
                    'status' => $page->status,
                    'followers_count' => $page->followers_count,
                ])->all()
            : [];

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'facebookWorkspace' => [
                'connectedPages' => $connectedPages,
                'activePage' => $activePage ? [
                    'id' => $activePage->id,
                    'page_id' => $activePage->page_id,
                    'name' => $activePage->name,
                    'category' => $activePage->category,
                    'avatar_url' => $activePage->avatar_url,
                    'status' => $activePage->status,
                    'followers_count' => $activePage->followers_count,
                ] : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
