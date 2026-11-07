<div align="center">
  <br />
  <h1>🎟️ Ticketing System REST API</h1>
  <p><strong>The High-Performance, Secure Laravel 13 Event Booking & E-Ticketing Engine</strong></p>
  <p><em>Equipped with dynamic ticket tiers, automated quota tracking, Midtrans payment integration, and Laravel Sanctum auth.</em></p>

  <p>
    <a href="https://laravel.com"><img src="https://img.shields.io/badge/Framework-Laravel%2013-FF2D20?style=flat-square&logo=laravel" alt="Laravel 13" /></a>
    <a href="https://php.net"><img src="https://img.shields.io/badge/Language-PHP%208.3+-777BB4?style=flat-square&logo=php" alt="PHP 8.3+" /></a>
    <a href="https://laravel.com/docs/sanctum"><img src="https://img.shields.io/badge/Auth-Laravel%20Sanctum-4F46E5?style=flat-square&logo=laravel" alt="Laravel Sanctum" /></a>
    <a href="https://pestphp.com"><img src="https://img.shields.io/badge/Testing-Pest%20PHP%205-F43F5E?style=flat-square&logo=pest" alt="Pest PHP" /></a>
    <a href="https://www.sqlite.org"><img src="https://img.shields.io/badge/Database-SQLite%20%7C%20MySQL-003B57?style=flat-square&logo=sqlite" alt="Database" /></a>
    <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square" alt="MIT License" /></a>
  </p>
  <br />
</div>

---

## ⚡ What is Ticketing System API?

**Ticketing System API** is the backend engine powering the event booking platform. Built with **Laravel 13** and **PHP 8.3+**, it delivers robust, authenticated RESTful endpoints for discovering events, managing multi-tier ticket quotas, processing orders, and authenticating users.

---

## 🏗️ Architecture & Data Flow

```mermaid
flowchart TD
    Client[Ticketing Frontend Client] -->|HTTP / JSON| Router[Laravel 13 API Routing]
    
    subgraph Security [Security & Middleware]
        Router --> Sanctum[Sanctum Auth Middleware]
        Router --> RoleGate[RoleMiddleware: Admin & Customer]
    end

    subgraph Controllers [Application Controllers]
        Sanctum --> AuthCtrl[AuthController: Register, Login, Me]
        RoleGate --> AdminEventCtrl[Admin Event & TicketType Controller]
        Router --> PublicEventCtrl[Public Event Catalog Controller]
    end

    subgraph CoreDomain [Eloquent Models & Logic]
        PublicEventCtrl --> EventModel[Event Model]
        AdminEventCtrl --> EventModel
        AdminEventCtrl --> TicketTypeModel[TicketType Model]
        AuthCtrl --> UserModel[User Model]
        
        EventModel --> OrderModel[Orders & Order Items]
        OrderModel --> PaymentModel[Payments State Machine]
        OrderModel --> TicketModel[Issued Tickets & Unique Barcodes]
    end

    CoreDomain --> Database[(SQLite / MySQL Database)]
```

---

## ✨ Key Capabilities

- **🎪 Comprehensive Event Lifecycle**: Support for upcoming events, slug-based SEO-friendly routing, venue management, and event banners.
- **🏷️ Multi-Tier Ticket Pricing**: Define multiple ticket categories per event (e.g. VIP, Early Bird, Regular) with distinct pricing and automated quota tracking.
- **🔐 Token-Based Authentication**: Secure authentication using Laravel Sanctum for API tokens with built-in role verification (`admin` vs `customer`).
- **🛡️ Request Validation & Resources**: Strict form request validation rules with normalized, consistent JSON responses via API Resources.
- **🧪 Comprehensive Test Suite**: 500+ lines of robust automated feature tests using **Pest PHP**.

---

## 📡 API Reference Overview

### 1. Authentication Endpoints

| Method | Endpoint | Access | Description |
| :--- | :--- | :---: | :--- |
| `POST` | `/api/register` | Public | Register new customer account |
| `POST` | `/api/login` | Public | Authenticate user and issue Sanctum token |
| `POST` | `/api/logout` | Authenticated | Revoke active token session |
| `GET` | `/api/me` | Authenticated | Fetch authenticated user profile |

