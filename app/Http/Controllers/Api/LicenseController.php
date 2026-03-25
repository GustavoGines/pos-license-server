<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    /**
     * Check the status of a license
     */
    public function check(Request $request)
    {
        $request->validate([
            'api_key' => 'required|string'
        ]);

        $license = License::where('api_key', $request->api_key)->first();

        if (!$license) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid API Key'
            ], 404);
        }

        if (!$license->is_active) {
            return response()->json([
                'status' => 'suspended',
                'message' => 'License is suspended. Please contact support.',
                'plan_type' => 'none',
                'allowed_addons' => []
            ], 403);
        }

        if ($license->expiration_date && $license->expiration_date->isPast()) {
            return response()->json([
                'status' => 'expired',
                'message' => 'License has expired.',
                'plan_type' => 'none',
                'allowed_addons' => []
            ], 403);
        }

        return response()->json([
            'status' => 'active',
            'client_name' => $license->client_name,
            'plan_type' => $license->plan_type,
            'allowed_addons' => $license->allowed_addons ?? []
        ]);
    }
}
