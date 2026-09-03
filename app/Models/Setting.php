<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'label', 'group', 'description'];

    protected static function booted(): void
    {
        $bust = fn () => Cache::forget(self::CACHE_KEY);
        static::saved($bust);
        static::deleted($bust);
    }

    public const CACHE_KEY = 'settings.all';

    /** @return array<string, mixed> */
    public static function all2(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return self::query()->get()->mapWithKeys(fn (self $s) => [
                $s->key => $s->castValue(),
            ])->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all2()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::query()->where('key', $key)->update(['value' => (string) $value]);
        Cache::forget(self::CACHE_KEY);
    }

    public function castValue(): mixed
    {
        return match ($this->type) {
            'percent', 'money' => (float) $this->value,
            'int' => (int) $this->value,
            'bool' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            default => $this->value,
        };
    }
}
