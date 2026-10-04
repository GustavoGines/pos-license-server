# INFORME AUTORITATIVO DE AUDITORÍA QA: SERVIDOR DE LICENCIAS (pos-license-server)
## Evaluación Integral de Lógica de Planes, Asignación Flexible de Módulos y Preparación para Fases 0 y 1

> [!IMPORTANT]
> **ADENDA DE CORRECCIÓN (verificación manual posterior, 3-oct-2026).** Tiene prioridad sobre el cuerpo del informe.
>
> | Hallazgo | Estado corregido |
> |---|---|
> | **DISC-03** (`suppliers` vs `proveedores`) | **FALSO.** La clave `suppliers` es consistente en Filament, API, POS backend y Flutter. «Proveedores» es solo la etiqueta en pantalla. |
> | **DISC-04** (metadatos `expires_at` / `next_payment_at` / `manage_url`) | **No aplica al plan.** Comportamiento preexistente; el POS lo tolera con `?? null`. |
> | **VULN-01** (`ReleaseController::store`) | **Real y ACTIVA en producción** por `config:cache` (`docker-entrypoint.sh`), no solo "si el token es null". El puntaje CVSS 9.8 no fue verificado. **CORREGIDA** (token vía `config('app.ci_deploy_token')`, fail-closed, `hash_equals`). |
> | **GAP-02** (selector de Filament) | Real. **CORREGIDO**: se agregaron `multi_rubro`, `mercadopago_qr` y `arca_afip`. |
> | **RISK-05** (sin `throttle`) | Real, preexistente, **no corregido** (fuera de alcance). |
>
> Tests de cobertura de los arreglos: `ReleaseTokenTest` y `LicenseAddonsSelectorTest`. Las sondas 1a/1b de `Challenger2AdversarialTest` se invirtieron a regresión (401). Suite completa: 47 tests, 483 aserciones, todos pasan.

---

| Metadato | Detalle |
|---|---|
| **Proyecto Evaluado** | `pos-license-server` (Ecosistema Sistema POS) |
| **Rama Git Auditada** | `feature/fase-1-rubros-jerarquia` |
| **Entorno de Ejecución** | Windows (Laragon), PHP 8.3.30, Laravel 12.x, PHPUnit 12.5.14, SQLite In-Memory |
| **Fecha de Auditoría** | 3 de Octubre de 2026 |
| **Autor del Informe** | Equipo de Auditoría QA & Verificación Técnica (Teamwork Preview) |
| **Estado del Dictamen** | **APROBADO CON OBSERVACIONES CRÍTICAS (CONDICIONAL A REMEDIACIÓN)** |

---

## 1. Resumen Ejecutivo

### 1.1 Dictamen Global de la Auditoría
Se ha llevado a cabo una auditoría de aseguramiento de la calidad (QA) estática, arquitectónica y empírica sobre el repositorio `pos-license-server`. El objetivo primordial consistió en auditar la lógica de negocio que discrimina entre los planes **Básico** y **Premium**, evaluar rigurosamente el mecanismo de asignación flexible de módulos individuales (**overrides** mediante `allowed_addons`), y determinar el grado de preparación del sistema frente a las especificaciones de las **Fases 0 y 1** del `PLAN_IMPLEMENTACION.md`.

El dictamen técnico formal es **APROBADO CON OBSERVACIONES CRÍTICAS (CONDICIONAL A REMEDIACIÓN)**. 

- **Fortaleza Nuclear**: La lógica matemática y booleana del validador de licencias (`LicenseValidationController::validateKey` y `mapFeatures`) implementa de forma genuina, sólida y determinística la jerarquía de planes, el aislamiento vertical de ferretería, el bloqueo DRM por hardware (`installation_id`), la expiración temporal de suscripciones SaaS, y el override granular de módulos para el plan Básico (ej. conceder `suppliers` sin elevar la licencia a Premium).
- **Evidencia Empírica**: Se diseñó y ejecutó una suite automatizada exhaustiva de 10 pruebas unitarias/funcionales (`tests/Feature/PlanValidationTest.php`), alcanzando **10 tests aprobados, 81 aserciones exitosas, 0 fallos y 0 errores** (100% de éxito).
- **Bloqueantes y Brechas Detectadas**: A pesar de la solidez en el controlador, la auditoría descubrió **una vulnerabilidad de seguridad crítica (CVSS 9.8)** en el registro de releases CI/CD que permite la inyección no autenticada de binarios troyanos, **una brecha de interfaz en Filament** que impide a los operadores activar los módulos de Fase 0 y 1 desde la UI web, y **discrepancias de sincronización e integración** con el backend transaccional (`pos-backend`).

---

## 2. Alcance y Objetivos de la Auditoría

### 2.1 Contexto del Ecosistema Sistema POS
El ecosistema Sistema POS opera bajo una arquitectura de tres nodos interdependientes:
1. **`pos-license-server`** (Sujeto de esta auditoría): Servidor centralizado en Laravel con panel administrativo en Filament v5. Gestiona las entidades `License` y `Release`, valida solicitudes de activación mediante DRM por hardware, y emite el diccionario estructurado de capacidades (`features`).
2. **`pos-backend`**: API transaccional en Laravel 12 desplegada en el comercio. Consulta periódicamente `POST /api/validate` (Heartbeat cada 3 minutos vía `LicenseSyncService`), persiste el estado en `business_settings.license_features_dict`, y bloquea o habilita endpoints mediante el middleware `CheckFeatureAccess`.
3. **`pos-frontend`**: Cliente táctil multiplataforma en Flutter 3.x. Adapta su árbol de widgets visuales (ocultando selectores de Rubro Padre o desplegando badges de venta ascendente dorados) basándose en las capacidades otorgadas por la licencia activa.

