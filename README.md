# Distributed E-Commerce Order System

A distributed order processing system built with Laravel, Flask, Celery, and Redis. Handles concurrent product purchases with atomic stock updates and asynchronous background task processing.

## Project Overview

This system demonstrates a microservices architecture for e-commerce order processing:

- **Laravel** serves as the main API gateway handling product management and order creation
- **Redis** acts as both cache layer and message broker
- **Flask + Celery** handles asynchronous background task processing
- **Docker** orchestrates all services with proper networking and dependencies

The system ensures data consistency during concurrent purchases using atomic database operations and delegates non-critical processing to background workers.

## Architecture Overview

```
┌─────────────┐
│   Client    │
└──────┬──────┘
       │ HTTP
       ▼
┌─────────────────────────────────────────┐
│         Laravel API (PHP-FPM)           │
│  - Product CRUD                         │
│  - Order creation with atomic stock     │
│  - HTTP client to Flask service         │
└────┬──────────────────┬─────────────────┘
     │                  │
     │ Query/Update     │ HTTP POST
     │                  │ (fire-and-forget)
     ▼                  ▼
┌──────────┐    ┌────────────────┐
│  MySQL   │    │ Flask Service  │
│          │    │ - Enqueue task │
└──────────┘    └────────┬───────┘
                         │ Publish
                         ▼
                  ┌─────────────┐
                  │    Redis    │◄──┐
                  │  (Broker)   │   │ Poll
                  └─────────────┘   │
                         │          │
                         └──────────┤
                                    │
                          ┌─────────┴─────────┐
                          │  Celery Worker    │
                          │  - Process order  │
                          │  - 5s delay       │
                          │  - Logging        │
                          └───────────────────┘
```

### Component Roles

**Laravel API**
- Handles HTTP requests for products and orders
- Executes atomic stock decrement: `UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?`
- Dispatches order processing task to Flask via HTTP (timeout: 2s, non-blocking)
- Returns response immediately without waiting for background task

**Redis**
- Cache layer for product data (optional, file cache fallback available)
- Message broker for Celery task queue
- Connects Celery worker with Flask API

**Flask Service**
- Lightweight API endpoint: `POST /tasks/order-processed`
- Validates payload and enqueues Celery task
- Returns 202 Accepted immediately

**Celery Worker**
- Consumes tasks from Redis queue
- Simulates order processing with 5-second delay
- Logs task execution and completion

### Purchase Flow

1. Client sends POST to `/api/orders/purchase` with `product_id` and `quantity`
2. Laravel begins database transaction
3. Laravel executes atomic stock check and decrement in single query
4. If insufficient stock, transaction rolls back → 409 Conflict
5. If sufficient, order record created → transaction commits
6. Laravel calls Flask API asynchronously (non-blocking, 2s timeout)
7. Flask enqueues task to Redis and returns 202
8. Laravel returns 201 with order data to client
9. Celery worker picks up task from queue
10. Worker processes order (5s delay simulation) and logs completion

**Key Design Decision**: Background task dispatch failure does not affect order creation. Errors are logged but not propagated to client.

## Tech Stack

| Component       | Technology         | Version      |
|----------------|--------------------|--------------|
| API Gateway    | Laravel            | 12.x         |
| Language       | PHP                | 8.2          |
| Database       | MySQL              | 8.0          |
| Cache/Broker   | Redis              | 7 (Alpine)   |
| Task Queue     | Celery             | Latest       |
| Task API       | Flask              | Latest       |
| Language       | Python             | 3.11         |
| Web Server     | Nginx              | Alpine       |
| Orchestration  | Docker Compose     | v2           |

## Setup Instructions

### Prerequisites

- Docker Desktop (Windows/Mac) or Docker Engine (Linux)
- Git

### Installation

1. **Clone repository**
   ```bash
   git clone <repository-url>
   cd chronicle-DistributedEcommerceOrderSystem
   ```

2. **Configure environment**
   ```bash
   cp .env.example .env
   ```

3. **Generate Laravel application key**
   ```bash
   docker-compose run --rm laravel-app php artisan key:generate --show
   ```
   Copy the output and paste into `.env` file as `APP_KEY` value.

