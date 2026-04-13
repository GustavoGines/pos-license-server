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
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'version' => $latestRelease->version,
                'download_url' => $latestRelease->download_url,
                'changelog' => $latestRelease->changelog,
                'is_critical' => $latestRelease->is_critical,
                'released_at' => $latestRelease->created_at->toISOString(),
            ]
        ]);
    }
}