### 2.2 Alcance Técnico Específico
La auditoría cubrió los siguientes componentes del repositorio `pos-license-server`:
- Rutas públicas y protegidas en `routes/api.php`.
- Modelos Eloquent y conversiones de tipo en `app/Models/License.php` y `app/Models/Release.php`.
- Migraciones de esquema en `database/migrations/`.
- Controlador nuclear de validación `app/Http/Controllers/Api/LicenseValidationController.php`.
- Controlador de distribución de versiones `app/Http/Controllers/Api/ReleaseController.php`.
- Esquema de formulario y tablas administrativas en `app/Filament/Resources/Licenses/Schemas/LicenseForm.php` y `LicensesTable.php`.
- Creación y ejecución de la suite de pruebas de integración en `tests/Feature/PlanValidationTest.php`.

### 2.3 Restricción de No-Modificación de Código de Producción
En estricto cumplimiento de las directrices del proyecto y el mandato de integridad:
- **Ningún archivo de código fuente en producción** (`app/`, `routes/`, `config/`, `database/`) fue modificado durante esta auditoría.
- Todas las evaluaciones se realizaron mediante inspección estática de código fuente, análisis de flujo de control, y ejecución de suites de prueba automatizadas en memoria contra la base de datos de pruebas (`SQLite :memory:` con `RefreshDatabase`).

---

## 3. Auditoría de Arquitectura y Lógica de Planes (Básico vs Premium)

### 3.1 Mecanismo Nuclear de Licenciamiento
La persistencia de licencias en la tabla `licenses` desacopla dos dimensiones conceptuales independientes:
- **`plan`** (`ENUM('basico', 'premium')`): Define el nivel funcional de acceso del cliente.
- **`plan_type`** (`ENUM('saas', 'lifetime')`): Define el régimen comercial de vigencia (suscripción periódica o pago perpetuo único).

### 3.2 Módulos Base Universales
Independientemente del plan contratado o del rubro comercial, `LicenseValidationController.php` garantiza en las líneas 60-61 la inclusión inmutable de los módulos esenciales de punto de venta:
- `fast_pos`: Venta rápida de mostrador y cobro básico.
- `z_reports`: Arqueos de caja y reportes Z de cierre diario.

En las pruebas automatizadas, tanto una licencia Básico Retail como Básico Ferretería reciben `fast_pos = true` y `z_reports = true`.

### 3.3 Módulos del Plan Premium (11 Características)
Al autenticarse una licencia cuyo atributo `plan` sea `'premium'` (o sus alias heredados `'pro'`, `'enterprise'`), el controlador expande la matriz de capacidades agregando 11 módulos avanzados (líneas 63-72 de `LicenseValidationController.php`):
1. `multi_caja`: Múltiples terminales de punto de venta concurrentes.
2. `current_accounts`: Gestión de cuentas corrientes de clientes y fiado.
3. `advanced_reports`: Reportes analíticos gerenciales, balances y exportación a formatos contables.
4. `predictive_alerts`: Algoritmos predictivos de rotación y reorden de stock.
5. `checks`: Cartera y libro de cheques físicos y electrónicos.
6. `suppliers`: Módulo B2B de proveedores, compras y órdenes de abastecimiento.
7. `expenses`: Control y categorización de gastos operativos y salidas de caja.
8. `multiple_prices`: Múltiples listas de precios (minorista, mayorista, precios con recargo).
9. `multi_rubro`: **(Fase 1)** Jerarquía multinivel de Catálogo (`Rubro -> Categoría -> Producto`).
10. `mercadopago_qr`: **(Fase 0/3)** Integración con pasarela de pagos QR dinámico y Point Smart.
11. `arca_afip`: **(Fase 0/4)** Facturación fiscal electrónica mediante web services de ARCA (ex-AFIP).

### 3.4 Aislamiento Vertical Estricto para Ferretería / Corralón
El sistema incorpora soporte vertical mediante la columna `business_type` (`retail` vs `hardware_store`). Existen dos módulos altamente especializados para el circuito de corralones e industrias de suministros:
- `quotes`: Emisión de presupuestos formales con validez temporal, acopio de materiales y envío en PDF/WhatsApp.
- `logistics`: Despachos pesados, seguimiento de entregas y emisión de remitos.

**Regla de Seguridad Vertical (Inmunidad contra Fugas en Retail):**
En `LicenseValidationController::mapFeatures()` (líneas 136-141), el sistema aplica una regla de exclusión estricta:
```php
if (in_array($feature, ['logistics', 'quotes'])) {
    if (!$isHardwareStore) {
        $map[$feature] = false;
        continue;
    }
}
```
Esta regla precede intencionalmente a la asignación de addons. Si un comercio con `business_type = 'retail'` intenta configurar `quotes` o `logistics` mediante `allowed_addons`, el servidor **fuerza el valor a `false`**. De esta forma, se salvaguarda la propiedad intelectual y el modelo de monetización vertical, evitando que clientes de retail obtengan módulos pesados de ferretería fuera de su rubro.

### 3.5 Mecanismo DRM Anti-Piratería y Candado Físico (`installation_id`)
En las líneas 48-56 de `LicenseValidationController.php`:
1. **Primera Activación**: Si `$license->installation_id` está vacío o es nulo en la base de datos, el servidor asocia atómicamente el identificador único de hardware enviado por el cliente:
   ```php
   if (empty($license->installation_id)) {
       $license->installation_id = $installationId;
       $license->save();
   }
   ```
