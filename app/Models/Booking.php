<?php
namespace BBS\App\Models;

use BBS\Core\Model;

class Booking extends Model
{
    protected static string $table = 'bookings';
    protected static array $fillable = [
        'tenant_id', 'customer_id', 'specialist_id', 'uuid', 'booking_code',
        'status', 'booking_date', 'booking_time', 'total_duration',
        'subtotal', 'discount_amount', 'discount_type', 'deposit_amount',
        'total_price', 'currency', 'notes', 'admin_notes', 'diagnostic_images',
        'requires_review', 'reviewed_by', 'reviewed_at',
        'cancelled_by', 'cancel_reason', 'cancelled_at',
        'source', 'ip_address', 'user_agent',
    ];
    protected static array $casts = [
        'diagnostic_images' => 'json',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    public function customer(): ?Customer
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function specialist(): ?Specialist
    {
        return $this->belongsTo(Specialist::class, 'specialist_id');
    }

    public function items(): array
    {
        return $this->hasMany(BookingItem::class, 'booking_id');
    }

    public function payments(): array
    {
        return $this->hasMany(Payment::class, 'booking_id');
    }

    public function history(): array
    {
        return $this->hasMany(BookingHistory::class, 'booking_id');
    }

    public static function generateBookingCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = 'BBS-';
        for ($i = 0; $i < 5; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        // Ensure uniqueness
        $exists = self::findBy('booking_code', $code);
        if ($exists) return self::generateBookingCode();
        return $code;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_NEEDS_REVIEW,
            self::STATUS_CONFIRMED,
        ]);
    }

    public function cancel(string $by = 'customer', ?string $reason = null): void
    {
        if (!$this->canBeCancelled()) return;
        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_by = $by;
        $this->cancel_reason = $reason;
        $this->cancelled_at = date('Y-m-d H:i:s');
        $this->save();
    }

    public function needsReview(): bool
    {
        return $this->requires_review || $this->status === self::STATUS_NEEDS_REVIEW;
    }

    public function markReviewed(int $userId): void
    {
        $this->status = self::STATUS_CONFIRMED;
        $this->reviewed_by = $userId;
        $this->reviewed_at = date('Y-m-d H:i:s');
        $this->save();
    }
}
