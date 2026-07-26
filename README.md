# ProgrammersArena Backend

Backend API for ProgrammersArena, an online contest platform supporting input/output problems and ICPC-like contests. Built with Laravel, PostgreSQL, and Redis.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Running](#running)
- [Database](#database)
- [Queue & Judge Integration](#queue--judge-integration)

## Prerequisites

- PHP 8.2+
- Composer
- PostgreSQL 14+
- Redis 7+
- Docker (optional, for containerized setup)

## Installation

### Clone Repository

```bash
git clone https://github.com/mali-ab/programmers-arena.git
cd programmers-arena/backend
```

### Install Dependencies

```bash
composer install
```

### Environment Setup

```bash
cp .env.example .env
php artisan key:generate
php artisan storage:link
```

Edit `.env` with your database and judge settings:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=programmers_arena
DB_USERNAME=postgres
DB_PASSWORD=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

JUDGE_MODE=queue
JUDGE_REDIS_URL=redis://127.0.0.1:6379/0
```

## Configuration

### Database Setup

```bash
php artisan migrate
php artisan db:seed
```

### Cache & Queue

```bash
php artisan config:cache
php artisan route:cache
```

## Running

### Development Server (without Docker)

```bash
php artisan serve --port 8000
```

### Running with Docker

The backend ships with two Docker approaches:

> **Important:** Inside Docker containers, services communicate via container names (e.g., `db`, `redis`), not `localhost`. The `docker-compose.yml` automatically overrides the `.env` values for `DB_HOST`, `DB_PORT`, `REDIS_HOST`, `REDIS_PORT`, and `JUDGE_REDIS_URL` to point to the correct Docker service names. Your `.env` file stays untouched for local non-Docker development.

---

#### 1. Docker Compose (Recommended for most setups)

Starts the full stack — Laravel API, queue workers, judge consumer, PostgreSQL, and Redis — in one command:

```bash
# Build and start all services
docker-compose up --build -d

# View logs
docker-compose logs -f

# Stop all services
docker-compose down
```

Services started automatically:

| Service          | Container Name          | URL                          |
|------------------|-------------------------|------------------------------|
| Laravel API      | `laravel-app`           | http://localhost:8000        |
| Queue Worker     | `laravel-queue-worker`  | —                            |
| Judge Consumer   | `judge-result-consumer` | —                            |
| PostgreSQL       | `postgres-db`           | localhost:5432               |
| Redis            | `laravel-redis`         | localhost:6379               |

**Best for:** Development teams, CI/CD, one-command local setup, production deployments.

---

#### 2. Docker Entrypoint (Manual container control)

Run individual containers directly with the entrypoint script that prepares the environment (creates runtime dirs, sets permissions):

```bash
# Build the image first
docker build -t laravel-app .

# Run Laravel API
docker run --rm -p 8000:80 \
    --env-file .env \
    --name laravel-app \
    -v "$(pwd):/var/www/html" \
    -v /var/run/docker.sock:/var/run/docker.sock \
    -v vendor_vol:/var/www/html/vendor \
    laravel-app

# Run queue worker
docker run --rm \
    --env-file .env \
    --name laravel-queue-worker \
    -v "$(pwd):/var/www/html" \
    -v /var/run/docker.sock:/var/run/docker.sock \
    -v vendor_vol:/var/www/html/vendor \
    laravel-app \
    sh -c "php /var/www/html/artisan queue:work redis --sleep=3 --tries=3"

# Run judge consumer
docker run --rm \
    --env-file .env \
    --name judge-result-consumer \
    -v "$(pwd):/var/www/html" \
    -v vendor_vol:/var/www/html/vendor \
    laravel-app \
    sh -c "php /var/www/html/artisan judge:worker"
```

> **Note:** The `docker-entrypoint.sh` script runs automatically before the container's main command. It creates runtime directories (`/tmp/run/php`, `/tmp/nginx-logs`), sets www-data ownership, and ensures storage/bootstrap/cache are writable.

**Best for:** Debugging, learning the container internals, custom service orchestration, integrating with external tools.

---

### Which approach is better?

| Scenario                         | Recommendation         | Reason                                                      |
|----------------------------------|------------------------|-------------------------------------------------------------|
| **Local development**            | ✅ **Docker Compose**  | One command starts everything; services are pre-configured  |
| **Production deployment**        | ✅ **Docker Compose**  | Declarative, reproducible, easy to scale                    |
| **Learning how the image works** | 🐳 **Docker Entrypoint** | See each step; understand the entrypoint script behavior    |
| **Custom orchestration**         | 🐳 **Docker Entrypoint** | Full control over mounts, networks, and container lifecycle |
| **CI/CD pipelines**              | ✅ **Docker Compose**  | Consistent environment across runners, easy teardown        |

**TL;DR:** Use **Docker Compose** unless you have a specific reason to run containers manually.

---

### Queue Workers (manual, without Docker)

In separate terminals:

```bash
# Job queue processor
php artisan queue:work redis --sleep=3 --tries=3

# Judge result consumer
php artisan judge:worker
```

> **Note:** When using Docker Compose, queue workers start automatically.

## Database

### Migrations

```bash
php artisan migrate          # Run migrations
php artisan migrate:rollback # Rollback last migration
php artisan migrate:reset    # Reset all
```

### Seeding

```bash
php artisan db:seed
```

## Queue & Judge Integration

Backend communicates with judge-box via Redis queues:

- **Job Queue**: `judge:jobs` — receives grading jobs
- **Result Queue**: `judge:results` — receives verdicts

### Commands

```bash
# Start result consumer (pulls from judge:results)
php artisan judge:worker

# Monitor queue status
php artisan queue:monitor

# Clear queue
php artisan queue:clear
```

## API Endpoints

- `GET /api/contests` — List contests
- `GET /api/contests/{id}` — Get contest
- `POST /api/contests/{id}/problems/{char}/submit` — Submit solution
- `GET /api/submissions` — List submissions

## License

MIT