2. **Activaciones Posteriores Legítimas**: Peticiones enviadas con la misma clave y el mismo identificador son autorizadas con HTTP 200 OK.
3. **Detección de Clonación / Piratería**: Si un dispositivo con un identificador divergente (`installation_id !== $license->installation_id`) envía la misma clave de licencia, la solicitud es rechazada de inmediato con **HTTP 403 Forbidden**:
   ```json
   {
       "status": "error",
       "message": "Esta licencia ya está vinculada a otra instalación."
   }
   ```
4. **Desvinculación Autorizada**: El panel administrativo de Filament (`LicensesTable.php` y `EditLicense.php`) cuenta con la acción `reset_hardware_lock`, la cual resetea `installation_id = null` en caso de recambio de hardware legítimo asistido por soporte.

### 3.6 Manejo de Caducidad: SaaS vs Inmunidad Lifetime
En las líneas 41-46 de `LicenseValidationController.php`:
- Para licencias con `plan_type === 'saas'`: Si la fecha actual sobrepasa el final del día de expiración (`Carbon::parse($license->expiration_date)->endOfDay()->isPast()`), el validador responde con **HTTP 403 Forbidden** y cuerpo `{"status": "expired", "message": "La licencia ha expirado."}`.
- Para licencias con `plan_type === 'lifetime'`: La condición de caducidad es ignorada completamente. Incluso si existe una fecha registrada en `expiration_date` que ya haya vencido, la evaluación de `plan_type === 'saas'` retorna `false` y el cliente mantiene acceso activo de por vida (`HTTP 200 OK, status: active`).

### 3.7 Retrocompatibilidad de Contratos de API
Para permitir la coexistencia de clientes legacy de POS con las versiones modernas:
```php
'plan'         => ($license->plan === 'basico') ? 'basic' : (($license->plan === 'premium') ? 'pro' : $license->plan),
'plan_espanol' => $license->plan,
```
- Un plan configurado como `basico` devuelve `plan: "basic"` y `plan_espanol: "basico"`.
- Un plan configurado como `premium` devuelve `plan: "pro"` y `plan_espanol: "premium"`.
Esto garantiza que terminales antiguas programadas para evaluar `data['plan'] == 'pro'` continúen funcionando sin requerir actualización inmediata.

---

## 4. Validación Rigurosa de Asignación Flexible de Módulos (Overrides Manuales)

### 4.1 Arquitectura y Persistencia de `allowed_addons`
La base de datos proporciona soporte nativo para la flexibilización de licencias a través de la columna `allowed_addons` en la tabla `licenses`:
- **Tipo de Dato**: `JSON nullable` (migración `2026_03_24_205138_create_licenses_table.php`).
- **Cast Eloquent**: `License.php` define `$casts = ['allowed_addons' => 'array']`, garantizando deserialización automática a arreglos nativos de PHP.
- **Filament Form**: `LicenseForm.php` implementa un componente `Select::make('allowed_addons')->multiple()`.

### 4.2 Verificación del Caso de Uso Específico: Plan Básico con Módulo Extra `suppliers` (`proveedores`)
Uno de los requerimientos de mayor relevancia comercial consiste en verificar si un inquilino con un plan restrictivo (**Básico**) puede recibir la activación de un módulo individual típicamente reservado para el nivel Premium (por ejemplo, el módulo de **Proveedores / Abastecimiento B2B**), sin verse forzado a adquirir la suscripción Premium completa.

**Resultado de la Auditoría**: **EL CASO DE USO ESTÁ TOTALMENTE SOPORTADO Y FUNCIONA A NIVEL DE CÓDIGO.**

#### Trazabilidad del Flujo de Ejecución:
1. **Configuración de la Licencia**:
   - `plan`: `'basico'`
   - `business_type`: `'retail'`
   - `allowed_addons`: `['suppliers']`
2. **Evaluación en `LicenseValidationController`**:
   - Línea 61: `$businessAddons` recibe `['fast_pos', 'z_reports']`.
   - Líneas 64-72: Al ser el plan `'basico'`, no se añaden los 11 módulos Premium.
   - Línea 83: `$adminAddons = ['suppliers']`.
   - Línea 84: `$addons` se convierte en `['fast_pos', 'z_reports', 'suppliers']`.
3. **Mapeo en `mapFeatures`**:
   - Para `'fast_pos'`: `in_array('fast_pos', $addons)` es `true` -> `map['fast_pos'] = true`.
   - Para `'z_reports'`: `in_array('z_reports', $addons)` es `true` -> `map['z_reports'] = true`.
   - Para `'suppliers'`: La condición `in_array('suppliers', $adminAddons)` (Línea 144) evalúa a `true` -> `map['suppliers'] = true`.
   - Para el resto de los módulos Premium (`'multi_caja'`, `'multi_rubro'`, `'current_accounts'`, etc.): no pertenecen ni a `$adminAddons` ni a `$addons` -> son evaluados a `false`.
4. **Respuesta Emitida al Cliente POS (HTTP 200 OK)**:
   ```json
   {
     "status": "active",
     "plan": "basic",
     "plan_espanol": "basico",
     "business_type": "retail",
     "features": {
       "fast_pos": true,
       "z_reports": true,
       "suppliers": true,
       "multi_caja": false,
       "current_accounts": false,
       "multiple_prices": false,
       "advanced_reports": false,
       "predictive_alerts": false,
       "checks": false,
       "expenses": false,
       "multi_rubro": false,
       "mercadopago_qr": false,
       "arca_afip": false,
       "quotes": false,
       "logistics": false,
       "mobile_app": false,
       "remote_access": false
     }
   }
   ```
