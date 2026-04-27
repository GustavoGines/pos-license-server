<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Release;

class ReleaseController extends Controller
{
    /**
     * GET /api/check-update?current_version=1.1.0
     *
     * Compara la versión actual del cliente con el último release en la BD.
     * Limpia el prefijo "v" de ambos lados antes de comparar con version_compare().
     */
    public function checkUpdate(Request $request)
    {
        $component = $request->query('component', 'frontend');
        $channel = $request->query('channel', 'stable'); // Default to stable for legacy clients
        
        $latestRelease = Release::where('component', $component)
            ->where('channel', $channel)
            ->latest()
            ->first();

        // ── Sin releases en la BD: sistema al día ─────────────────────────
        if (!$latestRelease) {
            return response()->json([
                'success'          => true,
                'update_available' => false,
                'message'          => "No hay releases registrados para el componente: {$component}.",
                'data'             => null,
            ]);
        }

        // ── Limpiar prefijo "v" de la versión guardada en BD ─────────────
        // El CI/CD guarda "v1.1.1" (del tag de Git). Lo normalizamos a "1.1.1".
        $serverVersion = ltrim($latestRelease->version, 'vV');

        // ── Limpiar prefijo "v" del parámetro del cliente ─────────────────
        // El cliente Flutter envía la versión del pubspec: "1.1.0" (sin "v").
        // Por si acaso viene con "v", lo limpiamos igual.
        $clientVersion = ltrim($request->query('current_version', '0.0.0'), 'vV');

        // ── Comparación semántica robusta con version_compare de PHP ──────
        $updateAvailable = version_compare($serverVersion, $clientVersion, '>');

        return response()->json([
            'success'          => true,
            'update_available' => $updateAvailable,
            'data'             => $updateAvailable ? [
                'version'      => $serverVersion,           // Devolvemos sin "v" para que Flutter compare limpio
                'download_url' => $latestRelease->download_url,
                'changelog'    => $latestRelease->changelog,
                'is_critical'  => (bool) $latestRelease->is_critical,
                'released_at'  => $latestRelease->created_at->toISOString(),
            ] : null,
        ]);
    }


    /**
     * Llamado por el CI/CD (GitHub Actions) al publicar un nuevo tag.
     * Requiere el header o campo `token` que coincida con LICENSE_SERVER_TOKEN.
     */
    public function store(Request $request)
    {
        // ── Validación del token secreto ───────────────────
        $expectedToken = config('app.ci_deploy_token', env('CI_DEPLOY_TOKEN'));
        if ($request->input('token') !== $expectedToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // ── Validación de campos ───────────────────────────
        $validated = $request->validate([
            'version'      => 'required|string|max:20',
            'component'    => 'nullable|string|max:50',
            'download_url' => 'required|url|max:500',
            'changelog'    => 'nullable|string',
            'is_critical'  => 'nullable|boolean',
            'channel'      => 'nullable|string|max:20',
        ]);

        // ── Crear el release en la BD ──────────────────────
        $release = Release::create([
            'version'      => $validated['version'],
            'component'    => $validated['component'] ?? 'frontend',
            'download_url' => $validated['download_url'],
            'changelog'    => $validated['changelog'] ?? "Release {$validated['version']}.",
            'is_critical'  => $validated['is_critical'] ?? false,
            'channel'      => $validated['channel'] ?? 'beta', // CI/CD deployments are beta by default
        ]);

        return response()->json([
            'success' => true,
            'message' => "Release {$release->version} registrado exitosamente.",
            'data'    => [
                'id'      => $release->id,
                'version' => $release->version,
            ]
        ], 201);
    }
}
