# Advanced Booking SaaS - System Architecture

## Deployment Modes

```
┌─────────────────────────────────────────────────────────────────┐
│                    BOOKING SYSTEM ARCHITECTURE                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                    DEPLOYMENT LAYER                        │  │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐  │  │
│  │  │   SaaS   │  │Standalone│  │WordPress │  │ MVP/Lite │  │  │
│  │  │ Multi-   │  │   Full   │  │  Plugin  │  │  Shared  │  │  │
│  │  │ Tenant   │  │  System  │  │          │  │  Hosting │  │  │
│  │  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘  │  │
│  └──────────┼───────────┼────────────┼──────────────┼────────┘  │
│             └───────────┼────────────┼──────────────┘           │
│                         ▼            ▼                          │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                  CORE APPLICATION LAYER                    │  │
│  │  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌──────────────┐   │  │
│  │  │ Router  │ │  Auth   │ │  RBAC   │ │ API Gateway  │   │  │
│  │  └─────────┘ └─────────┘ └─────────┘ └──────────────┘   │  │
│  └───────────────────────────────────────────────────────────┘  │
│                          │                                       │
│  ┌───────────────────────┴───────────────────────────────────┐  │
│  │                   MODULE LAYER                             │  │
│  │  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ │  │
│  │  │Booking │ │Payment │ │  CRM   │ │Notifica│ │Report  │ │  │
│  │  │ Engine │ │ System │ │System  │ │  tions │ │ Engine │ │  │
│  │  └────────┘ └────────┘ └────────┘ └────────┘ └────────┘ │  │
│  │  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐            │  │
│  │  │ Wallet │ │License │ │Referral│ │  Wait  │            │  │
│  │  │        │ │ Engine │ │System  │ │  list  │            │  │
│  │  └────────┘ └────────┘ └────────┘ └────────┘            │  │
│  └───────────────────────────────────────────────────────────┘  │
│                          │                                       │
│  ┌───────────────────────┴───────────────────────────────────┐  │
│  │                   DATA LAYER                               │  │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────────┐ │  │
│  │  │  MySQL   │ │  SQLite  │ │  Redis   │ │   File       │ │  │
│  │  │/MariaDB  │ │  (Lite)  │ │(Optional)│ │   Storage    │ │  │
│  │  └──────────┘ └──────────┘ └──────────┘ └──────────────┘ │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Multi-Tenant Architecture (SaaS Mode)

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  Tenant A   │     │  Tenant B   │     │  Tenant C   │
│  - Branding  │     │  - Branding  │     │  - Branding  │
│  - Config    │     │  - Config    │     │  - Config    │
│  - DB/Schema │     │  - DB/Schema │     │  - DB/Schema │
└──────┬───────┘     └──────┬───────┘     └──────┬───────┘
       │                    │                    │
       └────────────────────┼────────────────────┘
                            │
                    ┌───────┴────────┐
                    │   SaaS Admin   │
                    │  (Central Hub) │
                    └────────────────┘
```

## License Validation Flow

```
┌──────────┐         ┌──────────┐         ┌──────────┐
│  Client  │         │  License │         │  System  │
│  System  │         │  Server  │         │  Admin   │
└────┬─────┘         └────┬─────┘         └────┬─────┘
     │                    │                    │
     │──1. Install────────▶                    │
     │                    │──2. Generate Key───│
     │◀──3. License Key───│                    │
     │                    │                    │
     │──4. Decrypt & Verify Locally            │
     │    (SHA-256 + AES)                      │
     │                    │                    │
     │──5. Feature Toggle─────────────────────▶│
     │    Based on License                     │
     │                    │                    │
     │──6. Periodic───────────────────────────▶│
     │    Re-verify (Optional)                 │
     │                    │                    │
     │──7. Revoke─────────────────────────────▶│
     │    (If license revoked)                 │
```

## Database Isolation Strategies

```
┌─────────────────────────────────────────────────────┐
│              DATABASE ISOLATION MODES                │
├──────────┬──────────┬───────────────┬────────────────┤
│  Mode    │  SaaS   │  Standalone   │  WP Plugin    │
├──────────┼──────────┼───────────────┼────────────────┤
│  Per-DB  │    ✅    │      ✅       │     ❌        │
│  Schema  │    ✅    │      ❌       │     ❌        │
│  Prefix  │    ❌    │      ❌       │     ✅        │
│  SQLite  │    ❌    │      ✅       │     ❌        │
└──────────┴──────────┴───────────────┴────────────────┘
```

## File Structure

