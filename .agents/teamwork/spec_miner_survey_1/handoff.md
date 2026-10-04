# Handoff Report: Specification Mining Survey (Fases 0 y 1)

**Agent:** `spec_miner_survey_1`  
**Task:** Survey and specification extraction for Phase 0 and Phase 1 from `PLAN_IMPLEMENTACION.md` and related repos.  
**Report Destination:** `C:\laragon\www\pos-license-server\.agents\teamwork\spec_miner_survey_1\report.md`  
**Handoff Type:** Hard (Task complete)

---

## 1. Observation

1. **Documento Maestro**: `C:\laragon\www\Sistema_POS\PLAN_DE_IMPLEMENTACION.md`
   - Líneas 71-79: Establece cronología estricta donde **Fase 0** (Blindaje de Seguridad y Gestión de Credenciales) precede a **Fase 1** (Arquitectura Jerárquica Rubro -> Categoría).
   - Líneas 642-653 (§4.6): Especifica que `pos-license-server` debe incorporar los flags `'multi_rubro'`, `'mercadopago_qr'`, `'arca_afip'`. Plan Básico: `multi_rubro = false`; Plan Premium: `multi_rubro = true`, `mercadopago_qr = true`, `arca_afip = true`.
   - Líneas 493-525 (§4.1): Especifica que `GET /api/settings` debe filtrar claves públicas, `mp_access_token` debe cifrarse con `Crypt::encryptString`, y certificados de AFIP deben aislarse en `app/private/afip/{cuit}/`.
   - Líneas 374-448 (§3.3) y 583-619 (§4.4): Especifica tabla `rubros` (`id`, `name`, `is_system`, timestamps), `categories.rubro_id`, backfill por tipo de negocio (`hardware_store` -> Ferretería, etc.), y forzado de rubro sistema en Básico (`$isPremium = in_array(strtolower($plan), ['premium', 'pro']) || !empty($features['multi_rubro'])`).

2. **Servidor de Licencias (`pos-license-server`)**:
   - `app/Http/Controllers/Api/LicenseValidationController.php`:
     - Línea 65: `array_push($businessAddons, 'multi_caja', 'current_accounts', 'advanced_reports', 'predictive_alerts', 'checks', 'suppliers', 'expenses', 'multi_rubro', 'mercadopago_qr', 'arca_afip');` (incorporado en commit `d6cbbff`).
     - Líneas 83-84: `$adminAddons = is_array($license->allowed_addons) ? $license->allowed_addons : []; $addons = array_values(array_unique(array_merge($businessAddons, $adminAddons)));`
     - Líneas 136-141: Si no es ferretería, `quotes` y `logistics` son forzados a `false` antes del chequeo de `$adminAddons`.
     - Líneas 144-147: `if (in_array($feature, $adminAddons)) { $map[$feature] = true; continue; }` (override manual).
     - Línea 91: `'plan' => ($license->plan === 'basico') ? 'basic' : (($license->plan === 'premium') ? 'pro' : $license->plan)`.
   - `app/Filament/Resources/Licenses/Schemas/LicenseForm.php`:
     - Líneas 71-90: El componente `Select::make('allowed_addons')` lista solo 14 módulos históricos (`fast_pos`, `z_reports`, `quotes`, `current_accounts`, `multiple_prices`, `multi_caja`, `mobile_app`, `remote_access`, `advanced_reports`, `predictive_alerts`, `logistics`, `checks`, `suppliers`, `expenses`).
     - **Discrepancia crítica observada**: Omitió `'multi_rubro'`, `'mercadopago_qr'` y `'arca_afip'`.
   - `tests/`:
     - Solo contiene `ExampleTest.php` en `Feature/` y `Unit/`. Cobertura de tests para validación de licencias: **0%**.

3. **Backend Cliente (`pos-backend`)**:
   - `app/Services/LicenseSyncService.php`:
     - Líneas 130-148: Failsafe local activa `multi_rubro = true` en planes `premium` y `pro`.
     - Línea 144, 249, 318: Escribe `$features['cheques'] = true;` (español), mientras que las rutas en `routes/api.php` línea 156 usan `feature:checks` (inglés).
     - Líneas 122-124: Intenta leer `$data['expires_at']`, `$data['next_payment_at']`, `$data['manage_url']`, los cuales no son emitidos por `pos-license-server`.
   - `app/Http/Middleware/CheckFeatureAccess.php`:
     - Líneas 26-44: Consulta `business_settings.license_features_dict`. Si el feature no es `true`, rechaza con HTTP 403 `FEATURE_NOT_LICENSED`.

---

## 2. Logic Chain

