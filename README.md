# DENTALLINK Backend API

Backend service for **DENTALLINK**, a dental lab management platform that connects doctors and labs and manages the full workflow from order creation to delivery and payments.

---

## Overview

DENTALLINK is a RESTful API built with Laravel to support:

- Role-based workflows between doctors, lab teams, and delivery staff
- Order lifecycle and status tracking
- Department task management with QR-based start/end
- Pricing, payments, and debt tracking
- Delivery assignments and tracking
- Event-driven notifications
- Reporting and analytics

---

## Tech Stack

- **Framework:** Laravel (latest)
- **API Type:** REST (no Blade UI)
- **Database:** MySQL
- **Authentication:** Laravel Sanctum
- **Async Processing:** Laravel Queues
- **Workflow Events:** Laravel Events/Listeners

---

## Architecture Principles

- **Service Layer** for business logic (`app/Services`)
- **Form Requests** for validation (`app/Http/Requests`)
- **Policies/Gates** for authorization and RBAC
- **API Resources** for consistent response transformation
- **Transactions** for critical multi-step operations
- **Status History Logging** for all important lifecycle changes

---

## Roles

- `doctor`
- `receptionist`
- `department_manager`
- `lab_technician`
- `lab_manager`
- `system_admin`
- `delivery`

---

## Core Modules

### 1) Authentication
- Register / Login / Logout
- Sanctum token authentication
- Role-based access control
- Account lock after 5 failed login attempts

### 2) Users, Labs, Departments
- Users CRUD (admin/lab manager)
- Labs CRUD (system admin)
- Departments CRUD

### 3) Orders
- Doctor creates order
- Attach files/images
- Priority: `urgent` / `normal`
- Order status lifecycle:
  `pending -> priced -> in_progress -> completed -> delivered`
- Full timeline tracking in `order_status_history`

### 4) Tasks (Lab Workflow)
- Split order into department tasks
- Assign technician per task
- Start/end task via QR code
- Track `start_time`, `end_time`, `duration`

### 5) QR Code
- Generate QR for order/task actions
- Use QR for secure workflow state transitions

### 6) Pricing & Payments
- Receptionist sets order pricing
- Track debts per doctor
- Payment methods:
  - `cash`
  - `online` (provider-agnostic integration layer)
- Keep payment and order statuses consistent

### 7) Delivery
- Create delivery task for completed orders
- Assign delivery staff
- Delivery lifecycle:
  `assigned -> in_delivery -> delivered`
- Save delivery address and location details

### 8) Notifications
- Trigger notifications on:
  - new order
  - status changes
  - task assignment
  - payment updates
- Implemented via events + queued listeners/jobs

### 9) Reports
- Orders per department
- Employee productivity
- Revenue reports
- Top doctors

---

## Main Database Tables

- `users`
- `roles`
- `labs`
- `departments`
- `orders`
- `order_items` *(optional)*
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

---

## API Standards

- RESTful endpoint design
- Consistent JSON responses
- Proper HTTP status codes
- Pagination on list endpoints
- Filtering/sorting where applicable
- Validation for all incoming requests
- Authorization checks for all protected actions

---

## Suggested Response Format

```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": {},
  "meta": {},
  "errors": []
}
```

---

## Getting Started

### 1. Clone Repository

```bash
git clone https://github.com/HibaAlkhateeb237/DentalLink.git
cd DentalLink
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` with your MySQL credentials and app settings.

### 4. Run Migrations & Seeders

```bash
php artisan migrate --seed
```

### 5. Install Sanctum (if not already configured)

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### 6. Run Queue Worker

```bash
php artisan queue:work
```

### 7. Start Development Server

```bash
php artisan serve
```

API default URL:
`http://127.0.0.1:8000/api`

---

## Development Guidelines

- Keep controllers thin; move domain logic to services.
- Use Form Requests for validation.
- Use Policies for every role-protected action.
- Wrap critical flows in DB transactions.
- Log every status transition in dedicated history tables.
- Avoid out-of-scope features not defined in SRS.

---

## Testing

Run tests with:

```bash
php artisan test
```

Recommended:
- Feature tests for API endpoints and authorization
- Unit tests for services and domain logic
- Integration tests for workflows (orders/tasks/payments/delivery)

---

## Roadmap (SRS-Aligned)

- Finalize schema and enum/constants for statuses and roles
- Complete module-by-module API implementation
- Add comprehensive policy coverage
- Add events/listeners + queue jobs for notifications
- Add reporting endpoints
- Improve test coverage across critical workflows

---

## License

This project is proprietary unless stated otherwise by repository owner.
