# Inertia.js Laravel Components

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Style CI Build Status][ico-style-ci]][link-style-ci]
[![Total Downloads][ico-downloads]][link-downloads]

Opinionated Inertia.js Laravel & Vue helper library, to scaffolding and extended your app.

Features:

<!-- no toc -->
* [Error Handler](#error-handler)
* [Pagination](#pagination)
* [IDE Helper](#ide-helper)

## Supported Versions

| Version                                                 | Laravel Version |
| ------------------------------------------------------- | --------------- |
| [1.x](https://github.com/ycs77/inertia-engage/tree/1.x) | 11.x            |

## Installation

Install the package via composer:

```bash
composer require ycs77/inertia-engage
```

Publish the config file is optional:

```bash
php artisan vendor:publish --tag=inertia-engage-config
```

Then install the Inertia.js scaffold into this app:

```bash
php artisan inertia:install
# or export with TypeScript
php artisan inertia:install --ts
```

## Error Handler

Publish the error page:

```bash
php artisan inertia:ui error
# or export with TypeScript
php artisan inertia:ui error --ts
```

Then extends the exception handler for Inertia app:

*bootstrap/providers.php.php*
```php
<?php

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
        return Inertia::exception()->handle($request, $response, $e);
    });
})
```

## Pagination

Publish pagination component and css file:

```bash
php artisan inertia:ui pagination
# or export with TypeScript
php artisan inertia:ui pagination --ts
```

## IDE Helper

Publish IDE helper file:

```bash
php artisan inertia:ide-helper
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/ycs77/inertia-engage?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen?style=flat-square
[ico-style-ci]: https://github.styleci.io/repos/800696246/shield?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/ycs77/inertia-engage?style=flat-square

[link-packagist]: https://packagist.org/packages/ycs77/inertia-engage
[link-style-ci]: https://github.styleci.io/repos/800696246
[link-downloads]: https://packagist.org/packages/ycs77/inertia-engage
