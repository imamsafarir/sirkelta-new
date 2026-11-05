<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'user_id',
        'guest_name',
        'guest_email',
        'guest_token',
        'subject',
        'status',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'support_ticket_id');
    }

    /**
     * Mark ticket as resolved, purge all chat messages cleanly, and increment resolved complaints counter.
     */
    public function resolveAndCleanChat(): void
    {
        // 1. Delete all chat messages cleanly
        $this->messages()->delete();

        // 2. Mark ticket as resolved
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        // 3. Increment the lifetime resolved complaints counter
        WebsiteSetting::incrementResolvedComplaints();
    }
}
