<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseValidationController extends Controller
{
    /**
     * Valida una licencia con protección anti-piratería por installation_id.
     *
     * Recibe:  license_key   → mapeado internamente a api_key en la BD
     *          installation_id → UUID único del dispositivo/instalación
     *
     * Lógica anti-piratería:
     *   - Si installation_id en BD es null  → primer uso, se bindea (guarda).
     *   - Si installation_id en BD != null  → debe coincidir; si difiere → 403.
     */
    public function validateKey(Request $request): JsonResponse
    {
        $request->validate([
            'license_key'     => 'required|string',
            'installation_id' => 'required|string|max:255',
        ]);

        $licenseKey     = $request->input('license_key');
        $installationId = $request->input('installation_id');

        // 1. Buscar la licencia (la BD usa `api_key`, el endpoint recibe `license_key`)
        $license = License::where('api_key', $licenseKey)->first();

        if (!$license) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Licencia no encontrada.',
            ], 403);
        }

        // 2. Verificar que esté activa
        if (!$license->is_active) {
            return response()->json([
                'status'  => 'suspended',
                'message' => 'La licencia está suspendida. Contacte a soporte.',
            ], 403);
        }

        // 3. Verificar expiración
        if ($license->expiration_date && $license->expiration_date->isPast()) {
            return response()->json([
                'status'  => 'expired',
                'message' => 'La licencia ha expirado.',
            ], 403);
        }

        // 4. Protección anti-piratería por installation_id
        if (is_null($license->installation_id)) {
            // Primera activación: bindear el dispositivo a esta licencia
            $license->installation_id = $installationId;
            $license->save();
        } elseif ($license->installation_id !== $installationId) {
            // El installation_id no coincide con el que se registró → posible piratería
            return response()->json([
                'status'  => 'error',
                'message' => 'Esta licencia ya está vinculada a otra instalación.',
            ], 403);
        }

        // 5. Todo válido → retornar 200 con el plan y los addons vinculados
        return response()->json([
            'status'         => 'active',
            'plan'           => $license->plan_type,
            'client_name'    => $license->client_name,
            'addons'         => $license->allowed_addons ?? $license->addons ?? [],
            'allowed_addons' => $license->allowed_addons ?? $license->addons ?? [],
        ], 200);
    }
}
