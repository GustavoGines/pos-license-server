# Project: QA Audit — pos-license-server (Fases 0 y 1)

## Architecture
El repositorio `pos-license-server` es una aplicación Laravel 13 con Filament v5 que gestiona la validación centralizada de licencias, control DRM por candado de hardware (`installation_id`), y distribución de actualizaciones de software para el ecosistema Sistema POS.

### Flujo de Datos y Componentes Clave:
1. **Cliente POS (`pos-backend`)** → `POST /api/validate` (`LicenseValidationController::validateKey`)
2. **Modelo Eloquent `License`**: almacena `plan` ('basico', 'premium'), `plan_type` ('saas', 'lifetime'), `business_type` ('retail', 'hardware_store'), `allowed_addons` (JSON), `installation_id` y `api_key`.
3. **Resolución de Capacidades (`mapFeatures`)**:
   - Módulos Base: `fast_pos`, `z_reports`
   - Módulos Premium: `multi_caja`, `current_accounts`, `advanced_reports`, `predictive_alerts`, `checks`, `suppliers`, `expenses`, `multi_rubro`, `mercadopago_qr`, `arca_afip`, `multiple_prices`
   - Módulos Verticales: `quotes`, `logistics` (exclusivos para `hardware_store`)
   - Módulos Addon-Only: `mobile_app`, `remote_access`
   - Overrides Flexibles: Cualquier módulo en `allowed_addons` se activa en `true` (excepto exclusividad de hardware que se anula en retail).
4. **Panel Administrativo Filament (`app/Filament`)**: Gestión CRUD de licencias y releases de software.

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | Standard Basic Plan Flags | Emisión de flags base (`fast_pos`, `z_reports`) y desactivación de premium | M1 | Survey / Spec |
| 2 | Standard Premium Plan Flags | Emisión de flags base + 11 módulos premium (`multi_rubro`, `mercadopago_qr`, `arca_afip`, etc.) | M1 | Survey / Spec |
| 3 | Flexible Module Override (suppliers) | Habilitación de módulo extra (`suppliers`) en plan Básico mediante `allowed_addons` | M1 | Survey / Spec |
| 4 | Discrepancy Check: Spanish key `proveedores` | Evaluación empírica de clave en español vs clave en inglés `suppliers` | M1 | Survey / Explorer |
| 5 | Vertical Restriction Enforcement | Bloqueo de `quotes` y `logistics` en comercios retail incluso con override en `allowed_addons` | M1 | Survey / Spec |
| 6 | DRM & Hardware Lock Verification | Vinculación en primera activación y bloqueo ante `installation_id` divergente | M1 | Survey / Spec |
| 7 | SaaS vs Lifetime Expiration | Expiración estricta de SaaS tras `endOfDay()` e inmunidad en Lifetime | M1 | Survey / Spec |
| 8 | Filament UI Gap Analysis | Detección de omisión de `multi_rubro`, `mercadopago_qr`, `arca_afip` en `LicenseForm.php` | M2 | Survey / Explorer |
| 9 | Security Vulnerability: ReleaseController CI Token | Bypass de autenticación en `POST /api/releases/new` cuando `CI_DEPLOY_TOKEN` es null | M2 | Survey / Explorer |
| 10 | Security Analysis: Public Route Rate Limiting | Ausencia de middleware `throttle` en `/api/validate` y `/api/check-update` | M2 | Survey / Explorer |
| 11 | Phase 0/1 Readiness Evaluation | Evaluación de preparación frente a seguridad y jerarquía modular Rubro->Categoría | M2 | Survey / Spec |
| 12 | Consolidación de QA_AUDIT_REPORT.md | Generación del reporte formal de auditoría en la raíz del repositorio | M2 | User Request |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | M1: Empirical Test Suite Creation & Execution | Crear y ejecutar suites de prueba PHPUnit y CLI que validen flags estándar, overrides (`suppliers`), y restricciones | Survey | DONE |
| 2 | M2: QA Audit Report Generation | Redacción y generación de `QA_AUDIT_REPORT.md` en raíz con resultados de tests, bugs y discrepancias | M1 | DONE |
| 3 | M3: Verification, Independent Review & Audit Gate | Revisión independiente (Reviewers, Challengers, Forensic Auditor) y verificación de criterios de aceptación | M2 | DONE |

## Interface Contracts
### API /api/validate Contract
- **Entrada**: `{ "license_key": string, "installation_id": string }`
- **Salida 200 OK**:
  `{ "status": "active", "plan": "basic"|"pro", "plan_espanol": "basico"|"premium", "business_type": string, "features": { [key: string]: boolean } }`
- **Errores**:
  - `403` con `{ "status": "error"|"suspended"|"expired", "message": string }`
  - `422` con `{ "errors": { ... } }`

## Code Layout
- `app/Http/Controllers/Api/LicenseValidationController.php`: Lógica de validación y resolución de capacidades.
- `app/Http/Controllers/Api/ReleaseController.php`: Consulta y registro de versiones CI/CD.
- `app/Models/License.php`: Modelo de licencias y casts.
- `app/Filament/Resources/Licenses/Schemas/LicenseForm.php`: Esquema del formulario administrativo.
- `tests/Feature/PlanValidationTest.php`: Suite oficial de pruebas automatizadas creada para la auditoría (10 tests, 81 aserciones).
- `QA_AUDIT_REPORT.md`: Reporte final de auditoría en raíz (8 secciones exhaustivas).
