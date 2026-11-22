# Repository Guidelines

## Project Structure

This Laravel 12 application handles orders, reservations, payments, and WhatsApp integrations. Start with `routes/api.php` for API endpoints and `routes/web.php` for browser routes. Follow each route to its controller in `app/Http/Controllers/`. Keep business logic in `app/Services/`, Eloquent models in `app/Models/`, and queued work in `app/Jobs/`. Put schema changes in `database/migrations/`.

Blade templates live in `resources/views/`. The public landing pages and admin interface use React and TypeScript under `resources/js/next.js/`. Vite builds these entry points alongside the scripts in `resources/js/` and styles in `resources/css/`. WhatsApp Flow definitions live in `resources/whatsapp/flows/`.

## Build and Development Commands

Run commands from the repository root with PHP 8.2 or later in the 8.x series, Composer, Node.js, and npm.

- On a fresh checkout, copy `.env.example` to `.env` and configure a local database. Run `composer setup` to install dependencies, generate the application key, apply migrations, and build assets.
- Run `composer dev` to start the Laravel server, queue listener, log viewer, and Vite together. Use `php artisan serve` for the HTTP server alone.
- Run `npm run dev` for frontend hot reload or `npm run build` to verify the production bundle.

## Coding Style and Naming

Follow `.editorconfig`: use UTF-8, LF endings, a final newline, and four-space indentation. YAML uses two spaces except in `compose.yaml`. Match PHP class names to filenames, use PascalCase for classes and camelCase for methods, and preserve the `App\` namespace hierarchy. Check changed PHP files with `vendor/bin/pint --test --dirty`.

## Testing

Run `composer test` to clear cached configuration and execute PHPUnit. Put isolated logic in `tests/Unit/` and endpoint or integration tests in `tests/Feature/`. Name each file after the behavior under test, as in `ReservationSlotGeneratorTest.php`. Run one class with `php artisan test --filter=ReservationSlotGeneratorTest`. PHPUnit uses in-memory SQLite and synchronous queues. Coverage targets `app/` with no configured minimum percentage. Run scripts in `tests/manual/` separately.

## Commits and Pull Requests

Recent commits mix plain descriptions with `fix:`, `feat:`, and `docs:` prefixes. Prefer a short subject that names the affected behavior. In pull requests, describe the change, link any related issue, and list the checks you ran. Include screenshots for visible UI changes and flag any migration or configuration steps reviewers need.
