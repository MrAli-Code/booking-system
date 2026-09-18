<?php
namespace BBS\App\Models;

use BBS\Core\Model;

class Tenant extends Model
{
    protected static string $table = 'tenants';
    protected static array $fillable = [
        'uuid', 'name', 'slug', 'domain', 'database_name', 'db_host', 'db_user', 'db_pass',
        'plan', 'status', 'branding_logo', 'branding_name', 'branding_tagline',
        'branding_primary_color', 'branding_secondary_color',
        'contact_phone', 'contact_email', 'contact_address', 'contact_website',
        'settings', 'license_key', 'license_expires_at', 'features',
    ];
    protected static array $casts = [
        'settings' => 'json',
        'features' => 'json',
    ];

    public function customers(): array
    {
        return $this->hasMany(Customer::class, 'tenant_id');
    }

    public function bookings(): array
    {
        return $this->hasMany(Booking::class, 'tenant_id');
    }

    public function services(): array
    {
        return $this->hasMany(Service::class, 'tenant_id');
    }

    public function specialists(): array
    {
        return $this->hasMany(Specialist::class, 'tenant_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTrial(): bool
    {
        return $this->status === 'trial';
    }

    public function getBrandingArray(): array
    {
        return [
            'logo' => $this->branding_logo,
            'name' => $this->branding_name,
            'tagline' => $this->branding_tagline,
            'primary_color' => $this->branding_primary_color ?? '#6366f1',
            'secondary_color' => $this->branding_secondary_color ?? '#8b5cf6',
            'phone' => $this->contact_phone,
            'email' => $this->contact_email,
            'address' => $this->contact_address,
            'website' => $this->contact_website,
        ];
    }
}