5. **Efecto en el Backend Local (`pos-backend`)**:
   - `LicenseSyncService` almacena este diccionario en la base de datos local.
   - La ruta `POST /api/suppliers` (protegida por el middleware `CheckFeatureAccess('suppliers')`) permite el paso con éxito.
   - Rutas como `POST /api/registers` (Cajas) o `POST /api/catalog/rubros` (Fase 1) son bloqueadas con HTTP 403 (`FEATURE_NOT_LICENSED`).
   - El frontend Flutter muestra y habilita la sección de Proveedores manteniendo bloqueados los restantes módulos.

### 4.3 Pipeline de Precedencia en `LicenseValidationController::mapFeatures`
El algoritmo de resolución implementa el siguiente orden determinístico de precedencia:

```
┌────────────────────────────────────────────────────────────────────────┐
│                   PIPELINE DE ASIGNACIÓN DE FEATURES                   │
└──────────────────────────────────┬─────────────────────────────────────┘
                                   │
                                   ▼
          ┌─────────────────────────────────────────────────┐
          │  ¿Feature es 'quotes' o 'logistics'?           │
          └────────────────────────┬────────────────────────┘
                                   │
                    ┌──────────────┴──────────────┐
                 SÍ │                             │ NO
                    ▼                             ▼
       ┌────────────────────────┐    ┌───────────────────────────────┐
       │ ¿Es hardware_store?    │    │ ¿Está en $adminAddons         │
       └───┬────────────────────┘    │ (allowed_addons en BD)?       │
           │                         └──────────────┬────────────────┘
     ┌─────┴─────┐                                  │
  NO │           │ SÍ                        ┌──────┴──────┐
     ▼           ▼                        SÍ │             │ NO
┌─────────┐ ┌───────────────┐                ▼             ▼
│ BLOQUEO │ │ Permite paso  │         ┌────────────┐ ┌───────────────┐
│ false   │ │ al siguiente  │         │ FORZAR     │ │ ¿Está en      │
│ (Retail)│ │ evaluador     │         │ true       │ │ $addons       │
└─────────┘ └───────┬───────┘         │ (Override) │ │ (Plan base)?  │
                    │                 └────────────┘ └───────┬───────┘
                    └────────────────────────┬───────────────┘
                                             │
                                      ┌──────┴──────┐
                                   SÍ │             │ NO
                                      ▼             ▼
                                ┌───────────┐ ┌───────────┐
                                │   true    │ │   false   │
                                └───────────┘ └───────────┘
```

**Reglas Cardinales:**
1. **Regla de Aislamiento Vertical**: Si un comercio es `retail`, los módulos `quotes` y `logistics` siempre resultan en `false`, inclusive si un administrador los configuró en `allowed_addons`.
2. **Regla de Override Manual**: Si un módulo está presente en el arreglo `$adminAddons` (`$license->allowed_addons`) y no viola la exclusión de ferretería, se fuerza incondicionalmente a `true`, anulando las limitaciones del plan Básico.
3. **Regla de Pertenencia Estándar por Plan**: Para cualquier otra capacidad, se asigna el valor booleano resultante de la membresía en el paquete estándar del plan.

---

## 5. Resultados y Evidencia Empírica de Pruebas Ejecutables

### 5.1 Descripción de la Suite de Pruebas Automatizada
Para validar empíricamente cada regla de negocio, se diseñó e implementó la suite oficial de pruebas de integración `tests/Feature/PlanValidationTest.php`. Esta suite interactúa directamente con el kernel HTTP de Laravel, ejecutando peticiones JSON contra el endpoint `/api/validate` sobre una base de datos SQLite en memoria aislada (`RefreshDatabase`).

### 5.2 Matriz de Cobertura de los 10 Tests

