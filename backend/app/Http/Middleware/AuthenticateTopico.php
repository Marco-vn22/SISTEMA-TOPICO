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
        $request->setUserResolver(fn () => $access->user);
        $request->attributes->set('access_token_id', $access->id);

        return $next($request);
    }
}
