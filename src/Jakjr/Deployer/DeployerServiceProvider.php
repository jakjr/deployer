<?php namespace Jakjr\Deployer;

use Illuminate\Support\ServiceProvider;

class DeployerServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('jakjr/deployer');

        $this->app->bind('jakjr::command.deployer', function($app) {
            return new DeployerCommand();
        });
        $this->commands(array(
            'jakjr::command.deployer'
        ));
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		//
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}
