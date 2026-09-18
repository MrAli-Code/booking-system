# License Activation Guide

## Overview

The Booking System uses a hybrid cryptographic license system:
- **SHA-256** for signing and integrity verification
- **AES-256-CBC** for payload encryption
- Each license is uniquely bound to a domain or server fingerprint

## License Key Format

```
LCS-XXXX-XXXX-XXXX.XXXXXXXX
│    └─── Encrypted Payload ──┘└── Signature
│
License Server Code
```

## Payload Contents

```json
{
  "customer_id": "CUST-001",
  "customer_name": "Acme Clinic",
  "plan": "enterprise",
  "domain": "clinic.com,*.clinic.com",
  "features": "*",
  "issued_at": "2026-01-01 00:00:00",
  "expires_at": "2027-01-01 00:00:00",
  "version": "1.0"
}
```

## Activation Steps

### 1. Get a License Key
- Purchase from the official website
- Or generate via the License Server admin panel
- Or use the built-in 14-day trial

### 2. Activate via Installer
- During installation, Step 4 asks for license key
- Enter the key in format `LCS-XXXX-XXXX-XXXX.XXXXXXXX`
- Or click "Start Trial" for 14-day free trial

### 3. Activate via Admin Panel
- Login to admin panel
- Navigate to Settings → License
- Enter license key and click "Activate"

### 4. Activate via API
```bash
curl -X POST https://yourdomain.com/api/v1/license/activate \
  -H "Content-Type: application/json" \
  -d '{"license_key": "LCS-XXXX-XXXX-XXXX.XXXXXXXX"}'
```

## License Server Setup (for vendors)

### Quick Start
```bash
# Deploy license-server/ to a separate hosting
# Or run locally:
cd license-server
php -S 0.0.0.0:8080

# Default login: admin / admin123
# CHANGE THIS IMMEDIATELY!
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/generate` | Generate new license key |
| POST | `/api/validate` | Validate license key |
| POST | `/api/revoke` | Revoke license key |
| GET | `/api/health` | Server health check |
| GET | `/admin` | Admin dashboard |

### Generate License (Admin UI)
1. Login to License Server Admin
2. Click "Create License"
3. Fill in:
   - Customer Name
   - Plan (Basic/Pro/Enterprise/Lifetime)
   - Domain (or * for any)
   - Expiry days
   - Custom features (optional)
4. Click "Generate"
5. Copy the generated license key

### Generate License (API)
```bash
curl -X POST https://license-server.example.com/api/generate \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": "CUST-001",
    "customer_name": "Acme Clinic",
    "plan": "pro",
    "domain": "clinic.com",
    "expiry_days": 365,
    "features": ["booking", "payment", "crm", "wallet", "notification"]
  }'
```

### Validate License (API)
```bash
curl -X POST https://license-server.example.com/api/validate \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "LCS-XXXX-XXXX-XXXX.XXXXXXXX",
    "domain": "clinic.com",
    "fingerprint": "sha256-of-server"
  }'
```

## Offline Activation

When the system cannot reach the license server:

1. The system generates a server fingerprint
2. Send this fingerprint to the license administrator
3. Admin generates a key bound to the fingerprint
4. Enter the key locally - it validates offline using embedded public key

## Feature Toggle Matrix

| Module | Basic | Pro | Enterprise | Lifetime |
|--------|-------|-----|------------|----------|
| Booking Engine | ✅ | ✅ | ✅ | ✅ |
| Payment System | ✅ | ✅ | ✅ | ✅ |
| CRM | ✅ | ✅ | ✅ | ✅ |
| Wallet | ❌ | ✅ | ✅ | ✅ |
| Referral | ❌ | ✅ | ✅ | ✅ |
| Notifications | ✅ | ✅ | ✅ | ✅ |
| Reports | ❌ | ✅ | ✅ | ✅ |
| Multi-Currency | ❌ | ❌ | ✅ | ✅ |
| Crypto | ❌ | ❌ | ✅ | ✅ |
| Telegram | ❌ | ✅ | ✅ | ✅ |
| WhatsApp | ❌ | ❌ | ✅ | ✅ |
| Medical Mode | ❌ | ❌ | ✅ | ✅ |
| Multi-Tenant (SaaS) | ❌ | ❌ | ✅ | ✅ |

## Troubleshooting

| Error | Solution |
|-------|----------|
| "Invalid license format" | Check the key starts with LCS- |
| "License has expired" | Renew license via License Server |
| "License not valid for this domain" | Generate key with correct domain |
| "License not valid for this server" | Generate key bound to this server fingerprint |
| "License revoked" | Contact administrator |
