# Docker Setup Guide

## Quick Start

1. Copy environment file:
   ```bash
   cp .env.example .env
   ```

2. Generate Laravel APP_KEY:
   ```bash
   docker-compose run --rm laravel-app php artisan key:generate --show
   ```
   Copy the output and add to `.env` file.

3. Build and start services:
   ```bash
   docker-compose up -d --build
   ```

4. Run migrations:
   ```bash
   docker-compose exec laravel-app php artisan migrate
   ```

5. Access the application:
   - Web: http://localhost:8000
   - Celery Service: Internal only (http://celery-service:5000)

## Services

- **web**: Nginx web server (exposed on port 8000)
- **laravel-app**: PHP-FPM Laravel application
- **db**: MySQL 8.0 database
- **redis**: Redis cache and message broker
- **celery-service**: Flask API for task enqueuing
- **celery-worker**: Celery worker for background processing

## Network Architecture

```
web:8000 → laravel-app:9000 → db:3306
                             → redis:6379
                             → celery-service:5000 → redis:6379
celery-worker → redis:6379
```

## Useful Commands

```bash
# View logs
docker-compose logs -f [service-name]

# Stop services
docker-compose down

# Stop and remove volumes
docker-compose down -v

# Rebuild specific service
docker-compose up -d --build [service-name]

# Execute artisan commands
docker-compose exec laravel-app php artisan [command]

# Access MySQL
docker-compose exec db mysql -u laravel -p

# Access Redis CLI
docker-compose exec redis redis-cli
```
