# Optimization rollout

These changes span the backend, frontend, and judge-box repositories. They do not
add database migrations. They have not been deployed by the coding agent.

## Compatibility and rollout order

Use a coordinated release window: stop accepting new submissions and let active
jobs drain before replacing the services. Do not flush Redis queues or caches.

1. Install the locked backend dependencies (`composer install`); the backend now
   requires Symfony's HTML sanitizer. Deploy the version-aware internal test-bundle
   endpoint before processing new file-reference jobs.
2. Install the judge's locked dependencies (`npm ci` in `judge-box/src/daemon`)
   and restart all judge workers with the updated code. The worker can still read
   legacy inline tests. New jobs reference files in a downloaded bundle instead
   of putting full input/output data into Redis.
3. Restart Laravel queue workers with the updated producer, and publish the
   matching frontend build (`npm ci && npm run build`). Refresh cached Laravel
   routes/configuration using the deployment's normal procedure. Reopen traffic
   after the smoke checks below.

The frontend and backend must match: contest author/participant selections now
use unique **handles**, not display names, and submission polling calls the new
authenticated `/api/v1/submissions/statuses` endpoint. Rollback should also be
coordinated with drained queues, not performed on only one service.

## Runtime requirements

- Keep `executionMode` set to `docker` for the tested execution path. Isolate
  remains experimental; it has not been integration-tested in this change.
- The judge needs access to the protected internal test-bundle endpoint and a
  writable test-cache directory. Confirm its token and URL match the backend.
  Cache directories use a new `v2-` prefix, so allow space for an initial refill.
- Test bundles must contain the referenced `.in` and `.out` files. Reupload test
  bundles through the application to invalidate their version; manual file edits
  without changing the problem record do not invalidate cached bundles.
- Install the first configured GCC image/version on checker-capable workers.
  Custom checkers use it even when the contestant submits another language.
- Docker bind mounts must resolve to the same workspace path on the Docker host.
  The daemon must be able to create private directories writable by the configured
  numeric sandbox UID/GID. Do not run contestant containers as root.
- Contest notifications now run asynchronously. `QUEUE_NOTIFICATIONS_CONNECTION`
  can select the queue connection; otherwise the configured asynchronous
  `QUEUE_CONNECTION` is used (or `database` when that is `sync`). The supplied
  Redis worker supports `QUEUE_NOTIFICATIONS_CONNECTION=redis`. Other connections
  need a corresponding worker; the database connection also needs its jobs table.
- Rich-text descriptions are sanitized on read and write. Review representative
  existing problem statements/blog posts: unsafe HTML is intentionally removed.

## Verification

Run from each repository (PHP tests force an in-memory SQLite database):

```sh
# backend
composer validate
php artisan test
php vendor/bin/pint --dirty --test

# frontend
npm ci
npm test
npm run build

# judge-box/src/daemon
npm ci
npm test
ARENA_DOCKER_TESTS=1 npm test
```

Frontend tests cover stale-response cancellation, batched polling, retry states,
and duplicate verification-email requests. Backend tests cover rating accuracy,
authorization, bounded queries, submission throttling, HTML sanitization,
asynchronous notification dispatch, and small queue payloads. Judge tests cover
command arguments, time/output limits, path validation, cleanup, concurrent test
workspaces, and custom-checker results. Tests mock application Redis and email;
the optional Docker tests create and remove only their own containers/workspaces.

The repository-wide frontend lint command has existing errors outside this
change. Use targeted lint on changed files while treating cleanup of that
baseline as a separate task. Lazy loading defers editor/chart/flag code but does
not remove those dependencies or their route-specific download cost.

Before public traffic, smoke-test a profile, contest editing by handle, one
accepted/rejected/compile-error submission, an IOI submission, and a custom
checker. Verify status polling reaches a final verdict and the result consumer
persists it. Email delivery and a full production deployment are not covered by
the isolated test suite.
