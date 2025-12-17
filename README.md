# Multi-Agent LinkedIn Post Demo Application

Comprehensive Laravel-based demo application showcasing Multi-agents and a small LinkedIn post demo platform.

---

**Project**

This repository is a Laravel application that demonstrates an agent-based feature set under `app/Neuron/Agents`. It contains sample controllers, models, jobs, and resources intended as a starting point for building ML/agent-assisted workflows or micro services.

**Features**

- Agent framework scaffold under `app/Neuron/Agents`
- Example `User` model, factory, and migrations
- Laravel + Vite frontend setup (Tailwind/vanilla CSS in `resources`)
- PHPUnit / Pest tests scaffolded in `tests/`
- Basic job queue and storage structure

**Requirements**

- PHP 8.1+ (match composer.json engine requirement)
- Composer
- Node 16+ and npm or Yarn
- MySQL / MariaDB or SQLite (for quick local use)
- Git

**Installation (Local)**

1. Clone the repo

   git clone <repo-url> && cd post-demo

2. Install PHP dependencies

   composer install --no-interaction --prefer-dist

3. Install JS dependencies and build assets

   npm install
   npm run dev

4. Copy the environment file and generate an app key

   cp .env.example .env
   php artisan key:generate

5. Configure `.env` (DB credentials, queue driver, cache, mail)

6. Run database migrations and seeders

   php artisan migrate --seed

7. Start local server

   php artisan serve

Visit http://127.0.0.1:8000/agent-dashboard

**Configuration (.env)**

Common variables to configure in `.env`:

- `APP_NAME`, `APP_ENV`, `APP_URL`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `QUEUE_CONNECTION` (e.g., database, sync)

For agent-specific configuration, see `config/` and the `app/Neuron/` folder.

**Usage — Common Artisan & NPM commands**

- Install PHP deps: `composer install`
- Run migrations: `php artisan migrate`
- Run tests: `./vendor/bin/pest` or `php artisan test`
- Run queue worker: `php artisan queue:work`
- Build frontend assets (dev): `npm run dev`
- Build frontend assets (prod): `npm run build`

**Development**

- Code is PSR-12 oriented; follow project's existing style.
- Add feature branches, open PRs for review.
- Key folders:
  - [app/Neuron/Agents](app/Neuron/Agents) — custom agents (see ResearcherAgent)
  - [app/Http/Controllers](app/Http/Controllers) — web/API controllers
  - [database/migrations](database/migrations) — schema
  - [resources/js](resources/js) and [resources/css](resources/css) — frontend assets

Example important file: [app/Neuron/Agents/ResearcherAgent.php](app/Neuron/Agents/ResearcherAgent.php)

**Testing**

This project includes Pest/PHPUnit tests. To run all tests:

```
./vendor/bin/pest --coverage
```

Or invoke Laravel's wrapper:

```
php artisan test
```

Add tests under `tests/Feature` or `tests/Unit`.

**Deployment**

Minimal checklist for deploying to production:

- Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`
- Ensure proper `APP_URL` and HTTPS termination
- Use queue workers (supervisor/systemd) for jobs
- Run `php artisan migrate --force` during deploy
- Build frontend assets with `npm run build`
- Use a robust process manager for `php-fpm` or `php artisan queue:work`

**Project Structure (high-level)**

- `app/` — core application code (models, controllers, agents)
- `config/` — application configuration
- `database/` — migrations, factories, seeders
- `public/` — web root and built assets
- `resources/` — views, JS, and CSS source files
- `routes/` — `web.php` and `console.php`
- `tests/` — Pest/PHPUnit tests

**Contributing**

1. Fork the repo and create a feature branch.
2. Run tests and ensure linting/style checks pass.
3. Open a pull request describing the change.

Include tests for new behavior where practical.

**Security**

If you discover a security vulnerability, please open a private issue or contact the maintainer directly rather than creating a public PR.


**Troubleshooting**

- Common: `php artisan config:clear`, `php artisan cache:clear`, `php artisan route:clear`
- If migrations fail, verify DB credentials and connectivity.
