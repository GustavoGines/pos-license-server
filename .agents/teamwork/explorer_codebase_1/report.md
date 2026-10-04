# Reporte de Auditoría Técnica y Exploración de Código: `pos-license-server`

**Fecha:** 3 de Octubre de 2026  
**Rama activa:** `feature/fase-1-rubros-jerarquia`  
**Agente auditor:** `explorer_codebase_1` (Teamwork Explorer)  
**Entorno de referencia:** `C:\laragon\www\pos-license-server` y `C:\laragon\www\Sistema_POS`  

---

## 1. Resumen Ejecutivo

Se completó una auditoría estática y empírica exhaustiva del repositorio `pos-license-server` para evaluar la arquitectura de licenciamiento, la gestión de planes (**Básico** vs **Premium**), el mecanismo de asignación flexible de módulos (overrides individuales como `proveedores`/`suppliers`), y la preparación del sistema frente a las Fases 0 y 1 del `PLAN_IMPLEMENTACION.md`.

### Hallazgos Principales:
1. **Soporte de Overrides Manuales (Respaldado por BD y API)**: El sistema **SÍ soporta** de forma nativa a nivel de base de datos (`allowed_addons` JSON) y en el endpoint de validación (`POST /api/validate`) la habilitación de módulos individuales (como `suppliers` o `expenses`) en licencias con Plan Básico. Las pruebas empíricas demuestran que el validador emite `suppliers: true` manteniendo el plan `basic` y los demás flags restringidos.
2. **Brecha Crítica de UI en Filament (`LicenseForm.php`)**: Mientras que `LicenseValidationController.php` incorporó los nuevos flags de las Fases 0 y 1 (`multi_rubro`, `mercadopago_qr`, `arca_afip`), estos **NO fueron agregados** al array `options` del componente `allowed_addons` en el formulario del panel de administración Filament (`LicenseForm.php`). En consecuencia, un administrador no puede activar estos tres módulos de forma granular desde la UI para clientes del Plan Básico.
3. **Exclusividad Estricta por Vertical que Anula Overrides**: En `LicenseValidationController::mapFeatures()`, los módulos exclusivos de ferretería (`quotes` y `logistics`) tienen una regla que los fuerza a `false` si el negocio es `retail`, anulando intencionalmente cualquier intento de override manual en `allowed_addons`.
4. **Vulnerabilidad Crítica de Seguridad en Releases (`ReleaseController::store`)**: El endpoint `POST /api/releases/new` compara `$request->input('token') !== config('app.ci_deploy_token', env('CI_DEPLOY_TOKEN'))`. Cuando la variable `CI_DEPLOY_TOKEN` no está configurada o cuando se ejecuta `config:cache` en producción, `$expectedToken` es `null`. Una petición sin token (`token: null`) evalúa `null !== null` como falso, permitiendo a cualquier actor no autenticado registrar releases maliciosos con URLs de descarga arbitrarias para toda la red de terminales POS.
5. **Ausencia de Rate Limiting en Endpoint Público**: `POST /api/validate` carece de middleware de limitación de tasa (`throttle`), exponiendo el endpoint a ataques de fuerza bruta contra las claves de licencia.
6. **Carencia Total de Pruebas Automatizadas**: El repositorio no posee pruebas unitarias ni de integración para su lógica de negocio (solo los archivos esqueleto por defecto de Laravel).

---

## 2. Mapeo Exhaustivo de Componentes del Sistema

### 2.1 Modelos (`app/Models`)
| Modelo | Ubicación | Responsabilidad y Atributos Clave |
|---|---|---|
| `License` | `app/Models/License.php` | Modelo nuclear. Fillables: `uuid`, `client_name`, `business_type`, `api_key`, `plan`, `plan_type`, `is_active`, `expiration_date`, `allowed_addons`, `installation_id`. Casts: `is_active => boolean`, `expiration_date => date`, `allowed_addons => array`. Boot hook genera automáticamente `uuid` y `api_key = 'pos_' . Str::random(32)`. |
| `Release` | `app/Models/Release.php` | Gestión de actualizaciones de software. Fillables: `version`, `component`, `download_url`, `changelog`, `is_critical`, `channel`. Casts: `is_critical => boolean`. |
| `User` | `app/Models/User.php` | Usuarios administradores del panel Filament. Implementa `FilamentUser` con `canAccessPanel() => true`. |

