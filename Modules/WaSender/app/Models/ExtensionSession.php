<?php

namespace Modules\WaSender\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExtensionSession extends Model
{
    use HasFactory;

    /**
     * Generate a cryptographically strong plain session token.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    protected $table = 'wasender_extension_sessions';

    protected $fillable = [
        'user_id',
        'session_token_hash',
        'device_name',
        'device_id',
        'ip_address',
        'user_agent',
        'status',
        'last_heartbeat_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine whether this extension session is currently valid and active.
     */
    public function isCurrentlyActive(int $heartbeatGraceMinutes = 3): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->last_heartbeat_at !== null) {
            $diffMinutes = $this->last_heartbeat_at->diffInMinutes(Carbon::now());
            if ($diffMinutes > $heartbeatGraceMinutes) {
                return false;
            }
        }

        return true;
    }

    public function revoke(): void
    {
        $this->update([
            'status' => 'revoked',
        ]);
    }

    public function touchHeartbeat(int $extendHours = 24): void
    {
        $this->update([
            'last_heartbeat_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addHours($extendHours),
            'status' => 'active',
        ]);
    }
}
