<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Branding\BrandingResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * White-label branding. A profile with a null brandable is the global agency
 * branding; a profile attached to a Client or Site overrides it for that scope.
 * Values are resolved down the chain by {@see BrandingResolver}.
 */
class BrandingProfile extends Model
{
    protected $fillable = [
        'agency_name',
        'tagline',
        'logo_path',
        'favicon_path',
        'primary_color',
        'secondary_color',
        'website',
        'email',
        'phone',
        'address',
        'report_footer',
        'email_footer',
        'report_cover_style',
        'heading_font',
        'body_font',
        'custom_css',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function brandable(): MorphTo
    {
        return $this->morphTo();
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function faviconUrl(): ?string
    {
        return $this->favicon_path ? Storage::disk('public')->url($this->favicon_path) : null;
    }
}
