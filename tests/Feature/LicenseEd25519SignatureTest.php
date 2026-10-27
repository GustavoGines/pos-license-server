<?php

namespace Tests\Feature;

use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * FINDING-LIC-01: /api/validate firma con Ed25519 (asimétrico) y el cliente
 * puede verificar la respuesta usando únicamente la clave pública.
 */
class LicenseEd25519SignatureTest extends TestCase
{
    use RefreshDatabase;

    private function crearLicencia(): License
    {
        return License::create([
            'client_name' => 'Comercio Ed25519',
            'business_type' => License::BUSINESS_RETAIL,
            'plan' => License::PLAN_PREMIUM,
            'plan_type' => License::TYPE_SAAS,
            'is_active' => true,
            'expiration_date' => now()->addDays(30)->format('Y-m-d'),
            'allowed_addons' => [],
        ]);
    }

    private function canonical(array $data, string $installationId): string
    {
        $features = $data['features'] ?? [];
        ksort($features);

        return implode('|', [
            (string) ($data['status'] ?? ''),
            (string) ($data['plan'] ?? ''),
            (string) ($data['plan_type'] ?? ''),
            (string) ($data['business_type'] ?? ''),
            $installationId,
            (string) ($data['expires_at'] ?? ''),
            (string) ($data['server_time'] ?? ''),
            (string) ($data['nonce'] ?? ''),
            json_encode($features, JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function test_validate_returns_ed25519_signature_verifiable_with_public_key_only(): void
    {
        $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
        $publicKey = sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair($seed));
        Config::set('app.license_ed25519_private_key', bin2hex($seed));

        $license = $this->crearLicencia();

        $response = $this->postJson('/api/validate', [
            'license_key' => $license->api_key,
            'installation_id' => 'device-ed25519-001',
            'nonce' => 'nonce-ed25519-abc',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['signature', 'signature_ed25519']);

        $data = $response->json();
        $signatureHex = (string) $data['signature_ed25519'];
        $this->assertSame(128, strlen($signatureHex));

        $this->assertTrue(sodium_crypto_sign_verify_detached(
            hex2bin($signatureHex),
            $this->canonical($data, 'device-ed25519-001'),
            $publicKey
        ));

        // Alterar cualquier campo (p. ej. plan_type -> lifetime) invalida la firma.
        $data['plan_type'] = 'lifetime';
        $this->assertFalse(sodium_crypto_sign_verify_detached(
            hex2bin($signatureHex),
            $this->canonical($data, 'device-ed25519-001'),
            $publicKey
        ));
    }

    public function test_validate_omits_ed25519_signature_when_private_key_missing_or_invalid(): void
    {
        $license = $this->crearLicencia();

        foreach (['', 'not-hex-key', str_repeat('a', 10)] as $invalidKey) {
            Config::set('app.license_ed25519_private_key', $invalidKey);

            $response = $this->postJson('/api/validate', [
                'license_key' => $license->api_key,
                'installation_id' => 'device-ed25519-002',
                'nonce' => 'nonce-missing-key',
            ]);

            $response->assertStatus(200)->assertJsonMissingPath('signature_ed25519');
        }
    }
}
