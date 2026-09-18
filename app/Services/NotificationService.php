<?php
namespace BBS\App\Services;

use BBS\Core\App;
use BBS\App\Models\Booking;
use BBS\App\Models\Customer;
use BBS\App\Models\NotificationLog;

class NotificationService
{
    private array $channels = [];
    private App $app;

    public function __construct()
    {
        $this->app = App::getInstance();
        $this->loadChannels();
    }

    private function loadChannels(): void
    {
        $modules = $this->app->config('modules', []);
        if ($this->isModuleEnabled('telegram')) {
            $this->channels[] = 'telegram';
        }
        if ($this->isModuleEnabled('whatsapp')) {
            $this->channels[] = 'whatsapp';
        }
        if ($this->isModuleEnabled('bale')) {
            $this->channels[] = 'bale';
        }
        if ($this->isModuleEnabled('rubika')) {
            $this->channels[] = 'rubika';
        }
        // SMS always available if configured
        $this->channels[] = 'sms';
    }

    public function sendBookingConfirmation(Booking $booking, Customer $customer): void
    {
        $message = $this->buildMessage('booking_confirmation', [
            'customer_name' => $customer->getFullName(),
            'booking_code' => $booking->booking_code,
            'booking_date' => $booking->booking_date,
            'booking_time' => $booking->booking_time,
            'status' => $booking->status,
            'total_price' => number_format($booking->total_price) . ' ' . $booking->currency,
        ]);

        $channels = $this->getEnabledChannels();
        $this->sendToChannels($channels, $customer, $message, $booking, 'booking_confirmation');
    }

    public function sendBookingReminder(Booking $booking, Customer $customer): void
    {
        $message = $this->buildMessage('booking_reminder', [
            'customer_name' => $customer->getFullName(),
            'booking_code' => $booking->booking_code,
            'booking_date' => $booking->booking_date,
            'booking_time' => $booking->booking_time,
        ]);

        $channels = $this->getEnabledChannels();
        $this->sendToChannels($channels, $customer, $message, $booking, 'booking_reminder');
    }

    public function sendBookingCancelled(Booking $booking, Customer $customer): void
    {
        $message = $this->buildMessage('booking_cancelled', [
            'customer_name' => $customer->getFullName(),
            'booking_code' => $booking->booking_code,
        ]);

        $channels = $this->getEnabledChannels();
        $this->sendToChannels($channels, $customer, $message, $booking, 'booking_cancelled');
    }

    public function sendPaymentConfirmation(Booking $booking, Customer $customer, float $amount): void
    {
        $message = $this->buildMessage('payment_confirmation', [
            'customer_name' => $customer->getFullName(),
            'booking_code' => $booking->booking_code,
            'amount' => number_format($amount) . ' ' . $booking->currency,
        ]);

        $channels = $this->getEnabledChannels();
        $this->sendToChannels($channels, $customer, $message, $booking, 'payment_confirmation');
    }

    public function sendPaymentLink(Booking $booking, Customer $customer, string $paymentUrl): void
    {
        $message = $this->buildMessage('payment_link', [
            'customer_name' => $customer->getFullName(),
            'booking_code' => $booking->booking_code,
            'payment_url' => $paymentUrl,
            'amount' => number_format($booking->total_price) . ' ' . $booking->currency,
        ]);

        $channels = $this->getEnabledChannels();
        $this->sendToChannels($channels, $customer, $message, $booking, 'payment_link');
    }

    public function sendWaitlistNotification(Customer $customer, array $slotInfo): void
    {
        $message = $this->buildMessage('waitlist_available', [
            'customer_name' => $customer->getFullName(),
            'date' => $slotInfo['date'] ?? '',
            'time' => $slotInfo['time'] ?? '',
            'service_name' => $slotInfo['service_name'] ?? '',
        ]);

        $channels = $this->getEnabledChannels();
        $this->sendToChannels($channels, $customer, $message, null, 'waitlist_available');
    }

