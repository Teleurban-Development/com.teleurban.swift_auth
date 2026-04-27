<?php

/**
 * Provides CRUD endpoints for SwiftAuth user management.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Http\Controllers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://github.com/EquidnaMX/swift_auth
 */

namespace Equidna\SwiftAuth\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Support\Responsable;
use Equidna\SwiftAuth\Facades\SwiftAuth;
use Equidna\SwiftAuth\Http\Requests\RegisterUserRequest;
use Equidna\SwiftAuth\Http\Requests\UpdateUserRequest;
use Equidna\SwiftAuth\Models\Role;
use Equidna\BeeHive\Tenancy\TenantContext;
use Equidna\SwiftAuth\Models\User;
use Equidna\SwiftAuth\Support\Traits\SelectiveRender;
use Equidna\Toolkit\Exceptions\BadRequestException;
use Equidna\Toolkit\Exceptions\ForbiddenException;
use Equidna\Toolkit\Helpers\ResponseHelper;

/**
 * Manages SwiftAuth user lifecycle actions (listing, creation, updates, and deletion).
 *
 * Renders blade or Inertia resources and emits toolkit responses that honor the current context.
 */
class UserController extends Controller
{
    use SelectiveRender;

    /**
     * Displays the paginated user list optionally filtered by search term.
     *
     * @param  Request       $request  HTTP request containing the optional search filter.
     * @return View|Responsable           Blade or Inertia response with pagination data.
     */
    public function index(Request $request): View|Responsable
    {
        $searchRaw = $request->get('search', null);
        $search = is_string($searchRaw) ? $searchRaw : null;

        $users = User::search($search)
            ->paginate(10);

        return $this->render(
            'swift-auth::user.index',
            'SwiftAuth/User/Index',
            [
                'users' => $users,
                // Ensure actions is always an array for the view and static analysis
                'actions' => (array) Config::get('swift-auth.actions', []),
            ],
        );
    }

    /**
     * Shows the registration form for creating a new user.
     *
     * @param  Request       $request  HTTP request context.
     * @return View|Responsable           Blade or Inertia response with role list.
     */
    public function register(Request $request): View|Responsable
    {
        $roles = Cache::remember(
            $this->rolesCacheKey(),
            300,
            fn() => Role::orderBy('name')->get()
        );

        return $this->render(
            'swift-auth::user.register',
            'user/Register',
            [
                'roles' => $roles,
            ],
        );
    }

    /**
     * Shows the admin create user form.
     *
     * @param  Request       $request  HTTP request context.
     * @return View|Responsable           Blade or Inertia response with role list.
     */
    public function create(Request $request): View|Responsable
    {
        $roles = Cache::remember(
            $this->rolesCacheKey(),
            300,
            fn() => Role::orderBy('name')->get()
        );

        return $this->render(
            'swift-auth::user.create',
            'SwiftAuth/User/Create',
            [
                'roles' => $roles,
            ],
        );
    }

