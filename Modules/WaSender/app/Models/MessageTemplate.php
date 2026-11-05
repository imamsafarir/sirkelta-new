<?php

namespace Modules\WaSender\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplate extends Model
{
    use HasFactory;

    protected $table = 'wasender_message_templates';

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'category',
        'has_random_code',
    ];

    protected function casts(): array
    {
        return [
            'has_random_code' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a text or this template contains spintax / random options pattern like [a/b/c] or {a|b|c}.
     */
    public function containsSpintax(?string $text = null): bool
    {
        $target = $text ?? (string) $this->content;

        return (bool) preg_match('/(\[[^\]]+[\/|][^\]]+\]|\{(?!nama\}|nomor\}|phone\}|kode_acak\})[^\}]+[\/|][^\}]+\})/', $target);
    }

    /**
     * Parse and randomize spintax patterns like [selamat pagi/pagi/assalamualaikum/halo].
     */
    public static function processSpintax(string $text): string
    {
        $text = preg_replace_callback('/\[([^\]]+)\]/', function ($matches) {
            $inner = $matches[1];
            if (str_contains($inner, '/') || str_contains($inner, '|')) {
                $delimiter = str_contains($inner, '/') ? '/' : '|';
                $choices = array_map('trim', explode($delimiter, $inner));
                $choices = array_filter($choices, fn ($c) => $c !== '');
                if (! empty($choices)) {
                    $keys = array_keys($choices);

                    return $choices[$keys[array_rand($keys)]];
                }
            }

            return $matches[0];
        }, $text);

        return $text;
    }

    /**
     * Render template for a recipient, resolving variables and appending random code if requested.
     */
    public function renderFor(string $name, ?string $phone = null): string
    {
        $rendered = str_replace(
            ['{nama}', '{phone}', '{nomor}'],
            [$name, $phone ?? '', $phone ?? ''],
            $this->content
        );

        $rendered = self::processSpintax($rendered);

        $randomCode = self::generateRandomCode(5);

        if (str_contains($rendered, '{kode_acak}')) {
            $rendered = str_replace('{kode_acak}', '#'.$randomCode, $rendered);
        } elseif ($this->has_random_code) {
            $rendered .= "\n\n#".$randomCode;
        }

        return $rendered;
    }

    /**
     * Generate a unique random code for message anti-ban.
     */
    public static function generateRandomCode(int $length = 5): string
    {
        $pool = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        $max = strlen($pool) - 1;
        for ($i = 0; $i < $length; $i++) {
            $code .= $pool[random_int(0, $max)];
        }

        return $code;
    }
}