    public function sendSms(string $phone, string $message, string $type = 'general'): void
    {
        if (!$this->isModuleEnabled('sms')) return;

        $provider = $this->app->config('services.sms.provider', 'ippanel');
        $apiKey = $this->app->config('services.sms.api_key', '');
        $from = $this->app->config('services.sms.from', '');

        try {
            $log = NotificationLog::create([
                'tenant_id' => 1,
                'channel' => 'sms',
                'type' => $type,
                'recipient' => $phone,
                'message' => $message,
                'status' => 'queued',
            ]);

            $result = $this->sendViaProvider($provider, $phone, $message, $from, $apiKey);

            $log->status = $result ? 'sent' : 'failed';
            $log->sent_at = date('Y-m-d H:i:s');
            $log->save();
        } catch (\Exception $e) {
            // Log failure silently
        }
    }

    protected function sendToChannels(array $channels, Customer $customer, string $message, ?Booking $booking, string $type): void
    {
        foreach ($channels as $channel) {
            $recipient = match ($channel) {
                'sms' => $customer->phone,
                'telegram' => $customer->phone, // Would be chat ID in production
                'whatsapp' => $customer->phone,
                'email' => $customer->email,
                default => $customer->phone,
            };

            if (empty($recipient)) continue;

            try {
                $log = NotificationLog::create([
                    'tenant_id' => $booking ? $booking->tenant_id : 1,
                    'customer_id' => $customer->id,
                    'booking_id' => $booking ? $booking->id : null,
                    'channel' => $channel,
                    'type' => $type,
                    'recipient' => $recipient,
                    'message' => $message,
                    'status' => 'queued',
                ]);

                $sent = $this->sendViaChannel($channel, $recipient, $message);
                $log->status = $sent ? 'sent' : 'failed';
                $log->sent_at = date('Y-m-d H:i:s');
                $log->save();
            } catch (\Exception $e) {
                // Log failure, don't throw
            }
        }
    }

    private function sendViaChannel(string $channel, string $recipient, string $message): bool
    {
        return match ($channel) {
            'sms' => $this->sendViaSms($recipient, $message),
            'telegram' => $this->sendViaTelegram($recipient, $message),
            'whatsapp' => $this->sendViaWhatsApp($recipient, $message),
            'bale' => $this->sendViaBale($recipient, $message),
            'rubika' => $this->sendViaRubika($recipient, $message),
            default => false,
        };
    }

    private function sendViaSms(string $phone, string $message): bool
    {
        $provider = $this->app->config('services.sms.provider', 'ippanel');
        $apiKey = $this->app->config('services.sms.api_key', '');
        $from = $this->app->config('services.sms.from', '');
        return $this->sendViaProvider($provider, $phone, $message, $from, $apiKey);
    }

    private function sendViaProvider(string $provider, string $phone, string $message, string $from, string $apiKey): bool
    {
        try {
            return match ($provider) {
                'ippanel' => $this->ippanelSend($phone, $message, $apiKey),
                'kavenegar' => $this->kavenegarSend($phone, $message, $apiKey),
                'melipayamak' => $this->melipayamakSend($phone, $message, $apiKey),
                default => false,
            };
        } catch (\Exception $e) {
            return false;
        }
    }

    private function ippanelSend(string $phone, string $message, string $apiKey): bool
    {
        $url = 'https://api.ippanel.com/v1/messages';
        $data = json_encode([
            'originator' => $this->app->config('services.sms.from', ''),
            'recipients' => [$phone],
            'message' => $message,
        ]);
        return $this->httpPost($url, $data, ["Authorization: AccessKey {$apiKey}"]);
    }

