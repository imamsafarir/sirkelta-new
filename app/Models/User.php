<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Modules\Keuangan\Models\Transaction;
use Modules\Keuangan\Models\Wallet;
use Modules\Keuangan\Models\WalletShare;
use Modules\WaSender\Models\Contact;
use Modules\WaSender\Models\DailyUsage;
use Modules\WaSender\Models\ExtensionSession;
use Modules\WaSender\Models\MessageTemplate;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'email', 'password', 'referral_code', 'referred_by', 'last_module', 'monthly_budget', 'email_changed_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_changed_at' => 'datetime',
            'password' => 'hashed',
            'monthly_budget' => 'decimal:2',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if (! empty($user->username)) {
                $user->username = strtolower(trim((string) $user->username));
                $user->referral_code = $user->username;
            } elseif (empty($user->referral_code) && ! empty($user->email)) {
                $base = strtolower(Str::slug(explode('@', (string) $user->email)[0] ?: 'user', '_'));
                $user->username = $base;
                $user->referral_code = $base;
            }
        });
    }

    /**
     * Wallets belonging to user in Keuangan module.
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    /**
     * Shared wallets where this user is the recipient.
     */
    public function walletShares(): HasMany
    {
        return $this->hasMany(WalletShare::class, 'user_id');
    }

    /**
     * Transactions belonging to user in Keuangan module.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * User who referred this user.
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by');
    }

    /**
     * Users referred by this user.
     */
    public function referees(): HasMany
    {
        return $this->hasMany(self::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->referees();
    }

    /**
     * Referral reward logs earned by this user.
     */
    public function referralLogs(): HasMany
    {
        return $this->hasMany(ReferralLog::class, 'referrer_id');
    }

    /**
     * Calculate total unredeemed referral discount available.
     */
    public function availableReferralDiscount(): float
    {
        return (float) $this->referralLogs()
            ->where('is_redeemed', false)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'valid');
            })
            ->sum('reward_discount');
    }

    /**
     * Subscriptions belonging to this user.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    /**
     * Get active Pro subscription for a specific module.
     */
    public function getActiveProSubscription(string $module): ?UserSubscription
    {
        return $this->subscriptions()
            ->where('module', $module)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();
    }

    /**
     * Check if user is Pro for a specific module.
     */
    public function isPro(string $module): bool
    {
        if ($this->hasRole('superadmin')) {
            return true;
        }

        return $this->getActiveProSubscription($module) !== null;
    }

    /**
     * Support tickets created by this user.
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Get the list of modules this user has access to.
     *
     * @return array<string>
     */
    public function accessibleModules(): array
    {
        if ($this->hasRole('superadmin')) {
            return ['keuangan', 'wasender'];
        }

        $modules = [];

        if ($this->hasAnyRole(['admin_keuangan', 'user_keuangan'])) {
            $modules[] = 'keuangan';
        }

        if ($this->hasAnyRole(['admin_wasender', 'user_wasender'])) {
            $modules[] = 'wasender';
        }

        return $modules;
    }

    /**
     * Resolve the target dashboard route when user logs in.
     */
    public function resolveDefaultDashboardRoute(): string
    {
        $modules = $this->accessibleModules();

        if (empty($modules)) {
            if ($this->hasRole('admin_web')) {
                return route('admin.support.index');
            }
            if ($this->hasRole('admin_keuangan')) {
                return route('keuangan.admin.index');
            }

            return route('portal');
        }

        // If user only has access to exactly 1 module, route directly to its dashboard
        if (count($modules) === 1) {
            return route($modules[0].'.dashboard');
        }

        // If user has access to multiple modules, open the last accessed module
        if ($this->last_module && in_array($this->last_module, $modules, true)) {
            return route($this->last_module.'.dashboard');
        }

        // Default to the first accessible module
        return route($modules[0].'.dashboard');
    }

    /**
     * Check if the user is currently allowed to change their email (30-day cooldown).
     */
    public function canChangeEmail(): bool
    {
        if (! $this->email_changed_at) {
            return true;
        }

        return $this->email_changed_at->addDays(30)->isPast();
    }

    /**
     * Get remaining days until email can be changed again.
     */
    public function daysUntilNextEmailChange(): int
    {
        if (! $this->email_changed_at) {
            return 0;
        }

        $nextAvailableDate = $this->email_changed_at->addDays(30);
        if ($nextAvailableDate->isPast()) {
            return 0;
        }

        return max(1, (int) ceil(now()->diffInDays($nextAvailableDate, false)));
    }

    /**
     * WaSender Relationships
     */
    public function wasenderContacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'user_id');
    }

    public function wasenderMessageTemplates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class, 'user_id');
    }

    public function wasenderExtensionSessions(): HasMany
    {
        return $this->hasMany(ExtensionSession::class, 'user_id');
    }

    public function wasenderDailyUsages(): HasMany
    {
        return $this->hasMany(DailyUsage::class, 'user_id');
    }

    public function getTodayUsage(): DailyUsage
    {
        return $this->wasenderDailyUsages()->firstOrCreate(
            ['usage_date' => now()->toDateString()],
            ['messages_sent' => 0, 'files_attached' => 0]
        );
    }

    // Convenience aliases
    public function contacts(): HasMany
    {
        return $this->wasenderContacts();
    }

    public function messageTemplates(): HasMany
    {
        return $this->wasenderMessageTemplates();
    }

    public function extensionSessions(): HasMany
    {
        return $this->wasenderExtensionSessions();
    }
}
