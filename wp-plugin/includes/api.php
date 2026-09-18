<?php
/**
 * WordPress REST API Integration
 */

class BBS_WP_API
{
    public function registerRoutes(): void
    {
        register_rest_route('bbs/v1', '/services', [
            'methods' => 'GET',
            'callback' => [$this, 'getServices'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('bbs/v1', '/slots', [
            'methods' => 'GET',
            'callback' => [$this, 'getSlots'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('bbs/v1', '/bookings', [
            'methods' => 'POST',
            'callback' => [$this, 'createBooking'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('bbs/v1', '/bookings/(?P<code>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getBooking'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('bbs/v1', '/customer/bookings', [
            'methods' => 'GET',
            'callback' => [$this, 'getCustomerBookings'],
            'permission_callback' => [$this, 'checkAuth'],
        ]);
    }

    public function getServices(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $prefix = $wpdb->prefix . 'bbs_';
        $services = $wpdb->get_results("SELECT * FROM {$prefix}services WHERE is_active = 1");
        return new \WP_REST_Response(['success' => true, 'data' => $services]);
    }

    public function getSlots(\WP_REST_Request $request): \WP_REST_Response
    {
        $date = $request->get_param('date');
        $serviceId = (int)$request->get_param('service_id');

        if (empty($date)) {
            return new \WP_REST_Response(['success' => false, 'error' => 'Date is required'], 400);
        }

        // Generate slots based on settings
        $slotDuration = BBS_WP_Core::getSetting('slot_duration', 30);
        $slots = [];
        $start = strtotime('09:00');
        $end = strtotime('18:00');

        for ($time = $start; $time < $end; $time += $slotDuration * 60) {
            $slots[] = date('H:i', $time);
        }

        return new \WP_REST_Response(['success' => true, 'data' => ['slots' => $slots]]);
    }

    public function createBooking(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $prefix = $wpdb->prefix . 'bbs_';
        $params = $request->get_json_params();

        $wpdb->insert("{$prefix}customers", [
            'wp_user_id' => get_current_user_id() ?: null,
            'first_name' => sanitize_text_field($params['first_name'] ?? ''),
            'last_name' => sanitize_text_field($params['last_name'] ?? ''),
            'phone' => sanitize_text_field($params['phone'] ?? ''),
            'email' => sanitize_email($params['email'] ?? ''),
            'referral_code' => strtoupper(substr(md5(uniqid()), 0, 8)),
        ]);
        $customerId = $wpdb->insert_id;

        $bookingCode = 'BBS-' . strtoupper(substr(md5(uniqid()), 0, 5));
        $wpdb->insert("{$prefix}bookings", [
            'customer_id' => $customerId,
            'booking_code' => $bookingCode,
            'status' => 'pending',
            'booking_date' => sanitize_text_field($params['booking_date'] ?? ''),
            'booking_time' => sanitize_text_field($params['booking_time'] ?? ''),
            'total_duration' => 0,
            'total_price' => (float)($params['total_price'] ?? 0),
            'notes' => sanitize_textarea_field($params['notes'] ?? ''),
        ]);

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'booking_code' => $bookingCode,
                'booking' => [
                    'booking_code' => $bookingCode,
                    'booking_date' => $params['booking_date'],
                    'booking_time' => $params['booking_time'],
                ],
            ],
        ], 201);
    }

    public function getBooking(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $prefix = $wpdb->prefix . 'bbs_';
        $code = $request->get_param('code');

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, c.first_name, c.last_name, c.phone
             FROM {$prefix}bookings b
             JOIN {$prefix}customers c ON b.customer_id = c.id
             WHERE b.booking_code = %s",
            $code
        ));

        if (!$booking) {
            return new \WP_REST_Response(['success' => false, 'error' => 'Booking not found'], 404);
        }

        return new \WP_REST_Response(['success' => true, 'data' => ['booking' => $booking]]);
    }

    public function getCustomerBookings(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!is_user_logged_in()) {
            return new \WP_REST_Response(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        global $wpdb;
        $prefix = $wpdb->prefix . 'bbs_';
        $userId = get_current_user_id();

        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, c.first_name, c.last_name
             FROM {$prefix}bookings b
             JOIN {$prefix}customers c ON b.customer_id = c.id
             WHERE c.wp_user_id = %d
             ORDER BY b.created_at DESC",
            $userId
        ));

        return new \WP_REST_Response(['success' => true, 'data' => ['bookings' => $bookings]]);
    }

    public function checkAuth(): bool
    {
        return is_user_logged_in();
    }
}