### 2.2 Migraciones de Base de Datos (`database/migrations`)
1. `0001_01_01_000000_create_users_table.php`: Tablas `users`, `password_reset_tokens`, `sessions`.
2. `0001_01_01_000001_create_cache_table.php`: Tablas `cache`, `cache_locks`.
3. `0001_01_01_000002_create_jobs_table.php`: Tablas `jobs`, `job_batches`, `failed_jobs`.
4. `2026_03_24_205138_create_licenses_table.php`: Creación inicial con `plan_type` ENUM (`basic`, `pro`, `enterprise`), `allowed_addons` JSON.
5. `2026_03_24_205804_create_personal_access_tokens_table.php`: Tokens Sanctum.
6. `2026_03_29_193853_rename_plan_type_to_plan_in_licenses_table.php`: Renombra `plan_type` a `plan` y crea nuevo campo `plan_type` ENUM (`saas`, `lifetime`).
7. `2026_03_29_194059_add_installation_id_to_licenses_table.php`: Agrega columna `installation_id` string nullable.
8. `2026_04_08_195902_add_business_type_to_licenses_table.php`: Agrega columna `business_type` ENUM (`retail`, `hardware_store`) con default `retail`.
9. `2026_04_13_135355_create_releases_table.php`: Creación inicial de tabla `releases`.
10. `2026_04_14_003738_add_component_to_releases_table.php`: Agrega columna `component` string con default `frontend`.
11. `2026_04_14_043511_drop_version_unique_from_releases_table.php`: Elimina restricción UNIQUE sobre `version` para permitir versionado por componente.
12. `2026_04_16_230000_update_plan_enum_in_licenses_table.php`: Actualiza ENUM de `plan` a (`basico`, `premium`), migrando datos previos de forma universal (PostgreSQL, MySQL, SQLite).
13. `2026_04_27_195915_add_channel_to_releases_table.php`: Agrega columna `channel` string con default `stable`.

### 2.3 Seeders (`database/seeders`)
- `Database\Seeders\DatabaseSeeder`: Crea usuario administrador inicial (`admin@posserver.com`). No existen seeders de prueba para licencias o releases.

### 2.4 Controladores y Rutas (`routes/api.php`, `app/Http/Controllers/Api`)
| Ruta HTTP | Método | Controlador / Método | Middleware | Descripción |
|---|---|---|---|---|
| `/api/validate` | `POST` | `LicenseValidationController@validateKey` | Ninguno (público) | Validación de licencia, vinculación DRM por `installation_id`, resolución de features. |
| `/api/check-update` | `GET` | `ReleaseController@checkUpdate` | Ninguno (público) | Consulta semántica de actualizaciones por componente y canal. |
| `/api/releases/new` | `POST` | `ReleaseController@store` | Ninguno (token en payload) | Registro de nuevas versiones desde CI/CD. |
| `/api/user` | `GET` | Closure | `auth:sanctum` | Obtención de usuario autenticado. |

### 2.5 Panel de Administración Filament (`app/Filament`)
- `LicenseResource` (`app/Filament/Resources/Licenses/LicenseResource.php`): Recurso principal para Filament v5.
- `LicenseForm` (`app/Filament/Resources/Licenses/Schemas/LicenseForm.php`): Formulario con campos `client_name`, `business_type`, `api_key` (readonly), `plan` (`basico`, `premium`), `plan_type` (`saas`, `lifetime`), `expiration_date` (reactivo), `installation_id` (readonly), `allowed_addons` (select múltiple) y `is_active` (toggle).
- `LicensesTable` (`app/Filament/Resources/Licenses/Tables/LicensesTable.php`): Tabla interactiva con badges, filtros por `plan`, `plan_type`, `is_active`, y acciones: `reset_hardware_lock` (libera candado físico) y `copy_api_key`.
- `EditLicense` (`app/Filament/Resources/Licenses/Pages/EditLicense.php`): Acción de cabecera duplicada para liberar hardware.
- `ReleaseResource` (`app/Filament/Resources/ReleaseResource.php`): Formulario y tabla para releases de software con soporte de SemVer, componente, canal (`beta`/`stable`), y flags de actualización crítica.

