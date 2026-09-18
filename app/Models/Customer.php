<?php
namespace BBS\App\Models;

use BBS\Core\Model;

class Customer extends Model
{
    protected static string $table = 'customers';
    protected static array $fillable = [
        'tenant_id', 'uuid', 'first_name', 'last_name', 'phone', 'email',
        'national_code', 'gender', 'birth_date', 'avatar', 'notes',
        'referral_code', 'referred_by', 'total_bookings', 'total_spent',
        'wallet_balance', 'rating_avg', 'rating_count', 'tags', 'is_active',
        'last_visit_at',
    ];
    protected static array $casts = [
        'tags' => 'json',
    ];

    public function bookings(): array
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    public function walletTransactions(): array
    {
        return $this->hasMany(WalletTransaction::class, 'customer_id');
    }

    public function getFullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public static function findByPhone(string $phone, int $tenantId): ?self
    {
        return self::where('phone', '=', $phone)
            ->where('tenant_id', '=', $tenantId)
            ->first();
    }

    public static function findByPhoneOrCreate(string $phone, int $tenantId, array $data = []): self
    {
        $customer = self::findByPhone($phone, $tenantId);
        if ($customer) {
            if (!empty($data)) {
                foreach ($data as $key => $value) {
                    $customer->$key = $value;
                }
                $customer->save();
            }
            return $customer;
        }
        $data['phone'] = $phone;
        $data['tenant_id'] = $tenantId;
        $data['referral_code'] = self::generateReferralCode();
        return self::create($data);
    }

    public static function generateReferralCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $exists = self::findBy('referral_code', $code);
        if ($exists) return self::generateReferralCode();
        return $code;
    }

    public function addWalletBalance(float $amount, string $type = 'deposit', ?string $description = null): WalletTransaction
    {
        $balanceBefore = $this->wallet_balance ?? 0;
        $balanceAfter = $balanceBefore + $amount;

        $tx = WalletTransaction::create([
            'tenant_id' => $this->tenant_id,
            'customer_id' => $this->id,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'currency' => 'IRR',
            'description' => $description,
            'status' => 'completed',
        ]);

        $this->wallet_balance = $balanceAfter;
        $this->save();

        return $tx;
    }

    public function deductWalletBalance(float $amount, string $type = 'payment', ?string $description = null): ?WalletTransaction
    {
        if (($this->wallet_balance ?? 0) < $amount) return null;

        $balanceBefore = $this->wallet_balance;
        $balanceAfter = $balanceBefore - $amount;

        $tx = WalletTransaction::create([
            'tenant_id' => $this->tenant_id,
            'customer_id' => $this->id,
            'type' => $type,
            'amount' => -$amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'currency' => 'IRR',
            'description' => $description,
            'status' => 'completed',
        ]);

        $this->wallet_balance = $balanceAfter;
        $this->save();

        return $tx;
    }
}