```
booking-system/
├── core/                    # Zero-dependency PHP framework
│   ├── App.php              # Application bootstrap
│   ├── Router.php           # Request router
│   ├── Database.php         # DB abstraction (MySQL/SQLite)
│   ├── Model.php            # Base model with CRUD
│   ├── Controller.php       # Base controller
│   ├── View.php             # Template engine
│   ├── Middleware.php        # Middleware chain
│   ├── Request.php          # Request handler
│   ├── Response.php         # JSON/HTML response
│   ├── Session.php          # Session management
│   ├── Validator.php        # Input validation
│   └── Helpers.php          # Utility functions
│
├── app/
│   ├── Config/
│   │   ├── app.php          # Master config
│   │   ├── database.php     # DB connections
│   │   ├── license.php      # License config
│   │   ├── modules.php      # Feature flags
│   │   ├── payment.php      # Payment gateways
│   │   └── notification.php # Notification channels
│   │
│   ├── Controllers/
│   │   ├── Admin/           # Admin panel controllers
│   │   │   ├── DashboardController.php
│   │   │   ├── BookingController.php
│   │   │   ├── CustomerController.php
│   │   │   ├── PaymentController.php
│   │   │   ├── SettingsController.php
│   │   │   ├── LicenseController.php
│   │   │   └── TenantController.php  # SaaS only
│   │   ├── Api/             # REST API controllers
│   │   │   ├── AuthController.php
│   │   │   ├── BookingController.php
│   │   │   ├── PaymentController.php
│   │   │   ├── CustomerController.php
│   │   │   └── WebhookController.php
│   │   └── Public/          # Customer-facing
│   │       ├── HomeController.php
│   │       ├── BookingController.php
│   │       ├── PaymentController.php
│   │       └── TrackingController.php
│   │
│   ├── Models/
│   │   ├── Tenant.php
│   │   ├── Customer.php
│   │   ├── Booking.php
│   │   ├── Service.php
│   │   ├── Specialist.php
│   │   ├── Schedule.php
│   │   ├── Payment.php
│   │   ├── Invoice.php
│   │   ├── Wallet.php
│   │   ├── WalletTransaction.php
│   │   ├── Referral.php
│   │   ├── Rating.php
│   │   ├── Waitlist.php
│   │   ├── Setting.php
│   │   ├── AuditLog.php
│   │   └── License.php
│   │
│   ├── Middleware/
│   │   ├── AuthMiddleware.php
│   │   ├── TenantMiddleware.php
│   │   ├── LicenseMiddleware.php
│   │   ├── ModuleMiddleware.php
│   │   ├── RateLimitMiddleware.php
│   │   └── CorsMiddleware.php
│   │
│   ├── Services/
│   │   ├── BookingService.php
│   │   ├── PaymentService.php
│   │   ├── LicenseService.php
│   │   ├── NotificationService.php
│   │   ├── WalletService.php
│   │   ├── ReferralService.php
│   │   ├── ReportService.php
│   │   ├── TenantService.php
│   │   ├── SmsService.php
│   │   ├── TelegramService.php
│   │   ├── WhatsAppService.php
│   │   └── CalendarService.php
│   │
│   ├── Helpers/
│   │   ├── DateHelper.php       # Persian/Jalali dates
│   │   ├── PriceHelper.php      # Multi-currency
│   │   ├── StringHelper.php     # Booking codes
│   │   ├── FileHelper.php       # Upload management
│   │   └── BrandingHelper.php   # Brand injection
│   │
│   └── Plugins/
│       └── PluginManager.php    # Plugin loader
│
├── public/
│   ├── index.php               # Entry point
│   ├── .htaccess               # Apache rewrite
│   └── assets/
│       ├── css/
│       │   ├── app.css         # Main styles
│       │   ├── glassmorphism.css# Glassmorphism theme
│       │   └── minimal.css     # Lite theme
│       └── js/
│           ├── app.js          # Core JS
│           ├── booking.js      # Booking widget
│           └── admin.js        # Admin panel
│
├── database/
│   ├── schema.sql              # Full schema
│   ├── migrations/             # Versioned migrations
│   │   ├── 001_create_tenants.sql
│   │   ├── 002_create_customers.sql
│   │   ├── 003_create_bookings.sql
│   │   └── ...
│   └── seeds/                  # Default data
│       ├── admin.sql
│       ├── services.sql
│       └── settings.sql
│
├── modules/
│   ├── Booking/
│   ├── Payment/
│   ├── CRM/
│   ├── Notification/
│   ├── License/
│   ├── Tenant/
│   └── Report/
│
├── wp-plugin/                  # WordPress plugin
│   ├── booking-system.php      # Plugin main file
│   ├── includes/
│   │   ├── core.php            # WP integration
│   │   ├── shortcodes.php      # Shortcode handlers
│   │   ├── admin.php           # WP admin pages
│   │   └── api.php             # REST endpoints
│   └── assets/
│
├── license-server/             # Separate lightweight system
│   ├── index.php               # Entry point
│   ├── config.php              # License server config
│   ├── api/
│   │   ├── generate.php        # Key generation
│   │   ├── validate.php        # Key validation
│   │   └── revoke.php          # Key revocation
│   └── admin/
│       ├── index.php           # Admin panel
│       ├── create.php          # Create license
│       ├── list.php            # List licenses
│       └── login.php           # Auth
│
├── installer/                  # One-click installer
│   ├── index.php               # Install wizard
│   ├── step1.php               # Requirements check
│   ├── step2.php               # Database setup
│   ├── step3.php               # Configuration
│   ├── step4.php               # License activation
│   ├── step5.php               # Complete
│   └── assets/                 # Installer assets
│
├── frontend/                   # React SPA
│   ├── package.json
│   ├── vite.config.js
│   ├── src/
│   │   ├── main.jsx
│   │   ├── App.jsx
│   │   ├── components/
│   │   ├── pages/
│   │   ├── services/
│   │   └── styles/
│   └── public/
│
└── docs/
    ├── INSTALLATION.md
    ├── HOSTING_REQUIREMENTS.md
    ├── TROUBLESHOOTING.md
    ├── LICENSE_ACTIVATION.md
    ├── PLUGIN_INSTALLATION.md
    ├── API_CONFIGURATION.md
    └── DEPLOYMENT_GUIDE.md
```