4. **Build and start services**
   ```bash
   docker-compose up -d --build
   ```

5. **Run database migrations**
   ```bash
   docker-compose exec laravel-app php artisan migrate
   ```

6. **Copy environment file to container** (if needed)
   ```bash
   docker cp .env chronicle-laravel:/var/www/.env
   docker-compose exec laravel-app php artisan config:clear
   ```

### Service Endpoints

- **Web Application**: http://localhost:8000
- **API Base URL**: http://localhost:8000/api
- **MySQL**: Internal only (chronicle-db:3306)
- **Redis**: Internal only (redis:6379)
- **Flask API**: Internal only (celery-service:5000)
- **Celery Worker**: Background process (no HTTP endpoint)

### Verify Installation

```bash
# Check all containers are running
docker-compose ps

# View logs
docker-compose logs -f

# Test API
curl http://localhost:8000/api/products
```

## API Documentation

Base URL: `http://localhost:8000/api`

### Product Endpoints

#### List Products
```http
GET /products
```

**Response 200**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Laptop Gaming",
      "price": "15000000.00",
      "stock": 10,
      "created_at": "2026-01-07T08:00:00.000000Z",
      "updated_at": "2026-01-07T08:00:00.000000Z"
    }
  ]
}
```

#### Get Product
```http
GET /products/{id}
```

**Response 200**
```json
{
  "data": {
    "id": 1,
    "name": "Laptop Gaming",
    "price": "15000000.00",
    "stock": 10,
    "created_at": "2026-01-07T08:00:00.000000Z",
    "updated_at": "2026-01-07T08:00:00.000000Z"
  }
}
```

#### Create Product
```http
POST /products
Content-Type: application/json

{
  "name": "Laptop Gaming",
  "price": 15000000,
  "stock": 10
}
```

**Response 201**
```json
{
  "data": {
    "id": 1,
    "name": "Laptop Gaming",
    "price": "15000000.00",
    "stock": 10,
    "created_at": "2026-01-07T08:00:00.000000Z",
    "updated_at": "2026-01-07T08:00:00.000000Z"
  }
}
```

#### Update Product
```http
PUT /products/{id}
Content-Type: application/json

{
  "name": "Laptop Gaming Pro",
  "price": 18000000,
  "stock": 5
}
```

#### Delete Product
```http
DELETE /products/{id}
```

**Response 204** (No Content)

### Order Endpoints

#### Create Order (Purchase)
```http
POST /orders/purchase
Content-Type: application/json

{
  "product_id": 1,
  "quantity": 2
}
```

**Response 201** (Success)
```json
{
  "data": {
    "id": 1,
    "product_id": 1,
    "quantity": 2,
    "total_price": "30000000.00",
    "status": "created",
    "created_at": "2026-01-07T08:00:00.000000Z",
    "updated_at": "2026-01-07T08:00:00.000000Z",
    "product": {
      "id": 1,
      "name": "Laptop Gaming",
      "price": "15000000.00",
      "stock": 8
    }
  }
}
```

**Response 409** (Out of Stock)
```json
{
  "message": "Out of stock"
}
```

**Response 500** (Server Error)
```json
{
  "message": "Purchase failed"
}
```

### Internal Endpoints

#### Flask Task Enqueue (Internal)
```http
POST http://celery-service:5000/tasks/order-processed
Content-Type: application/json