    private function kavenegarSend(string $phone, string $message, string $apiKey): bool
    {
        $url = "https://api.kavenegar.com/v1/{$apiKey}/sms/send.json";
        $data = [
            'receptor' => $phone,
            'message' => $message,
            'sender' => $this->app->config('services.sms.from', ''),
        ];
        return $this->httpPost($url, http_build_query($data), [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
    }

    private function melipayamakSend(string $phone, string $message, string $apiKey): bool
    {
        $url = 'https://api.melipayamak.com/v1/sms/send';
        $data = json_encode([
            'username' => $this->app->config('services.sms.username', ''),
            'password' => $this->app->config('services.sms.password', ''),
            'from' => $this->app->config('services.sms.from', ''),
            'to' => $phone,
            'text' => $message,
        ]);
        return $this->httpPost($url, $data, ['Content-Type: application/json']);
    }

    private function sendViaTelegram(string $recipient, string $message): bool
    {
        $botToken = $this->app->config('services.telegram.bot_token', '');
        if (empty($botToken)) return false;

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $data = json_encode([
            'chat_id' => $recipient,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);
        return $this->httpPost($url, $data, ['Content-Type: application/json']);
    }

    private function sendViaWhatsApp(string $recipient, string $message): bool
    {
        $apiKey = $this->app->config('services.whatsapp.api_key', '');
        if (empty($apiKey)) return false;

        $url = 'https://api.whatsapp.com/v1/messages';
        $data = json_encode([
            'to' => $recipient,
            'type' => 'text',
            'text' => ['body' => $message],
        ]);
        return $this->httpPost($url, $data, [
            'Content-Type: application/json',
            "Authorization: Bearer {$apiKey}",
        ]);
    }

    private function sendViaBale(string $recipient, string $message): bool
    {
        $botToken = $this->app->config('services.bale.bot_token', '');
        if (empty($botToken)) return false;

        $url = "https://api.bale.ai/bot{$botToken}/sendMessage";
        $data = json_encode([
            'chat_id' => $recipient,
            'text' => $message,
        ]);
        return $this->httpPost($url, $data, ['Content-Type: application/json']);
    }

    private function sendViaRubika(string $recipient, string $message): bool
    {
        // Rubika API integration placeholder
        return false;
    }

    private function buildMessage(string $type, array $params): string
    {
        $branding = $this->getBrandingFooter();
        $templates = $this->getDefaultTemplates();

        $template = $templates[$type] ?? '';
        $message = $template;

        foreach ($params as $key => $value) {
            $message = str_replace('{' . $key . '}', $value, $message);
        }

        return $message . "\n\n---\n" . $branding;
    }

    private function getDefaultTemplates(): array
    {
        return [
            'booking_confirmation' =>
                "✅ {customer_name} عزیز، رزرو شما با موفقیت ثبت شد.\n" .
                "کد پیگیری: {booking_code}\n" .
                "تاریخ: {booking_date}\n" .
                "ساعت: {booking_time}\n" .
                "وضعیت: {status}\n" .
                "مبلغ: {total_price}",

            'booking_reminder' =>
                "🔔 یادآوری نوبت\n" .
                "{customer_name} عزیز، فردا نوبت شما راس ساعت {booking_time} می‌باشد.\n" .
                "کد پیگیری: {booking_code}\n" .
                "تاریخ: {booking_date}",

            'booking_cancelled' =>
                "❌ {customer_name} عزیز، نوبت شما با کد {booking_code} لغو شد.",

            'payment_confirmation' =>
                "✅ {customer_name} عزیز، پرداخت شما به مبلغ {amount} با موفقیت انجام شد.\n" .
                "کد پیگیری: {booking_code}",

            'payment_link' =>
                "🔗 {customer_name} عزیز، لینک پرداخت شما:\n" .
                "{payment_url}\n" .
                "مبلغ: {amount}\n" .
                "کد پیگیری: {booking_code}",

            'waitlist_available' =>
                "🎉 {customer_name} عزیز، زمان خالی شد!\n" .
                "تاریخ: {date}\n" .
                "ساعت: {time}\n" .
                "خدمت: {service_name}\n" .
                "همین حالا رزرو کنید.",
        ];
    }

    private function getBrandingFooter(): string
    {
        $name = $this->app->config('app.name', '');
        $phone = $this->app->config('branding.phone', '');
        $website = $this->app->config('branding.website', '');
        $footer = $name;
        if ($phone) $footer .= " | تلفن: {$phone}";
        if ($website) $footer .= " | {$website}";
        return $footer;
    }

    private function httpPost(string $url, string $data, array $headers = []): bool
    {
        if (!function_exists('curl_init')) return false;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    private function isModuleEnabled(string $module): bool
    {
        $modules = $this->app->config('modules', []);
        return isset($modules[$module]['enabled']) && $modules[$module]['enabled'];
    }

    private function getEnabledChannels(): array
    {
        return $this->channels;
    }
}
