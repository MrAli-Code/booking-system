<?php
namespace BBS\App\Services;

use BBS\Core\App;
use BBS\App\Models\Booking;
use BBS\App\Models\BookingItem;
use BBS\App\Models\Customer;
use BBS\App\Models\Service;
use BBS\App\Models\Specialist;
use BBS\App\Models\Schedule;
use BBS\App\Models\Payment;
use BBS\App\Models\BookingHistory;

class BookingService
{
    private App $app;
    private CalendarService $calendar;

    public function __construct()
    {
        $this->app = App::getInstance();
        $this->calendar = new CalendarService();
    }

    public function createBooking(array $data): array
    {
        $tenantId = $data['tenant_id'] ?? 1;
        $customer = Customer::findByPhoneOrCreate(
            $data['phone'],
            $tenantId,
            [
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'email' => $data['email'] ?? null,
            ]
        );

        $this->db()->beginTransaction();

        try {
            // Calculate total
            $subtotal = 0;
            $totalDuration = 0;
            $items = $data['items'] ?? [];

            foreach ($items as $item) {
                $service = Service::find($item['service_id']);
                if (!$service || !$service->is_active) {
                    throw new \Exception("Service not found or inactive: {$item['service_id']}");
                }
                $subtotal += $service->price * ($item['quantity'] ?? 1);
                $totalDuration += $service->duration_minutes * ($item['quantity'] ?? 1);
            }

            // Apply discounts
            $discountAmount = $this->calculateDiscount($tenantId, $items, $subtotal);
            $totalPrice = $subtotal - $discountAmount;

            // Calculate deposit
            $depositAmount = $this->calculateDeposit($items, $totalPrice);

            $booking = Booking::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'specialist_id' => $data['specialist_id'] ?? null,
                'uuid' => Booking::generateUuid(),
                'booking_code' => Booking::generateBookingCode(),
                'status' => Booking::STATUS_PENDING,
                'booking_date' => $data['booking_date'],
                'booking_time' => $data['booking_time'],
                'total_duration' => $totalDuration,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'discount_type' => $discountAmount > 0 ? 'combo' : 'none',
                'deposit_amount' => $depositAmount,
                'total_price' => $totalPrice,
                'currency' => $data['currency'] ?? 'IRR',
                'notes' => $data['notes'] ?? null,
                'source' => $data['source'] ?? 'website',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);

            // Create booking items
            foreach ($items as $item) {
                $service = Service::find($item['service_id']);
                BookingItem::create([
                    'booking_id' => $booking->id,
                    'service_id' => $service->id,
                    'specialist_id' => $item['specialist_id'] ?? $data['specialist_id'] ?? null,
                    'service_name' => $service->name,
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $service->price,
                    'duration_minutes' => $service->duration_minutes,
                    'total_price' => $service->price * ($item['quantity'] ?? 1),
                ]);
            }

            // Check if needs review (medical mode)
            $needsReview = $this->checkNeedsReview($items);
            if ($needsReview) {
                $booking->status = Booking::STATUS_NEEDS_REVIEW;
                $booking->requires_review = true;
                $booking->save();
            }

            $this->logHistory($booking->id, 'created', null, $booking->status);

            $this->db()->commit();

            // Send notifications
            $this->sendBookingNotifications($booking, $customer);

            return [
                'success' => true,
                'booking' => $booking->toArray(),
                'customer' => $customer->toArray(),
            ];
        } catch (\Exception $e) {
            $this->db()->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getAvailableSlots(int $tenantId, string $date, ?int $specialistId = null, ?int $serviceId = null): array
    {
        $service = $serviceId ? Service::find($serviceId) : null;
        $duration = $service ? $service->duration_minutes : 30;

        $schedules = Schedule::where('tenant_id', '=', $tenantId)
            ->where('is_active', '=', 1);

        if ($specialistId) {
            $schedules = $schedules->where('specialist_id', '=', $specialistId);
        }

        $schedules = $schedules->get();
        $dayOfWeek = $this->calendar->getDayOfWeek($date);
        $availableSlots = [];

        foreach ($schedules as $schedule) {
            if ($schedule->day_of_week != $dayOfWeek) continue;

            $start = strtotime($schedule->start_time);
            $end = strtotime($schedule->end_time);
            $slotDur = $schedule->slot_duration * 60;

            $existingBookings = $this->getExistingBookings($tenantId, $date, $schedule->specialist_id);
            $breakStart = $schedule->break_start ? strtotime($schedule->break_start) : null;
            $breakEnd = $schedule->break_end ? strtotime($schedule->break_end) : null;

            for ($time = $start; $time + $duration * 60 <= $end; $time += $slotDur) {
                // Skip break time
                if ($breakStart && $breakEnd && $time >= $breakStart && $time < $breakEnd) continue;

                $timeStr = date('H:i', $time);
                $isBooked = false;

                foreach ($existingBookings as $booked) {
                    $bookedStart = strtotime($booked->booking_time);
                    $bookedEnd = $bookedStart + $booked->total_duration * 60;
                    $slotEnd = $time + $duration * 60;
                    if ($time < $bookedEnd && $slotEnd > $bookedStart) {
                        $isBooked = true;
                        break;
                    }
                }

                if (!$isBooked) {
                    $availableSlots[] = $timeStr;
                }
            }
        }

        sort($availableSlots);
        return $availableSlots;
    }

    private function getExistingBookings(int $tenantId, string $date, ?int $specialistId): array
    {
        $query = Booking::where('tenant_id', '=', $tenantId)
            ->where('booking_date', '=', $date)
            ->whereIn('status', [
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_IN_PROGRESS,
                Booking::STATUS_PENDING,
                Booking::STATUS_NEEDS_REVIEW,
            ]);

        if ($specialistId) {
            $query = $query->where('specialist_id', '=', $specialistId);
        }

        return $query->get();
    }

    private function calculateDiscount(int $tenantId, array $items, float $subtotal): float
    {
        // Combo discount logic
        $serviceCount = count($items);
        if ($serviceCount >= 3) {
            return $subtotal * 0.15; // 15% for 3+ services
        }
        if ($serviceCount >= 2) {
            return $subtotal * 0.10; // 10% for 2 services
        }
        return 0;
    }

    private function calculateDeposit(array $items, float $totalPrice): float
    {
        $maxDeposit = 0;
        foreach ($items as $item) {
            $service = Service::find($item['service_id']);
            if ($service) {
                if ($service->deposit_type === 'fixed') {
                    $maxDeposit = max($maxDeposit, (float) $service->deposit_amount);
                } elseif ($service->deposit_type === 'percentage') {
                    $amount = $totalPrice * ((float) $service->deposit_percentage / 100);
                    $maxDeposit = max($maxDeposit, $amount);
                }
            }
        }
        return $maxDeposit;
    }

    private function checkNeedsReview(array $items): bool
    {
        foreach ($items as $item) {
            $service = Service::find($item['service_id']);
            if ($service && $service->requires_review) return true;
        }
        return false;
    }

    public function approveBooking(int $bookingId, int $userId): array
    {
        $booking = Booking::find($bookingId);
        if (!$booking) return ['success' => false, 'error' => 'Booking not found'];

        $oldStatus = $booking->status;
        $booking->markReviewed($userId);
        $this->logHistory($booking->id, 'approved', $oldStatus, $booking->status);

        return ['success' => true, 'booking' => $booking->toArray()];
    }

    public function cancelBooking(int $bookingId, string $by = 'admin', ?string $reason = null): array
    {
        $booking = Booking::find($bookingId);
        if (!$booking) return ['success' => false, 'error' => 'Booking not found'];

        $oldStatus = $booking->status;
        $booking->cancel($by, $reason);
        $this->logHistory($booking->id, 'cancelled', $oldStatus, $booking->status);

        // Notify waitlist if someone is waiting
        $this->notifyWaitlist($booking);

        return ['success' => true, 'booking' => $booking->toArray()];
    }

    private function logHistory(int $bookingId, string $action, ?string $from, ?string $to): void
    {
        BookingHistory::create([
            'booking_id' => $bookingId,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'performed_by_type' => 'system',
        ]);
    }

    private function sendBookingNotifications(Booking $booking, Customer $customer): void
    {
        try {
            $notification = App::getInstance()->make(NotificationService::class);
            $notification->sendBookingConfirmation($booking, $customer);
        } catch (\Exception $e) {
            // Log but don't fail booking
        }
    }

    private function notifyWaitlist(Booking $cancelledBooking): void
    {
        try {
            $waitlist = App::getInstance()->make(\BBS\App\Services\WaitlistService::class);
            $waitlist->notifyOnCancellation($cancelledBooking);
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    private function db()
    {
        return App::getInstance()->getDatabase();
    }
}