### 2.6 Capas Ausentes (Déficit Arquitectónico)
- **Servicios (`app/Services`)**: No existe capa de servicios. Toda la lógica de resolución de módulos y validaciones DRM está inyectada monolíticamente en `LicenseValidationController`.
- **Form Requests (`app/Http/Requests`)**: No existen Form Requests dedicados. Las validaciones se ejecutan inline mediante `$request->validate()`.
- **Enums PHP 8.1+ (`app/Enums`)**: No existen Enums tipados para Planes, Tipos de Negocio o Features. Se utilizan constantes en el modelo `License` y cadenas mágicas repetidas en controladores y formularios.

---

## 3. Implementación de Planes y Licencias

### 3.1 Niveles de Acceso y Modelos de Facturación
El sistema separa dos dimensiones ortogonales:
1. **Nivel de Acceso (`plan`)**:
   - `basico`: Nivel de entrada con funcionalidades esenciales de venta rápida y arqueos.
   - `premium`: Nivel completo con módulos avanzados.
2. **Modelo de Facturación (`plan_type`)**:
   - `saas`: Suscripción temporal sujeta a `expiration_date`. Se evalúa: `\Carbon\Carbon::parse($license->expiration_date)->endOfDay()->isPast()`.
   - `lifetime`: Licencia perpetua sin fecha de caducidad.

### 3.2 Protección DRM y Candado de Hardware (`installation_id`)
En `LicenseValidationController.php` (líneas 48-56):
- Al activarse por primera vez una licencia (`installation_id` nulo o vacío), el sistema vincula automáticamente el identificador enviado por el cliente:
  ```php
  if (empty($license->installation_id)) {
      $license->installation_id = $installationId;
      $license->save();
  } elseif ($license->installation_id !== $installationId) {
      return response()->json([
          'status'  => 'error',
          'message' => 'Esta licencia ya está vinculada a otra instalación.',
      ], 403);
  }
  ```
- Si una terminal con distinto `installation_id` intenta usar la misma API Key, es rechazada con HTTP 403.
- El panel de Filament provee la acción `reset_hardware_lock` en `LicensesTable` y `EditLicense` para que un operador pueda desvincular el equipo de forma controlada.

### 3.3 Retrocompatibilidad de Nombres de Planes
Para proteger clientes legacy que consumen el backend de POS y apps móviles:
- En la respuesta JSON de `validateKey`:
  ```php
  'plan' => ($license->plan === 'basico') ? 'basic' : (($license->plan === 'premium') ? 'pro' : $license->plan),
  'plan_espanol' => $license->plan,
  ```
- Un plan `basico` se traduce a `basic`.
- Un plan `premium` se traduce a `pro` en el campo `plan`, y a `premium` en `plan_espanol`.
- Esto garantiza compatibilidad simultánea con versiones antiguas del POS que verificaban `$data['plan'] === 'pro'` y con versiones modernas que verifican `$data['plan_espanol'] === 'premium'`.

---

## 4. Cálculo, Almacenamiento y Verificación de Flags/Permisos

### 4.1 Catálogo Completo de Features (17 Módulos)
El validador mapea 17 características en `mapFeatures()`:

| Identificador Feature | Categoría | Plan por Defecto | Restricción Vertical |
|---|---|---|---|
| `fast_pos` | Base / Esencial | Básico y Premium | Global |
| `z_reports` | Base / Esencial | Básico y Premium | Global |
| `quotes` | Vertical Ferretería | Hardware Store | Exclusivo `hardware_store` |
| `logistics` | Vertical Ferretería | Hardware Store | Exclusivo `hardware_store` |
| `current_accounts` | Comercial | Premium | Global |
| `multiple_prices` | Comercial | Premium | Global |
| `multi_caja` | Operativo | Premium | Global |
| `advanced_reports` | Analítica | Premium | Global |
| `predictive_alerts` | Logística / IA | Premium | Global |
| `checks` | Financiero | Premium | Global |
| `suppliers` | B2B / Abastecimiento | Premium | Global |
| `expenses` | Financiero | Premium | Global |
| `multi_rubro` | Catálogo (Fase 1) | Premium | Global |
| `mercadopago_qr` | Pasarela (Fase 0/3) | Premium | Global |
| `arca_afip` | Facturación Fiscal (Fase 0/4) | Premium | Global |
| `mobile_app` | Standalone Addon | Ninguno (Addon manual) | Global |
| `remote_access` | Standalone Addon | Ninguno (Addon manual) | Global |

