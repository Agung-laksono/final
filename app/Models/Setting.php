<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get setting value safely with fallback
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            if (!Schema::hasTable('settings')) {
                return $default;
            }
            return static::where('key', $key)->value('value') ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
