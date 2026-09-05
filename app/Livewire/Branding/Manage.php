<?php

declare(strict_types=1);

namespace App\Livewire\Branding;

use App\Models\BrandingProfile;
use App\Models\Client;
use App\Models\Site;
use App\Rules\SafeCss;
use App\Support\AuditLogger;
use App\Support\Branding\BrandingResolver;
use App\Support\GoogleFonts;
use Closure;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Branding')]
class Manage extends Component
{
    use WithFileUploads;

    /** Which profile is being edited; fixed at mount so the gate cannot be swapped from the browser. */
    #[Locked]
    public string $scope = 'global';

    #[Locked]
    public ?Client $client = null;

    #[Locked]
    public ?Site $site = null;

    #[Locked]
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
            'heading_font' => ['nullable', 'string', 'max:255', $this->knownFont()],
            'body_font' => ['nullable', 'string', 'max:255', $this->knownFont()],
            'custom_css' => ['nullable', 'string', 'max:20000', new SafeCss],
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:512'],
        ]);

        foreach ($validated as $field => $value) {
            if (in_array($field, ['logo', 'favicon'], true)) {
                continue;
            }

            // Fonts are stored as the canonical stack for the chosen catalogue
            // family, never as the raw string the browser sent.
            if (in_array($field, ['heading_font', 'body_font'], true)) {
                $family = GoogleFonts::extractFamily(is_string($value) ? $value : null);
                $value = $family !== null ? GoogleFonts::cssStack($family) : '';
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

    /**
     * The gate follows the profile actually being edited (its owner type), not
     * a request value, so a client/site editor can never reach the global profile.
     */
    private function authorizeScope(): void
    {
        $owner = $this->profile->exists ? $this->profile->brandable_type : null;

        $ability = match (true) {
            $owner === (new Site)->getMorphClass(), $this->scope === 'site' && $owner === null => 'manage-sites',
            $owner === (new Client)->getMorphClass(), $this->scope === 'client' && $owner === null => 'manage-clients',
            default => 'manage-branding',
        };

        $this->authorize($ability);
    }

    /**
     * Only a family from the curated catalogue may be stored: the value ends
     * up inside a <style> block on every client-facing report.
     */
    private function knownFont(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && trim($value) !== '' && GoogleFonts::extractFamily($value) === null) {
                $fail('Choose a font from the list.');
            }
        };
    }

    public function render(): mixed
    {
        return view('livewire.branding.manage');
    }
}