### 4.2 Lógica de Resolución de Capacidades
El flujo de cálculo en `LicenseValidationController` se ejecuta en 4 etapas:
1. **Módulos Base**: Siempre se incluyen `['fast_pos', 'z_reports']`.
2. **Módulos por Plan**: Si el plan es `premium` (o `pro`/`enterprise`), se agregan `multi_caja`, `current_accounts`, `advanced_reports`, `predictive_alerts`, `checks`, `suppliers`, `expenses`, `multi_rubro`, `mercadopago_qr`, `arca_afip` y `multiple_prices`.
3. **Módulos por Vertical**: Si `business_type === 'hardware_store'`, se agregan `quotes` y `logistics`.
4. **Fusión con Overrides Manuales (`allowed_addons`)**:
   ```php
   $adminAddons = is_array($license->allowed_addons) ? $license->allowed_addons : [];
   $addons = array_values(array_unique(array_merge($businessAddons, $adminAddons)));
   ```
5. **Mapeo Booleano Estricto (`mapFeatures`)**:
   - Si el módulo es `quotes` o `logistics` y el comercio es `retail`, se fuerza a `false` (incluso si está en `allowed_addons`).
   - Si el módulo está en `$adminAddons`, se fuerza a `true`.
   - En cualquier otro caso, se evalúa `in_array($feature, $addons)`.

---

## 5. Análisis Específico de Overrides Manuales (Requisito 4)

### 5.1 Caso de Uso: Habilitar `proveedores` (`suppliers`) en Plan Básico
**Pregunta de auditoría:** ¿Está soportado técnicamente habilitar módulos individuales como `proveedores` en un plan restrictivo (Básico)?

**Hallazgo:** **SÍ, ESTÁ TOTALMENTE SOPORTADO A NIVEL DE CÓDIGO Y BASE DE DATOS.**

**Cadena de Evidencia:**
1. **Esquema de BD**: La columna `allowed_addons` en la tabla `licenses` es de tipo `json` nullable (`2026_03_24_205138_create_licenses_table.php`). Permite almacenar cualquier lista de claves arbitrarias.
2. **Modelo Eloquent**: `License.php` incluye `allowed_addons` en `$fillable` y en `$casts = ['allowed_addons' => 'array']`.
3. **Formulario Filament**: `LicenseForm.php` (línea 87) tiene configurado:
   `'suppliers' => '📦 Gestión de Proveedores (B2B)'` dentro de las opciones de `allowed_addons`.
4. **Controlador API**: En `LicenseValidationController.php`:
   - Con `$license->plan = 'basico'`, `$businessAddons` solo contiene `['fast_pos', 'z_reports']`.
   - Al tener `$license->allowed_addons = ['suppliers']`, `$adminAddons` es `['suppliers']`.
   - En `mapFeatures()`, la condición `in_array('suppliers', $adminAddons)` evalúa a `true`.
   - El resultado devuelto en `features` incluye `'suppliers' => true`, mientras que `'multi_caja'`, `'expenses'`, `'multi_rubro'`, etc., permanecen en `false`.
5. **Consumo en Backend POS (`pos-backend`)**:
   - `LicenseSyncService` guarda el diccionario completo en `business_settings.license_features_dict`.
   - La ruta `/api/suppliers` está protegida por `Route::middleware(['feature:suppliers'])`.
   - El middleware `CheckFeatureAccess` verifica `$features['suppliers'] === true`. Como es `true`, **permite el acceso**, a pesar de que el plan sea Básico.

