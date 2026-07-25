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

### Development Server

```bash
php artisan serve --port 8000
```

### With Docker Compose

```bash
docker-compose up --build
```

This starts:
- **Laravel API**: http://localhost:8000
- **PostgreSQL**: localhost:5432
- **Redis**: localhost:6379

### Queue Workers

In separate terminals:

```bash
# Job queue processor
php artisan queue:work redis --sleep=3 --tries=3

# Judge result consumer
php artisan judge:worker
```

Or with Docker Compose (automatically started).

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
