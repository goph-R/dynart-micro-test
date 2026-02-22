# dynart-micro-test
### Tests for the Micro PHP framework
* [GitHub repository for the framework](https://github.com/goph-R/dynart-micro)
* [API documentation](https://micro.dynart.net/docs/api/)
* [Coverage report](https://micro.dynart.net/reports/coverage-html/)

## Running tests

```bash
composer update
php vendor/bin/phpunit --stderr
```

The `--stderr` flag is required because some tests exercise stdout output.

## Implementation decisions

### JWT auth event subscription

`JwtAuth` subscribes to `WebApp::EVENT_ROUTE_MATCHED` using `[$this, 'onRouteMatched']` (an object-instance callable), **not** `[JwtAuth::class, 'onRouteMatched']` (a Micro-style string-pair callable). The reason: the singleton is registered in the DI container under `JwtAuthInterface::class`, not `JwtAuth::class`. When `EventService::emit()` calls `Micro::getCallable([JwtAuth::class, 'onRouteMatched'])`, it would call `Micro::get(JwtAuth::class)`, which throws because that key is not registered. Using `[$this, ...]` bypasses the DI lookup entirely — `Micro::isMicroCallable` returns `false` for non-string first elements, so the callable is used directly.

### firebase/php-jwt version

`firebase/php-jwt ^7.0` is required (not `^6.0`). The entire v6 line carries a Composer security advisory (`PKSA-y2cr-5h3j-g3ys`) that blocks installation by default. v7.0+ is clean. Note: v7 also enforces a minimum **32-byte key** for HS256 — test secrets must be at least 32 characters long.