### 5.2 Brecha Crítica Detectada: Nuevos Módulos de Fase 0 y 1 en la UI
A pesar de que el mecanismo funciona para `suppliers`, existe una **inconsistencia severa** con los nuevos módulos introducidos en el commit `d6cbbff`:
- En `LicenseValidationController.php` se añadieron: `'multi_rubro'`, `'mercadopago_qr'`, `'arca_afip'`.
- En `LicenseForm.php`, el selector `allowed_addons` **NO incluye** estas tres opciones.
- **Impacto Real**: Si un cliente con Plan Básico contrata el módulo adicional de "Facturación Electrónica ARCA" o "Múltiples Rubros", el administrador del sistema **no puede habilitarlo desde la interfaz web de Filament** porque no figura en la lista desplegable. Tendría que modificarse la base de datos de forma directa por SQL/Tinker.

### 5.3 Limitación Intencionada: Exclusividad de Rubro vs Overrides
En `mapFeatures()`:
```php
if (in_array($feature, ['logistics', 'quotes'])) {
    if (!$isHardwareStore) {
        $map[$feature] = false;
        continue;
    }
}
```
Si un comercio con rubro `retail` solicita presupuestos (`quotes`) o logística (`logistics`), el sistema bloquea el módulo forzándolo a `false`, incluso si el administrador lo incluye explícitamente en `allowed_addons`. Esta regla de negocio está codificada como una exclusividad vertical rígida.

---

## 6. Riesgos de Seguridad, Bugs y Desviaciones de Fase 0 y 1

### 6.1 Vulnerabilidad Crítica de Seguridad: Inyección no Autenticada de Releases
- **Archivo afectado**: `app/Http/Controllers/Api/ReleaseController.php` (líneas 69-74).
- **Código vulnerable**:
  ```php
  $expectedToken = config('app.ci_deploy_token', env('CI_DEPLOY_TOKEN'));
  if ($request->input('token') !== $expectedToken) {
      return response()->json(['error' => 'Unauthorized'], 401);
  }
  ```
- **Causa raíz**:
  1. La variable `CI_DEPLOY_TOKEN` no está definida en `config/app.php` ni en `.env.example`.
  2. Cuando una aplicación Laravel en producción ejecuta `php artisan config:cache`, las funciones `env()` devuelven `null` en todos los controladores.
  3. Si `$expectedToken` es `null`, cualquier petición HTTP enviada a `POST /api/releases/new` sin el campo `token` (o con `token: null`) provoca que `$request->input('token')` sea `null`.
  4. La comparación `null !== null` es `false`, por lo que **la condición de error no se ejecuta y se otorga acceso no autorizado**.
- **Impacto**: Un atacante en Internet puede inyectar un release falso con versión superior (ej: `v9.9.9`), componente `frontend` o `backend`, y una `download_url` apuntando a un binario troyano. La red de terminales POS conectadas al servidor descargaría y ejecutaría el código malicioso automáticamente.

### 6.2 Riesgo de Seguridad: Falta de Rate Limiting en API Pública
- **Archivo afectado**: `routes/api.php` (líneas 13-16).
- **Observación**: Las rutas `POST /api/validate` y `GET /api/check-update` no cuentan con middleware `throttle` (ej: `throttle:60,1`).
- **Impacto**: Exposición a ataques de denegación de servicio (DoS) y fuerza bruta contra claves de licencia y versiones de clientes.

### 6.3 Bug Potencial: Búsqueda en Campo JSON en Filament Table
- **Archivo afectado**: `app/Filament/Resources/Licenses/Tables/LicensesTable.php` (líneas 95-99).
- **Código**:
  ```php
  TextColumn::make('allowed_addons')
      ->label('Módulos')
      ->badge()
      ->searchable()
      ->toggleable(),
  ```
- **Observación**: En bases de datos relacionales estrictas (como PostgreSQL o SQLite antiguo), invocar `searchable()` (que ejecuta un `LIKE %...%`) directamente sobre una columna de tipo `json` o `jsonb` sin un `cast` explícito a texto puede arrojar un error de base de datos (`QueryException: operator does not exist: json ~~ unknown`).

