<?php

namespace Modules\WaSender\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyUsage extends Model
{
    use HasFactory;

    protected $table = 'wasender_daily_usages';

    protected $fillable = [
        'user_id',
        'usage_date',
        'messages_sent',
        'files_attached',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'string',
            'messages_sent' => 'integer',
            'files_attached' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
