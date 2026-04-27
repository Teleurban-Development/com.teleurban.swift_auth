<?php

/**
 * Manages SwiftAuth roles and permissions screens.
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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Support\Responsable;
use Equidna\SwiftAuth\Facades\SwiftAuth;
use Equidna\SwiftAuth\Models\Role;
use Equidna\SwiftAuth\Support\Traits\SelectiveRender;
use Equidna\Toolkit\Exceptions\BadRequestException;
use Equidna\Toolkit\Helpers\ResponseHelper;

/**
 * Administers SwiftAuth roles through list, create, update, and delete endpoints.
 *
 * Relies on SelectiveRender to harmonize Blade and Inertia outputs.
 */
class RoleController extends Controller
{
    use SelectiveRender;

    /**
     * Displays the paginated role list.
     *
     * @param  Request       $request  HTTP request with optional search term.
     * @return View|Responsable           Blade or Inertia response containing roles.
     */
    public function index(Request $request): View|Responsable
    {
        $roles = Role::search($request->get('search'))
            ->paginate(10);

        return $this->render(
            'swift-auth::role.index',
            'SwiftAuth/Role/Index',
            [
                'roles' => $roles,
                'actions' => Config::get('swift-auth.actions'),
            ],
        );
    }

    /**
     * Shows the role creation form.
     *
     * @param  Request       $request  HTTP request context.
     * @return View|Responsable           Blade or Inertia response with action list.
     */
    public function create(Request $request): View|Responsable
    {
        return $this->render(
            'swift-auth::role.create',
            'SwiftAuth/Role/Create',
            [
                'actions' => Config::get('swift-auth.actions'),
            ],
        );
    }

    /**
     * Stores a new role with description and allowed actions.
     *
     * @param  Request                   $request  HTTP request containing role data.
     * @return RedirectResponse|JsonResponse       Context-aware created response.
     * @throws BadRequestException                 When validation fails.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $prefix = config('swift-auth.table_prefix', '');
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|unique:' . $prefix . 'Roles,name',
                'description' => 'required|string',
                'actions' => 'required|array|min:1',
                'actions.*' => 'string',
            ]
        );

        if ($validator->fails()) {
            throw new BadRequestException(
                'Role data invalid.',
                errors: $validator->errors()->toArray()
            );
        }

        $payload = $validator->validated();

        $roleActions = array_values(
            array_filter(
                $payload['actions'],
                static fn($action) => filled($action),
            ),
        );

        $role = Role::create([
            'name' => $payload['name'],
            'description' => $payload['description'],
            'actions' => $roleActions, // Now stored as JSON array
        ]);

        logger()->info('Role created', [
            'role_id' => $role->getKey(),
            'role_name' => $role->name,
            'created_by' => SwiftAuth::id(),
            'ip' => $request->ip(),
        ]);

        return ResponseHelper::created(
            message: 'Role created successfully.',
            data: null,
            forward_url: route('swift-auth.roles.index'),
        );
    }

    /**
     * Shows the edit form for a role.
     *
     * @param  Request       $request  HTTP request context.
     * @param  string        $id_role  Identifier for the role.
     * @return View|Responsable           Blade or Inertia response with role data.
     */
    public function edit(
        Request $request,
        string $id_role,
    ): View|Responsable {
        $role = Role::findOrFail($id_role);

        return $this->render(
            'swift-auth::role.edit',
            'SwiftAuth/Role/Edit',
            [
                'role' => $role,
                'actions' => Config::get('swift-auth.actions'),
            ],
        );
    }

    /**
     * Updates a role description and actions.
     *
     * @param  Request                   $request  HTTP request carrying modifications.
     * @param  string                    $id_role  Identifier of the role.
     * @return RedirectResponse|JsonResponse       Context-aware success response.
     * @throws BadRequestException                 When validation fails.
     */
    public function update(
        Request $request,
        string $id_role,
    ): RedirectResponse|JsonResponse {
        $role = Role::findOrFail($id_role);

        $validator = Validator::make(
            $request->all(),
            [
                'description' => 'required|string',
                'actions' => 'sometimes|array',
                'actions.*' => 'string',
            ]
        );

        if ($validator->fails()) {
            throw new BadRequestException(
                'Role update invalid.',
                errors: $validator->errors()->toArray()
            );
        }

        $payload = $validator->validated();

        $roleActions = array_values(
            array_filter(
                $payload['actions'] ?? [],
                static fn($action) => filled($action),
            ),
        );

        $role->update([
            'description' => $payload['description'],
            'actions' => $roleActions, // Now stored as JSON array
        ]);

        logger()->info('Role updated', [
            'role_id' => $role->getKey(),
            'role_name' => $role->name,
            'updated_by' => SwiftAuth::id(),
            'changes' => $payload,
            'ip' => $request->ip(),
        ]);

        return ResponseHelper::success(
            message: 'Role updated successfully.',
            data: [
                'role_id' => $role->getKey(),
            ],
            forward_url: route('swift-auth.roles.index'),
        );
    }

    /**
     * Deletes a role.
     *
     * @param  Request                   $request  HTTP request context.
     * @param  string                    $id_role  Identifier of the role to delete.
     * @return RedirectResponse|JsonResponse       Context-aware success response.
     */
    public function destroy(
        Request $request,
        string $id_role,
    ): RedirectResponse|JsonResponse {
        $role = Role::findOrFail($id_role);

        logger()->warning('Role deleted', [
            'role_id' => $role->getKey(),
            'role_name' => $role->name,
            'deleted_by' => SwiftAuth::id(),
            'ip' => $request->ip(),
        ]);

        $role->delete();

        return ResponseHelper::success(
            message: 'Role deleted successfully.',
            data: [
                'role_id' => (int) $id_role,
            ],
            forward_url: route('swift-auth.roles.index'),
        );
    }
}