### 6.4 Desviación de Fase 0 y Fase 1 del `PLAN_IMPLEMENTACION.md`
- **Fase 0 (Blindaje de Seguridad)**: En `PLAN_IMPLEMENTACION.md`, la Fase 0 exige asegurar que las credenciales no se filtren. En `pos-license-server`, el token de CI/CD para releases está desprotegido ante configuraciones vacías o caché de configuración.
- **Fase 1 (Rubros y Feature Flags)**: La integración de flags está completa en el validador, pero incompleta en el panel administrativo (`LicenseForm.php`).

---

## 7. Resultados de Verificación Empírica

Se ejecutó un script de verificación automatizado en el entorno local (`verify_empirical.php`) conectando con el kernel de Laravel.

### Salida Verbatim del Script:
```
=== EMPIRICAL VERIFICATION SCRIPT ===

1. Checking LicenseForm allowed_addons options:
 - 'suppliers' in LicenseForm: YES
 - 'multi_rubro' in LicenseForm: NO
 - 'mercadopago_qr' in LicenseForm: NO
 - 'arca_afip' in LicenseForm: NO

2. Testing LicenseValidationController feature calculations:
Case A: Basic Plan (Retail, no addons):
 - fast_pos: true
 - z_reports: true
 - suppliers: false
 - multi_rubro: false
 - mercadopago_qr: false
 - arca_afip: false

Case B: Premium Plan (Retail, no addons):
 - fast_pos: true
 - suppliers: true
 - multi_rubro: true
 - mercadopago_qr: true
 - arca_afip: true
 - quotes (hardware only): false
 - logistics (hardware only): false

Case C: Basic Plan with 'suppliers' override in allowed_addons:
 - fast_pos: true
 - suppliers: true
 - expenses: false
 - multi_rubro: false

Case D: Retail trying to override 'quotes' in allowed_addons:
 - quotes: false (Expected false due to vertical restriction)

3. Security Analysis: ReleaseController store token check:
 - config('app.ci_deploy_token'): NULL
 - env('CI_DEPLOY_TOKEN'): NULL
 - Evaluated expectedToken: NULL
 - If expectedToken is null and request sends no token, is authorized? YES (VULNERABILITY!)

=== END OF SCRIPT ===
```

---

## 8. Recomendaciones de Remediación

Como parte del protocolo de auditoría (sin alterar el código fuente en esta etapa de diagnóstico), se presentan las propuestas de corrección listas para implementación:

### Propuesta 1: Completar `LicenseForm.php` con flags de Fase 0 y 1
En `app/Filament/Resources/Licenses/Schemas/LicenseForm.php`, añadir al array de `allowed_addons`:
```php
'multi_rubro'       => '🗂️ Múltiples Rubros (Catálogo)',
'mercadopago_qr'    => '📱 Mercado Pago QR (Cobro Dinámico)',
'arca_afip'         => '🏛️ Facturación Fiscal ARCA (ex-AFIP)',
```

### Propuesta 2: Remediar la Vulnerabilidad Crítica en `ReleaseController.php`
1. Agregar en `config/app.php`:
   ```php
   'ci_deploy_token' => env('CI_DEPLOY_TOKEN'),
   ```
2. Modificar la validación en `app/Http/Controllers/Api/ReleaseController.php`:
   ```php
   $expectedToken = config('app.ci_deploy_token');
   $providedToken = $request->input('token');

   if (empty($expectedToken) || empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
       return response()->json(['error' => 'Unauthorized'], 401);
   }
   ```
   *Uso de `hash_equals` para prevenir ataques de temporización (timing attacks) y comprobación explícita de `empty()` para evitar accesos con tokens nulos.*

### Propuesta 3: Blindaje de Rutas API con Rate Limiting
En `routes/api.php`:
```php
Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/validate', [LicenseValidationController::class, 'validateKey']);
    Route::get('/check-update', [ReleaseController::class, 'checkUpdate']);
});
```

### Propuesta 4: Desacoplar Lógica de Licencias a un Servicio Dedicado
Crear `app/Services/LicenseResolutionService.php` y Form Requests (`ValidateLicenseRequest`, `StoreReleaseRequest`), reduciendo la complejidad ciclomática de los controladores y facilitando la creación de pruebas unitarias con PHPUnit.