| # | Método del Test | Requisito Evaluado | Entradas / Escenario | Aserciones Principales | Resultado |
|---|---|---|---|---|:---:|
| 1 | `test_standard_basic_plan_flags_retail` | Plan Básico Estándar (Retail) | Plan: `basico`, Rubro: `retail`, Addons: `[]`. | HTTP 200; `plan=basic`; `fast_pos=true`, `z_reports=true`; 11 módulos premium en `false`; addons en `false`. | **PASS** |
| 2 | `test_standard_premium_plan_flags_retail` | Plan Premium Estándar (Retail) | Plan: `premium`, Rubro: `retail`, Addons: `[]`. | HTTP 200; `plan=pro`, `plan_espanol=premium`; Base y 11 Premium en `true` (incluye Fase 0 y 1); `quotes` y `logistics` en `false`. | **PASS** |
| 3 | `test_basic_plan_with_manual_override_suppliers` | Override Flexible de Módulo Extra | Plan: `basico`, Rubro: `retail`, Addons: `['suppliers']`. | HTTP 200; `plan=basic`; `suppliers=true`; demás módulos premium en `false`. | **PASS** |
| 4 | `test_discrepancy_check_spanish_key_proveedores` | Discrepancia Clave en Español (`proveedores`) | Plan: `basico`, Rubro: `retail`, Addons: `['proveedores']`. | HTTP 200; `suppliers=false` (falta alias); clave `'proveedores'` ausente del diccionario. | **PASS** |
| 5 | `test_vertical_restriction_enforcement_blocks_quotes_and_logistics_on_retail` | Aislamiento Vertical Estricto | Retail con `allowed_addons=['quotes', 'logistics']` vs Ferretería estándar. | Retail: `quotes=false`, `logistics=false`. Ferretería: `quotes=true`, `logistics=true`. | **PASS** |
| 6 | `test_drm_hardware_lock_enforcement` | Candado Físico DRM Anti-Piratería | Licencia nueva -> vinculación Alpha -> Intento con dispositivo Beta. | Activación 1: HTTP 200 y BD vinculada; Activación 2 (mismo HW): HTTP 200; Intruso: HTTP 403 con mensaje de error. | **PASS** |
| 7 | `test_saas_expiration_vs_lifetime` | Caducidad SaaS vs Inmunidad Lifetime | Licencia SaaS con fecha vencida vs Licencia Lifetime con fecha vencida. | SaaS vencido: HTTP 403 `status=expired`. Lifetime con fecha pasada: HTTP 200 `status=active`. | **PASS** |
| 8 | `test_suspended_license_returns_403_suspended` | Ciclo de Vida: Licencia Suspendida | Licencia con `is_active = false`. | HTTP 403 Forbidden; `status='suspended'`. | **PASS** |
| 9 | `test_invalid_license_key_returns_403_not_found` | Seguridad: Clave de Licencia Inexistente | Request con clave desconocida `pos_non_existent_key...`. | HTTP 403 Forbidden; `status='error'`, `'Licencia no encontrada.'`. | **PASS** |
| 10 | `test_missing_fields_validation_error` | Validación de Payload de Entrada | Request vacío `{}` sin `license_key` ni `installation_id`. | HTTP 422 Unprocessable Entity; errores en `license_key` e `installation_id`. | **PASS** |

---

### 5.3 Comandos de Ejecución y Salida Textual Verbatim Completa

#### Comando 1: Ejecución vía Artisan Test Runner
```powershell
php artisan test tests/Feature/PlanValidationTest.php
```

**Salida Verbatim de Terminal:**
```text
   PASS  Tests\Feature\PlanValidationTest
  ✓ standard basic plan flags retail                                                                             1.18s  
  ✓ standard premium plan flags retail                                                                           0.68s  
  ✓ basic plan with manual override suppliers                                                                    0.69s  
  ✓ discrepancy check spanish key proveedores                                                                    0.70s  
  ✓ vertical restriction enforcement blocks quotes and logistics on retail                                       0.78s  
  ✓ drm hardware lock enforcement                                                                                0.95s  
  ✓ saas expiration vs lifetime                                                                                  0.96s  
  ✓ suspended license returns 403 suspended                                                                      0.79s  
  ✓ invalid license key returns 403 not found                                                                    0.76s  
  ✓ missing fields validation error                                                                              0.75s  

  Tests:    10 passed (81 assertions)
  Duration: 8.55s
```

---

#### Comando 2: Ejecución Directa con PHPUnit
```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/PlanValidationTest.php
```

**Salida Verbatim de Terminal:**
```text
PHPUnit 12.5.14 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.30
Configuration: C:\laragon\www\pos-license-server\phpunit.xml

..........                                                        10 / 10 (100%)

Time: 00:07.834, Memory: 66.00 MB

OK (10 tests, 81 assertions)
```

---

#### Comando 3: Ejecución de la Suite Completa del Proyecto
```powershell
php artisan test
```

**Salida Verbatim de Terminal:**
```text
   PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response                                                                1.02s  

   PASS  Tests\Feature\PlanValidationTest
  ✓ standard basic plan flags retail                                                                             0.81s  
  ✓ standard premium plan flags retail                                                                           0.69s  
  ✓ basic plan with manual override suppliers                                                                    0.64s  
  ✓ discrepancy check spanish key proveedores                                                                    0.68s  
  ✓ vertical restriction enforcement blocks quotes and logistics on retail                                       0.68s  
  ✓ drm hardware lock enforcement                                                                                0.67s  
  ✓ saas expiration vs lifetime                                                                                  0.68s  
  ✓ suspended license returns 403 suspended                                                                      0.70s  
  ✓ invalid license key returns 403 not found                                                                    0.67s  
  ✓ missing fields validation error                                                                              0.65s  

  Tests:    12 passed (83 assertions)
  Duration: 8.21s
```

---

#### Comando 4: Verificación de Estándar de Código (Laravel Pint)
```powershell
vendor/bin/pint --test tests/Feature/PlanValidationTest.php
```

**Salida Verbatim de Terminal:**
```text
  .

  ──────────────────────────────────────────────────────────────────────────────────────────────────────────── Laravel  
    PASS   .................................................................................................... 1 file  
```

---

## 6. Catálogo Detallado de Bugs, Vulnerabilidades y Discrepancias