### 2. Public Event Catalog

| Method | Endpoint | Access | Description |
| :--- | :--- | :---: | :--- |
| `GET` | `/api/events` | Public | List all active events with pricing & date filters |
| `GET` | `/api/events/{slug}` | Public | Get detailed event info with ticket tiers and remaining quotas |

### 3. Admin Backoffice Management (`role:admin`)

| Method | Endpoint | Access | Description |
| :--- | :--- | :---: | :--- |
| `GET` | `/api/admin/events` | Admin | List all events (including drafts and archived) |
| `POST` | `/api/admin/events` | Admin | Create a new event with venue & banner |
| `GET` | `/api/admin/events/{id}` | Admin | Retrieve single event detail |
| `PUT` | `/api/admin/events/{id}` | Admin | Update event details |
| `DELETE` | `/api/admin/events/{id}` | Admin | Delete event |
| `GET` | `/api/admin/ticket-types` | Admin | List all ticket types |
| `POST` | `/api/admin/ticket-types` | Admin | Create new ticket tier for an event |
| `PUT` | `/api/admin/ticket-types/{id}` | Admin | Update price or quota for ticket tier |
| `DELETE` | `/api/admin/ticket-types/{id}` | Admin | Remove ticket tier |

### 4. Customer Orders & Checkout

| Method | Endpoint | Access | Description |
| :--- | :--- | :---: | :--- |
| `POST` | `/api/checkout` | Authenticated | Create order with pessimistic lock & generate Midtrans Snap token |
| `GET` | `/api/orders` | Authenticated | Get customer order history |
| `GET` | `/api/orders/{order_code}` | Authenticated | Get customer order details and ticket barcodes |

### 5. Webhook Payments (Midtrans)

| Method | Endpoint | Access | Description |
| :--- | :--- | :---: | :--- |
| `POST` | `/api/midtrans/notification` | Public (SHA512 Signature) | Midtrans webhook notification: marks paid, issues tickets, queues emails |

---

## 🗄️ Database Entity Schema

```mermaid
erDiagram
    USERS ||--o{ ORDERS : places
    EVENTS ||--|{ TICKET_TYPES : contains
    TICKET_TYPES ||--o{ ORDER_ITEMS : includes
    ORDERS ||--|{ ORDER_ITEMS : contains
    ORDERS ||--|| PAYMENTS : processes
    ORDER_ITEMS ||--|{ TICKETS : generates

    USERS {
        bigint id PK
        string name
        string email
        string password
        enum role "admin, customer"
        timestamp email_verified_at
    }

    EVENTS {
        bigint id PK
        string title
        string slug UK
        text description
        string venue
        timestamp start_at
        string banner
        enum status "draft, published, cancelled"
    }

    TICKET_TYPES {
        bigint id PK
        bigint event_id FK
        string name "VIP, Regular, Early Bird"
        decimal price
        integer quota
        integer sold
    }

    ORDERS {
        bigint id PK
        bigint user_id FK
        string order_number UK
        decimal total_amount
        enum status "pending, paid, expired, cancelled"
    }

    PAYMENTS {
        bigint id PK
        bigint order_id FK
        string payment_type "midtrans, transfer"
        string transaction_id
        decimal amount
        enum status
    }

    TICKETS {
        bigint id PK
        bigint order_item_id FK
        string ticket_code UK
        enum status "valid, used, refunded"
    }
```

---

## 🚀 Quick Start (Local Setup)

### Prerequisites
* [PHP](https://www.php.net) (v8.3 or newer)
* [Composer](https://getcomposer.org)
* SQLite or MySQL

### 1. Configure Environment
Copy `.env.example` to create `.env`:
```bash
cp .env.example .env
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Generate App Key & Run Migrations
```bash
php artisan key:generate
php artisan migrate --seed
```
*The database will be migrated and seeded with initial demo events, ticket types, and user accounts.*

### 4. Start Development Server
```bash
php artisan serve
```
*API will run at `http://localhost:8000`.*

---

## 🧪 Automated Testing

Execute the test suite powered by Pest PHP:
```bash
php artisan test
```

---

## 📄 License

This software is open-sourced under the [MIT License](LICENSE).
