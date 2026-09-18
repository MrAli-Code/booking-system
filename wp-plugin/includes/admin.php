<?php
class BBS_WP_Admin
{
    public function renderDashboard(): void
    {
        global $wpdb;
        $prefix = $wpdb->prefix . 'bbs_';

        $stats = [
            'total_bookings' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}bookings"),
            'today_bookings' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}bookings WHERE booking_date = %s", current_time('Y-m-d'))),
            'total_customers' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}customers"),
            'pending_bookings' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}bookings WHERE status = 'pending'"),
            'revenue' => $wpdb->get_var("SELECT COALESCE(SUM(total_price), 0) FROM {$prefix}bookings WHERE status IN ('confirmed','completed')"),
        ];

        $recentBookings = $wpdb->get_results(
            "SELECT b.*, c.first_name, c.last_name, c.phone
             FROM {$prefix}bookings b
             JOIN {$prefix}customers c ON b.customer_id = c.id
             ORDER BY b.created_at DESC LIMIT 10"
        );

        ?>
        <div class="wrap bbs-admin-wrap">
            <h1><?php _e('Booking System Dashboard', 'booking-system'); ?></h1>

            <div class="bbs-stats-grid">
                <div class="bbs-stat-card">
                    <div class="bbs-stat-icon">📅</div>
                    <div class="bbs-stat-value"><?php echo (int)$stats['today_bookings']; ?></div>
                    <div class="bbs-stat-label"><?php _e('Today', 'booking-system'); ?></div>
                </div>
                <div class="bbs-stat-card">
                    <div class="bbs-stat-icon">📋</div>
                    <div class="bbs-stat-value"><?php echo (int)$stats['total_bookings']; ?></div>
                    <div class="bbs-stat-label"><?php _e('Total Bookings', 'booking-system'); ?></div>
                </div>
                <div class="bbs-stat-card">
                    <div class="bbs-stat-icon">⏳</div>
                    <div class="bbs-stat-value"><?php echo (int)$stats['pending_bookings']; ?></div>
                    <div class="bbs-stat-label"><?php _e('Pending', 'booking-system'); ?></div>
                </div>
                <div class="bbs-stat-card">
                    <div class="bbs-stat-icon">👥</div>
                    <div class="bbs-stat-value"><?php echo (int)$stats['total_customers']; ?></div>
                    <div class="bbs-stat-label"><?php _e('Customers', 'booking-system'); ?></div>
                </div>
                <div class="bbs-stat-card">
                    <div class="bbs-stat-icon">💰</div>
                    <div class="bbs-stat-value"><?php echo number_format((float)$stats['revenue']); ?></div>
                    <div class="bbs-stat-label"><?php _e('Revenue', 'booking-system'); ?></div>
                </div>
            </div>

            <div class="bbs-section">
                <h2><?php _e('Recent Bookings', 'booking-system'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Code', 'booking-system'); ?></th>
                            <th><?php _e('Customer', 'booking-system'); ?></th>
                            <th><?php _e('Date', 'booking-system'); ?></th>
                            <th><?php _e('Time', 'booking-system'); ?></th>
                            <th><?php _e('Amount', 'booking-system'); ?></th>
                            <th><?php _e('Status', 'booking-system'); ?></th>
                            <th><?php _e('Actions', 'booking-system'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentBookings as $b): ?>
                        <tr>
                            <td><code><?php echo esc_html($b->booking_code); ?></code></td>
                            <td><?php echo esc_html($b->first_name . ' ' . $b->last_name); ?><br><small><?php echo esc_html($b->phone); ?></small></td>
                            <td><?php echo esc_html($b->booking_date); ?></td>
                            <td><?php echo esc_html($b->booking_time); ?></td>
                            <td><?php echo number_format((float)$b->total_price); ?></td>
                            <td><span class="bbs-status bbs-status-<?php echo esc_attr($b->status); ?>"><?php echo esc_html($b->status); ?></span></td>
                            <td>
                                <a href="#" class="button button-small" data-action="view" data-id="<?php echo (int)$b->id; ?>"><?php _e('View', 'booking-system'); ?></a>
                                <?php if ($b->status === 'pending'): ?>
                                <a href="#" class="button button-small button-primary" data-action="approve" data-id="<?php echo (int)$b->id; ?>"><?php _e('Approve', 'booking-system'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <style>
        .bbs-admin-wrap { margin: 20px 0; }
        .bbs-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin: 20px 0; }
        .bbs-stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .bbs-stat-icon { font-size: 28px; margin-bottom: 10px; }
        .bbs-stat-value { font-size: 28px; font-weight: bold; color: #1a1a2e; }
        .bbs-stat-label { font-size: 13px; color: #64748b; margin-top: 4px; }
        .bbs-section { margin-top: 30px; }
        .bbs-section h2 { margin-bottom: 15px; }
        .bbs-status { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .bbs-status-pending { background: #fef3c7; color: #d97706; }
        .bbs-status-confirmed { background: #d1fae5; color: #059669; }
        .bbs-status-completed { background: #dbeafe; color: #2563eb; }
        .bbs-status-cancelled { background: #fee2e2; color: #dc2626; }
        </style>
        <?php
    }

    public function renderBookings(): void
    {
        echo '<div class="wrap"><h1>' . __('Bookings', 'booking-system') . '</h1><p>Booking management interface.</p></div>';
    }

    public function renderCustomers(): void
    {
        echo '<div class="wrap"><h1>' . __('Customers', 'booking-system') . '</h1><p>Customer management interface.</p></div>';
    }

    public function renderServices(): void
    {
        echo '<div class="wrap"><h1>' . __('Services', 'booking-system') . '</h1><p>Service management interface.</p></div>';
    }

    public function renderSettings(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bbs_settings'])) {
            update_option('bbs_settings', $_POST['bbs_settings']);
            echo '<div class="notice notice-success"><p>' . __('Settings saved.', 'booking-system') . '</p></div>';
        }

        $settings = get_option('bbs_settings', []);
        ?>
        <div class="wrap">
            <h1><?php _e('Booking System Settings', 'booking-system'); ?></h1>
            <form method="POST">
                <table class="form-table">
                    <tr>
                        <th><label for="currency"><?php _e('Currency', 'booking-system'); ?></label></th>
                        <td>
                            <select name="bbs_settings[currency]" id="currency">
                                <option value="IRR" <?php selected($settings['currency'] ?? '', 'IRR'); ?>><?php _e('IRR (Rial)', 'booking-system'); ?></option>
                                <option value="IRT" <?php selected($settings['currency'] ?? '', 'IRT'); ?>><?php _e('IRT (Toman)', 'booking-system'); ?></option>
                                <option value="USD" <?php selected($settings['currency'] ?? '', 'USD'); ?>><?php _e('USD', 'booking-system'); ?></option>
                                <option value="EUR" <?php selected($settings['currency'] ?? '', 'EUR'); ?>><?php _e('EUR', 'booking-system'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="calendar"><?php _e('Calendar', 'booking-system'); ?></label></th>
                        <td>
                            <select name="bbs_settings[calendar]" id="calendar">
                                <option value="jalali" <?php selected($settings['calendar'] ?? '', 'jalali'); ?>><?php _e('Persian (Jalali)', 'booking-system'); ?></option>
                                <option value="gregorian" <?php selected($settings['calendar'] ?? '', 'gregorian'); ?>><?php _e('Gregorian', 'booking-system'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="slot_duration"><?php _e('Default Slot Duration (min)', 'booking-system'); ?></label></th>
                        <td><input type="number" name="bbs_settings[slot_duration]" id="slot_duration" value="<?php echo (int)($settings['slot_duration'] ?? 30); ?>" min="5" max="240" step="5"></td>
                    </tr>
                    <tr>
                        <th><label for="booking_prefix"><?php _e('Booking Code Prefix', 'booking-system'); ?></label></th>
                        <td><input type="text" name="bbs_settings[booking_prefix]" id="booking_prefix" value="<?php echo esc_attr($settings['booking_prefix'] ?? 'BBS'); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <p class="submit"><button type="submit" class="button button-primary"><?php _e('Save Settings', 'booking-system'); ?></button></p>
            </form>
        </div>
        <?php
    }
}