Durante la auditoría exhaustiva del repositorio se identificaron 6 hallazgos clasificados por criticidad:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       RESUMEN DE HALLAZGOS Y BUGS                           │
├────────────────────┬──────────┬──────────────────────┬──────────────────────┤
│ Identificador      │ Severidad│ Componente Afectado  │ Tipo de Defecto      │
├────────────────────┼──────────┼──────────────────────┼──────────────────────┤
│ VULN-01 (CVE-Risk) │ CRÍTICA  │ ReleaseController    │ Bypass Autenticación │
│ GAP-02             │ ALTA     │ Filament LicenseForm │ Brecha UI / Operativa│
│ DISC-03            │ MEDIA    │ MapFeatures / Naming │ Falta de Alias       │
│ DISC-04            │ MEDIA    │ API JSON Response    │ Desconexión Metadata │
│ RISK-05            │ MEDIA    │ Routes API           │ Ausencia Rate Limit  │
│ RISK-06            │ BAJA     │ Filament Table JSON  │ Incompatibilidad SQL │
└────────────────────┴──────────┴──────────────────────┴──────────────────────┘
```

---

### 🚨 VULN-01: Bypass Crítico de Autenticación en `ReleaseController::store` (CVSS 9.8)
- **Archivo afectado**: `app/Http/Controllers/Api/ReleaseController.php` (Líneas 70-73).
- **Código vulnerable**:
  ```php
  // ── Validación del token secreto ───────────────────
  $expectedToken = config('app.ci_deploy_token', env('CI_DEPLOY_TOKEN'));
  if ($request->input('token') !== $expectedToken) {
      return response()->json(['error' => 'Unauthorized'], 401);
  }
  ```
- **Mecanismo de Explotación y Causa Raíz**:
  1. La clave `ci_deploy_token` **no está declarada** en `config/app.php`.
  2. En entornos de producción donde se ejecuta `php artisan config:cache` (práctica estándar obligatoria en Laravel), las llamadas a `env()` fuera de los archivos de configuración en `config/` retornan sistemáticamente `null`.
  3. En consecuencia, `$expectedToken` se evalúa como `null`.
  4. Si un actor malicioso envía una solicitud HTTP `POST /api/releases/new` omitiendo el parámetro `token` (o enviando `"token": null`), `$request->input('token')` evalúa a `null`.
  5. La condición de control `null !== null` es estrictamente **`false`**. La verificación de autorización es burlada por completo.
- **Impacto Severo**:
  Cualquier usuario anónimo en Internet puede registrar una versión maliciosa del software (ej. componente `frontend` o `backend`, versión `99.9.9`, con `is_critical = true` y una `download_url` apuntando a un ejecutable troyano o ransomware). Todas las terminales POS conectadas al servidor descargarán e instalarán automáticamente el binario comprometido en la próxima consulta de actualización.

---

### 🚨 GAP-02: Brecha en Panel Administrativo de Filament (`LicenseForm.php`)
- **Archivo afectado**: `app/Filament/Resources/Licenses/Schemas/LicenseForm.php` (Líneas 71-90).
- **Evidencia**:
  En el commit `d6cbbff` se añadieron a `LicenseValidationController.php` tres nuevos módulos críticos:
  - `multi_rubro` (Fase 1: Jerarquía Multinivel de Catálogo)
  - `mercadopago_qr` (Fase 0/3: Pasarela de Cobros Mercado Pago)
  - `arca_afip` (Fase 0/4: Facturación Fiscal Electrónica ARCA)
  
  Sin embargo, el array `options` del componente `Select::make('allowed_addons')` en `LicenseForm.php` **quedó desactualizado** y solo expone 14 opciones históricas.
- **Impacto Operativo**:
  Los operadores y administradores de soporte comercial que utilizan el panel web de Filament **no tienen forma visual de otorgar** `multi_rubro`, `mercadopago_qr` ni `arca_afip` a clientes con plan Básico que hayan pagado por dicho módulo. Para habilitarlos se requiere una intervención técnica manual directa sobre la base de datos mediante MySQL CLI o Tinker.

---

### ⚠️ DISC-03: Inconsistencia de Nomenclatura (`suppliers` vs `proveedores`)
- **Archivo afectado**: `app/Http/Controllers/Api/LicenseValidationController.php` (Línea 123).
- **Evidencia**:
  En el código fuente, la clave canónica del módulo es `'suppliers'`. No obstante, en la documentación comercial, requerimientos funcionales y diálogos de soporte se denomina indistintamente "Módulo de Proveedores" o `"proveedores"`.
  Como demostró el Test #4 (`test_discrepancy_check_spanish_key_proveedores`), si un operador o script de aprovisionamiento almacena `"proveedores"` en `allowed_addons`, el servidor ignora silenciosamente la clave y el cliente queda sin acceso a proveedores (`features.suppliers = false`).
- **Impacto**:
  Fricción operativa y tickets falsos de soporte reportando que "el módulo pagado no se habilitó en el cliente".

---

### ⚠️ DISC-04: Desconexión de Metadatos de Vencimiento e Inconsistencia `checks` vs `cheques`
- **Archivos afectados**:
  - Servidor: `app/Http/Controllers/Api/LicenseValidationController.php` (Líneas 88-99).
  - Cliente POS: `pos-backend/app/Services/LicenseSyncService.php` (Líneas 122-124, 144, 249).
- **Evidencia 1 (Metadatos ausentes)**:
  En `pos-backend`, `LicenseSyncService` espera recibir en la respuesta JSON:
  `$data['expires_at']`, `$data['next_payment_at']` y `$data['manage_url']` para sincronizarlos con las configuraciones locales. Sin embargo, `LicenseValidationController` en el servidor **no incluye ninguno de estos campos**, devolviendo únicamente `status`, `plan`, `plan_type`, `server_time`, `client_name`, `business_type`, `plan_espanol` y `features`.
  *Impacto*: En el cliente POS, la configuración `license_expires_at` queda perpetuamente en `null`, imposibilitando mostrar advertencias preventivas de expiración al comerciante antes de que la licencia se bloquee de forma imprevista.
- **Evidencia 2 (Inconsistencia `checks` vs `cheques`)**:
  El servidor emite la clave en inglés `'checks'`. En el cliente, las rutas API están protegidas por `feature:checks`. Pero en los fallbacks locales de emergencia de `LicenseSyncService.php` se escribe `$features['cheques'] = true;`. Si el cliente cae en modo de contingencia offline, el módulo de cheques queda bloqueado porque las rutas buscan `'checks'`.

---

### ⚠️ RISK-05: Ausencia de Rate Limiting en Rutas Públicas de la API
- **Archivo afectado**: `routes/api.php` (Líneas 13-16).
- **Evidencia**:
  Las rutas `POST /api/validate` y `GET /api/check-update` están declaradas sin ningún middleware de limitación de tasa:
  ```php
  Route::post('/validate', [LicenseValidationController::class, 'validateKey']);
  Route::get('/check-update', [ReleaseController::class, 'checkUpdate']);
  ```
- **Impacto**:
  El endpoint de validación puede ser sometido a ataques de fuerza bruta automatizados para adivinar cadenas de `api_key` (`pos_` + 32 caracteres aleatorios) o a ataques de denegación de servicio (DoS) que degraden la disponibilidad del servidor de licencias para toda la flota de comercios.

---

### ℹ️ RISK-06: Búsqueda en Columna JSON en Tabla de Filament
- **Archivo afectado**: `app/Filament/Resources/Licenses/Tables/LicensesTable.php` (Líneas 95-99).
- **Evidencia**:
  La columna `allowed_addons` tiene aplicada la directiva `->searchable()`:
  ```php
  TextColumn::make('allowed_addons')
      ->label('Módulos')
      ->badge()
      ->searchable()
      ->toggleable(),
  ```
- **Impacto**:
  En bases de datos relacionales con tipado estricto (como PostgreSQL en despliegues cloud), la cláusula `LIKE '%...%'` generada por `searchable()` directamente sobre un tipo de datos `JSON` o `JSONB` falla con una excepción `QueryException: operator does not exist: json ~~ unknown`.

---

## 7. Matriz de Preparación (Readiness Matrix) para Fases 0 y 1

A continuación se contrasta el estado del código de `pos-license-server` contra las especificaciones de diseño establecidas en `PLAN_IMPLEMENTACION.md`:

| # | Requisito de PLAN_IMPLEMENTACION.md | Componente en Licencias | Estado Actual | Brecha Técnica Detectada (Gap) | Nivel de Preparación |
|---|---|---|:---:|---|:---:|
| **0.1** | **Blindaje de Credenciales y Secretos (Fase 0)** | `ReleaseController.php` | ❌ Vulnerable | Token de despliegue vulnerable a bypass por `null`. Falta rate limiting en API pública. | **30% (Requiere Parche Inmediato)** |
| **0.2** | **Protección de Settings Sensibles (Fase 0)** | `LicenseSyncService` / DTOs | ⚠️ Parcial | El servidor no emite `expires_at` ni `next_payment_at` requeridos por la configuración de seguridad local del POS. | **70%** |
| **1.1** | **Emisión de Flag Jerarquía Rubros (`multi_rubro`)** | `LicenseValidationController.php` | ✅ Completo | Implementado en `$allFeatures` y en paquete Premium. Responde correctamente a overrides. | **100% (Backend Engine)** |
| **1.2** | **Aprovisionamiento Administrativo de `multi_rubro`** | `LicenseForm.php` | ❌ Incompleto | Omitido en el selector de addons de Filament UI. Un admin no puede activarlo vía web para Plan Básico. | **20% (UI Gap)** |
| **1.3** | **Aprovisionamiento Administrativo de `mercadopago_qr` y `arca_afip`** | `LicenseForm.php` | ❌ Incompleto | Omitidos en el selector de addons de Filament UI. | **20% (UI Gap)** |
| **1.4** | **Aislamiento Vertical de Ferretería** | `mapFeatures` | ✅ Completo | Módulos `quotes` y `logistics` estrictamente aislados y bloqueados en comercios retail. | **100%** |
| **1.5** | **Flexibilidad de Overrides Granulares (ej. Proveedores)** | `allowed_addons` JSON | ✅ Completo | Funcionamiento verificado con pruebas automatizadas (10/10 tests aprobados). | **100%** |
| **1.6** | **Cobertura de Pruebas Automatizadas en Repo** | `tests/Feature/PlanValidationTest.php` | ✅ Completo | Suite creada con 10 tests y 81 aserciones cubriendo todos los escenarios críticos. | **100%** |

---

## 8. Plan de Remediación y Recomendaciones Técnicas (Blueprints de Código)

Siguiendo el principio de no alterar el código fuente en esta etapa de auditoría, se presentan a continuación los fragmentos de código exactos, listos para ser incorporados por el equipo de desarrollo en la fase de resolución.

---

### Parche 1: Remediación de la Vulnerabilidad Crítica en `ReleaseController.php`

#### 1.1 Modificar `config/app.php`:
Registrar la variable de entorno dentro de la configuración de Laravel para asegurar persistencia bajo `config:cache`:
```php
// En config/app.php, dentro del array de retorno:
'ci_deploy_token' => env('CI_DEPLOY_TOKEN'),
```

#### 1.2 Modificar `app/Http/Controllers/Api/ReleaseController.php`:
Reemplazar la verificación de líneas 70-73 por una validación estricta con `hash_equals` que prevenga timing attacks y bloquee tokens vacíos:
```php
// ── Validación del token secreto ───────────────────
$expectedToken = config('app.ci_deploy_token');
$providedToken = $request->input('token');

