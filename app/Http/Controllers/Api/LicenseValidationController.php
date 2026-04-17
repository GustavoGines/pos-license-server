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
     */
    public function validateKey(Request $request): JsonResponse
    {
        $request->validate([
            'license_key'     => 'required|string',
            'installation_id' => 'required|string|max:255',
        ]);

        $licenseKey     = $request->input('license_key');
        $installationId = $request->input('installation_id');

        $license = License::where('api_key', $licenseKey)->first();

        if (!$license) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Licencia no encontrada.',
            ], 403);
        }

        if (!$license->is_active) {
            return response()->json([
                'status'  => 'suspended',
                'message' => 'La licencia está suspendida. Contacte a soporte.',
            ], 403);
        }

        if ($license->plan_type === 'saas' && $license->expiration_date && \Carbon\Carbon::parse($license->expiration_date)->endOfDay()->isPast()) {
            return response()->json([
                'status'  => 'expired',
                'message' => 'La licencia ha expirado.',
            ], 403);
        }

        if (empty($license->installation_id)) {
            $license->installation_id = $installationId;
            $license->save();
        } elseif ($license->installation_id !== $installationId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Esta licencia ya está vinculada a otra instalación.',
            ], 403);
        }

        $businessAddons = [];

        // Módulos Base: siempre disponibles
        array_push($businessAddons, 'fast_pos', 'z_reports');

        // 2. Módulos por Plan (Premium para Retail y Ferretería)
        if (in_array($license->plan, ['premium', 'pro', 'enterprise'])) {
            array_push($businessAddons, 'multi_caja', 'current_accounts', 'advanced_reports', 'predictive_alerts');
        }

        // 3. Módulos Exclusivos por Vertical / Rubro
        // La Ferretería SIEMPRE tiene presupuestos, múltiples listas y remitos, sin importar su plan (incluso Basico)
        // Retail NUNCA los tiene, por más que sea Premium.
        if ($license->business_type === 'hardware_store') {
            array_push($businessAddons, 'quotes', 'multiple_prices', 'logistics');
        }

        // Módulos extra/individuales pagados por el cliente (ej: reportes_gerenciales para un plan Básico)
        // La actualización de plan no borrará los módulos extra que un cliente ya haya pagado.
        $adminAddons = is_array($license->allowed_addons) ? $license->allowed_addons : [];
        $addons = array_values(array_unique(array_merge($businessAddons, $adminAddons)));

        $features = $this->mapFeatures($addons, $license);

        return response()->json([
            'status'                => 'active',
            // Retrocompatibilidad: Si el cliente viejo usa hardcoded 'basic', devolvemos 'basic'
            'plan'                  => ($license->plan === 'basico') ? 'basic' : (($license->plan === 'premium') ? 'pro' : $license->plan),
            'plan_type'             => $license->plan_type,
            'server_time'           => now()->toIso8601String(),
            'client_name'           => $license->client_name,
            'business_type'         => $license->business_type,
            // Nombres nuevos para clientes nuevos
            'plan_espanol'          => $license->plan, 
            'features'              => $features,
            // --- CAMPOS LEGACY PARA CLIENTES VIEJOS EN PRODUCCIÓN ---
            'has_advanced_reports'  => in_array('advanced_reports', $addons),
            'has_predictive_alerts' => in_array('predictive_alerts', $addons),
        ], 200);
    }

    /**
     * Mapea el array de addons a un diccionario de booleanos estructurado.
     */
    private function mapFeatures(array $addons, License $license): array
    {
        $isHardwareStore = ($license->business_type === License::BUSINESS_HARDWARE);
        $adminAddons = is_array($license->allowed_addons) ? $license->allowed_addons : [];

        $allFeatures = [
            'fast_pos',
            'z_reports',
            'quotes',
            'current_accounts',
            'multiple_prices',
            'multi_caja',
            'advanced_reports',
            'predictive_alerts',
            'logistics',
        ];

        $map = [];
        foreach ($allFeatures as $feature) {
            $hasAddon = in_array($feature, $addons);

            // Respetar estrictamente los módulos individuales que el cliente haya adquirido
            if (in_array($feature, $adminAddons)) {
                $map[$feature] = true;
                continue;
            }

            // Exclusividad doble por Rubro para ciertos módulos
            if (in_array($feature, ['logistics', 'quotes'])) {
                $map[$feature] = $hasAddon && $isHardwareStore;
            } else {
                $map[$feature] = $hasAddon;
            }
        }

        return $map;
    }
}
