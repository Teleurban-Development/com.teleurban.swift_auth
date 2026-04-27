<?php

/**
 * Artisan command to create an administrator user for SwiftAuth.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Console\Commands
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://github.com/EquidnaMX/swift_auth Package repository
 */

namespace Equidna\SwiftAuth\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Hash;
use Equidna\SwiftAuth\Models\Role;
use Equidna\SwiftAuth\Models\User;

/**
 * Creates an administrator user for SwiftAuth.
 *
 * Accepts admin name and email from CLI arguments or environment variables.
 */
class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     */
    protected $signature = 'swift-auth:create-admin'
        . ' {name? : Admin name}'
        . ' {email? : Admin email}';
    /**
     * The console command description.
     *
     */
    protected $description = 'Create an administrator user with name and email arguments';

    /**
     * Executes the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        // Retrieve raw inputs (may be mixed/null) then normalize to strings.
        $rawName = $this->argument('name');
        $rawEmail = $this->argument('email');

        $userName = is_string($rawName) ? trim($rawName) : '';
        $email = is_string($rawEmail) ? trim($rawEmail) : '';

        if ($userName === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Aborting: please provide admin name and email as arguments.');
            $this->info('Usage: php artisan swift-auth:create-admin "Name" email@example.com');
            return;
        }

        // Always prompt for password (never from command line for security)
        $password = (string) $this->secret('Enter admin password (leave empty to generate random)');
        $password = trim($password);

        // Generate random password if empty
        $passwordGenerated = false;
        if ($password === '') {
            try {
                $password = bin2hex(random_bytes(16));
            } catch (\Exception $e) {
                $password = bin2hex(openssl_random_pseudo_bytes(16));
            }
            $passwordGenerated = true;
        }

        if (!$this->confirm("Do you want to create the admin user '{$userName}' with email '{$email}'?", true)) {
            $this->info('Operation cancelled.');
            return;
        }

        $this->createAdminUser($userName, $email, $password, false);

        if ($passwordGenerated) {
            $this->info('Admin user created with generated password.');
            $this->warn('Password: ' . $password);
            $this->info('Save this password securely. It will not be shown again.');
        } else {
            $this->info('Admin user created successfully.');
        }
    }

    private function createAdminUser(
        string $userName,
        string $email,
        string $textPassword,
        bool $verifyEmail = true
    ): void {
        $driver = config('swift-auth.hash_driver');
        $driver = is_string($driver) ? $driver : null;
        if ($driver) {
            /** @var Hasher $hasher */
            $hasher = Hash::driver($driver);
            $hashed = $hasher->make($textPassword);
        } else {
            $hashed = Hash::make($textPassword);
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $userName,
                'password' => $hashed,
                'email_verified_at' => $verifyEmail ? now() : null,
            ]
        );

        $role = Role::firstOrCreate(
            ['name' => 'root'],
            [
                'description' => 'System administrator',
                'actions' => ['sw-admin'], // Now stored as JSON array
            ]
        );

        $user->roles()->syncWithoutDetaching([$role->id_role]);

        logger()->info('Admin user created via CLI', [
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'role' => 'root',
        ]);
    }
}
