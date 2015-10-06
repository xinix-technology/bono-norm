<?php

namespace ROH\FNorm\Resolver;

use Bono\App;

class NormResolver
{
    protected $basePath;

    protected $cache = [];

    public function __construct()
    {
        $this->basePath = rtrim(App::getInstance()->getOption('config.path'), '/').'/collections/';
    }

    public function resolve($options)
    {
        if (!array_key_exists($options['name'], $this->cache)) {
            $path = $this->basePath . $options['name'] . '.php';
            if (is_readable($path)) {
                $this->cache[$options['name']] = include $path;
            } else {
                $this->cache[$options['name']] = null;
            }
        }

        return $this->cache[$options['name']];
    }
}
