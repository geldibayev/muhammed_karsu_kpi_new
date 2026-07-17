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
