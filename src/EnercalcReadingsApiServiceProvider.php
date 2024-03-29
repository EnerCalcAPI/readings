<?php

namespace Enercalcapi\Readings;

use Illuminate\Support\ServiceProvider;

class EnercalcReadingsApiServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom($this->configPath(), 'config');
    }

    /**
     * Get the config path
     *
     * @return string
     */
    public function configPath()
    {
        return __DIR__ . '/../config/config.php';
    }

    /**
     * Get the config path
     *
     * @return string
     */
    protected function getConfigName()
    {
        return config_path('enercalcreadingsapi.php');
    }
}