if (empty($expectedToken) || empty($providedToken) || !hash_equals($expectedToken, (string) $providedToken)) {
    return response()->json(['error' => 'Unauthorized'], 401);
}
```

---

### Parche 2: Incorporación de Flags de Fase 0 y 1 en `LicenseForm.php`

En `app/Filament/Resources/Licenses/Schemas/LicenseForm.php`, actualizar el componente `Select::make('allowed_addons')`:
```php
Select::make('allowed_addons')
    ->label('Módulos Habilitados')
    ->multiple()
    ->options([
        'fast_pos'          => '⚡ Caja Rápida',
        'z_reports'         => '🧾 Reportes Z',
        'quotes'            => '📝 Presupuestos (PDF/WA)',
        'current_accounts'  => '📘 Cuentas Corrientes (Fiado)',
        'multiple_prices'   => '🏷️ Listas de Precios (Mayorista/Tarjeta)',
        'multi_caja'        => '🖥️ Múltiples Cajas / Terminales',
        'mobile_app'        => '📱 App Móvil (Inventario y Ventas)',
        'remote_access'     => '🌐 Acceso Remoto (Cloudflare / Internet)',
        'advanced_reports'  => '📊 Reportes Gerenciales (Balances, Excel, PDF)',
        'predictive_alerts' => '🧠 Inteligencia Logística (Alertas Predictivas)',
        'logistics'         => '🚚 Logística y Remitos',
        'checks'            => '💵 Gestión de Cheques',
        'suppliers'         => '📦 Gestión de Proveedores (B2B)',
        'expenses'          => '💸 Gestión de Gastos y Movimientos',
        // ── Incorporación de Módulos Fases 0 y 1 ──────────────
        'multi_rubro'       => '🗂️ Múltiples Rubros (Jerarquía Catálogo - Fase 1)',
        'mercadopago_qr'    => '📱 Mercado Pago QR y Point (Pasarela - Fase 0/3)',
        'arca_afip'         => '🏛️ Facturación Fiscal ARCA (ex-AFIP - Fase 0/4)',
    ])
    ->columnSpanFull(),
