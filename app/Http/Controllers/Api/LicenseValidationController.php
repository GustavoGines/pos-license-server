<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'nonce'           => 'nullable|string|max:128',
        ]);

        $licenseKey     = $request->input('license_key');
        $installationId = $request->input('installation_id');
        $nonce          = $request->input('nonce');

        return DB::transaction(function () use ($licenseKey, $installationId, $nonce) {
            $license = License::where('api_key', $licenseKey)->lockForUpdate()->first();

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

            // L-06 / L-07: Comprobación estricta de nulidad/vacío (previene bypass con installation_id = "0")
            // y actualización atómica bajo lock pesimista para evitar condiciones de carrera.
            $currentInstallationId = $license->installation_id;
            if ($currentInstallationId === null || trim((string) $currentInstallationId) === '') {
                $affected = License::where('id', $license->id)
                    ->where(function ($query) {
                        $query->whereNull('installation_id')
                            ->orWhere('installation_id', '');
                    })
                    ->update(['installation_id' => $installationId]);

                if ($affected === 0) {
                    $license->refresh();
                    if ((string) $license->installation_id !== (string) $installationId) {
                        return response()->json([
                            'status'  => 'error',
                            'message' => 'Esta licencia ya está vinculada a otra instalación.',
                        ], 403);
                    }
                } else {
                    $license->installation_id = $installationId;
                }
            } elseif ((string) $currentInstallationId !== (string) $installationId) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Esta licencia ya está vinculada a otra instalación.',
                ], 403);
            }

            $businessAddons = [];

            // 1. Módulos Base: siempre disponibles en todos los planes (Básico y Premium)
            array_push(
                $businessAddons,
                'fast_pos',
                'z_reports',
                'advanced_reports',
                'mercadopago_qr',
                'arca_afip',
                'mobile_app'
            );

            // 2. Módulos por Plan Premium (disponibles para TODOS los rubros con plan premium)
            if (in_array($license->plan, ['premium', 'pro', 'enterprise'])) {
                array_push(
                    $businessAddons,
                    'current_accounts',
                    'multi_caja',
                    'predictive_alerts',
                    'checks',
                    'suppliers',
                    'expenses',
                    'multi_rubro',
                    'ecommerce_sync',
                    'quotes'
                );
            }

            // 3. Múltiples listas de precios:
            // - Incluido en plan Premium (cualquier rubro)
            // - Incluido en Ferretería / Corralón (cualquier plan, incluso Básico)
            if (in_array($license->plan, ['premium', 'pro', 'enterprise']) || $license->business_type === License::BUSINESS_HARDWARE) {
                array_push($businessAddons, 'multiple_prices');
            }

            // 4. Módulos Nativos por Vertical / Rubro:
            // quotes y logistics son nativos del flujo de Ferretería/Corralón en cualquier plan.
            if ($license->business_type === License::BUSINESS_HARDWARE) {
                array_push($businessAddons, 'quotes', 'logistics');
            }

            // Módulos extra/individuales pagados por el cliente (ej: logistics para un plan Retail)
            // La actualización de plan no borrará los módulos extra que un cliente ya haya pagado.
            $adminAddons = is_array($license->allowed_addons) ? $license->allowed_addons : [];
            $addons = array_values(array_unique(array_merge($businessAddons, $adminAddons)));

            $features = $this->mapFeatures($addons, $license);
            $normalizedPlan = ($license->plan === 'basico') ? 'basic' : (($license->plan === 'premium') ? 'pro' : $license->plan);
            $serverTime = now()->toIso8601String();
            $expiresAt = $license->expiration_date
                ? \Carbon\Carbon::parse($license->expiration_date)->endOfDay()->toIso8601String()
                : null;

            $payload = [
                'status'                => 'active',
                // Retrocompatibilidad: Si el cliente viejo usa hardcoded 'basic', devolvemos 'basic'
                'plan'                  => $normalizedPlan,
                'plan_type'             => $license->plan_type,
                'server_time'           => $serverTime,
                'expires_at'            => $expiresAt,
                'client_name'           => $license->client_name,
                'business_type'         => $license->business_type,
                // Nombres nuevos para clientes nuevos
                'plan_espanol'          => $license->plan,
                'features'              => $features,
                'nonce'                 => $nonce,
            ];

            $payload['signature'] = $this->signValidationPayload($payload, $installationId);

            return response()->json($payload, 200);
        });
    }

    /**
     * Firma criptográficamente el payload de validación para evitar spoofing/replay (L-01, L-17).
     */
    private function signValidationPayload(array $payload, string $installationId): string
    {
        $secret = (string) (config('app.license_signing_secret') ?: config('app.key') ?: 'pos-default-signing-key');
        $features = $payload['features'] ?? [];
        ksort($features);

        $canonical = implode('|', [
            (string) ($payload['status'] ?? ''),
            (string) ($payload['plan'] ?? ''),
            (string) ($payload['plan_type'] ?? ''),
            (string) ($payload['business_type'] ?? ''),
            $installationId,
            (string) ($payload['expires_at'] ?? ''),
            (string) ($payload['server_time'] ?? ''),
            (string) ($payload['nonce'] ?? ''),
            json_encode($features, JSON_UNESCAPED_SLASHES),
        ]);

        return hash_hmac('sha256', $canonical, $secret);
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
            'checks',
            'mobile_app',
            'remote_access',
            'suppliers',
            'expenses',
            'multi_rubro',
            'mercadopago_qr',
            'arca_afip',
            'ecommerce_sync',
        ];

        $map = [];
        foreach ($allFeatures as $feature) {
            $hasAddon = in_array($feature, $addons);

            // Respetar estrictamente los módulos individuales que el cliente/administrador haya habilitado específicamente
            if (in_array($feature, $adminAddons)) {
                $map[$feature] = true;
                continue;
            }

            // Exclusividad nativa por Rubro:
            // logistics es automático solo para Ferretería.
            // Para Retail es false por defecto a menos que se haya asignado en allowed_addons (evaluado arriba).
            if ($feature === 'logistics') {
                if (!$isHardwareStore) {
                    $map[$feature] = false;
                    continue;
                }
            }

            $map[$feature] = $hasAddon;
        }

        return $map;
    }
}
