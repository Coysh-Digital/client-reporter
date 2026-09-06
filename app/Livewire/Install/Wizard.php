<?php

declare(strict_types=1);

namespace App\Livewire\Install;

use App\Enums\UserRole;
use App\Models\BrandingProfile;
use App\Models\User;
use App\Support\EnvWriter;
use App\Support\Settings;
use Database\Seeders\ReportTemplateSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * The browser installer. It runs before any account exists, so it is the one
 * screen that must refuse to work twice: once `installed` is set it aborts
 * from every entry point, including Livewire actions, not only the page route.
 * Passwords entered on earlier steps are parked in the session rather than
 * kept in component state, so they never round-trip in later responses.
 */
#[Layout('components.layouts.install')]
#[Title('Install Client Reporter')]
class Wizard extends Component
{
    public int $step = 1;

    // Database
    public string $db_connection = 'sqlite';

    public string $db_host = '127.0.0.1';

    public string $db_port = '3306';

    public string $db_database = '';

    public string $db_username = '';

    public string $db_password = '';

    public ?string $dbTestResult = null;

    public bool $dbTested = false;

    // Administrator
    public string $admin_name = '';

    public string $admin_email = '';

    public string $admin_password = '';

    public string $admin_password_confirmation = '';

    // Agency
    public string $agency_name = '';

    public string $app_url = '';

    public string $primary_color = '#33406b';

    public ?string $envNotWritable = null;

    public function mount(Settings $settings): void
    {
        $this->abortIfInstalled($settings);

        $this->app_url = (string) config('app.url');
        $this->db_port = '3306';
    }

    /**
     * @return array<int, array{label: string, ok: bool, required: bool}>
     */
    public function requirements(): array
    {
        return [
            ['label' => 'PHP 8.3 or newer ('.PHP_VERSION.')', 'ok' => version_compare(PHP_VERSION, '8.3.0', '>='), 'required' => true],
            ['label' => 'PDO extension', 'ok' => extension_loaded('pdo'), 'required' => true],
            ['label' => 'Mbstring extension', 'ok' => extension_loaded('mbstring'), 'required' => true],
            ['label' => 'OpenSSL extension', 'ok' => extension_loaded('openssl'), 'required' => true],
            ['label' => 'cURL extension', 'ok' => extension_loaded('curl'), 'required' => true],
            ['label' => 'storage/ is writable', 'ok' => is_writable(storage_path()), 'required' => true],
            ['label' => '.env is writable', 'ok' => (new EnvWriter(app()->environmentFilePath()))->isWritable(), 'required' => false],
        ];
    }

