
---

## Quick Start

### 1. Start Docker Services

```bash
# Start all containers (MySQL, Elasticsearch, Redis, PHP, Nginx)
docker-compose up -d

# Wait for services to be ready (especially Elasticsearch takes ~30-60 seconds)
docker-compose ps
# All services should show "Up"

# Fix permissions (first time only)
docker-compose exec app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
docker-compose exec app chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
```
```

### 2. Install Dependencies

```bash
# Install PHP packages
docker-compose exec app composer install
```

### 3. Configure Environment

```bash
# Copy environment file (already configured for Docker)
cp .env.example .env

# Generate application key
docker-compose exec app php artisan key:generate
```

The `.env` file is pre-configured with correct Docker service names:
- `DB_HOST=db` (MySQL container)
- `REDIS_HOST=redis` (Redis container)
- `ELASTICSEARCH_HOST=elasticsearch:9200` (Elasticsearch container)

### 4. Set Up Database

```bash
# Run migrations
docker-compose exec app php artisan migrate

# Seed database with sample data (50 brands, 5000 ads)
docker-compose exec app php artisan db:seed
```

### 5. Set Up Elasticsearch

```bash
# Create Elasticsearch index
docker-compose exec app php artisan es:setup

# Index all data from database to Elasticsearch
docker-compose exec app php artisan es:reindex
```

### 6. Verify Setup

```bash
# Check Elasticsearch has data
curl http://localhost:9200/ads/_count
# Should return: {"count":5000,...}

# Test the search API
curl "http://localhost:8080/api/search?q=sneakers"
# Should return JSON with search results
```

### 8. Access the Application

- **API Endpoint**: http://localhost:8080/api/search
- **Elasticsearch**: http://localhost:9200
- **MySQL**: localhost:3306 (user: root, password: root, database: laravel)

---


---

## API Documentation

### Search Endpoint

**Endpoint:** `GET /api/search`

**Query Parameters:**

| Parameter | Type | Required | Description | Example |
|-----------|------|----------|-------------|---------|
| `q` | string | Yes | Search keyword (typo-tolerant) | `sneakers` |
| `country_iso` | string | No | 2-letter country code (uppercase) | `US` |
| `start_date` | string | No | Filter ads starting on/after date (YYYY-MM-DD) | `2026-01-01` |
| `page` | integer | No | Page number (default: 1) | `2` |
| `per_page` | integer | No | Results per page (default: 20, max: 100) | `10` |

**Example Requests:**

```bash
# Basic search
curl "http://localhost:8080/api/search?q=sneakers"

# With country filter
curl "http://localhost:8080/api/search?q=running&country_iso=US"

# With start date filter
curl "http://localhost:8080/api/search?q=shoes&start_date=2026-01-01"

# With pagination
curl "http://localhost:8080/api/search?q=nike&page=2&per_page=10"

# All filters combined
curl "http://localhost:8080/api/search?q=fitness&country_iso=US&start_date=2026-01-01&page=1&per_page=20"
```

**Success Response (200 OK):**

```json
{
  "data": [
    {
      "id": 123,
      "brand_id": 1,
      "brand_name": "Nike",
      "title": "Nike running shoes",
      "keywords": "running, shoes, athletic, footwear",
      "country_iso": "US",
      "start_date": "2026-02-15",
      "relevance_score": 0.850
    }
  ],
  "total": 245,
  "page": 1,
  "per_page": 20,
  "last_page": 13
}
```

**Validation Error Response (422 Unprocessable Entity):**

```json
{
  "message": "The q field is required.",
  "errors": {
    "q": ["The q field is required."]
  }
}
```

---

## Running Tests

### Run All Tests

```bash
docker-compose exec app php artisan test
```

### Run Specific Test Suites

```bash
# Unit tests only
docker-compose exec app php artisan test --testsuite=Unit

# Feature tests only
docker-compose exec app php artisan test --testsuite=Feature

# Specific test file
docker-compose exec app php artisan test tests/Unit/SearchServiceTest.php
```

---


---

## Development

### Common Commands

```bash
# Start all services
docker-compose up -d

# Stop all services
docker-compose down

# View logs
docker-compose logs -f app
docker-compose logs -f elasticsearch

# Access app container shell
docker-compose exec app bash

# Run artisan commands
docker-compose exec app php artisan [command]

# Run composer commands
docker-compose exec app composer [command]

# Access MySQL
docker-compose exec db mysql -uroot -proot laravel

# Access Tinker (Laravel REPL)
docker-compose exec app php artisan tinker
```

### Reindexing Elasticsearch

When you make changes to ad data or Elasticsearch mappings:

```bash
# Recreate index
docker-compose exec app php artisan es:setup

# Reindex all data
docker-compose exec app php artisan es:reindex
```

### Clearing Caches

```bash
# Clear all caches
docker-compose exec app php artisan optimize:clear

# Clear specific caches
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan view:clear
```

### Database Operations

```bash
# Reset and reseed database
docker-compose exec app php artisan migrate:fresh --seed

# Run specific seeder
docker-compose exec app php artisan db:seed --class=BrandSeeder

# Check database connection
docker-compose exec app php artisan tinker
>>> DB::connection()->getPdo();
>>> \App\Models\Ad::count();
```

---