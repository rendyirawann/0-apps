<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active', 'phone', 'jabatan', 'alamat',
    ];

    protected $hidden = [
        'password', 'remember_token', 'biometric_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'biometric_enrolled_at' => 'datetime',
            'biometric_expires_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function kegiatan(): HasMany
    {
        return $this->hasMany(Kegiatan::class, 'created_by');
    }

    // ------------------------------------------------------------------
    // Peran
    // ------------------------------------------------------------------

    public const SUPERADMIN = 'superadmin';

    public const PETUGAS = 'petugas';

    /** @return array<int, string> */
    public static function peranTersedia(): array
    {
        return [self::SUPERADMIN, self::PETUGAS];
    }

    public function isSuperadmin(): bool
    {
        return $this->role === self::SUPERADMIN;
    }

    public function isPetugas(): bool
    {
        return $this->role === self::PETUGAS;
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::SUPERADMIN => 'Super Admin',
            self::PETUGAS => 'Petugas',
            default => ucfirst($this->role),
        };
    }

    // ------------------------------------------------------------------
    // Biometric / fingerprint
    // ------------------------------------------------------------------

    /**
     * Terbitkan token biometrik baru. Nilai mentahnya HANYA dikembalikan di
     * sini (disimpan di Android Keystore oleh aplikasi); yang tersimpan di
     * database cuma hash SHA-256-nya, jadi bocornya tabel users tidak cukup
     * untuk login.
     */
    public function issueBiometricToken(?string $deviceName = null): string
    {
        $plain = Str::random(64);

        $this->forceFill([
            'biometric_token_hash' => hash('sha256', $plain),
            'biometric_enrolled_at' => now(),
            'biometric_expires_at' => now()->addDays((int) config('taksasi.biometric_ttl_days', 90)),
            'biometric_device_name' => $deviceName,
        ])->save();

        return $plain;
    }

    public function revokeBiometricToken(): void
    {
        $this->forceFill([
            'biometric_token_hash' => null,
            'biometric_enrolled_at' => null,
            'biometric_expires_at' => null,
            'biometric_device_name' => null,
        ])->save();
    }

    public function biometricTokenIsValid(string $plain): bool
    {
        if ($this->biometric_token_hash === null) {
            return false;
        }

        if ($this->biometric_expires_at instanceof Carbon && $this->biometric_expires_at->isPast()) {
            return false;
        }

        return hash_equals($this->biometric_token_hash, hash('sha256', $plain));
    }

    public function hasBiometricEnrolled(): bool
    {
        return $this->biometric_token_hash !== null
            && ! ($this->biometric_expires_at instanceof Carbon && $this->biometric_expires_at->isPast());
    }

    public function checkPassword(string $plain): bool
    {
        return Hash::check($plain, $this->password);
    }
}