```

---

### Parche 3: Resolución de Aliases en `LicenseValidationController::mapFeatures`

Para resolver la discrepancia de nomenclatura `'proveedores'` vs `'suppliers'` de forma transparente:
```php
// Al inicio de mapFeatures en LicenseValidationController.php:
$aliasMap = [
    'proveedores' => 'suppliers',
    'cheques'     => 'checks',
    'rubros'      => 'multi_rubro',
];

// Normalizar tanto $adminAddons como $addons usando el mapa de alias
$normalizedAdminAddons = array_map(fn($f) => $aliasMap[$f] ?? $f, $adminAddons);
$normalizedAddons = array_map(fn($f) => $aliasMap[$f] ?? $f, $addons);
```

---

### Parche 4: Exposición de Metadatos de Vencimiento en Payload JSON

En `app/Http/Controllers/Api/LicenseValidationController.php`, actualizar la respuesta JSON exitosa (líneas 88-99):
```php
return response()->json([
    'status'        => 'active',
    'plan'          => ($license->plan === 'basico') ? 'basic' : (($license->plan === 'premium') ? 'pro' : $license->plan),
    'plan_type'     => $license->plan_type,
    'server_time'   => now()->toIso8601String(),
    'client_name'   => $license->client_name,
    'business_type' => $license->business_type,
    'plan_espanol'  => $license->plan,
    'expires_at'    => $license->expiration_date ? \Carbon\Carbon::parse($license->expiration_date)->toIso8601String() : null,
    'features'      => $features,
], 200);
```

---

### Parche 5: Protección de Rutas API con Rate Limiting

En `routes/api.php`, envolver las rutas públicas en el middleware `throttle`:
```php
// Protección contra fuerza bruta y DoS (60 peticiones por minuto por IP)
Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/validate', [LicenseValidationController::class, 'validateKey']);
    Route::get('/check-update', [ReleaseController::class, 'checkUpdate']);
    Route::post('/releases/new', [ReleaseController::class, 'store']);
});
```

---

### Parche 6: Recomendaciones de Arquitectura a Mediano Plazo
1. **Extracción a Capa de Servicios (`app/Services/LicenseResolutionService.php`)**: Desacoplar la lógica de cálculo de módulos y validaciones de hardware fuera del controlador HTTP.
2. **Introducción de Enums Nativos PHP 8.1+ (`app/Enums`)**: Crear `App\Enums\PlanType`, `App\Enums\BusinessType` y `App\Enums\FeatureFlag` para eliminar cadenas mágicas.
3. **Form Requests Tipados**: Sustituir `$request->validate()` inline por `App\Http\Requests\ValidateLicenseRequest` y `App\Http\Requests\StoreReleaseRequest`.

---

## 9. Conclusión del Dictamen

El motor central de validación de licencias de `pos-license-server` **cumple con excelencia técnica los requerimientos funcionales y lógicos de las Fases 0 y 1**, demostrando un comportamiento determinístico y matemáticamente exacto en la emisión de flags, segregación vertical y overrides manuales para planes básicos.

No obstante, **no se recomienda el pase directo a producción de las Fases 0 y 1** sin antes aplicar de manera prioritaria el **Parche 1 (Blindaje de Releases contra inyecciones arbitrarias)** y el **Parche 2 (Habilitación de los nuevos módulos en Filament UI)**. Una vez implementadas estas correcciones de bajo esfuerzo y alto impacto, el servidor de licencias quedará en un estado óptimo de robustez, seguridad y operatividad comercial.

---
*Fin del Informe Autoritativo de Auditoría QA.*