    public function requirementsMet(): bool
    {
        foreach ($this->requirements() as $check) {
            if ($check['required'] && ! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    public function testDatabase(): void
    {
        $config = $this->databaseConfig();

        try {
            Config::set('database.connections.install_test', $config);
            DB::purge('install_test');
            DB::connection('install_test')->getPdo();
            $this->dbTestResult = 'ok';
            $this->dbTested = true;
        } catch (Throwable $e) {
            $this->dbTestResult = 'Could not connect: '.$this->cleanDbError($e);
            $this->dbTested = false;
        }
    }

    public function next(): void
    {
        if ($this->step === 1 && ! $this->requirementsMet()) {
            return;
        }

        if ($this->step === 2) {
            $this->validate($this->databaseRules());

            if ($this->db_connection !== 'sqlite' && ! $this->dbTested) {
                $this->testDatabase();
                if (! $this->dbTested) {
                    return;
                }
            }

            // Park the database password server-side for the final step.
            if ($this->db_password !== '') {
                session()->put('install.db_password', $this->db_password);
                $this->db_password = '';
            }
        }

        if ($this->step === 3) {
            $this->validate([
                'admin_name' => ['required', 'string', 'max:255'],
                'admin_email' => ['required', 'email', 'max:255'],
                'admin_password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);

            session()->put('install.admin_password', $this->admin_password);
            $this->reset('admin_password', 'admin_password_confirmation');
        }

        $this->step = min(4, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function install(Settings $settings): mixed
    {
        $this->abortIfInstalled($settings);

        $this->validate(array_merge([
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'agency_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url:http,https'],
            'primary_color' => ['required', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
        ], $this->databaseRules()));

        $adminPassword = $this->adminPassword();
        Validator::make(
            ['admin_password' => $adminPassword],
            ['admin_password' => ['required', 'string', Password::defaults()]],
        )->validate();

        // 1. Persist configuration to .env (or surface copy/paste instructions).
        $env = new EnvWriter(app()->environmentFilePath());
        $written = $env->write($this->envValues());

        if (! $written) {
            $this->envNotWritable = $env->preview($this->envValues());

            return null;
        }

        // 2. When switching to a different database, point the live connection at
        //    it before migrating. When keeping the current database (e.g. the
        //    default SQLite), migrate in place.
        if ($this->db_connection !== config('database.default')) {
            Config::set('database.default', $this->db_connection);
            Config::set('database.connections.'.$this->db_connection, $this->databaseConfig());
            DB::purge($this->db_connection);
        }

        Artisan::call('migrate', ['--force' => true]);

        // 3. Seed the administrator, agency settings and global branding.
        User::query()->create([
            'name' => $this->admin_name,
            'email' => $this->admin_email,
            'password' => Hash::make($adminPassword),
            'role' => UserRole::Administrator,
            'is_active' => true,
        ]);

        BrandingProfile::query()->create([
            'agency_name' => $this->agency_name,
            'primary_color' => $this->primary_color,
        ]);

        // Seed the out-of-the-box report templates so a fresh install has ready-made
        // report layouts to build from.
        Artisan::call('db:seed', ['--class' => ReportTemplateSeeder::class, '--force' => true]);

        $settings->flush();
        $settings->setMany([
            'agency_name' => $this->agency_name,
            'installed' => true,
            'installed_at' => now()->toIso8601String(),
        ]);

        Artisan::call('optimize:clear');

        session()->forget(['install.db_password', 'install.admin_password']);

        return redirect()->route('login');
    }

    private function abortIfInstalled(Settings $settings): void
    {
        try {
            $installed = $settings->isInstalled();
        } catch (Throwable) {
            $installed = false;
        }

        abort_if($installed, 404);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function databaseRules(): array
    {
        $server = Rule::requiredIf(fn (): bool => $this->db_connection !== 'sqlite');

        return [
            'db_connection' => ['required', Rule::in(['sqlite', 'mysql', 'pgsql'])],
            'db_host' => [$server, 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-_:\[\]]+$/'],
            'db_port' => [$server, 'nullable', 'integer', 'between:1,65535'],
            'db_database' => [$server, 'nullable', 'string', 'max:255'],
            'db_username' => [$server, 'nullable', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** The password entered on the administrator step (parked in the session). */
    private function adminPassword(): string
    {
        return $this->admin_password !== '' ? $this->admin_password : (string) session('install.admin_password', '');
    }

    private function dbPassword(): string
    {
        return $this->db_password !== '' ? $this->db_password : (string) session('install.db_password', '');
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseConfig(): array
    {
        if ($this->db_connection === 'sqlite') {
            return ['driver' => 'sqlite', 'database' => database_path('database.sqlite'), 'prefix' => '', 'foreign_key_constraints' => true];
        }

        return [
            'driver' => $this->db_connection,
            'host' => $this->db_host,
            'port' => $this->db_port,
            'database' => $this->db_database,
            'username' => $this->db_username,
            'password' => $this->dbPassword(),
            'charset' => $this->db_connection === 'pgsql' ? 'utf8' : 'utf8mb4',
            'prefix' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function envValues(): array
    {
        $values = [
            'APP_URL' => $this->app_url,
            'DB_CONNECTION' => $this->db_connection,
        ];

        if ($this->db_connection !== 'sqlite') {
            $values += [
                'DB_HOST' => $this->db_host,
                'DB_PORT' => $this->db_port,
                'DB_DATABASE' => $this->db_database,
                'DB_USERNAME' => $this->db_username,
                'DB_PASSWORD' => $this->dbPassword(),
            ];
        }

        return $values;
    }

    private function cleanDbError(Throwable $e): string
    {
        // Avoid leaking credentials that may appear in a DSN.
        return preg_replace('/password=\S+/', 'password=***', $e->getMessage()) ?? 'connection failed';
    }

    public function render(): mixed
    {
        return view('livewire.install.wizard');
    }
}
