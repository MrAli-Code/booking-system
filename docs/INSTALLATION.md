# Installation Guide - Booking System

## Quick Start

### Method 1: Shared Hosting (cPanel / DirectAdmin)
1. Upload all files to `public_html` via FTP
2. Navigate to `https://yourdomain.com/install`
3. Follow the 5-step wizard
4. Login at `https://yourdomain.com/admin`

### Method 2: Standalone VPS / Dedicated Server
```bash
# Clone or upload files to /var/www/booking
cd /var/www/booking

# Set permissions
chmod -R 755 storage/ public/uploads/
chmod -R 644 .env

# Configure web server (Apache/Nginx) to point to public/

# Run installer
php -S 0.0.0.0:8080 -t public/
```

### Method 3: Docker
```bash
docker run -d \
  -p 8080:80 \
  -e DB_HOST=mysql \
  -e DB_NAME=booking \
  -e DB_USER=root \
  -e DB_PASS=secret \
  -v booking_data:/var/www/html/storage \
  booking-system:latest
```

### Method 4: WordPress Plugin
1. Upload `wp-plugin/booking-system.zip` via WordPress admin
2. Activate plugin
3. Use `[booking_system]` shortcode on any page

## System Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| PHP | 7.4 | 8.1+ |
| MySQL | 5.7 | 8.0+ |
| MariaDB | 10.3 | 10.11+ |
| SQLite | 3.0 | 3.40+ |
| Memory | 64MB | 128MB+ |
| Storage | 50MB | 500MB+ |
| CPU | 1 core | 2 cores |
| Extensions | PDO, OpenSSL, JSON, MBString, cURL, GD | + Imagick, Redis |

## Environment Configuration

Create `.env` file in root directory:
```env
APP_NAME="My Clinic"
APP_ENV=production
APP_DEBUG=false
APP_MODE=standalone          # saas | standalone | wordpress | mvp
APP_KEY=your-32-char-key

DB_DRIVER=mysql              # mysql | sqlite
DB_HOST=localhost
DB_NAME=booking_system
DB_USER=root
DB_PASS=your_password
DB_PREFIX=bbs_

CURRENCY=IRR
TIMEZONE=Asia/Tehran
CALENDAR=jalali

MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@email.com
MAIL_PASSWORD=your_password

SMS_PROVIDER=ippanel         # ippanel | kavenegar | melipayamak
SMS_API_KEY=your-api-key
SMS_FROM=1000xxxxx

TELEGRAM_BOT_TOKEN=your-bot-token
WHATSAPP_API_KEY=your-api-key
```

## Deployment Modes

### SaaS Multi-Tenant
```env
APP_MODE=saas
SAAS_DB_HOST=localhost
SAAS_DB_NAME=booking_saas
SAAS_DB_USER=root
SAAS_DB_PASS=secret
```

### Standalone (default)
```env
APP_MODE=standalone
```

### WordPress Plugin
- Install as WordPress plugin
- Tables use `wp_bbs_` prefix automatically
- Use shortcodes in pages/posts

### MVP / Lite (shared hosting)
```env
APP_MODE=mvp
```
Heavy modules (wallet, referral, crypto, telegram, medical mode) are disabled automatically.

## Post-Installation

1. Change default admin password (`admin` / `admin123`)
2. Configure payment gateways in Settings
3. Upload your branding (logo, colors)
4. Add services and specialists
5. Set up notification channels
6. Activate license

## Troubleshooting

| Issue | Solution |
|-------|----------|
| White screen after install | Check PHP error log, enable APP_DEBUG |
| Database connection failed | Verify .env credentials, check host permissions |
| 404 on all pages | Ensure .htaccess is uploaded and mod_rewrite is enabled |
| Booking form not loading | Check JavaScript console for API errors |
| SMS not sending | Verify API key and provider credentials |
| License validation failed | Check date/time on server, verify key format |
| Slow performance | Enable MVP mode, disable animations, use SQLite |
