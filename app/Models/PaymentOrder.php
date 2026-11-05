<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentOrder extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'user_id',
        'module',
        'duration_months',
        'gross_amount',
        'discount_applied',
        'final_amount',
        'payment_method',
        'payment_proof_path',
        'status',
        'rejection_reason',
        'midtrans_snap_token',
        'midtrans_transaction_id',
        'midtrans_payment_type',
        'paid_at',
        'verified_by',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'discount_applied' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'duration_months' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * The customer user who placed this order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The admin user who verified this order.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Generate unique order number: INV-YYYYMMDD-XXXX
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'INV-'.date('Ymd').'-';
        $random = strtoupper(substr(uniqid(), -5));

        while (self::where('order_number', $prefix.$random)->exists()) {
            $random = strtoupper(substr(uniqid(), -5));
        }

        return $prefix.$random;
    }

    /**
     * Check if order is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if order is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Human readable app name.
     */
    public function getAppNameAttribute(): string
    {
        return $this->module === 'keuangan' ? 'Sirkelta Keuangan Pro' : 'Sirkelta WaSender Pro';
    }

    /**
     * Human readable payment method label.
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            'manual_bank' => 'Transfer Bank Manual',
            'manual_qris' => 'QRIS Manual (Scan)',
            'midtrans' => 'Midtrans Snap (Otomatis)',
            default => 'Belum Dipilih',
        };
    }
}
