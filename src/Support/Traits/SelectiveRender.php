<?php

/**
 * Selective render helper for Blade or Inertia frontends.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Traits
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://github.com/EquidnaMX/swift_auth Package repository
 */

namespace Equidna\SwiftAuth\Support\Traits;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;

/**
 * Provides selective rendering for Blade or Inertia frontends.
 *
 * Determines view technology based on configuration and automatically includes flash messages
 * (success, error, status) in view data. For Inertia frontends (TypeScript or JavaScript),
 * component resolution is handled by the Inertia middleware and build tool (Vite/Webpack).
 */
trait SelectiveRender
{
    /**
     * Renders a view or Inertia component based on the frontend configuration.
     *
     * Flash messages (success, error, status) are automatically added to the view data.
     * For Inertia frontends, pass the full component namespace (e.g., 'SwiftAuth/Login').
     * The TypeScript/JavaScript distinction is handled during the build phase by Vite/Webpack.
     *
     * @param  string              $bladeView         Blade view name to render (if frontend is Blade).
     * @param  string              $inertiaComponent  Full Inertia component name (e.g., 'SwiftAuth/Login').
     * @param  array<string,mixed> $data              Additional data to pass to the view or component.
     * @return View|Responsable                      Rendered Blade view or Inertia component.
     */
    protected function render(
        string $bladeView,
        string $inertiaComponent,
        array $data = [],
    ): View|Responsable {
        $flashMessages = [
            'success' => session('success'),
            'error' => session('error'),
            'status' => session('status'),
        ];

        $data = array_merge($data, $flashMessages);

        $frontend = Config::get('swift-auth.frontend', 'typescript');

        return $frontend === 'blade'
            ? view($bladeView, $data)
            : Inertia::render($inertiaComponent, $data);
    }
}
