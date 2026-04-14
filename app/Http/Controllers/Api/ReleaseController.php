<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Release;

class ReleaseController extends Controller
{
    public function checkUpdate()
    {
        $latestRelease = Release::latest()->first();

        if (!$latestRelease) {
            return response()->json([
                'success' => false,
                'message' => 'No release found',
                'data'    => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'version'      => $latestRelease->version,
                'download_url' => $latestRelease->download_url,
                'changelog'    => $latestRelease->changelog,
                'is_critical'  => $latestRelease->is_critical,
                'released_at'  => $latestRelease->created_at->toISOString(),
            ]
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
            'download_url' => 'required|url|max:500',
            'changelog'    => 'nullable|string',
            'is_critical'  => 'nullable|boolean',
        ]);

        // ── Crear el release en la BD ──────────────────────
        $release = Release::create([
            'version'      => $validated['version'],
            'download_url' => $validated['download_url'],
            'changelog'    => $validated['changelog'] ?? "Release {$validated['version']}.",
            'is_critical'  => $validated['is_critical'] ?? false,
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
