<?php
class BBS_WP_Shortcodes
{
    public function register(): void
    {
        add_shortcode('booking_system', [$this, 'renderBookingSystem']);
        add_shortcode('booking_form', [$this, 'renderBookingForm']);
        add_shortcode('booking_track', [$this, 'renderTracking']);
        add_shortcode('booking_calendar', [$this, 'renderCalendar']);
        add_shortcode('booking_services', [$this, 'renderServices']);
        add_shortcode('customer_bookings', [$this, 'renderCustomerBookings']);
    }

    public function renderBookingSystem(array $atts, ?string $content = null): string
    {
        $atts = shortcode_atts([
            'view' => 'all',
            'theme' => 'glassmorphism',
            'show_header' => 'yes',
            'show_footer' => 'yes',
        ], $atts);

        ob_start();
        ?>
        <div class="bbs-booking-system" data-theme="<?php echo esc_attr($atts['theme']); ?>">
            <?php if ($atts['show_header'] === 'yes'): ?>
            <div class="bbs-header">
                <h2><?php _e('Book an Appointment', 'booking-system'); ?></h2>
                <p><?php _e('Choose your service, date, and time.', 'booking-system'); ?></p>
            </div>
            <?php endif; ?>
            <div class="bbs-booking-container">
                <div class="bbs-steps">
                    <div class="bbs-step active" data-step="1">
                        <span class="step-number">1</span>
                        <span class="step-label"><?php _e('Service', 'booking-system'); ?></span>
                    </div>
                    <div class="bbs-step" data-step="2">
                        <span class="step-number">2</span>
                        <span class="step-label"><?php _e('Date & Time', 'booking-system'); ?></span>
                    </div>
                    <div class="bbs-step" data-step="3">
                        <span class="step-number">3</span>
                        <span class="step-label"><?php _e('Info', 'booking-system'); ?></span>
                    </div>
                    <div class="bbs-step" data-step="4">
                        <span class="step-number">4</span>
                        <span class="step-label"><?php _e('Confirm', 'booking-system'); ?></span>
                    </div>
                </div>
                <div class="bbs-step-content" data-step="1">
                    <h3><?php _e('Select Services', 'booking-system'); ?></h3>
                    <div class="bbs-services-grid" id="bbs-services">
                        <p><?php _e('Loading services...', 'booking-system'); ?></p>
                    </div>
                </div>
                <div class="bbs-step-content" data-step="2" style="display:none">
                    <h3><?php _e('Select Date & Time', 'booking-system'); ?></h3>
                    <div class="bbs-calendar-container">
                        <div class="bbs-calendar" id="bbs-calendar"></div>
                        <div class="bbs-time-slots" id="bbs-time-slots">
                            <p><?php _e('Select a date to see available times.', 'booking-system'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="bbs-step-content" data-step="3" style="display:none">
                    <h3><?php _e('Your Information', 'booking-system'); ?></h3>
                    <form id="bbs-customer-form" class="bbs-form">
                        <div class="bbs-form-row">
                            <div class="bbs-form-group">
                                <label for="first_name"><?php _e('First Name *', 'booking-system'); ?></label>
                                <input type="text" id="first_name" name="first_name" required>
                            </div>
                            <div class="bbs-form-group">
                                <label for="last_name"><?php _e('Last Name *', 'booking-system'); ?></label>
                                <input type="text" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        <div class="bbs-form-row">
                            <div class="bbs-form-group">
                                <label for="phone"><?php _e('Phone *', 'booking-system'); ?></label>
                                <input type="tel" id="phone" name="phone" required>
                            </div>
                            <div class="bbs-form-group">
                                <label for="email"><?php _e('Email', 'booking-system'); ?></label>
                                <input type="email" id="email" name="email">
                            </div>
                        </div>
                        <div class="bbs-form-group">
                            <label for="notes"><?php _e('Notes (optional)', 'booking-system'); ?></label>
                            <textarea id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="bbs-step-content" data-step="4" style="display:none">
                    <h3><?php _e('Confirm Booking', 'booking-system'); ?></h3>
                    <div class="bbs-summary" id="bbs-summary"></div>
                    <button class="bbs-btn bbs-btn-primary" id="bbs-submit">
                        <?php _e('Confirm Booking', 'booking-system'); ?>
                    </button>
                </div>
                <div class="bbs-nav">
                    <button class="bbs-btn bbs-btn-secondary" id="bbs-prev" style="display:none">
                        <?php _e('Previous', 'booking-system'); ?>
                    </button>
                    <button class="bbs-btn bbs-btn-primary" id="bbs-next">
                        <?php _e('Next', 'booking-system'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function renderBookingForm(array $atts): string
    {
        $atts = shortcode_atts([
            'service_id' => 0,
            'hide_services' => 'no',
        ], $atts);

        ob_start();
        ?>
        <div class="bbs-booking-form-widget" data-service-id="<?php echo (int)$atts['service_id']; ?>"
             data-hide-services="<?php echo esc_attr($atts['hide_services']); ?>">
            <div class="bbs-widget-inner">
                <div class="bbs-widget-step active">
                    <h3><?php _e('Select Date', 'booking-system'); ?></h3>
                    <div class="bbs-mini-calendar" id="bbs-mini-calendar"></div>
                </div>
                <div class="bbs-widget-step">
                    <h3><?php _e('Select Time', 'booking-system'); ?></h3>
                    <div class="bbs-time-buttons" id="bbs-time-buttons"></div>
                </div>
                <div class="bbs-widget-step">
                    <h3><?php _e('Your Info', 'booking-system'); ?></h3>
                    <input type="text" placeholder="<?php esc_attr_e('Name', 'booking-system'); ?>">
                    <input type="tel" placeholder="<?php esc_attr_e('Phone', 'booking-system'); ?>">
                    <button class="bbs-btn bbs-btn-primary bbs-btn-block">
                        <?php _e('Book Now', 'booking-system'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function renderTracking(array $atts): string
    {
        ob_start();
        ?>
        <div class="bbs-tracking">
            <h3><?php _e('Track Your Booking', 'booking-system'); ?></h3>
            <div class="bbs-tracking-form">
                <input type="text" id="bbs-tracking-code" placeholder="<?php esc_attr_e('Enter booking code (e.g. BBS-A1B2C)', 'booking-system'); ?>">
                <button class="bbs-btn bbs-btn-primary" id="bbs-tracking-btn">
                    <?php _e('Track', 'booking-system'); ?>
                </button>
            </div>
            <div id="bbs-tracking-result" class="bbs-tracking-result"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function renderCalendar(array $atts): string
    {
        ob_start();
        ?>
        <div class="bbs-calendar-widget">
            <div class="bbs-calendar-header">
                <button class="bbs-calendar-nav" data-dir="prev">&lsaquo;</button>
                <h3 class="bbs-calendar-title"></h3>
                <button class="bbs-calendar-nav" data-dir="next">&rsaquo;</button>
            </div>
            <div class="bbs-calendar-grid" id="bbs-calendar-grid"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function renderServices(array $atts): string
    {
        global $wpdb;
        $prefix = $wpdb->prefix . 'bbs_';
        $services = $wpdb->get_results("SELECT * FROM {$prefix}services WHERE is_active = 1 ORDER BY name");

        ob_start();
        ?>
        <div class="bbs-services-list">
            <?php foreach ($services as $service): ?>
            <div class="bbs-service-card" data-id="<?php echo (int)$service->id; ?>">
                <h4><?php echo esc_html($service->name); ?></h4>
                <p><?php echo esc_html($service->description); ?></p>
                <span class="bbs-service-price"><?php echo number_format($service->price); ?> <?php echo esc_html($service->currency); ?></span>
                <span class="bbs-service-duration"><?php echo (int)$service->duration_minutes; ?> <?php _e('min', 'booking-system'); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function renderCustomerBookings(array $atts): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please login to view your bookings.', 'booking-system') . '</p>';
        }

        global $wpdb;
        $prefix = $wpdb->prefix . 'bbs_';
        $userId = get_current_user_id();
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, c.first_name, c.last_name
             FROM {$prefix}bookings b
             JOIN {$prefix}customers c ON b.customer_id = c.id
             WHERE c.wp_user_id = %d
             ORDER BY b.created_at DESC
             LIMIT 20",
            $userId
        ));

        if (empty($bookings)) {
            return '<p>' . __('No bookings found.', 'booking-system') . '</p>';
        }

        ob_start();
        ?>
        <div class="bbs-customer-bookings">
            <table class="bbs-table">
                <thead>
                    <tr>
                        <th><?php _e('Code', 'booking-system'); ?></th>
                        <th><?php _e('Date', 'booking-system'); ?></th>
                        <th><?php _e('Time', 'booking-system'); ?></th>
                        <th><?php _e('Status', 'booking-system'); ?></th>
                        <th><?php _e('Actions', 'booking-system'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><code><?php echo esc_html($booking->booking_code); ?></code></td>
                        <td><?php echo esc_html($booking->booking_date); ?></td>
                        <td><?php echo esc_html($booking->booking_time); ?></td>
                        <td><span class="bbs-status bbs-status-<?php echo esc_attr($booking->status); ?>"><?php echo esc_html($booking->status); ?></span></td>
                        <td>
                            <?php if (in_array($booking->status, ['pending', 'confirmed'])): ?>
                            <button class="bbs-btn bbs-btn-sm bbs-btn-danger" data-action="cancel" data-id="<?php echo (int)$booking->id; ?>">
                                <?php _e('Cancel', 'booking-system'); ?>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}
