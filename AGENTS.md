<laravel-boost-guidelines>
=== .ai/karsu-kpi rules ===

# KarSU KPI Domain Guidelines

## Product Purpose

- This system evaluates and scores scientific, educational-methodological, and other work submitted by university teachers.
- HEMIS is the source of identity, employment, department, position, academic degree, and academic rank data. Preserve HEMIS identifiers when synchronizing records.
- The application is inherited and still under development. Do not assume that an existing route, controller, menu item, checking mode, or role already represents a complete workflow.
- Treat `api/chat` and its current utilities-domain prompt as unrelated prototype code unless the user explicitly makes it part of the KPI product.

## Core Domain Model

- `users` stores HEMIS users. `users.rol` is a JSON array of roles and `users.degree` is the evaluation category used to select criterion limits.
- `workplaces` stores a user's HEMIS employment records and connects the user to departments and reference data.
- `reports` represents a KPI reporting period. `criteria` is a parent/child hierarchy attached to a report.
- `criterion_evaluations` maps a criterion and evaluation category to its maximum score.
- `data` is the submission/evidence table; its Eloquent model is `Datum`. `material` is JSON describing a file or URL.
- `datum_authors` represents co-authorship and `datum_histories` is the audit trail for submission decisions.
- Accepted submissions are aggregated into `criterion_points`; formula processing writes final criterion scores to `points`.
- Preserve multilingual JSON values using the existing `uz`, `kaa`, `ru`, and `en` keys.

## Submission and Evaluation Workflow

- Preserve the submission lifecycle: `received` -> `checking` -> `accepted` or `cancelled`. Treat `deleted` as a deliberate domain state when soft/audited deletion is required.
- Checking modes currently found in criteria include `manual`, `ai`, `pointing`, `department`, `hemis:*`, and `site:*`. Implement each mode explicitly; never silently fall back to another mode.
- AI output is an untrusted recommendation. Validate its schema, allowed status, numeric score, score boundaries, and reason before persisting it. Ambiguous or failed AI evaluation must remain `checking` for human review.
- Every manual or automated status/score transition must be attributable and recorded in `datum_histories`.
- Do not hardcode report ID `1`, a year such as `2025`, a special user ID, or a criterion ID in new code. Resolve active records or use explicit configuration/domain data.
- Score recalculation must be idempotent, transactional, safe under concurrent execution, and scoped to the intended report.

## Roles and Authorization

- Known roles are `super_admin`, `moder`, `dean`, `department`, and `teacher`. The historical `user` role may be supported only as a temporary compatibility alias while old records are migrated.
- Authentication alone is not authorization. Use policies for model/resource actions and gates or middleware for non-model administration actions.
- Teachers may create, view, download, and delete only their own permitted submissions.
- Moderators may review assigned submissions; department and dean access must be limited by the user's workplace hierarchy; only super administrators may change roles and global KPI configuration.
- State-changing actions must use POST, PUT/PATCH, or DELETE routes with CSRF protection. Never trigger recalculation, deletion, logout, or another mutation through GET.

## Integrity and Security

- Treat uploaded evidence, scores, roles, reports, and evaluation decisions as high-integrity data.
- Use Form Request classes for non-trivial validation and authorization. Validate nested template fields, resource type against the criterion, MIME type, extension, size, active year, and criterion upload eligibility.
- Store service credentials only in configuration files backed by environment variables; application code must call `config()`, not `env()`.
- External HEMIS and AI requests need connection timeouts, request timeouts, bounded retries, error handling, and tests with `Http::fake()` or package fakes.
- AI evaluation should run in a queued job after the submission transaction commits. Keep the submission in `checking` while processing.
- Authorize every file download. Prefer non-public evidence storage, safe generated storage names, and delete or retain physical files according to an explicit audited policy when records change.
- Escape user-provided and AI-provided text in Blade. Render raw HTML only for trusted, sanitized criterion descriptions.
- Add database uniqueness constraints for domain identities used by `updateOrCreate`, especially aggregate rows and pivot-like records. Inspect the live schema before creating migrations.

## Architecture and Eloquent

- Keep controllers focused on HTTP orchestration. Put validation in Form Requests, authorization in policies/gates, scoring in dedicated action/service classes, and slow external evaluation in jobs.
- Model methods must not depend on the global authenticated user. Pass the user explicitly or constrain relationships at the query/caller level.
- A model holding a foreign key normally defines `belongsTo`; use `hasOne` only when the related table owns the foreign key.
- Use casts through a `casts()` method for new or refactored models, following Laravel 12 conventions.
- Wrap multi-record writes, aggregate replacement, and audit history creation in database transactions.

## Frontend Convention

- The active interface uses Blade, AdminLTE, vendored Bootstrap 4-style assets, jQuery, and plugins from `public/`.
- Tailwind CSS 4 is installed, but `resources/css/app.css` is not currently a Vite entry point and the main layouts do not load it. Follow the existing AdminLTE/Bootstrap convention unless the user explicitly requests a Tailwind migration or a Tailwind-based component.
- Check `vite.config.js` and the layout asset tags before assuming that a CSS or JavaScript source file is bundled.

## Testing Priorities

- Use PHPUnit feature tests for authorization boundaries, HEMIS synchronization, uploads, file ownership, evaluation transitions, AI failure/fallback behavior, score caps/formulas, recalculation idempotency, and report isolation.
- Use factories that match the actual HEMIS-shaped user schema; do not rely on Laravel's default email/password user factory fields.
- A changed feature is not complete until its focused happy-path, failure-path, and authorization tests pass.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v4

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
