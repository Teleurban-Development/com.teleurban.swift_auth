<?php

/**
 * Eloquent model for user API tokens.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Models
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://github.com/EquidnaMX/swift_auth Package repository
 */

namespace Equidna\SwiftAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a user API token for stateless authentication.
 *
 * Stores hashed tokens with abilities/scopes, expiration, and usage tracking.
 * Follows SwiftAuth patterns for token hashing and metadata validation.
 *
 * @property int                             $id_user_token  Primary key.
 * @property int                             $id_user        User ID.
 * @property string                          $name           Token name/label.
 * @property string                          $hashed_token   SHA-256 hashed token.
 * @property array<string>                   $abilities      Scopes/permissions.
 * @property \Illuminate\Support\Carbon|null $last_used_at   Last usage timestamp.
 * @property \Illuminate\Support\Carbon|null $expires_at     Expiration timestamp.
 * @property \Illuminate\Support\Carbon|null $created_at     Creation timestamp.
 * @property \Illuminate\Support\Carbon|null $updated_at     Update timestamp.
 * @property-read User                       $user           Owning user.
    * @method static static create(array<string, mixed> $attributes = [])
 */
class UserToken extends Model
{
    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'id_user_token';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_user',
        'name',
        'hashed_token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Creates a new instance of the model.
     *
     * @param array<string, mixed> $attributes  Initial attributes.
     */
    public function __construct(array $attributes = [])
    {
        $prefix = (string) config('swift-auth.table_prefix', '');
        $this->table = $prefix . 'UserTokens';

        parent::__construct($attributes);
    }

    /**
     * Returns the user that owns this token.
     *
     * @return BelongsTo<User, UserToken>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            related: User::class,
            foreignKey: 'id_user',
            ownerKey: 'id_user',
        );
    }

    /**
     * Checks if the token is expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Checks if the token has a specific ability.
     *
     * @param  string $ability  Ability to check.
     * @return bool
     */
    public function can(string $ability): bool
    {
        if ($this->abilities === null || $this->abilities === []) {
            return true; // Wildcard token
        }

        return in_array('*', $this->abilities, strict: true)
            || in_array($ability, $this->abilities, strict: true);
    }

    /**
     * Updates the last used timestamp.
     *
     * @return void
     */
    public function markAsUsed(): void
    {
        $this->last_used_at = now();
        $this->save();
    }
}
