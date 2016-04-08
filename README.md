# bono-norm

If you want to use [Bono 2](http://bono.github.com) as your web application development framework, and you need database access. Best chance that you need [Norm](http://github.com/xinix-technology/norm). This library is best breed to integrate between Bono 2 and Norm 2.

## How to Use

Prepare Norm repository using bono-norm middleware, add `ROH\BonoNorm\Middleware\Norm` to Bono bundle middlewares configuration section or `config/config.php` file.

```php
return [
  "middlewares": [
    [ ROH\BonoNorm\Middleware\Norm::class, [
      "options" => [
        "connections" => [...],
        "collections" => [...],
        "attributes" => [...],
      ]
    ]]
  ]
];
```

This DSL is comply to `ROH\Util\Injector` accepted DSL.
