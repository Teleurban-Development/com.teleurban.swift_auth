<?php

/**
 * Middleware to share common data with Inertia.js requests.
 *
 * Shares the current locale, translations, and authenticated user data
 * with all Inertia.js responses for use in React components.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Http\Middleware
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\SwiftAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

/**
 * Shares authentication state, locale, and translations with Inertia responses.
 */
final class ShareInertiaData
{
    /**
     * Handles an incoming request and shares data with Inertia.
     *
     * @param Request $request Incoming HTTP request.
     * @param Closure $next    Next middleware in the pipeline.
     * @return mixed Response from the next middleware.
     */
    public function handle(
        Request $request,
        Closure $next,
    ): mixed {
        Inertia::share([
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
            ],
            'locale' => App::getLocale(),
            'translations' => $this->loadTranslations(App::getLocale()),
        ]);

        return $next($request);
    }

    /**
     * Loads all translation files for the given locale.
     *
     * @param string $locale Current locale code (e.g., 'en', 'es').
     * @return array<string, string> Flattened translation keys and values.
     */
    private function loadTranslations(string $locale): array
    {
        $translations = [];
        $langPath = __DIR__ . '/../../../resources/lang/' . $locale;

        if (!File::isDirectory($langPath)) {
            return $translations;
        }

        $files = File::files($langPath);

        foreach ($files as $file) {
            $module = $file->getFilenameWithoutExtension();
            $moduleTranslations = include $file->getPathname();

            foreach ($moduleTranslations as $key => $value) {
                $translations[$module . '.' . $key] = $value;
            }
        }

        return $translations;
    }
}
