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
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

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

    public function mount(): void
    {
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
            if ($this->db_connection !== 'sqlite' && ! $this->dbTested) {
                $this->testDatabase();
                if (! $this->dbTested) {
                    return;
                }
            }
        }

        if ($this->step === 3) {
            $this->validate([
                'admin_name' => ['required', 'string', 'max:255'],
                'admin_email' => ['required', 'email', 'max:255'],
                'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);
        }

        $this->step = min(4, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function install(Settings $settings): mixed
    {
        $this->validate([
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8'],
            'agency_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url'],
            'primary_color' => ['required', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'db_connection' => [Rule::in(['sqlite', 'mysql', 'pgsql'])],
        ]);

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
            'password' => Hash::make($this->admin_password),
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

        return redirect()->route('login');
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
            'password' => $this->db_password,
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
                'DB_PASSWORD' => $this->db_password,
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