{
  "order_id": 1
}
```

**Response 202** (Accepted)
```json
{
  "message": "Task queued",
  "order_id": 1
}
```

**Response 400** (Bad Request)
```json
{
  "error": "order_id is required"
}
```

## Race Condition Handling

### Problem Statement

Multiple clients attempting to purchase the same product simultaneously can cause:
- Overselling (stock becomes negative)
- Lost updates (one transaction overwrites another)

### Solution: Atomic Stock Update

The system uses a single SQL query with conditional check:

```sql
UPDATE products 
SET stock = stock - ? 
WHERE id = ? AND stock >= ?
```

**How it works:**
1. Database locks the row during UPDATE
2. Condition `stock >= quantity` evaluated atomically
3. If condition fails, `affectedRows = 0` and update does not occur
4. Transaction rolls back, returns 409 to client

**Example Scenario:**

- Product has stock = 5
- User A requests quantity = 3
- User B requests quantity = 4 (simultaneous)

**Execution:**
1. Transaction A executes first (database lock)
   - Check: `5 >= 3` → TRUE
   - Update: `stock = 5 - 3 = 2`
   - Commit successful, returns 201
2. Transaction B executes after lock released
   - Check: `2 >= 4` → FALSE
   - Update skipped, `affectedRows = 0`
   - Rollback, returns 409

**Why This Is Safe:**
- Single query eliminates SELECT-then-UPDATE gap
- Database-level locking prevents concurrent modifications
- Conditional update ensures stock never goes negative
- No application-level locking or distributed locks needed

### Alternative Approaches (Not Used)

- ❌ **Read-then-write**: `SELECT stock` → check in PHP → `UPDATE` (race condition window)
- ❌ **Optimistic locking**: Version column (requires retry logic, more complex)
- ❌ **Pessimistic locking**: `SELECT ... FOR UPDATE` (requires explicit lock management)
- ✅ **Atomic conditional update**: Single query, database-native, simplest and safest

## Background Task Processing

### Why Celery?

**Requirements:**
- Asynchronous task execution
- Reliable message queuing
- Worker process management
- Task retry and failure handling

**Celery Advantages:**
- Production-ready distributed task queue
- Native Redis broker support
- Built-in monitoring and logging
- Language-agnostic (communicates via Redis)

### Task Implementation

**Task Definition** (`celery-service/tasks.py`):
```python
@celery_app.task(name='process_order')
def process_order(order_id):
    time.sleep(5)  # Simulate processing
    return f"Order #{order_id} Processed."
```

**5-Second Delay Purpose:**
- Simulates real-world order processing (payment gateway, inventory sync, email)
- Demonstrates async behavior (client receives response immediately)
- Shows worker can handle long-running tasks without blocking API

### Task Flow

1. **Enqueue** (Flask API):
   ```python
   process_order.delay(order_id)  # Non-blocking
   return jsonify({"message": "Task queued"}), 202
   ```

2. **Execute** (Celery Worker):
   - Worker polls Redis for new tasks
   - Picks up `process_order` task
   - Executes function (5s delay)
   - Marks task as completed in Redis

3. **Logging**:
   ```
   [INFO] Task process_order[uuid] received
   [WARNING] Order #1 Processed.
   [INFO] Task process_order[uuid] succeeded in 5.01s
   ```

### Error Handling

**Laravel Side** (CeleryService.php):
```php
try {
    Http::timeout(2)->post('http://celery-service:5000/tasks/order-processed', [
        'order_id' => $orderId,
    ]);
    Log::info("Order {$orderId} dispatched to Celery service");
} catch (\Exception $e) {
    Log::warning("Failed to dispatch order {$orderId}: " . $e->getMessage());
    // Order creation still succeeds, error only logged
}
```

**Design Decision:**
- Task dispatch failure does NOT fail the order creation
- Order is already committed to database
- Background processing is best-effort
- Failures are logged for monitoring

### Monitoring

```bash
# Real-time worker logs
docker-compose logs -f celery-worker

# Laravel dispatch logs
docker-compose exec laravel-app tail -f storage/logs/laravel.log

# Flask API logs
docker-compose logs -f celery-service
```

## Development

### Useful Commands

```bash
# Restart specific service
docker-compose restart laravel-app

# Execute artisan commands
docker-compose exec laravel-app php artisan migrate
docker-compose exec laravel-app php artisan route:list

# Access MySQL
docker-compose exec db mysql -u laravel -p

# Access Redis CLI
docker-compose exec redis redis-cli

# View container logs
docker-compose logs -f [service-name]

# Stop all services
docker-compose down

# Remove volumes (reset database)
docker-compose down -v
```

### Testing Concurrent Requests

```bash
# Requires GNU Parallel or similar tool
seq 10 | parallel -j 10 'curl -X POST http://localhost:8000/api/orders/purchase \
  -H "Content-Type: application/json" \
  -d "{\"product_id\":1,\"quantity\":1}"'
```

Expected: Only N requests succeed where N = current stock. Others return 409.

## License

MIT 
