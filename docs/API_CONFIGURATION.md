# API Configuration Guide

## Base URL
```
https://yourdomain.com/api/v1
```

## Authentication

### JWT Token
Most endpoints require a Bearer token:
```
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
```

### Get Token
```bash
curl -X POST https://yourdomain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@example.com", "password": "admin123"}'
```

### Response
```json
{
  "success": true,
  "token": "eyJhbGciOiJIUzI1NiIs...",
  "user": { "id": 1, "name": "Admin", "role": "super_admin" }
}
```

## Endpoints

### Booking
| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/services` | List active services | No |
| GET | `/specialists` | List specialists | No |
| GET | `/slots?date=2026-01-15&service_id=1` | Get available time slots | No |
| POST | `/bookings` | Create booking | No |
| GET | `/bookings/{code}` | Track booking by code | No |

### Create Booking
```json
{
  "first_name": "علی",
  "last_name": "رضایی",
  "phone": "09123456789",
  "email": "ali@example.com",
  "items": [
    { "service_id": 1, "quantity": 1 },
    { "service_id": 2, "quantity": 1 }
  ],
  "booking_date": "2026-01-20",
  "booking_time": "10:00",
  "notes": "لطفا زنگ بزنید",
  "source": "website"
}
```

### Payment
| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/payments/process` | Process payment | No |
| GET | `/payments/gateways` | Get active gateways | No |
| POST | `/payments/verify/{gateway}` | Verify gateway payment | No |

### Customer (Auth Required)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/customer/profile` | Get customer profile |
| GET | `/customer/bookings` | List customer bookings |
| POST | `/customer/bookings/{id}/cancel` | Cancel booking |
| GET | `/wallet/balance` | Get wallet balance |
| GET | `/wallet/transactions` | List wallet transactions |

### Admin (Auth + Admin Role Required)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/dashboard/stats` | Dashboard statistics |
| GET | `/admin/bookings` | List all bookings |
| POST | `/admin/bookings/{id}/approve` | Approve booking |
| POST | `/admin/bookings/{id}/cancel` | Cancel booking |
| POST | `/admin/bookings/{id}/confirm-payment` | Confirm payment |
| GET | `/admin/customers` | List customers |
| POST | `/admin/customers/{id}/add-wallet` | Add wallet credit |
| POST | `/admin/payments/{id}/verify` | Verify card-to-card payment |
| POST | `/admin/payments/{id}/refund` | Refund payment |
| POST | `/admin/settings/branding` | Update branding |
| POST | `/admin/license/activate` | Activate license |

### Webhooks
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/webhook/payment/{gateway}` | Payment callback |
| POST | `/api/v1/webhook/sms` | SMS delivery status |

## Response Format

### Success
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "total": 100,
    "page": 1,
    "per_page": 15,
    "last_page": 7
  }
}
```

### Error
```json
{
  "success": false,
  "error": "Error message",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

## Rate Limiting
- 60 requests per minute per IP
- Headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`
- Status 429 when exceeded

## License Server API

### Base URL
```
https://license-server.yourdomain.com/api
```

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/generate` | Bearer token | Generate license |
| POST | `/validate` | No | Validate license |
| POST | `/revoke` | Bearer token | Revoke license |
| GET | `/info` | No | Server info |
| GET | `/health` | No | Health check |
