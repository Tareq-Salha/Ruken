# Technical Decisions

## 1. Redis cache for order reads

**Decision:**  
Use Laravel's Redis cache store with a 60-second TTL and key `order_status_{id}`.

**Alternatives considered:**  
Database or file caching, or no caching.

**Why this choice:**  
Redis was already installed and verified in the environment, the PHP `redis` extension is available, and Redis provides fast key expiration suitable for order-status reads. The cache is optional; MySQL remains the source of truth.

**What I sacrificed:**  
There is a brief possibility of stale cache data for the TTL duration if invalidation itself fails or is not reached. Redis also adds an operational dependency, though API reads and writes are designed to continue through MySQL when Redis is unavailable.

**What I would change with twice the time:**  
Add cache metrics and a health-check endpoint, plus integration tests running against a dedicated Redis database on CI.

## 2. Database transactions and cache invalidation after update

**Decision:**  
Update the order in a database transaction, then invalidate `order_status_{id}` only after the update succeeds.

**Alternatives considered:**  
Invalidate before the database update or update and invalidate outside a transaction.

**Why this choice:**  
Committing MySQL first keeps the database as the authoritative state. Invalidation afterward prevents Redis from retaining the old status after a successful transition. Redis failures are caught and do not undo or reject the successful database update.

**What I sacrificed:**  
A Redis outage can leave an already cached old value until its 60-second TTL expires, since invalidation cannot be guaranteed. The API degrades safely but consistency is temporarily weaker.

**What I would change with twice the time:**  
Use an outbox or durable cache-invalidation queue and monitor failed Redis invalidations.

## 3. Small service and Form Request architecture

**Decision:**  
Use Laravel Form Requests for validation, an `OrderService` for database/cache logic, and an `OrderController` for HTTP responses.

**Alternatives considered:**  
All logic in the controller, or a repository abstraction for this small API.

**Why this choice:**  
This separates validation, application logic, and HTTP concerns without unnecessary repository or domain layers. It is consistent with Laravel conventions and remains easy to test.

**What I sacrificed:**  
The service is more than the controller needs for a tiny API, and there is some additional class and method overhead.

**What I would change with twice the time:**  
Add dedicated API Resource classes and a typed status-transition value object if the domain grew beyond the three required states.

## AI assistance disclosure

AI assistance was used to inspect the existing Laravel project, generate the migration/model/requests/service/controller/tests, configure Redis caching, write documentation, run PHPUnit and Laravel commands, and perform the local API smoke test. It did not install or expose MySQL/Redis credentials.
