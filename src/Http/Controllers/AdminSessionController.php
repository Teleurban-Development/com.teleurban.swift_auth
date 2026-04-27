<?php

/**
 * Admin endpoints for managing user sessions.
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
 * Provides session listing and revocation for administrators.
 */
class AdminSessionController extends Controller
{
    public function __construct(private SwiftSessionAuth $sessionAuth) {}

    /**
     * Lists all sessions across all users.
     *
     * @param  Request $request  HTTP request context.
     * @return JsonResponse|RedirectResponse
     */
    public function all(Request $request): JsonResponse|RedirectResponse
    {
        return ResponseHelper::success(
            message: 'All sessions loaded.',
            data: [
                'sessions' => $this->sessionAuth->allSessions(),
            ],
        );
    }

    /**
     * Lists all sessions for the selected user.
     *
     * @param  Request $request  HTTP request context.
     * @param  int     $userId   Identifier of the user to inspect.
     * @return JsonResponse|RedirectResponse
     */
    public function index(
        Request $request,
        int $userId,
    ): JsonResponse|RedirectResponse {
        $sessions = $this->sessionAuth->sessionsForUser($userId);

        return ResponseHelper::success(
            message: 'User sessions loaded.',
            data: [
                'user_id' => $userId,
                'sessions' => $sessions,
            ],
        );
    }

    /**
     * Revokes a specific session for the given user.
     *
     * @param  Request $request     HTTP request context.
     * @param  int     $userId      Identifier of the user who owns the session.
     * @param  string  $sessionId   Identifier of the session to revoke.
     * @return JsonResponse|RedirectResponse
     */
    public function destroy(
        Request $request,
        int $userId,
        string $sessionId,
    ): JsonResponse|RedirectResponse {
        $this->sessionAuth->revokeSession($userId, $sessionId);

        return ResponseHelper::success(
            message: 'Session revoked.',
            data: [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'revoked_by' => SwiftAuth::id(),
            ],
        );
    }

    /**
     * Revokes all sessions for the given user.
     *
     * @param  Request $request  HTTP request context.
     * @param  int     $userId   Identifier of the user whose sessions are revoked.
     * @return JsonResponse|RedirectResponse
     */
    public function destroyAll(Request $request, int $userId): JsonResponse|RedirectResponse
    {
        $includeRememberTokens = $request->boolean('include_remember_tokens', false);

        $result = $this->sessionAuth->revokeAllSessionsForUser(
            userId: $userId,
            includeRememberTokens: $includeRememberTokens,
        );

        return ResponseHelper::success(
            message: 'All sessions revoked.',
            data: [
                'user_id' => $userId,
                'deleted_sessions' => $result['deleted_sessions'],
                'cleared_remember_tokens' => $result['cleared_remember_tokens'],
                'revoked_by' => SwiftAuth::id(),
            ],
        );
    }
}
