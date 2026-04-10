# Copilot Instructions for DENTALLINK Backend (Laravel API)

## Project Context

This repository contains the backend API for **DENTALLINK**, a dental lab management system connecting doctors and labs.

Primary objective:
- Build a scalable, maintainable **RESTful API** for order lifecycle, lab workflow, pricing/payments, delivery, notifications, and reporting.

## Tech Stack Constraints (Must Follow)

- **Laravel (latest)**
- **REST API only** (no Blade/UI rendering)
- **MySQL**
- **Laravel Sanctum** for authentication
- **Queues** for async processing (especially notifications/workflow side effects)
- **Events/Listeners** for workflow updates

## Architecture Rules (Strict)

1. **Use Service Layer**
   - Controllers must stay thin.
   - Business logic belongs in `app/Services/*`.

2. **Use Form Requests**
   - Validation must be in `app/Http/Requests/*`.
   - Avoid inline validation in controllers unless trivial edge case.

3. **Use Policies for Authorization (RBAC)**
   - Authorization decisions must use Policies/Gates.
   - Role checks should be centralized and consistent.

4. **Use API Resources for Responses**
   - Return transformed, consistent JSON through `app/Http/Resources/*`.
   - Avoid returning raw Eloquent models directly from controllers.

5. **Use Transactions for Critical Operations**
   - Any multi-step write flow (order creation, status transitions, payment/debt updates, task assignment) must be wrapped in DB transactions.

6. **Log All Status Changes**
   - Every order/task/delivery/payment status transition must be traceable and persisted in dedicated history/logging tables where applicable.

## Scope Guard (SRS Compliance)

- Implement only features that are within the SRS scope.
- **Do NOT introduce extra modules/features خارج SRS**.
- If a requirement is ambiguous, ask for clarification before implementing.

## Roles (RBAC)

System roles:
- `doctor`
- `receptionist`
- `department_manager`
- `lab_technician`
- `lab_manager`
- `system_admin`
- `delivery`

Copilot must:
- Enforce strict role permissions in policy suggestions.
- Avoid bypassing authorization in controllers/services.

## Core Modules to Support

### 1) Auth
- Register / Login / Logout
- Sanctum token-based auth
- Role-based access control
- Account lock after 5 failed attempts

### 2) Users, Labs, Departments
- Users CRUD (admin/lab manager scope)
- Labs CRUD (system admin)
- Departments CRUD

### 3) Orders
- Doctor creates order
- Attach files/images
- Priority: `urgent` / `normal`
- Status lifecycle:  
  `pending -> priced -> in_progress -> completed -> delivered`
- Persist timeline in `order_status_history`

### 4) Tasks (Lab Workflow)
- Split order into department tasks
- Assign technician
- Start/end task using QR code
- Persist `start_time`, `end_time`, `duration`

### 5) QR Code
- Generate QR per order/task
- QR used to start/end tasks

### 6) Pricing & Payments
- Receptionist sets price
- Track debts per doctor
- Payment methods:
  - `cash`
  - `online` (abstracted provider)
- Update payment/order status consistently

### 7) Delivery
- Create delivery task
- Assign delivery user
- Delivery lifecycle:  
  `assigned -> in_delivery -> delivered`
- Store delivery address and location

### 8) Notifications
Trigger notifications on:
- New order
- Status change
- Task assigned
- Payment update

Implementation preference:
- Domain events + queued listeners/jobs

### 9) Reports
- Orders per department
- Employee productivity
- Revenue
- Top doctors

## Database Entities (Main Tables)

- `users`
- `roles`
- `labs`
- `departments`
- `orders`
- `order_items` (optional)
- `order_status_history`
- `tasks`
- `qr_codes`
- `payments`
- `debts`
- `delivery_tasks`
- `notifications`
- `ratings`
- `subscriptions`
- `logs`

## API Design Standards

- RESTful endpoint naming and verbs
- Proper HTTP status codes
- Consistent JSON response envelope
- Paginate list endpoints
- Filter/sort/search where useful (within scope)
- Version routes if architecture already supports it (e.g., `/api/v1`)

## Suggested Response Shape (Default)

When appropriate, prefer a consistent structure:
- `success` (boolean)
- `message` (string)
- `data` (object/array)
- `meta` (pagination/extra metadata when needed)
- `errors` (validation or domain errors)

## Coding Conventions for Copilot Suggestions

- Prefer explicit, readable code over clever shortcuts.
- Keep controllers small; delegate to services.
- Use typed method signatures and return types where possible.
- Avoid duplicated business logic across controllers/services.
- Prefer enums/constants for statuses and role names.
- Add tests for critical workflows (feature + unit).
- Keep migrations reversible and aligned with MySQL best practices.
- Use eager loading to avoid N+1 issues.
- Never trust client-provided role/status transitions without authorization and domain checks.

## Workflow/Domain Safety Checks

Before suggesting code that changes state, ensure:
1. Actor role is authorized.
2. Current entity status allows transition.
3. Transition is logged in history.
4. Side effects (notifications, debts, delivery assignments) are triggered safely.
5. Critical writes are transactional.

## Out of Scope / Avoid

- Blade pages, Livewire, Inertia, or frontend code
- Unapproved external services/features not requested in SRS
- Breaking existing API contracts without clear migration path
- Large refactors unrelated to the requested task

## If Ambiguity Exists

Copilot should ask concise clarification questions, especially for:
- Status transition rules
- Role ownership boundaries
- Payment/debt edge cases
- Delivery assignment constraints
- Report calculation definitions
