<?php namespace Pivotal\Cms;

use Illuminate\Support\ServiceProvider;
use Pivotal\Course\Models\Course;
use Pivotal\Repositories\CourseRepository;

class CmsServiceProvider extends ServiceProvider
{

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
        $this->package('pivotal/cms','cms');
        include __DIR__ . '/../../routes.php';
    }


    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

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
