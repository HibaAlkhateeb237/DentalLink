# DentaLink — Agent Instructions

## Project Overview

Dental lab management REST API (no Blade UI). Laravel 12, PHP 8.2, Sanctum tokens, MySQL, Queue (database driver).

## Commands

| Action | Command |
|--------|---------|
| Full setup | `composer run setup` |
| Seed demo data | `composer run seed:demo` |
| Dev servers | `composer run dev` (serves + queue:listen + vite) |
| Run all tests | `php artisan test --compact` |
| Single test/file | `php artisan test --compact --filter=testName` or `path/to/Test.php` |
| Format PHP | `vendor/bin/pint --format agent` |
| Run single Artisan | `php artisan <command>` |
| Queue worker | `php artisan queue:work` |

Run tests after every change. Always run `vendor/bin/pint --format agent` after modifying PHP files.

## Architecture

- **No Blade UI** — pure REST JSON API, routes in `routes/api.php`
- **Services** at `app/Http/Services/` (controllers import from here). Also `app/Services/Auth/`.
- **Form Requests** in `app/Http/Requests/` — validation lives here, not in controllers.
- **API Resources** in `app/Http/Resources/` — response transformations.
- **Policies** in `app/Policies/` — authorization per model.
- **Repositories** in `app/Repositories/` — `OrderRepository`, `TaskRepository`.
- **Support** classes in `app/Support/` — `OrderStatus`, `TaskStatus`, `EmployeeRoles`, RBAC helpers.
- **Custom middleware** aliases: `role` (`EnsureUserHasRole`), `permission` (`EnsureUserHasPermission`) — registered in `bootstrap/app.php`.
- **Middleware/exceptions** configured in `bootstrap/app.php` (Laravel 12 style).
- **API response format**: `{"success": bool, "status": int, "message": string, "data": mixed, "errors": mixed}` via `App\Http\Responses\ApiResponse`.

## Authentication & RBAC

- **Sanctum token auth** — `auth:sanctum` middleware on all protected routes.
- **Registration flow**: OTP-based — `request-otp` → `verify-otp` → `complete`.
- **Seven roles**: `doctor`, `receptionist`, `department_manager`, `lab_technician`, `lab_manager`, `system_admin`, `delivery`.
- Roles can be **global** (via `model_has_roles` pivot) or **department-scoped** (via `department_user_roles`).
- Check roles/permissions via `$user->hasRole()`, `hasPermission()`, `hasDepartmentAccess()` on `User` model.
- Use Policies for granular model authorization alongside role middleware.

## Domain

- **Order statuses**: `pending → new → in_progress → try_on / resend_wrong_impression → completed`
- **Task lifecycle**: QR-based start/finish via `endroid/qr-code` package.
- **Time zone**: `Asia/Damascus`, **Default locale**: `ar` (Arabic), fallback `en`.

## Testing

- PHPUnit (not Pest), uses SQLite `:memory:` in tests.
- `php artisan make:test --phpunit {Name}` to create tests.
- Factories in `database/factories/`, seeders in `database/seeders/`.

## Key Packages

- `laravel/sanctum` — API auth
- `endroid/qr-code` — QR generation for task workflow
- `knuckleswtf/scribe` — API documentation generation
- `laravel/pint` — PHP code style fixer
- `laravel/boost` — MCP server with DB/Schema/URL tools (prefer `database-query`, `database-schema` over raw SQL)

## Database

- Dev: MySQL (update `.env`). Test: SQLite `:memory:` (configured in `phpunit.xml`).
- Queue connection: `database` (run `php artisan queue:work` for async jobs).
- Cache store: `database`.
