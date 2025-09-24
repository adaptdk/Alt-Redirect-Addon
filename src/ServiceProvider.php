<?php

namespace AltDesign\AltRedirect;

use AltDesign\AltRedirect\Listeners\StoreOldUri;
use AltDesign\AltRedirect\Listeners\CreateRedirect;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\CollectionTreeSaving;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntrySaving;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;

class ServiceProvider extends AddonServiceProvider
{
    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $vite = [
        'input' => [
            'resources/js/alt-redirect-addon.js',
            'resources/css/alt-redirect-addon.css'
        ],
        'publicDirectory' => 'resources/dist',
    ];

    protected $middlewareGroups = [
        'web' => [
            \AltDesign\AltRedirect\Http\Middleware\CheckForRedirects::class,
        ]
    ];


	protected $listen = [
		EntrySaving::class => [
			StoreOldUri::class,
		],
		EntrySaved::class => [
			CreateRedirect::class,
		],
	];


    /**
     * Register our addon and child menus in the nav
     *
     * @return self
     */
    public function addToNav() : self
    {
        Nav::extend(function ($nav) {
            $nav->content('Redirect')
                ->section('Tools')
                ->route('alt-redirect.index')
                ->can('view alt-redirect')
                ->icon('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" transform="scale(.66667)"><rect width="13" height="3" x=".5" y=".499" rx="1" ry="1"/><rect width="13" height="3" x="10.5" y="12.499" rx="1" ry="1"/><rect width="13" height="3" x="10.5" y="20.499" rx="1" ry="1"/><path d="M17.5 15.499v5M7.5 3.5V8A1.5 1.5 0 0 0 9 9.5h7a1.5 1.5 0 0 1 1.5 1.5v1.5"/></g></svg>');
        });

        return $this;
    }

    /**
     * Register our permissions, so we can control who can see the settings.
     *
     * @return self
     */
    public function registerPermissions() : self
    {
        Permission::register('view alt-redirect')
                  ->label('View Alt Redirect Settings');

        return $this;
    }

    public function bootAddon()
    {
        $this->addToNav()
            ->registerPermissions();

		$this->publishesMigrations([
			__DIR__.'/../database/migrations' => database_path('migrations'),
		]);
    }
}

