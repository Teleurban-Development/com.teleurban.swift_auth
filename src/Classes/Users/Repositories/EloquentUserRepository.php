<?php

/**
 * Eloquent implementation of user repository.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Repositories
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://github.com/EquidnaMX/swift_auth
 */

namespace Equidna\SwiftAuth\Classes\Users\Repositories;

use Carbon\CarbonInterval;
use Equidna\SwiftAuth\Classes\Users\Contracts\UserRepositoryInterface;
use Equidna\SwiftAuth\Models\User;

/**
 * Provides Eloquent-backed user persistence operations.
 */
final class EloquentUserRepository implements UserRepositoryInterface
{
    /**
     * Finds a user by their primary key.
     *
     * @param  int|string $id  User primary key.
     * @return User|null       User instance or null if not found.
     */
    public function findById(int|string $id): ?User
    {
        /** @var User|null $user */
        $user = User::find($id);
        return $user;
    }

    /**
     * Finds a user by their email address.
     *
     * @param  string    $email  Email address (case-insensitive).
     * @return User|null         User instance or null if not found.
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', strtolower(trim($email)))->first();
    }

    /**
     * Increments the failed login attempt counter for a user.
     *
     * Updates failed_login_attempts and last_failed_login_at timestamp.
     *
     * @param  User $user  User instance to update.
     * @return void
     */
    public function incrementFailedLogins(User $user): void
    {
        $user->failed_login_attempts++;
        $user->last_failed_login_at = now();
        $user->save();
    }

    /**
     * Resets failed login attempts and clears lockout state.
     *
     * Sets failed_login_attempts to 0, clears locked_until and last_failed_login_at.
     *
     * @param  User $user  User instance to reset.
     * @return void
     */
    public function resetFailedLogins(User $user): void
    {
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->last_failed_login_at = null;
        $user->save();
    }

    /**
     * Locks a user account for a specified duration.
     *
     * Sets locked_until timestamp and saves the user.
     *
     * @param  User $user     User instance to lock.
     * @param  int  $seconds  Lockout duration in seconds.
     * @return void
     */
    public function lockAccount(
        User $user,
        int $seconds,
    ): void {
        $user->locked_until = now()->add(CarbonInterval::seconds($seconds));
        $user->save();
    }
}
