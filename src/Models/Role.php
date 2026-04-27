<?php

/**
 * Represents a system role.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Models
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://github.com/EquidnaMX/swift_auth Package repository
 */

namespace Equidna\SwiftAuth\Models;

use Equidna\BeeHive\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Equidna\SwiftAuth\Models\User;

/**
 * @property int $id_role
 * @property string $name
 * @property string|null $description
 * @property array<int, string> $actions List of action identifiers
 *
 * @method static \Illuminate\Database\Eloquent\Builder<\Equidna\SwiftAuth\Models\Role> search(null|string $term)
 * @method static static create(array<string,mixed> $attributes = [])
 * @method static static findOrFail(string|int $id)
 * @method static static firstOrCreate(array<string,mixed> $attributes, array<string,mixed> $values = [])
 * @method static \Illuminate\Database\Eloquent\Builder<\Equidna\SwiftAuth\Models\Role> orderBy(string $column, string $direction = 'asc')
 */
class Role extends Model
{
    use BelongsToTenant;

    protected $table;
    protected $primaryKey = 'id_role';

    /**
     * Initialize the model.
     *
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = $this->tablePrefix() . 'Roles';
    }

    /**
     * Returns configured table prefix.
     */
    protected function tablePrefix(): string
    {
        try {
            return (string) config('swift-auth.table_prefix', '');
        } catch (\Throwable) {
            return '';
        }
    }

    protected $fillable = [
        'id_tenant',
        'name',
        'description',
        'actions',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'actions' => 'array',
    ];

    /**
     * The users that belong to this role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<
     *     \Equidna\SwiftAuth\Models\User,
     *     $this
     * >
     */
    public function users(): BelongsToMany
    {
        $prefix = (string) config('swift-auth.table_prefix', '');
        return $this->belongsToMany(
            User::class,
            $prefix . 'UsersRoles',
            'id_role',
            'id_user'
        );
    }

    /**
     * Scopes a query to filter roles by name.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Equidna\SwiftAuth\Models\Role> $query   Query builder instance.
     * @param  string|null                                                              $search  Search term.
     * @return \Illuminate\Database\Eloquent\Builder<\Equidna\SwiftAuth\Models\Role>          Filtered query.
     */
    public function scopeSearch(
        Builder $query,
        null|string $search,
    ): Builder {
        if (empty($search)) {
            return $query;
        }

        return $query->where('name', 'LIKE', '%' . $search . '%');
    }
}
