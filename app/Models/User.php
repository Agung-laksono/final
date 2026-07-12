<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'avatar', 'brand_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Get the user's avatar URL
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar ? Storage::url($this->avatar) : null;
    }

    /**
     * Get users with specific permission or Super Admin role.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array $permissions
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithPermissionOrSuperAdmin($query, $permissions)
    {
        return $query->where(function ($q) use ($permissions) {
            $q->permission($permissions)
              ->orWhereHas('roles', function ($roleQuery) {
                  $roleQuery->where('name', 'Super Admin');
              });
        });
    }

    /**
     * Gudang dimana staf ini ditugaskan
     */
    public function warehouses()
    {
        return $this->belongsToMany(\Modules\Inventory\Models\Warehouse::class, 'user_warehouse')->withTimestamps();
    }

    /**
     * Brand yang dipegang oleh staf ini
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}
