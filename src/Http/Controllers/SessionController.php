<?php

/**
 * Manages user sessions for SwiftAuth.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Http\Controllers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://github.com/EquidnaMX/swift_auth
 */

namespace Equidna\SwiftAuth\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Equidna\SwiftAuth\Facades\SwiftAuth;
use Equidna\Toolkit\Helpers\ResponseHelper;
use Equidna\SwiftAuth\Classes\Auth\SwiftSessionAuth;

/**
 * Exposes endpoints to list and revoke active sessions for the authenticated user.
 */
class SessionController extends Controller
{
    public function __construct(private SwiftSessionAuth $sessionAuth) {}

    /**
     * Lists all sessions for the authenticated user.
     *
     * @param  Request $request  HTTP request context.
     * @return JsonResponse|RedirectResponse
     */
    public function index(Request $request): JsonResponse|RedirectResponse
    {
        $userId = SwiftAuth::id();

        $sessions = $userId ? $this->sessionAuth->sessionsForUser($userId) : collect();

        return ResponseHelper::success(
            message: 'Active sessions loaded.',
            data: [
                'sessions' => $sessions,
            ],
        );
    }

    /**
     * Revokes a specific session for the authenticated user.
     *
     * @param  Request $request      HTTP request context.
     * @param  string  $sessionId    Identifier of the session to revoke.
     * @return JsonResponse|RedirectResponse
     */
    public function destroy(
        Request $request,
        string $sessionId,
    ): JsonResponse|RedirectResponse {
        $userId = SwiftAuth::id();

        if (!$userId) {
            return ResponseHelper::unauthorized(message: 'Unauthenticated.');
        }

        $this->sessionAuth->revokeSession($userId, $sessionId);

        return ResponseHelper::success(
            message: 'Session revoked.',
        );
    }
}
