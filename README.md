# B-17 — Rukn Coffee Pre-Order API

A RESTful Laravel API for placing and tracking Rukn Coffee pre-orders.

## Requirements

- PHP 8.3 or newer
- Composer
- MySQL 8 or MariaDB
- Redis 7 or compatible Redis server
- PHP Redis extension (`redis`) or Predis

The development environment was verified with PHP 8.5.12, Laravel 13.43.0, MySQL with `pdo_mysql`, Redis, and the `redis` PHP extension.

## Installation

```powershell
composer install
copy .env.example .env
php artisan key:generate
```

Configure MySQL and Redis in `.env`. Redis is the cache backend; MySQL remains the source of truth.

## Environment

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ruken
DB_USERNAME=root
DB_PASSWORD=

CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_CACHE_CONNECTION=cache
```

Create the database manually if it does not already exist:

```sql
CREATE DATABASE ruken CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then run:

```powershell
php artisan migrate
```

## Redis

Start Redis on Windows using its installed service or executable, for example:

```powershell
redis-server
redis-cli ping
```

`PONG` confirms that Redis is available. Laravel's cache store is configured with `CACHE_STORE=redis`, and cache reads/writes use the `cache` Redis connection in database 1. Redis database 1 is isolated for Laravel cache; application tests use an isolated cache setup and do not flush the user's Redis databases.

## Run the API

```powershell
php artisan serve --host=127.0.0.1 --port=8081
```

## API Documentation

### Create an order

`POST /api/orders`

```json
{
  "customer_name": "Ahmed",
  "size": "medium",
  "notes": "No sugar"
}
```

Response: `201 Created`

```json
{
  "success": true,
  "message": "Order created successfully.",
  "data": {
    "id": 1,
    "customer_name": "Ahmed",
    "size": "medium",
    "notes": "No sugar",
    "status": "new",
    "created_at": "2026-11-22T16:38:33.000000Z",
    "updated_at": "2026-11-22T16:38:33.000000Z"
  }
}
```

Validation: `customer_name` is required and max 100 characters; `size` must be `small`, `medium`, or `large`; `notes` is nullable and max 500 characters.

### Update order status

`PATCH /api/orders/{id}/status`

```json
{
  "status": "preparing"
}
```

Allowed transitions are:

- `new` → `preparing` or `cancelled`
- `preparing` → `ready` or `cancelled`
- `ready` and `cancelled` are terminal states

Cancellation example:

```json
{
  "status": "cancelled",
  "cancellation_reason": "Customer changed plans"
}
```

`cancellation_reason` is required when cancelling and is limited to 500 characters.

Invalid transitions and updates to the current status return `409 Conflict`. Invalid status values return `422 Unprocessable Content`. A missing order returns `404 Not Found`.

### Retrieve order

`GET /api/orders/{id}`

Response: `200 OK` with the same `success`, `message`, and `data` structure as creation/update responses.

A missing order returns:

```json
{
  "success": false,
  "message": "Order not found.",
  "errors": null
}
```

### Validation and server errors

Validation errors return `422` in Laravel's standard JSON shape. Server/database failures are returned as:

```json
{
  "success": false,
  "message": "An internal server error occurred.",
  "errors": null
}
```

HTTP 500 responses do not expose database details or stack traces.

## Redis cache strategy

- Key: `order_status_{id}` (for example, `order_status_42`)
- TTL: 60 seconds
- Implementation: `Cache::remember` for successful reads
- Missing orders are not cached
- Cache backend: Redis database 1 via the `cache` Redis connection
- Graceful degradation: Redis read/write failures fall back to MySQL for API operations
- Invalidation: after a status update is committed to MySQL, the corresponding key is deleted immediately
- The next successful GET reads MySQL and caches the updated response for 60 seconds

## Tests

The test suite uses SQLite in memory and therefore does not require or modify the development MySQL database.

```powershell
php artisan test
```

Redis tests run only when Redis is reachable and use an isolated test Redis database configuration. They do not flush Redis databases.

## Code quality

```powershell
vendor\bin\pint
php artisan test
```
