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

        // 3. Verificar expiración (solo para SaaS)
        if ($license->plan_type === 'saas' && $license->expiration_date && \Carbon\Carbon::parse($license->expiration_date)->endOfDay()->isPast()) {
            return response()->json([
                'status'  => 'expired',
                'message' => 'La licencia ha expirado.',
            ], 403);
        }

        // 4. Protección anti-piratería por installation_id
        if (empty($license->installation_id)) {
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

        // 5. Todo válido → retornar 200 con el plan y los addons habilitados
        // Lógica de Negocio Cruce Plan + Rubro:
        $businessAddons = [];

        // 1. Módulos Base (Siempre disponibles para Retail y Ferretería)
        array_push($businessAddons, 'fast_pos', 'z_reports');

        // 2. Módulos por Plan (PRO / Enterprise para Retail y Ferretería)
        if (in_array($license->plan, ['pro', 'enterprise'])) {
            array_push($businessAddons, 'multi_caja', 'current_accounts');
        }

        // 3. Módulos Exclusivos por Vertical / Rubro
        // La Ferretería SIEMPRE tiene presupuestos y múltiples listas, sin importar su plan (incluso Basic)
        // Retail NUNCA los tiene, por más que sea Enterprise.
        if ($license->business_type === 'hardware_store') {
            array_push($businessAddons, 'quotes', 'multiple_prices');
        }

        // 4. Si el panel de administración inyectó algún addon extra manual, lo fusionamos
        $adminAddons = is_array($license->allowed_addons) ? $license->allowed_addons : [];
        $addons = array_values(array_unique(array_merge($businessAddons, $adminAddons)));

        return response()->json([
            'status'                => 'active',
            'plan'                  => $license->plan,
            'plan_type'             => $license->plan_type,
            'server_time'           => now()->toIso8601String(),
            'client_name'           => $license->client_name,
            'business_type'         => $license->business_type,
            'addons'                => $addons,
            // Feature flags individuales (boolean) para gating rápido en el cliente POS
            'has_advanced_reports'  => in_array('advanced_reports', $addons),
            'has_predictive_alerts' => in_array('predictive_alerts', $addons),
        ], 200);
    }
}