1. **Premisa**: El sistema requiere que un comercio con Plan Básico pueda acceder a módulos adicionales individuales (ej. 'proveedores' / 'suppliers' o 'multi_rubro') si fueron adquiridos individualmente (R2 del `ORIGINAL_REQUEST.md`).
2. **Observación**: En `LicenseValidationController.php`, `mapFeatures` comprueba `in_array($feature, $adminAddons)` y fuerza `$map[$feature] = true` aun cuando el plan de la licencia sea `'basico'` (`Observation 2`).
3. **Inferencia 1**: La lógica de evaluación en el controlador soporta técnica y algorítmicamente la asignación flexible para cualquier módulo registrado en `$allFeatures`.
4. **Observación**: En `LicenseForm.php`, el selector de Filament omite `'multi_rubro'`, `'mercadopago_qr'` y `'arca_afip'` (`Observation 2`).
5. **Inferencia 2**: Un administrador que intente habilitar 'multi_rubro' individualmente a un cliente con plan Básico a través del panel visual de Filament no podrá seleccionarlo. El override de `multi_rubro` solo funciona si se inyecta directamente en la base de datos o mediante seeder/API.
6. **Observación**: En `LicenseValidationController.php`, el aislamiento de ferretería (`if (in_array($feature, ['logistics', 'quotes'])) { if (!$isHardwareStore) { $map[$feature] = false; continue; } }`) ocurre ANTES de evaluar `$adminAddons` (`Observation 2`).
7. **Inferencia 3**: Ningún cliente de tipo Retail puede recibir `quotes` o `logistics`, incluso si un administrador los coloca manualmente en `allowed_addons`.
8. **Observación**: `pos-license-server` no cuenta con tests automatizados en `tests/` (`Observation 2`).
9. **Inferencia 4**: Para cumplir el requerimiento R3 del `ORIGINAL_REQUEST.md` (pruebas ejecutables), el equipo de testing/QA debe generar suites de pruebas que cubran tanto los planes estándar como las excepciones de addons individuales.

---

## 3. Caveats

1. **Solo Lectura Respetada**: No se alteró ningún archivo de código fuente ni de documentación en ninguno de los repositorios durante esta auditoría.
2. **Entorno de Red Remoto vs Local**: En producción, `pos-license-server` se aloja en Render (`https://pos-license-server-2jma.onrender.com`), mientras que las auditorías y pruebas locales operan sobre Laragon (`http://localhost` o base de datos local SQLite/MySQL).
3. **Firmas Criptográficas**: La especificación confirmó que el transporte de la licencia es HTTPS REST sincrónico y no requiere firma CMS/RSA de los payloads de licencia (la criptografía RSA y CMS PKCS#7 mencionada en el plan pertenece al módulo fiscal ARCA WSAA de Fase 4 y no al licenciamiento de Fase 0/1).

---

## 4. Conclusion

La especificación técnica de las Fases 0 y 1 ha sido extraída íntegramente y documentada en `report.md`. Se determinó que:
1. La arquitectura de planes diferencia taxativamente Básico (solo `fast_pos` y `z_reports`) de Premium (11 módulos adicionales, incluyendo `multi_rubro`, `mercadopago_qr` y `arca_afip`).
2. El mecanismo de override flexible (`allowed_addons`) está correctamente implementado en el controlador de validación para `suppliers` y otros módulos, pero existe una discrepancia en el formulario Filament (`LicenseForm.php`) que excluye `multi_rubro`, `mercadopago_qr` y `arca_afip` de la interfaz administrativa.
3. Se identificaron discrepancias secundarias: inconsistencia de nomenclatura (`checks` vs `cheques`) y omisión de fechas de expiración en el payload JSON de `/api/validate`.
4. El repositorio `pos-license-server` carece de cobertura de tests automatizados, requiriendo la creación de las suites especificadas en la sección 10 de `report.md`.

---

## 5. Verification Method

Para verificar independientemente los hallazgos:
1. **Inspección de código del validador de licencias**:
   - Abrir `C:\laragon\www\pos-license-server\app\Http\Controllers\Api\LicenseValidationController.php` y revisar líneas 60 a 153 para comprobar la lógica de addons, overrides y exclusión de ferretería.
2. **Inspección de opciones en Filament**:
   - Abrir `C:\laragon\www\pos-license-server\app\Filament\Resources\Licenses\Schemas\LicenseForm.php` y verificar líneas 71-90 para confirmar la ausencia de `'multi_rubro'`, `'mercadopago_qr'` y `'arca_afip'`.
3. **Inspección de inconsistencia en cliente**:
   - Abrir `C:\laragon\www\Sistema_POS\pos-backend\app\Services\LicenseSyncService.php` línea 144 (`cheques`) vs `routes/api.php` línea 156 (`checks`).
4. **Verificación del plan de Fase 1**:
   - Inspeccionar `C:\laragon\www\Sistema_POS\PLAN_DE_IMPLEMENTACION.md` secciones 4.4 y 4.6 para contrastar los requerimientos con la implementación.
