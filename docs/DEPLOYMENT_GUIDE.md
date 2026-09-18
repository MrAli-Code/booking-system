# Deployment Guide

## 1. SHARED HOSTING (cPanel / DirectAdmin)

### Step-by-Step
1. **Download** the latest release ZIP
2. **Extract** on your computer
3. **Upload** all files to `public_html` using FileZilla / cPanel File Manager
4. **Set permissions**: `chmod 755 storage/`, `chmod 644 .env`
5. **Visit** `https://yourdomain.com/install`
6. **Follow** the 5-step installation wizard
7. **Set up cron job** for reminders:
   ```
   * * * * * php /home/user/public_html/cron/run.php
   ```

### cPanel Specific
- **PHP Version**: Select PHP 8.1+ in "Select PHP Version"
- **Extensions**: Enable pdo_mysql, openssl, mbstring, curl, gd, json
- **Cron Jobs**: Add under "Cron Jobs" section
- **SSL**: Enable "AutoSSL" or install Let's Encrypt

## 2. VPS / DEDICATED SERVER

### Apache Configuration
```apache
<VirtualHost *:80>
    ServerName booking.yourdomain.com
    DocumentRoot /var/www/booking/public

    <Directory /var/www/booking/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/booking-error.log
    CustomLog ${APACHE_LOG_DIR}/booking-access.log combined
</VirtualHost>
```

### Nginx Configuration
```nginx
server {
    listen 80;
    server_name booking.yourdomain.com;
    root /var/www/booking/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~* \.(env|sql|md|log|lock|json)$ {
        deny all;
    }
}
```

### Docker Deployment
```dockerfile
FROM php:8.1-apache

RUN docker-php-ext-install pdo pdo_mysql pdo_sqlite
RUN a2enmod rewrite

COPY . /var/www/html/
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

RUN chmod -R 755 /var/www/html/storage/
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80
CMD ["apache2-foreground"]
```

## 3. OFFLINE / INTRANET

### Requirements
- PHP 7.4+ installed on the local server
- No internet access required for core functionality
- License validation works offline (local decryption)

### Installation
```bash
# Copy to offline server
cp -r booking-system /var/www/html/

# Use SQLite for zero configuration
sed -i 's/DB_DRIVER=mysql/DB_DRIVER=sqlite/' .env

# Run installer
php -S 0.0.0.0:8080 -t public/
```

### License Activation (Offline)
1. Run the system - it shows a license prompt
2. Generate a server fingerprint
3. Send fingerprint to license server (via email if no internet)
4. Receive license key
5. Enter key to activate

## 4. IRAN-ONLY SERVERS

For servers in Iran that cannot access international services:

### Disable external dependencies:
```env
DISABLE_EXTERNAL_API=true
SMS_PROVIDER=ippanel  # Iran-based
TELEGRAM_BOT_TOKEN=   # Leave empty
WHATSAPP_API_KEY=     # Leave empty
```

### Use Iran-based services:
- **SMS**: IPPanel, Kavenegar, Melipayamak
- **Payment**: Zarinpal, NextPay, IDPay
- **CDN**: Disable external CDN, use local assets

## 5. PERFORMANCE TUNING

### MVP Mode (Shared Hosting)
```env
APP_MODE=mvp
```
- Disables: Wallet, Referral, Crypto, Telegram, WhatsApp, Medical Mode
- Uses: Minimal CSS, no animations, SQLite option

### Caching
```env
CACHE_DRIVER=file       # file | redis | sqlite
CACHE_TTL=3600
```

### Database Optimization
```sql
-- Add indexes for faster queries
ALTER TABLE bookings ADD INDEX idx_booking_lookup (tenant_id, booking_date, status);
ALTER TABLE customers ADD INDEX idx_customer_phone_tenant (tenant_id, phone);
```

## 6. SECURITY

### Essential Steps
1. Change admin password immediately
2. Use HTTPS (SSL certificate)
3. Set strong APP_KEY in .env
4. Restrict file permissions
5. Enable firewall (ufw, CSF)
6. Regular backups
7. Monitor audit logs

### File Permissions
```bash
chmod 644 .env
chmod 755 public/
chmod 755 storage/
chmod 755 public/uploads/
chmod 644 public/index.php
```

### Rate Limiting
```env
API_RATE_LIMIT=60        # requests per minute
API_RATE_LIMIT_WINDOW=60 # window in seconds
```
