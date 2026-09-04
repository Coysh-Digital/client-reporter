<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property UserRole $role
 * @property int|null $client_id
 * @property bool $is_active
 */
#[Fillable(['name', 'email', 'password', 'role', 'client_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * The client a portal user belongs to (null for agency staff).
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isAdministrator(): bool
    {
        return $this->role === UserRole::Administrator;
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }

    /**
     * Whether the user holds at least the given staff role, using the
     * Administrator > Manager > Viewer hierarchy. Client users never satisfy
     * a staff-role requirement.
     */
    public function hasAtLeastRole(UserRole $role): bool
    {
        $ranks = [
            UserRole::Viewer->value => 1,
            UserRole::Manager->value => 2,
            UserRole::Administrator->value => 3,
        ];

        $current = $ranks[$this->role->value] ?? 0;
        $required = $ranks[$role->value] ?? 0;

        return $current > 0 && $current >= $required;
    }
}
