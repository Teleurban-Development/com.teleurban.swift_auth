<?php

/**
 * Defines the SwiftAuth user model and related helpers.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Models
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://github.com/EquidnaMX/swift_auth
 */

namespace Equidna\SwiftAuth\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;
use Laravel\Sanctum\HasApiTokens;

/**
 * Represents an authenticated SwiftAuth user record.
 *
 * @property string $email
 * @property string $password
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Equidna\SwiftAuth\Models\Role> $roles
 *
 * @method static Builder<\Equidna\SwiftAuth\Models\User> where(string $column, mixed $value = null)
 * @method static Builder<\Equidna\SwiftAuth\Models\User> whereNotNull(string $column)
 * @method static Builder<\Equidna\SwiftAuth\Models\User> search(null|string $term)
 * @method static static create(array<string,mixed> $attributes = [])
 * @method static static find(string|int $id)
 * @method static static findOrFail(string|int $id)
 * @method static static firstOrCreate(array<string,mixed> $attributes, array<string,mixed> $values = [])
 */
class User extends Authenticatable implements WebAuthnAuthenticatable
{
    use WebAuthnAuthentication;
    use HasApiTokens;

    protected $table;
    protected $primaryKey = 'id_user';

    /**
     * Cached available actions to avoid repeated parsing.
     *
    @@    use WebAuthnAuthentication;
    private ?array $cachedActions = null;

    /**
     * Initialize the model.
     *
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = $this->tablePrefix() . 'Users';
    }

    protected function tablePrefix(): string
    {
        try {
            return (string) config('swift-auth.table_prefix', '');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @var array<int, string>
     */
    protected $with = ['roles'];
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'locked_until' => 'datetime',
        'last_failed_login_at' => 'datetime',
    ];

    /**
     * The roles associated with the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<
     *     \Equidna\SwiftAuth\Models\Role,
     *     $this
     * >
     */
    public function roles(): BelongsToMany
    {
        $prefix = (string) config('swift-auth.table_prefix', '');
        return $this->belongsToMany(
            Role::class,
            $prefix . 'UsersRoles',
            'id_user',
            'id_role'
        );
    }

    /**
     * Checks if the user has any of the given roles (by name).
     *
     * @param  string|array<string> $roles  List of role names to check.
     * @return bool                          True if the user has at least one of the roles.
     */
    public function hasRoles(string|array $roles): bool
    {
        $rolesToCheck = collect((array) $roles)->map(fn($r) => strtolower($r));

        return $this->roles
            ->pluck('name')
            ->map(fn($name) => strtolower($name))
            ->intersect($rolesToCheck)
            ->isNotEmpty();
    }

    public function hasRole(string|array $roles): bool
    {
        return $this->hasRoles($roles);
    }

    /**
     * Returns the list of available actions from all assigned roles.
     *
     * @return array<int, string>  Unique list of actions the user can perform.
     */
    public function availableActions(): array
    {
        // Return cached result if available
        if ($this->cachedActions !== null) {
            return $this->cachedActions;
        }

        $actions = [];

        foreach ($this->roles as $role) {
            if (empty($role->actions)) {
                continue;
            }

            // Actions are now stored as JSON array
            $roleActions = is_array($role->actions)
                ? $role->actions
                : [];

            $actions = array_merge($actions, $roleActions);
        }

        // Cache the result
        /** @var array<int, string> $uniqueActions */
        $uniqueActions = array_values(array_unique($actions));
        $this->cachedActions = $uniqueActions;

        return $this->cachedActions;
    }

    /**
     * Scopes a query to filter users by name or email.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Equidna\SwiftAuth\Models\User> $query   Query builder instance.
     * @param  string|null                                                               $search  Search term.
     * @return \Illuminate\Database\Eloquent\Builder<\Equidna\SwiftAuth\Models\User>          Filtered query.
     */
    public function scopeSearch(
        Builder $query,
        null|string $search,
    ): Builder {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', '%' . $search . '%')
                ->orWhere('email', 'LIKE', '%' . $search . '%');
        });
    }
}
