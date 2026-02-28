# wp-rest-fluent

A fluent routing layer for the WordPress REST API with middleware, groups and response formatting.

Inspired by Laravel-style routing and middleware patterns, adapted for WordPress.

---

## Requirements

- PHP >= 8.1
- WordPress (must run inside a WP environment)

If using Roots Sage, v10 or above is required.

---

## Installation

Install via Composer:

```bash
composer require mrldavies/wp-rest-fluent
```

---

## Basic Usage

Import the class:

```php
use Mrldavies\WpRestFluent\Rest;
```

Define your routes:

```php
Rest::get('/hello/{name:alpha}')
    ->handler(function ($request) {
        return [
            'data' => ['message' => "Hello {$request['name']}"],
            'status' => 200,
        ];
    })
    ->formatter();
```

Register routes during boot:

```php
add_action('plugins_loaded', function () {
    Rest::registerRoutes();
});
```

---

## Using with Roots Sage (v10+)

If using Roots Sage v10 or above (Acorn-based), register routes inside a service provider.

Example:

```php
<?php

namespace App\Providers;

use Roots\Acorn\Sage\SageServiceProvider;
use Mrldavies\WpRestFluent\Rest;

class ThemeServiceProvider extends SageServiceProvider
{
    public function register()
    {
        Rest::registerRoutes();
        parent::register();
    }

    public function boot()
    {
        parent::boot();
    }
}
```

Ensure your provider is registered in `config/app.php`.

This works because Acorn bootstraps WordPress hooks correctly within the container lifecycle.

---

## HTTP Methods

```php
Rest::get('/endpoint')
Rest::post('/endpoint')
Rest::put('/endpoint')
Rest::patch('/endpoint')
Rest::delete('/endpoint')
```

---

## Route Parameters

You can define typed parameters using curly braces:

```php
Rest::get('/product/{id:int}')
Rest::get('/user/{name:alpha}')
Rest::get('/optional/{slug?}')
```

### Supported built-in types

- `int` → `[0-9]+`
- `alpha` → `[a-zA-Z]+`
- default (no type) → `[a-zA-Z0-9-+_]+`

Example:

```php
Rest::get('/invoice/{ref}')
```

Optional parameter:

```php
Rest::get('/category/{slug?}')
```

---

## Advanced / Raw Regex Routes

If you require full regex control, you can bypass the curly-brace syntax entirely and use native WordPress-style patterns:

```php
Rest::get('/legacy/age(?:/(?P<id>[0-9]+))')
```

This gives complete control over route matching.

---

## Response Handling

Your handler may return:

### Array

```php
return [
    'data' => $payload,
    'status' => 200,
];
```

### Object

```php
return (object)[
    'data' => $payload,
    'status' => 200,
];
```

### Returning WP Native Responses

You may also return:

- `WP_REST_Response`
- `WP_Error`

These will be passed through untouched.

---

## Formatter

Enable formatted responses:

```php
->formatter();
```

Output shape:

```json
{
  "data": {...},
  "status": 200,
  "success": true
}
```

---

## Mapping Custom Response Shapes

If your handler returns a different structure:

```php
return [
  'payload' => [...],
  'code' => 418
];
```

Map it:

```php
Rest::get('/example')
    ->map('payload', 'code')
    ->handler(fn() => externalCall())
    ->formatter();
```

---

## Middleware

Attach middleware to routes:

```php
use Mrldavies\WpRestFluent\Middleware\RateLimitMiddleware;

Rest::get('/limited')
    ->middleware([new RateLimitMiddleware(3, 1)])
    ->handler(fn() => ['data' => ['ok' => true], 'status' => 200])
    ->formatter();
```

Middleware follows the same conceptual structure as Laravel middleware.

Laravel middleware documentation:
https://laravel.com/docs/middleware

Your middleware must implement:

```php
public function handle($request, $next)
```

To continue the chain:

```php
return $next($request);
```

Middleware may return:

- `WP_REST_Response`
- `WP_Error`
- Or allow execution to continue

Middleware execution order is LIFO (last attached runs closest to the handler).

---

## Shipped Middleware

### RateLimitMiddleware

The package includes a simple transient-based rate limiter.

Example:

```php
Rest::get('/limited')
    ->middleware([new RateLimitMiddleware(5, 1)]) // 5 requests per 1 minute
    ->handler(...)
    ->formatter();
```

Features:

- Per-IP limiting
- Per-user limiting when logged in
- Sliding window
- Returns HTTP 429 with `Retry-After` header

The rate limiter is provided as a convenience and can be replaced or extended.

---

## Route Groups

Group routes under shared configuration:

```php
Rest::group(['prefix' => 'v2'], function () {

    Rest::get('/users')
        ->handler(fn() => ['data' => [], 'status' => 200])
        ->formatter();

});
```

You may also group middleware and permissions:

```php
Rest::group([
    'prefix' => 'admin',
    'middleware' => [new RateLimitMiddleware(10, 1)],
], function () {

    Rest::get('/dashboard')
        ->permissions(fn() => current_user_can('manage_options'))
        ->handler(...)
        ->formatter();

});
```

---

## Permissions

Attach permission callbacks:

```php
Rest::get('/admin')
    ->permissions(function () {
        return current_user_can('manage_options');
    })
    ->handler(fn() => ['data' => ['ok' => true], 'status' => 200])
    ->formatter();
```

Permission callbacks must return:

- `true`
- `false`
- or `WP_Error`

---

## Custom Namespace Prefix

Default namespace prefix is `v1`.

Override per-route:

```php
Rest::get('/custom')
    ->prefix('v9')
    ->handler(...)
```

---

## Debugging Routes

Inspect registered routes:

```php
Rest::debugRoutes();
```

---

## Notes

- Designed specifically for WordPress REST API.
- Requires WordPress runtime.
- Middleware architecture mirrors Laravel's pipeline concept.
- Supports array and object response normalization.
- Allows full custom WordPress regex routes when needed.

---

## License

MIT
