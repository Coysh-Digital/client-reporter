<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Staff and client roles for a Client Reporter installation.
 *
 * Roles are intentionally coarse. Fine-grained authorisation is expressed
 * through Laravel policies/gates (see AuthServiceProvider), so additional
 * roles can be introduced later without reworking call sites.
 */
enum UserRole: string
{
    case Administrator = 'administrator';
    case Manager = 'manager';
    case Viewer = 'viewer';
    case Client = 'client';

    /**
     * Human-friendly label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Manager => 'Manager',
            self::Viewer => 'Viewer',
            self::Client => 'Client',
        };
    }

    /**
     * Short description shown when assigning roles.
     */
    public function description(): string
    {
        return match ($this) {
            self::Administrator => 'Full access, including users, branding, integrations and configuration.',
            self::Manager => 'Manage clients, sites and integrations; create, edit and send reports.',
            self::Viewer => 'View clients, sites, data and reports. No editing.',
            self::Client => 'Restricted portal access to their own sites and reports only.',
        };
    }

    /**
     * Whether this is an internal agency staff role (as opposed to a client).
     */
    public function isStaff(): bool
    {
        return $this !== self::Client;
    }

    /**
     * Staff roles selectable when creating/editing agency users.
     *
     * @return array<int, self>
     */
    public static function staffRoles(): array
    {
        return [self::Administrator, self::Manager, self::Viewer];
    }
}
