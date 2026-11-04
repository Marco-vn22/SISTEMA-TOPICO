<?php

namespace App\Http\Middleware;

use App\Models\AccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTopico
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        abort_unless($token, 401, 'Debes iniciar sesión.');
        $access = AccessToken::query()->with('user')
            ->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
        abort_unless($access && $access->user && $access->user->active, 401, 'La sesión ha caducado. Ingresa nuevamente.');
        if ($access->updated_at->lte(now()->subMinutes(config('topico.idle_minutes')))) {
            $access->delete();
            abort(401, 'Tu sesión se cerró por inactividad. Ingresa nuevamente.');
        }
        if (! ($request->isMethod('GET') && $request->header('X-Topico-Background') === '1') && $access->updated_at->lt(now()->subMinute())) {
            $access->touch();
        }
        $request->setUserResolver(fn () => $access->user);
        $request->attributes->set('access_token_id', $access->id);

        $passwordSetupRoute = ($request->isMethod('GET') && $request->is('api/v1/me'))
            || ($request->isMethod('PUT') && $request->is('api/v1/password'))
            || ($request->isMethod('POST') && $request->is('api/v1/logout'));
        if ($access->user->uses_initial_password && ! $passwordSetupRoute) {
            return response()->json([
                'code' => 'password_change_required',
                'message' => 'Debes cambiar tu contraseña inicial antes de utilizar el sistema.',
            ], 403);
        }

        $response = $next($request);
        $deadline = $access->updated_at->copy()->addMinutes(config('topico.idle_minutes'));
        $response->headers->set('X-Session-Expires-At', $deadline->min($access->expires_at)->toIso8601String());

        return $response;
    }
}