    /**
     * Stores a new user and assigns the selected role.
     *
     * @param  RegisterUserRequest       $request  Validated registration payload.
     * @return RedirectResponse|JsonResponse       Context-aware created response.
     */
    public function store(RegisterUserRequest $request): RedirectResponse|JsonResponse
    {
        /** @var array{name:string,email:string,password:string,role?:int|string} $payload */
        $payload = $request->validated();

        // If role not provided (public registration), assign a default role if configured.
        if (!isset($payload['role'])) {
            $defaultRole = config('swift-auth.default_role_id', null);
            if ($defaultRole === null) {
                $defaultRole = Role::orderBy('id_role')->value('id_role');
            }

            if ($defaultRole !== null) {
                $payload['role'] = $defaultRole;
            }
        }

        $driverRaw = config('swift-auth.hash_driver');
        $driver = is_string($driverRaw) ? $driverRaw : null;
        if ($driver) {
            /** @var Hasher $hasher */
            $hasher = Hash::driver($driver);
            $hashed = $hasher->make($payload['password']);
        } else {
            $hashed = Hash::make($payload['password']);
        }

        $user = User::create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => $hashed,
        ]);

        // Normalize role attachment to an array so attach() works with scalar or array.
        $user->roles()->attach((array) ($payload['role'] ?? []));

        logger()->info('User created', [
            'user_id' => $user->getKey(),
            'email' => $user->email,
            // cast to string for consistent logging and static analysis
            'created_by' => (string) SwiftAuth::id(),
            'ip' => $request->ip(),
        ]);

        if (SwiftAuth::check()) {
            return ResponseHelper::created(
                message: 'Registration successful.',
                data: [
                    'user_id' => $user->getKey(),
                ],
                forward_url: route('swift-auth.users.index'),
            );
        }

        // Normalize device name header which may be string|array|null
        $deviceNameRaw = $request->header('X-Device-Name', '');
        $deviceName = is_array($deviceNameRaw) ? ($deviceNameRaw[0] ?? '') : (string) $deviceNameRaw;

        /** @var string $deviceName */

        SwiftAuth::login(
            user: $user,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            deviceName: $deviceName,
            remember: false,
        );

        return ResponseHelper::created(
            message: 'Registration successful.',
            data: [
                'user_id' => $user->getKey(),
            ],
            forward_url: route('swift-auth.users.index'),
        );
    }

    /**
     * Displays the detail page for a specific user.
     *
     * @param  Request       $request  HTTP request context.
     * @param  string        $id_user  Identifier of the user to show.
     * @return View|Responsable           Blade or Inertia response with user data.
     */
    public function show(
        Request $request,
        string $id_user,
    ): View|Responsable {
        $user = User::findOrFail($id_user);
        $roles = Cache::remember(
            $this->rolesCacheKey(),
            300,
            fn() => Role::orderBy('name')->get()
        );

        return $this->render(
            'swift-auth::user.show',
            'user/Details',
            [
                'user' => $user,
                'roles' => $roles,
            ],
        );
    }

    /**
     * Updates the selected user name and roles.
     *
     * @param  UpdateUserRequest         $request  Validated HTTP request containing changes.
     * @param  string                    $id_user  Identifier of the user to update.
     * @return RedirectResponse|JsonResponse       Context-aware success response.
     */
    public function update(
        UpdateUserRequest $request,
        string $id_user,
    ): RedirectResponse|JsonResponse|string {
        $user = User::findOrFail($id_user);

        /** @var array{name?:string,roles?:array<int>,role?:int} $payload */
        $payload = $request->validated();

        $user->update([
            'name' => $payload['name'] ?? $user->name,
        ]);

        /** @var array<int> $roleIds */
        $roleIds = [];

        if (isset($payload['roles'])) {
            $roleIds = is_array($payload['roles']) ? $payload['roles'] : [];
        } elseif (isset($payload['role'])) {
            $roleIds = [$payload['role']];
        }

        // Normalize role ids to integers for the sync operation.
        $roleIds = array_map('intval', $roleIds ?: []);

        if (!empty($roleIds)) {
            $user->roles()->sync($roleIds);
        }

        logger()->info('User updated', [
            'user_id' => $user->getKey(),
            'updated_by' => SwiftAuth::id(),
            'changes' => $payload,
            'ip' => $request->ip(),
        ]);

        return ResponseHelper::success(
            message: 'User updated successfully.',
            data: [
                'user_id' => $user->getKey(),
            ],
            forward_url: route('swift-auth.users.index'),
        );
    }

    /**
     * Shows the admin edit form for a user.
     *
     * @param  Request       $request  HTTP request context.
     * @param  string        $id_user  Identifier of the user to edit.
     * @return View|Responsable           Blade or Inertia response with user and role data.
     */
    public function edit(
        Request $request,
        string $id_user,
    ): View|Responsable {
        $user = User::findOrFail($id_user);
        $roles = Cache::remember(
            $this->rolesCacheKey(),
            300,
            fn() => Role::orderBy('name')->get()
        );

        return $this->render(
            'swift-auth::user.edit',
            'SwiftAuth/User/Edit',
            [
                'user' => $user,
                'roles' => $roles,
            ],
        );
    }

    /**
     * Deletes a user from the system.
     *
     * @param  Request                   $request  HTTP request context.
     * @param  string                    $id_user  Identifier for the user to delete.
     * @return RedirectResponse|JsonResponse       Context-aware success response.
     * @throws ForbiddenException                  When attempting to delete your own account.
     */
    public function destroy(
        Request $request,
        string $id_user,
    ): RedirectResponse|JsonResponse|string {
        // Compare as strings to support both numeric and non-numeric identifiers (UUIDs).
        if ((string) SwiftAuth::id() === (string) $id_user) {
            throw new ForbiddenException('You cannot delete your own account.');
        }

        $user = User::findOrFail($id_user);

        logger()->warning('User deleted', [
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'deleted_by' => (string) SwiftAuth::id(),
            'ip' => $request->ip(),
        ]);

        $user->delete();

        return ResponseHelper::success(
            message: 'User successfully deleted.',
            data: [
                'user_id' => (int) $id_user,
            ],
            forward_url: route('swift-auth.users.index'),
        );
    }

    /**
     * Returns a cache key for roles, scoped to the current tenant when multi-tenancy is enabled.
     */
    private function rolesCacheKey(): string
    {
        if (config('swift-auth.multi_tenancy.enabled', false)) {
            /** @var TenantContext $context */
            $context  = app(TenantContext::class);
            $tenantId = $context->get() ?? '0';
            return 'swift-auth.roles.' . $tenantId;
        }

        return 'swift-auth.roles';
    }
}
