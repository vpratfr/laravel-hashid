<?php

namespace ElfSundae\Laravel\Hashid;

use InvalidArgumentException;
use Illuminate\Support\Manager;

class HashidManager extends Manager
{
    /**
     * Get a hashid connection instance.
     *
     * @param  string|null  $name
     * @return mixed
     */
    public function connection($name = null)
    {
        return $this->driver($name);
    }

    /**
     * Get all of the created connections.
     *
     * @return array
     */
    public function getConnections()
    {
        return $this->getDrivers();
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection()
    {
        return $this->app['config']['hashid.default'];
    }

    /**
     * Set the default connection name.
     *
     * @param  string  $name
     * @return $this
     */
    public function setDefaultConnection($name)
    {
        $this->app['config']['hashid.default'] = $name;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultDriver()
    {
        return $this->getDefaultConnection();
    }

    /**
     * {@inheritdoc}
     */
    protected function createDriver($name)
    {
        $config = $this->app['config']->get("hashid.connections.{$name}", []);

        // First we will check by the connection name to see if a creator method
        // exists for the given connection.
        if (isset($this->customCreators[$name])) {
            return $this->customCreators[$name]($this->app, $config, $name);
        }

        // Next we will check by the driver name to see if a creator method
        // exists for the given driver, and will call the Closure if so,
        // which allows us to have a more generic resolver for the drivers
        // themselves which applies to all connections.
        if (isset($config['driver']) &&
            isset($this->customCreators[$driver = $config['driver']])
        ) {
            return $this->customCreators[$driver]($this->app, $this->getConfigWithoutDriver($config), $name);
        }

        return $this->createConnection($config);
    }

    /**
     * Create a new hashid connection instance.
     *
     * @param  array  $config
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function createConnection(array $config)
    {
        if (! isset($config['driver'])) {
            throw new InvalidArgumentException('A driver must be specified.');
        }

        $driver = $config['driver'];

        if ($this->app->bound($key = "hashid.connection.{$driver}")) {
            if ($this->app->isShared($key)) {
                return $this->app->make($key);
            }

            // `Container::make($abstract, $parameters)` which can
            // pass additional parameters to the constructor was removed
            // in Laravel 5.4 (https://github.com/laravel/internals/issues/391),
            // but then re-added as `makeWith()` in v5.4.16
            // (https://github.com/laravel/framework/pull/18271). And in L55
            // `makeWith()` is just an alias to `make()`.
            $makeWith = method_exists($this->app, 'makeWith') ? 'makeWith' : 'make';

            return $this->app->{$makeWith}($key, [
                'app' => $this->app,
                'config' => $this->getConfigWithoutDriver($config),
            ]);
        }

        throw new InvalidArgumentException("Unsupported driver [$driver]");
    }

    /**
     * Get connection configuration without "driver" element.
     *
     * @param  array  $config
     * @return array
     */
    protected function getConfigWithoutDriver($config)
    {
        unset($config['driver']);

        return $config;
    }
}
