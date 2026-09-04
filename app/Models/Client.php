<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_name',
        'contact_email',
        'company',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Portal users belonging to this client.
     *
     * @return HasMany<User, $this>
     */
    public function portalUsers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The websites belonging to this client.
     *
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * Optional branding override for this client.
     *
     * @return MorphOne<BrandingProfile, $this>
     */
    public function branding(): MorphOne
    {
        return $this->morphOne(BrandingProfile::class, 'brandable');
    }

    /**
     * Invoices the agency has raised against this client.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * This client's link to a contact on a billing connection (FreeAgent,
     * Xero), if one has been mapped.
     *
     * @return HasOne<ClientBillingConnection, $this>
     */
    public function billingConnection(): HasOne
    {
        return $this->hasOne(ClientBillingConnection::class);
    }
}
