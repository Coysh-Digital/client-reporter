<?php

declare(strict_types=1);

namespace App\Livewire\Branding;

use App\Models\BrandingProfile;
use App\Models\Client;
use App\Models\Site;
use App\Support\AuditLogger;
use App\Support\Branding\BrandingResolver;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Branding')]
class Manage extends Component
{
    use WithFileUploads;

    public string $scope = 'global';

    public ?Client $client = null;

    public ?Site $site = null;

    public BrandingProfile $profile;

    // Editable fields
    public string $agency_name = '';

    public string $tagline = '';

    public string $primary_color = '';

    public string $secondary_color = '';

    public string $website = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $report_footer = '';

    public string $email_footer = '';

    public string $report_cover_style = 'standard';

    public string $heading_font = '';

    public string $body_font = '';

    public string $custom_css = '';

    public $logo = null;

    public $favicon = null;

    public function mount(?Client $client = null, ?Site $site = null): void
    {
        if ($site?->exists) {
            $this->scope = 'site';
            $this->site = $site->load('client');
            $this->authorize('manage-sites');
            $this->profile = $site->branding()->firstOrNew([]);
        } elseif ($client?->exists) {
            $this->scope = 'client';
            $this->client = $client;
            $this->authorize('manage-clients');
            $this->profile = $client->branding()->firstOrNew([]);
        } else {
            $this->scope = 'global';
            $this->authorize('manage-branding');
            $this->profile = app(BrandingResolver::class)->global();
        }

        $this->fillFromProfile();
    }

    private function fillFromProfile(): void
    {
        foreach ([
            'agency_name', 'tagline', 'primary_color', 'secondary_color', 'website',
            'email', 'phone', 'address', 'report_footer', 'email_footer',
            'heading_font', 'body_font', 'custom_css',
        ] as $field) {
            $this->{$field} = (string) $this->profile->{$field};
        }

        $this->report_cover_style = $this->profile->report_cover_style ?: 'standard';
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorizeScope();

        $validated = $this->validate([
            'agency_name' => ['nullable', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'secondary_color' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'report_footer' => ['nullable', 'string', 'max:2000'],
            'email_footer' => ['nullable', 'string', 'max:2000'],
            'report_cover_style' => ['required', 'in:minimal,standard,bold'],
            'heading_font' => ['nullable', 'string', 'max:255'],
            'body_font' => ['nullable', 'string', 'max:255'],
            'custom_css' => ['nullable', 'string', 'max:20000'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:512'],
        ]);

        foreach ($validated as $field => $value) {
            if (in_array($field, ['logo', 'favicon'], true)) {
                continue;
            }
            $this->profile->{$field} = $value !== '' ? $value : null;
        }

        if ($this->logo) {
            $this->profile->logo_path = $this->logo->store('branding', 'public');
        }

        if ($this->favicon) {
            $this->profile->favicon_path = $this->favicon->store('branding', 'public');
        }

        // Attach to the correct owner for a fresh override profile.
        if (! $this->profile->exists && $this->scope !== 'global') {
            $owner = $this->scope === 'site' ? $this->site : $this->client;
            $this->profile->brandable()->associate($owner);
        }

        $this->profile->save();
        $this->logo = null;
        $this->favicon = null;

        $audit->log('branding.updated', $this->profile, metadata: ['scope' => $this->scope]);
        session()->flash('status', 'Branding saved.');
    }

    public function removeLogo(): void
    {
        $this->authorizeScope();
        $this->profile->update(['logo_path' => null]);
    }

    public function removeFavicon(): void
    {
        $this->authorizeScope();
        $this->profile->update(['favicon_path' => null]);
    }

    private function authorizeScope(): void
    {
        $this->authorize(match ($this->scope) {
            'site' => 'manage-sites',
            'client' => 'manage-clients',
            default => 'manage-branding',
        });
    }

    public function render(): mixed
    {
        return view('livewire.branding.manage');
    }
}
