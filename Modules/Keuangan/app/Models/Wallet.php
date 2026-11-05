<?php

namespace Modules\Keuangan\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $table = 'keuangan_wallets';

    protected $fillable = [
        'user_id',
        'name',
        'icon',
        'category_label',
        'account_number',
        'initial_balance',
        'current_balance',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'wallet_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(WalletShare::class, 'wallet_id');
    }

    public function acceptedShares(): HasMany
    {
        return $this->hasMany(WalletShare::class, 'wallet_id')->where('status', 'accepted');
    }

    /**
     * Determine if given user is the wallet owner.
     */
    public function isOwner(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Determine if given user can view this wallet and its transactions.
     */
    public function canUserView(User $user): bool
    {
        if ($this->isOwner($user) || $user->hasRole('superadmin')) {
            return true;
        }

        return $this->shares()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->exists();
    }

    /**
     * Determine if given user can create transactions in this wallet.
     */
    public function canUserEdit(User $user): bool
    {
        if ($this->isOwner($user) || $user->hasRole('superadmin')) {
            return true;
        }

        return $this->shares()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->where('role', 'edit')
            ->exists();
    }

    /**
     * Backward-compatible alias for current balance.
     */
    public function getBalanceAttribute(): float
    {
        return (float) $this->current_balance;
    }
}
