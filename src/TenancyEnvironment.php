<?php namespace LaraLeague\MultiTenant;

use LaraLeague\MultiTenant\Helpers\TenancyRequestHelper;
use LaraLeague\MultiTenant\Models\Hostname;
use LaraLeague\MultiTenant\Models\Tenant;
use LaraLeague\MultiTenant\Models\Website;
use LaraLeague\MultiTenant\Repositories\HostnameRepository;
use LaraLeague\MultiTenant\Repositories\TenantRepository;
use LaraLeague\MultiTenant\Repositories\WebsiteRepository;
use LaraLeague\MultiTenant\Tenant\DatabaseConnection;
use LaraLeague\MultiTenant\Tenant\Directory;
use LaraLeague\MultiTenant\Tenant\View as TenantView;
use View;

/**
 * Class TenancyEnvironment
 *
 * Sets the tenant environment; overrules laravel core and sets the database connection
 *
 * @package LaraLeague\MultiTenant
 */
class TenancyEnvironment
{

    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * @var \LaraLeague\MultiTenant\Models\Hostname
     */
    protected $hostname;

    /**
     * @var \LaraLeague\MultiTenant\Models\Website
     */
    protected $website;

    public function __construct($app)
    {
        // share the application
        $this->app = $app;

        // sets file access to as wide as possible, ignoring server masks
        umask(0);

        // bind tenancy environment into IOC
        $this->setupBinds();

        // load hostname object or default
        $this->hostname = TenancyRequestHelper::hostname($this->app->make('LaraLeague\MultiTenant\Contracts\HostnameRepositoryContract'));

        // set website
        $this->website = !is_null($this->hostname) ? $this->hostname->website : null;

        // sets the database connection for the tenant website
        if(!is_null($this->website)) {
            $this->website->database->setCurrent();
        }

        // register tenant IOC bindings
        $this->setupTenantBinds();

        // register tenant paths for website
        if(!is_null($this->website)) {
            $this->app->make('LaraLeague\MultiTenant\Contracts\DirectoryContract')->registerPaths($app);
        }

        // register view shares
        View::composer('*', 'LaraLeague\MultiTenant\Composers\TenantComposer');
    }

    /**
     * Binds all interfaces to the IOC container
     */
    protected function setupBinds()
    {
        /*
         * Tenant repository
         */
        $this->app->bind('LaraLeague\MultiTenant\Contracts\TenantRepositoryContract', function()
        {
            return new TenantRepository(new Tenant);
        });
        /*
         * Tenant hostname repository
         */
        $this->app->bind('LaraLeague\MultiTenant\Contracts\HostnameRepositoryContract', function()
        {
            return new HostnameRepository(new Hostname);
        });
        /*
         * Tenant website repository
         */
        $this->app->bind('LaraLeague\MultiTenant\Contracts\WebsiteRepositoryContract', function($app)
        {
            return new WebsiteRepository(
                new Website,
                $this->app->make('LaraLeague\MultiTenant\Contracts\HostnameRepositoryContract')
            );
        });
    }

    /**
     * Binds all tenant specific interfaces into the IOC container
     */
    protected function setupTenantBinds()
    {
        $hostname = $this->hostname;

        /*
         * Tenant directory mapping and functionality
         */
        $this->app->singleton('LaraLeague\MultiTenant\Contracts\DirectoryContract', function() use ($hostname)
        {
            return $hostname ? new Directory($hostname->website) : null;
        });
        /*
         * Tenant view shares
         */
        $this->app->singleton('tenant.view', function() use ($hostname)
        {
            return new TenantView([
                'hostname' => $hostname
            ]);
        });
        /*
         * Tenant hostname
         */
        $this->app->singleton('tenant.hostname', function() use ($hostname)
        {
            return $hostname;
        });
    }
}