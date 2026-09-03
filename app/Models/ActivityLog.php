<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris jejak aktivitas.
 *
 * Bersifat catatan riwayat: hanya ditulis, tidak pernah diubah. Karena itu
 * tidak ada softDeletes dan tidak ada kolom updated-by.
 */
class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id', 'user_nama', 'user_role',
        'aksi', 'modul',
        'subjek_tipe', 'subjek_id', 'subjek_label',
        'metode', 'path', 'route_name', 'status', 'berhasil',
        'payload', 'ip', 'user_agent', 'durasi_ms',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'berhasil' => 'boolean',
            'status' => 'integer',
            'durasi_ms' => 'integer',
            'subjek_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeUntukUser(Builder $q, ?int $userId): Builder
    {
        return $userId === null ? $q : $q->where('user_id', $userId);
    }

    public function scopeModul(Builder $q, ?string $modul): Builder
    {
        return blank($modul) ? $q : $q->where('modul', $modul);
    }

    public function scopePeriode(Builder $q, ?string $dari, ?string $sampai): Builder
    {
        return $q
            ->when(filled($dari), fn (Builder $b) => $b->whereDate('created_at', '>=', $dari))
            ->when(filled($sampai), fn (Builder $b) => $b->whereDate('created_at', '<=', $sampai));
    }

    public function scopeHanyaGagal(Builder $q, bool $hanyaGagal): Builder
    {
        return $hanyaGagal ? $q->where('berhasil', false) : $q;
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (blank($term)) {
            return $q;
        }

        $term = '%'.strtolower($term).'%';

        return $q->where(function (Builder $b) use ($term) {
            $b->whereRaw('LOWER(aksi) LIKE ?', [$term])
                ->orWhereRaw('LOWER(COALESCE(subjek_label, \'\')) LIKE ?', [$term])
                ->orWhereRaw('LOWER(COALESCE(user_nama, \'\')) LIKE ?', [$term]);
        });
    }
}
