<?php

namespace App\Http\Controllers;

use App\Models\AccessToken;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessions = AccessToken::query()->where('user_id', $request->user()->id)
            ->where('expires_at', '>', now())
            ->where('updated_at', '>', now()->subMinutes(config('topico.idle_minutes')))
            ->latest('updated_at')->get()
            ->map(fn (AccessToken $session): array => [
                'id' => $session->id,
                'current' => $session->id === $request->attributes->get('access_token_id'),
                'created_at' => $session->created_at->toIso8601String(),
                'last_activity_at' => $session->updated_at->toIso8601String(),
                'expires_at' => $session->expires_at->toIso8601String(),
            ]);

        return response()->json(['sessions' => $sessions, 'idle_minutes' => config('topico.idle_minutes')]);
    }

    public function destroy(Request $request, int $session): JsonResponse
    {
        abort_if($session === $request->attributes->get('access_token_id'), 422, 'Utiliza Cerrar sesión para salir de tu sesión actual.');
        $deleted = AccessToken::query()->where('user_id', $request->user()->id)->whereKey($session)->delete();
        abort_unless($deleted, 404, 'La sesión ya no está disponible.');
        AuditLog::query()->create(['user_id' => $request->user()->id, 'action' => 'session_revoked', 'entity' => 'access_token', 'entity_id' => $session]);

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}
