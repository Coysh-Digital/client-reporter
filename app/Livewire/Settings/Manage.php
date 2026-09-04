<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Support\AuditLogger;
use App\Support\Settings;
use App\Support\UpdateChecker;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Settings')]
class Manage extends Component
{
    public bool $updates_enabled = true;

    public string $pdf_driver = 'dompdf';

    public ?int $default_share_expiry_days = null;

    public int $collection_interval = 360;

    public ?int $collection_retention_days = null;

    public function mount(Settings $settings): void
    {
        $this->authorize('manage-settings');

        $this->updates_enabled = (bool) $settings->get('updates_enabled', config('client-reporter.updates.enabled', true));
        $this->pdf_driver = (string) $settings->get('pdf_driver', config('client-reporter.pdf.driver', 'dompdf'));
        $expiry = $settings->get('default_share_expiry_days', config('client-reporter.reports.default_share_expiry_days'));
        $this->default_share_expiry_days = $expiry !== null ? (int) $expiry : null;
        $this->collection_interval = (int) $settings->get('collection_interval', config('client-reporter.collection.default_interval', 360));
        $retention = $settings->get('collection_retention_days', config('client-reporter.collection.retention_days'));
        $this->collection_retention_days = $retention !== null ? (int) $retention : null;
    }

    public function save(Settings $settings, AuditLogger $audit): void
    {
        $this->authorize('manage-settings');

        $this->validate([
            'pdf_driver' => ['required', 'in:dompdf,browsershot'],
            'default_share_expiry_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'collection_interval' => ['required', 'integer', 'min:15', 'max:10080'],
            'collection_retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $settings->setMany([
            'updates_enabled' => $this->updates_enabled,
            'pdf_driver' => $this->pdf_driver,
            'default_share_expiry_days' => $this->default_share_expiry_days,
            'collection_interval' => $this->collection_interval,
            'collection_retention_days' => $this->collection_retention_days,
        ]);

        $audit->log('settings.updated');

        session()->flash('status', 'Settings saved.');
    }

    public function render(UpdateChecker $updates): mixed
    {
        return view('livewire.settings.manage', [
            'update' => $updates->status(),
            'appName' => config('client-reporter.name', 'Client Reporter'),
            'version' => config('client-reporter.version', '0.0.0'),
            'repository' => config('client-reporter.repository'),
            'installedAt' => app(Settings::class)->get('installed_at'),
        ]);
    }
}
