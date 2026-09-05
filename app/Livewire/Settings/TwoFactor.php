<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Support\AuditLogger;
use App\Support\QrCode;
use App\Support\Settings;
use App\Support\TwoFactor as Totp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Lets a signed-in user turn on two-factor authentication for their own
 * account: scan/enter a TOTP secret, confirm a code, and save one-time recovery
 * codes. Disabling requires the account password.
 */
#[Layout('components.layouts.app')]
#[Title('Two-factor authentication')]
class TwoFactor extends Component
{
    public bool $enabled = false;

    /** Setup in progress: the pending secret, shown for entry until confirmed. */
    public string $secret = '';

    public string $otpauthUri = '';

    public string $confirmCode = '';

    /** Freshly generated recovery codes, shown once after enabling/regenerating. */
    /** @var array<int, string> */
    public array $recoveryCodes = [];

    public string $password = '';

    public function mount(): void
    {
        $this->enabled = (bool) Auth::user()?->hasTwoFactorEnabled();
    }

    /**
     * Begin setup: generate a secret to display (not saved until confirmed).
     */
    public function enable(Settings $settings): void
    {
        if ($this->enabled) {
            return;
        }

        $this->secret = Totp::generateSecret();
        $this->otpauthUri = Totp::otpauthUri(
            $this->secret,
            (string) Auth::user()?->email,
            $settings->get('agency_name', config('client-reporter.name', 'Client Reporter')),
        );
        $this->confirmCode = '';
        $this->recoveryCodes = [];
    }

    public function cancelSetup(): void
    {
        $this->reset(['secret', 'otpauthUri', 'confirmCode']);
    }

    /**
     * The setup QR code as an inline SVG (empty until setup begins).
     */
    public function qrSvg(): string
    {
        return $this->otpauthUri !== '' ? QrCode::svg($this->otpauthUri, 190) : '';
    }

    /**
     * Confirm setup by verifying a code, then persist the secret and issue
     * recovery codes (shown once).
     */
    public function confirm(AuditLogger $audit): void
    {
        if ($this->secret === '') {
            return;
        }

        if (! Totp::verify($this->secret, $this->confirmCode)) {
            throw ValidationException::withMessages(['confirmCode' => 'That code is invalid or has expired. Try the current code.']);
        }

        $codes = Totp::generateRecoveryCodes();

        $user = Auth::user();
        $user?->forceFill([
            'two_factor_secret' => $this->secret,
            'two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $codes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $audit->log('settings.two_factor.enabled', $user);

        $this->enabled = true;
        $this->recoveryCodes = $codes;
        $this->reset(['secret', 'otpauthUri', 'confirmCode']);
    }

    /**
     * Replace the recovery codes with a fresh set (shown once).
     */
    public function regenerateRecoveryCodes(AuditLogger $audit): void
    {
        if (! $this->enabled) {
            return;
        }

        $codes = Totp::generateRecoveryCodes();
        Auth::user()?->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $codes),
        ])->save();

        $audit->log('settings.two_factor.recovery_regenerated', Auth::user());
        $this->recoveryCodes = $codes;
    }

    /**
     * Turn 2FA off, after confirming the account password.
     */
    public function disable(AuditLogger $audit): void
    {
        $user = Auth::user();

        if ($user === null || ! Hash::check($this->password, (string) $user->password)) {
            throw ValidationException::withMessages(['password' => 'That password is incorrect.']);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $audit->log('settings.two_factor.disabled', $user);

        $this->enabled = false;
        $this->reset(['secret', 'otpauthUri', 'confirmCode', 'recoveryCodes', 'password']);
    }

    public function render(): mixed
    {
        return view('livewire.settings.two-factor');
    }
}
