<?php

namespace Equidna\Toolkit\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ResponseHelper
{
    public static function success(
        string $message = 'OK',
        mixed $data = null,
        ?string $forward_url = null,
        int $status = 200,
    ): JsonResponse|RedirectResponse {
        if ($forward_url !== null) {
            return redirect($forward_url)->with('message', $message);
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'forward_url' => $forward_url,
        ], $status);
    }

    public static function created(
        string $message = 'Created',
        mixed $data = null,
        ?string $forward_url = null,
    ): JsonResponse|RedirectResponse {
        return self::success($message, $data, $forward_url, 201);
    }

    public static function error(
        string $message = 'Error',
        int $status = 500,
        mixed $data = null,
        ?string $forward_url = null,
    ): JsonResponse|RedirectResponse {
        if ($forward_url !== null) {
            return redirect($forward_url)->with('error', $message);
        }

        return response()->json([
            'status' => 'error',
            'message' => $message,
            'data' => $data,
            'forward_url' => $forward_url,
        ], $status);
    }

    public static function badRequest(
        string $message = 'Bad request',
        mixed $data = null,
        ?string $forward_url = null,
    ): JsonResponse|RedirectResponse {
        return self::error($message, 400, $data, $forward_url);
    }

    public static function unauthorized(
        string $message = 'Unauthorized',
        mixed $data = null,
        ?string $forward_url = null,
    ): JsonResponse|RedirectResponse {
        return self::error($message, 401, $data, $forward_url);
    }

    public static function forbidden(
        string $message = 'Forbidden',
        mixed $data = null,
        ?string $forward_url = null,
    ): JsonResponse|RedirectResponse {
        return self::error($message, 403, $data, $forward_url);
    }
}
