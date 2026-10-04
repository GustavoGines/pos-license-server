# Especificación Técnica y Auditoría de Fases 0 y 1: Sistema de Licenciamiento y Jerarquía Modular

**Documento:** Reporte de Extracción de Especificaciones y Auditoría  
**Agente:** `spec_miner_survey_1` (Specification Miner)  
**Fecha:** 2026-10-03  
**Fuentes de Verdad Analizadas:**
- `Sistema_POS/PLAN_DE_IMPLEMENTACION.md` (Versión 2.0 - Octubre 2026)
- `Sistema_POS/PROJECT.md` & `Sistema_POS/MEMORY.md`
- `pos-license-server` (Controlador `LicenseValidationController`, Modelo `License`, Esquema Filament `LicenseForm`, Migraciones)
- `pos-backend` (`LicenseSyncService`, Middleware `CheckFeatureAccess`, Suites `FeatureGateTest` y `LicenseSyncServiceTest`)

---

## 1. Resumen Ejecutivo y Alcance Global

El ecosistema **Sistema POS** consta de tres pilares interconectados:
1. **`pos-license-server`**: Servidor centralizado de validación de licencias, control de DRM anti-piratería (hardware lock por `installation_id`), definición de niveles de acceso (**Básico** vs **Premium**) y asignación flexible de módulos (`allowed_addons`).
2. **`pos-backend`**: Backend transaccional en Laravel 12 que sincroniza la licencia (`LicenseSyncService`), persiste el diccionario de capacidades en `business_settings.license_features_dict`, y aplica control de acceso mediante el middleware `CheckFeatureAccess` (`feature:{modulo}`).
3. **`pos-frontend`**: Cliente multiplataforma Flutter 3.x (Provider) que adapta la interfaz visual (ocultando selectores de Rubro Padre o mostrando badges de upselling dorado) según el plan activo y las capacidades habilitadas.

Este relevamiento extrae de manera exhaustiva las especificaciones de diseño, reglas de negocio, contratos de API, tablas de features, casos de borde y discrepancias detectadas entre la especificación y la implementación actual para las **Fases 0 y 1**.

---

## 2. Especificaciones de Fase 0: Blindaje de Seguridad y Gestión de Credenciales

### 2.1 Justificación y Riesgo Crítico
En el backend original, la ruta `GET /api/settings` era pública e iteraba indiscriminadamente sobre todas las filas de `business_settings`. Si se introducían credenciales de pasarelas de pago o certificados fiscales, cualquier dispositivo en la red local podía descargar tokens de recaudación o claves RSA privadas.

### 2.2 Requerimientos de Diseño
1. **Separación Estricta de Rutas de Configuración**:
   - `GET /api/settings` (Pública, requerida antes del login en Flutter): Solo expone una lista blanca estricta de claves estéticas y funcionales no sensibles:
     `['business_name', 'address', 'phone', 'cuit', 'ticket_footer', 'logo_path', 'currency_symbol', 'theme', 'license_features_dict', 'app_plan', 'afip_enabled', 'mp_qr_enabled', 'mp_point_device_id']`.
   - `GET /api/settings/integrations` (Protegida por sesión y permisos `manage_settings`): Retorna credenciales enmascaradas (`APP_USR-****...****`).
2. **Cifrado en Reposo de Secretos**:
   - Credenciales como `mp_access_token` y `mp_webhook_secret` se encriptan al persistirse con `Crypt::encryptString()` y solo se descifran en memoria de ejecución del servicio (`Crypt::decryptString()`).
3. **Aislamiento de Certificados ARCA (AFIP)**:
   - Archivos `.crt` y `.key` se almacenan exclusivamente en `storage_path('app/private/afip/{cuit}/')` con permisos del sistema de archivos `0600`.
   - Jamás se almacenan en la base de datos ni en `storage/app/public/`.
   - La carga valida la correspondencia de par RSA con `openssl_x509_check_private_key($cert, $key)`.

---

## 3. Especificaciones de Fase 1: Arquitectura Jerárquica Rubro -> Categoría (Básico vs Premium)

### 3.1 Modelo Nuclear y Relaciones
- **Jerarquía:** `Rubro (1) -> (N) Categorías (1) -> (N) Productos`.
- **Estructura de Base de Datos**:
  - Tabla `rubros`:
    - `id` (bigint unsigned PK)
    - `name` (varchar unique)
    - `is_system` (boolean, default false) — Identifica el rubro maestro por defecto.
    - `timestamps`
  - Tabla `categories`:
    - Columna añadida `rubro_id` (foreign key restrictiva hacia `rubros.id` con `restrictOnDelete()`).
  - Tabla `products`:
    - Mantiene relación directa 1:N con `category_id` (no se alteró la tabla `products` para garantizar retrocompatibilidad y cero latencia de reportes).

### 3.2 Lógica de Backfill Inteligente
Al ejecutarse la migración en bases de datos con datos previos:
1. Detecta `license_business_type` desde `business_settings` (default: `'retail'`).
2. Asigna el nombre del Rubro Principal:
   - `'hardware_store'` -> `'Ferretería'`
   - `'kiosko'` -> `'Kiosko'`
   - `'market'` -> `'Autoservicio'`
   - default -> `'Comercio General'`
3. Inserta el Rubro con `is_system = true`.
4. Asigna todas las categorías preexistentes a este Rubro Principal (`UPDATE categories SET rubro_id = $defaultRubroId`).

### 3.3 Reglas de Negocio Diferenciadas por Plan

| Aspecto | Plan Básico (`multi_rubro = false`) | Plan Premium (`multi_rubro = true`) |
|---|---|---|
| **CRUD de Rubros** | Bloqueado completamente (HTTP 403 Forbidden). No puede crear, renombrar ni eliminar rubros. | Acceso completo vía API (`RubroController`) y diálogo visual en Catálogo. |
| **Creación/Edición de Categorías** | El campo `rubro_id` se fuerza al Rubro Principal del sistema (`is_system = true`), ignorando valores externos. | Puede asignar libremente la categoría a cualquier Rubro existente. |
| **Protección del Rubro Sistema** | Inmutable. No puede ser eliminado ni por Básico ni por Premium (`is_system = true` -> HTTP 422). | Inmutable. Solo puede editar rubros personalizados. |
| **Protección contra Eliminación Huérfana** | N/A | Bloqueo (HTTP 422) si un Rubro tiene categorías asociadas (`categories_count > 0`). |
| **Interfaz Flutter** | Selector de Rubro Padre oculto. Notificación sutil con Badge dorado (`Icons.workspace_premium`). | Selector `DropdownButtonFormField` visible para asociar la categoría a su Rubro. |

---

## 4. Definición de Planes y Matriz Completa de Capacidades

El servidor de licencias administra **17 módulos/capacidades** clasificadas en cuatro niveles de asignación:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 1. MÓDULOS BASE (Disponibles para todos los planes y rubros)                 │
│    • fast_pos (Caja Rápida)                                                 │
│    • z_reports (Reportes Z / Cierre de Turno)                               │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ 2. MÓDULOS PLAN PREMIUM (Habilitados para plan premium, pro, enterprise)    │
│    • multi_caja (Múltiples terminales)      • checks (Gestión de cheques)   │
│    • current_accounts (Cuentas corrientes) • suppliers (Proveedores B2B)   │
│    • advanced_reports (Reportes gerenciales)• expenses (Gastos de caja)     │
│    • predictive_alerts (Alertas predictivas)• multiple_prices (Listas de $) │
│    • multi_rubro (Fase 1: Jerarquía Rubros) • mercadopago_qr (Fase 3: MP)   │
│    • arca_afip (Fase 4: Facturación fiscal)                                 │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ 3. MÓDULOS EXCLUSIVOS POR VERTICAL (Ferretería / Corralón)                  │
│    • quotes (Presupuestos PDF/WhatsApp)                                     │
│    • logistics (Logística y Remitos)                                        │
│    (Restringidos estrictamente a business_type == 'hardware_store')         │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ 4. MÓDULOS ADDON-ONLY (Solo activables individualmente en allowed_addons)   │
│    • mobile_app (App Móvil para Inventario)                                 │
│    • remote_access (Acceso Remoto / Cloudflare Tunnels)                     │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Tabla Resumen de Asignación Predeterminada por Nivel

| # | Feature Key | Módulo Funcional | Básico (Retail) | Básico (Hardware) | Premium (Retail) | Premium (Hardware) | Addon Manual Básico |
|---|---|---|:---:|:---:|:---:|:---:|:---:|
| 1 | `fast_pos` | Caja Rápida de mostrador | **Sí** | **Sí** | **Sí** | **Sí** | Base |
| 2 | `z_reports` | Reportes Z de auditoría fiscal/caja | **Sí** | **Sí** | **Sí** | **Sí** | Base |
| 3 | `multi_caja` | Múltiples Cajas / Terminales concurrentes | No | No | **Sí** | **Sí** | **Permitido** |
| 4 | `current_accounts` | Cuentas Corrientes y Fiado | No | No | **Sí** | **Sí** | **Permitido** |
| 5 | `advanced_reports` | Reportes Gerenciales (Balances, Exportación) | No | No | **Sí** | **Sí** | **Permitido** |
| 6 | `predictive_alerts` | Inteligencia de Stock y Alertas Predictivas | No | No | **Sí** | **Sí** | **Permitido** |
| 7 | `checks` | Cartera y Gestión de Cheques | No | No | **Sí** | **Sí** | **Permitido** |
| 8 | `suppliers` | Gestión de Proveedores (B2B) | No | No | **Sí** | **Sí** | **Permitido** |
| 9 | `expenses` | Control de Gastos y Salidas de Caja | No | No | **Sí** | **Sí** | **Permitido** |
| 10 | `multiple_prices` | Listas de Precios (Mayorista, Tarjeta, etc.) | No | No | **Sí** | **Sí** | **Permitido** |
| 11 | `multi_rubro` | Jerarquía Rubro -> Categoría (Fase 1) | No | No | **Sí** | **Sí** | **Permitido** (*) |
| 12 | `mercadopago_qr` | Cobros con Mercado Pago QR y Point (Fase 3) | No | No | **Sí** | **Sí** | **Permitido** (*) |
| 13 | `arca_afip` | Facturación Electrónica WSFEv1 CAE (Fase 4) | No | No | **Sí** | **Sí** | **Permitido** (*) |
| 14 | `quotes` | Presupuestos con exportación PDF/WhatsApp | No | **Sí** | No | **Sí** | **Solo Hardware** |
| 15 | `logistics` | Gestión de Envíos, Despachos y Remitos | No | **Sí** | No | **Sí** | **Solo Hardware** |
| 16 | `mobile_app` | Aplicación móvil para inventario y ventas | No | No | No | No | **Permitido** |
| 17 | `remote_access` | Túneles de conexión externa (Cloudflare) | No | No | No | No | **Permitido** |

*(*) Permitido en lógica de servidor, pero actualmente omitido en el formulario Filament UI (ver sección de Hallazgos y Discrepancias).*

---

## 5. Lógica de Asignación Flexible y Reglas de Excepción / Override

### 5.1 Mecanismo de Almacenamiento y Evaluación
1. **Persistencia de Excepciones**: En la tabla `licenses`, el campo `allowed_addons` almacena un array JSON con las claves de los módulos adicionales asignados manualmente a esa licencia en particular.
2. **Unión de Módulos (Merge no destructivo)**:
   En `LicenseValidationController.php`:
   ```php
   $adminAddons = is_array($license->allowed_addons) ? $license->allowed_addons : [];
   $addons = array_values(array_unique(array_merge($businessAddons, $adminAddons)));
   $features = $this->mapFeatures($addons, $license);
   ```
3. **Prioridad y Regla de Excepción en `mapFeatures`**:
   ```php
   // Regla 1: Aislamiento vertical estricto (Hardware Store)
   if (in_array($feature, ['logistics', 'quotes'])) {
       if (!$isHardwareStore) {
           $map[$feature] = false;
           continue; // Bloquea 'quotes' y 'logistics' en Retail aunque estén en allowed_addons
       }
   }

   // Regla 2: Override manual individual explícito
   if (in_array($feature, $adminAddons)) {
       $map[$feature] = true;
       continue;
   }

   // Regla 3: Pertenencia por plan base/premium
   $map[$feature] = $hasAddon;
   ```

### 5.2 Caso de Uso Específico: Cliente con Plan Básico y Módulo 'suppliers' Habilitado
- **Escenario**: Un comercio suscribe un plan Básico (`plan = 'basico'`) y abona un adicional individual por el módulo de Proveedores.
- **Configuración en Licencia**: `plan = 'basico'`, `allowed_addons = ['suppliers']`.
- **Resolución**:
  - `$businessAddons = ['fast_pos', 'z_reports']`.
  - `$addons = ['fast_pos', 'z_reports', 'suppliers']`.
  - En `mapFeatures`:
    - `fast_pos`: `true`
    - `z_reports`: `true`
    - `suppliers`: `true` (reconocido vía `$adminAddons`)
    - Todos los demás módulos Premium (`multi_caja`, `multi_rubro`, `current_accounts`, etc.): `false`.
- **Efecto en el Backend Cliente (`pos-backend`)**:
  - `LicenseSyncService` guarda el diccionario en `business_settings.license_features_dict`.
  - Peticiones a `POST /api/suppliers` atraviesan con éxito el middleware `CheckFeatureAccess('suppliers')`.
  - Peticiones a `POST /api/registers` (MultiCaja) o `POST /api/catalog/rubros` son bloqueadas con HTTP 403 (`FEATURE_NOT_LICENSED`).
  - La interfaz de Flutter habilita la pestaña de Proveedores pero mantiene bloqueado el resto de funciones Premium.

---

## 6. Contrato de API, Criptografía, Validación y DRM

### 6.1 Contrato del Endpoint de Validación (`POST /api/validate`)

#### Petición (Request)
- **Método**: `POST`
- **Ruta**: `/api/validate`
- **Headers**: `Accept: application/json`, `Content-Type: application/json`
- **Cuerpo JSON**:
  ```json
  {
    "license_key": "pos_1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d",
    "installation_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d"
  }
  ```
- **Reglas de Validación**:
  - `license_key`: `required|string`
  - `installation_id`: `required|string|max:255`

#### Pipeline de Decisión del Servidor (Secuencia de Control)
1. **Existencia**: Busca `License::where('api_key', $licenseKey)->first()`. Si no existe -> **HTTP 403** (`status: 'error'`, `message: 'Licencia no encontrada.'`).
2. **Suspensión**: Si `!$license->is_active` -> **HTTP 403** (`status: 'suspended'`, `message: 'La licencia está suspendida. Contacte a soporte.'`).
3. **Expiración de Modelo SaaS**:
   `if ($license->plan_type === 'saas' && $license->expiration_date && Carbon::parse($license->expiration_date)->endOfDay()->isPast())`
   -> **HTTP 403** (`status: 'expired'`, `message: 'La licencia ha expirado.'`).
   *(Nota: Si `plan_type === 'lifetime'`, la fecha de expiración se ignora por completo).*
4. **Protección Anti-Piratería (Hardware Lock por `installation_id`)**:
   - Si `$license->installation_id` está vacío en la base de datos (primera activación): vincula la instalación guardando `$license->installation_id = $installationId`.
   - Si `$license->installation_id !== $installationId`: rechaza con **HTTP 403** (`status: 'error'`, `message: 'Esta licencia ya está vinculada a otra instalación.'`).
5. **Cálculo de Capacidades**: Ejecuta la lógica de módulos base + premium + vertical + allowed_addons.
6. **Respuesta Exitosa (HTTP 200 OK)**:
   ```json
   {
     "status": "active",
     "plan": "basic",
     "plan_type": "saas",
     "server_time": "2026-10-03T22:00:00+00:00",
     "client_name": "Ferretería Central S.A.",
     "business_type": "hardware_store",
     "plan_espanol": "basico",
     "features": {
       "fast_pos": true,
       "z_reports": true,
       "quotes": true,
       "current_accounts": false,
       "multiple_prices": false,
       "multi_caja": false,
       "advanced_reports": false,
       "predictive_alerts": false,
       "logistics": true,
       "checks": false,
       "mobile_app": false,
       "remote_access": false,
       "suppliers": true,
       "expenses": false,
       "multi_rubro": false,
       "mercadopago_qr": false,
       "arca_afip": false
     }
   }
   ```

### 6.2 Criptografía y Firma de Licencias
- **Canal Seguro**: La comunicación entre los clientes (`pos-backend`) y el servidor central (`pos-license-server`) se efectúa mediante túnel HTTPS/TLS estándar.
- **Firma Asimétrica**: El payload JSON de la licencia **no** utiliza firmas asimétricas locales (RSA / JWT) sobre el payload retornado; la seguridad se fundamenta en la consulta en línea periódica (Heartbeat cada 3 minutos en `pos-backend`), la validación estricta de la API key contra la base de datos central y el período de gracia local offline de 72 horas para planes SaaS (e indefinido para planes Lifetime).

---

## 7. Hallazgos Críticos, Discrepancias y Bugs Identificados

Durante la auditoría del código fuente frente a las especificaciones se identificaron **cuatro discrepancias de alta severidad**:

### 🚨 Discrepancia 1: Omisión de Nuevos Módulos en el Formulario Administrativo de Filament
- **Archivo afectado**: `app/Filament/Resources/Licenses/Schemas/LicenseForm.php` (Líneas 71-90).
- **Evidencia**: En el commit `d6cbbff`, se añadieron `'multi_rubro'`, `'mercadopago_qr'` y `'arca_afip'` a `LicenseValidationController.php`. Sin embargo, el array `options` del componente `Select::make('allowed_addons')` en `LicenseForm.php` **no fue actualizado** y solo lista 14 opciones históricas.
- **Impacto operativo**: Los administradores que gestionan licencias desde el panel web de Filament **no pueden habilitar manualmente** `multi_rubro`, `mercadopago_qr` o `arca_afip` a clientes con plan Básico, ya que no aparecen disponibles en el selector desplegable.

### 🚨 Discrepancia 2: Inconsistencia de Clave de Módulo (`checks` vs `cheques`)
- **Evidencia**:
  - En `pos-license-server`: el controlador emite la clave `'checks'` (`$allFeatures`).
  - En `pos-backend` (`routes/api.php` línea 156): el middleware protege las rutas con `feature:checks`.
  - Sin embargo, en el servicio cliente `LicenseSyncService.php` (Líneas 144, 249, 318): en el mecanismo de fallback local para planes Pro/Premium se escribe `$features['cheques'] = true;`.
- **Impacto**: Si el servidor remoto de licencias no respondiera y el cliente aplicara su fallback local offline, el módulo de cheques quedaría inaccesible porque las rutas buscan `'checks'` y el fallback asignó `'cheques'`.

### 🚨 Discrepancia 3: Metadatos de Expiración Ausentes en la Respuesta JSON de Validación
- **Evidencia**:
  - En `pos-backend` (`LicenseSyncService.php` Líneas 122-124), el cliente intenta leer y persistir:
    `$data['expires_at']`, `$data['next_payment_at']`, `$data['manage_url']`.
  - En `pos-license-server` (`LicenseValidationController.php` Líneas 88-99), la respuesta JSON **no incluye** `expires_at` ni `expiration_date`.
- **Impacto**: La configuración local `license_expires_at` en el POS cliente siempre queda en `null`, imposibilitando mostrar advertencias preventivas de vencimiento al usuario en el frontend antes de que ocurra el corte total.

### 🚨 Discrepancia 4: Ausencia Total de Pruebas Automatizadas en el Repositorio de Licencias
- **Evidencia**: El directorio `tests/Feature` y `tests/Unit` de `pos-license-server` contiene únicamente las plantillas vacías por defecto de Laravel (`ExampleTest.php`).
- **Impacto**: La lógica de planes, el bloqueo de hardware, la expiración de SaaS y la asignación flexible de addons carecen de cobertura de tests automatizados unitarios y de integración dentro del propio repositorio de licencias.

---

## 8. Features Discovered

| # | Category | Feature | Description | Inputs | Outputs | Error Behavior | Discovered Via |
|---|----------|---------|-------------|--------|---------|----------------|----------------|
| 1 | Fase 0: Seguridad | Blindaje de Settings | Ocultamiento de claves sensibles en `GET /api/settings` público | `GET /api/settings` (sin auth) | JSON con claves whitelist públicas | 403 para rutas protegidas sin credenciales | `PLAN_IMPLEMENTACION.md` §4.1 |
| 2 | Fase 0: Seguridad | Cifrado en Reposo | Cifrado simétrico de tokens MP y secretos de webhooks | Strings de tokens | Ciphertext en base de datos (`Crypt::encryptString`) | Excepción si falla descifrado | `PLAN_IMPLEMENTACION.md` §4.1 |
| 3 | Fase 0: Seguridad | Aislamiento de Certificados | Almacenamiento seguro de claves privadas RSA y certificados AFIP en `app/private/` | Par `.crt` y `.key` | Archivos en disco con permisos 0600 | 422 si `.key` no coincide con `.crt` | `PLAN_IMPLEMENTACION.md` §4.1 |
| 4 | Fase 1: Catálogo | Jerarquía Rubro -> Categoría | Arquitectura nuclear 1:N donde toda categoría depende de un Rubro maestro | Migración de base de datos | Tablas `rubros` y FK `categories.rubro_id` | Restricción foránea en eliminación | `PLAN_IMPLEMENTACION.md` §3.3 |
| 5 | Fase 1: Catálogo | Backfill Inteligente | Detección de tipo de negocio para crear Rubro del Sistema inicial | `business_settings.license_business_type` | Inserción de Rubro Principal y asignación masiva | Fallback a 'Comercio General' si no está definido | `PLAN_IMPLEMENTACION.md` §3.3 |
| 6 | Fase 1: Planes | Gating de Rubros Básico | Bloqueo de creación y edición de rubros a usuarios con plan Básico | `POST/PUT/DELETE /api/catalog/rubros` con plan Básico | Código HTTP 403 Forbidden | Retorna 403 con mensaje de plan requerido | `PLAN_IMPLEMENTACION.md` §4.4 |
| 7 | Fase 1: Planes | Auto-asignación en Básico | Fuerza que las nuevas categorías en plan Básico se asocien al Rubro del Sistema | `POST /api/catalog/categories` con plan Básico | Categoría creada con `rubro_id = system_id` | Ignora `rubro_id` externo sin fallar | `PLAN_IMPLEMENTACION.md` §4.4 |
| 8 | Fase 1: Planes | CRUD Rubros Premium | Administración completa de rubros para usuarios del plan Premium (`multi_rubro`) | Requests a `RubroController` con plan Premium | Respuestas 200/201 con entidad Rubro | 422 en validación | `PLAN_IMPLEMENTACION.md` §4.4 |
| 9 | Fase 1: Catálogo | Protección Rubro Sistema | Impide la eliminación del rubro base del sistema (`is_system = true`) | `DELETE /api/catalog/rubros/{id}` donde `is_system=1` | Código HTTP 422 Unprocessable Content | Rechazo con mensaje de protección | `Sistema_POS/PROJECT.md` §Interface Contracts |
| 10 | Fase 1: Catálogo | Protección Rubro con Categorías | Impide eliminar cualquier rubro que tenga categorías asociadas | `DELETE /api/catalog/rubros/{id}` con categorías | Código HTTP 422 Unprocessable Content | Rechazo por integridad referencial | `Sistema_POS/PROJECT.md` §Interface Contracts |
| 11 | Fase 1: UI | Selector Condicional en UI | Formulario de categorías oculta o muestra selector de rubro según plan | `SettingsProvider.currentPlan` / `hasFeature` | UI adaptada o Badge dorado de upselling | N/A | `PLAN_IMPLEMENTACION.md` §5.1 |
| 12 | Licenciamiento | Validación de Licencia | Endpoint principal de autenticación y validación de instalaciones | `POST /api/validate` con `license_key` e `installation_id` | JSON con estado, plan y diccionario de features | 403 si suspendida, expirada o no encontrada | `LicenseValidationController.php` |
| 13 | Licenciamiento: DRM | Hardware Lock | Bloqueo por dispositivo anti-piratería que vincula `installation_id` | `installation_id` del cliente | Vinculación en 1er uso o validación en usos subsiguientes | 403 si `installation_id` difiere del registrado | `LicenseValidationController.php` |
| 14 | Licenciamiento | Expiración SaaS | Validación de vigencia de suscripción temporal para licencias SaaS | `plan_type = saas` y `expiration_date` | Permite acceso hasta `endOfDay()` de la fecha | 403 status: 'expired' al día siguiente | `LicenseValidationController.php` |
| 15 | Licenciamiento | Licencia Lifetime | Modo perpetuo que ignora expiración temporal | `plan_type = lifetime` | Acceso continuo irrestricto en el tiempo | N/A | `LicenseValidationController.php` |
| 16 | Licenciamiento: Módulos | Módulos Base | Módulos inmutables otorgados a todos los planes (`fast_pos`, `z_reports`) | Cualquier licencia activa | `fast_pos: true`, `z_reports: true` | N/A | `LicenseValidationController.php` |
| 17 | Licenciamiento: Módulos | Módulos Plan Premium | Paquete de 11 módulos otorgados automáticamente al plan Premium | `plan in ['premium', 'pro', 'enterprise']` | Flags `multi_caja`, `multi_rubro`, etc. en `true` | N/A | `LicenseValidationController.php` |
| 18 | Licenciamiento: Módulos | Aislamiento Vertical | Exclusividad de `quotes` y `logistics` para ferreterías | `business_type == 'hardware_store'` | `quotes: true`, `logistics: true` solo para ferretería | Forzados a `false` en comercios Retail | `LicenseValidationController.php` |
| 19 | Licenciamiento: Módulos | Override Manual (`allowed_addons`)| Habilitación de módulos individuales sobre planes restrictivos | Claves en `licenses.allowed_addons` | Flags individuales en `true` independientemente del plan | No sobrepasa exclusividad vertical de Retail | `LicenseValidationController.php` |
| 20 | Licenciamiento: Retrocompat| Compatibilidad de Nombres | Normalización de nombres de planes para clientes viejos y nuevos | `licenses.plan` ('basico', 'premium') | `'plan': 'basic'/'pro'`, `'plan_espanol': 'basico'/'premium'` | N/A | `LicenseValidationController.php` |

---

## 9. Edge Cases

| # | Feature | Input | Observed Behavior |
|---|---------|-------|-------------------|
| 1 | Hardware Lock | Primera activación con `installation_id` no registrado previamente y licencia con `installation_id = null` | El servidor asocia inmediatamente el UUID recibido al registro de la licencia en BD y retorna HTTP 200 OK. |
| 2 | Hardware Lock | Petición con `installation_id = 'INST-B'` en una licencia ya vinculada a `'INST-A'` | Retorna HTTP 403 Forbidden con `{"status": "error", "message": "Esta licencia ya está vinculada a otra instalación."}`. |
| 3 | Expiración SaaS | Licencia SaaS con fecha de expiración configurada para el día actual a las 14:00 | Retorna HTTP 200 OK hasta las 23:59:59 del día de vencimiento debido a `endOfDay()->isPast()`. |
| 4 | Expiración SaaS | Licencia SaaS con `expiration_date` pasada (ej. ayer a las 23:59:59) | Retorna HTTP 403 con `{"status": "expired", "message": "La licencia ha expirado."}`. |
| 5 | Expiración Lifetime | Licencia Lifetime con `expiration_date` en el pasado | Retorna HTTP 200 OK activa. La condición `plan_type === 'saas'` previene la expiración de licencias perpetuas. |
| 6 | Expiración SaaS Incompleta | Licencia SaaS con `expiration_date = null` | Retorna HTTP 200 OK activa indefinidamente debido a la cláusula `&& $license->expiration_date`. |
| 7 | Licencia Inactiva | Licencia con `is_active = false` pero fecha futura e `installation_id` correcto | Retorna HTTP 403 con `{"status": "suspended", "message": "La licencia está suspendida. Contacte a soporte."}`. |
| 8 | Clave Inexistente | `license_key = "pos_invalid_key_9999999999999999"` | Retorna HTTP 403 con `{"status": "error", "message": "Licencia no encontrada."}`. |
| 9 | Request Incompleto | Payload sin campo `installation_id` o sin `license_key` | Retorna HTTP 422 Unprocessable Content por validación de formulario de Laravel. |
| 10 | Override Básico: Proveedores | Plan Básico (`plan = 'basico'`) con `allowed_addons = ['suppliers']` en Retail | Retorna `features.suppliers = true`, `features.fast_pos = true`, pero todos los demás módulos Premium en `false`. |
| 11 | Override Básico: Módulo Fase 1 | Plan Básico (`plan = 'basico'`) con `allowed_addons = ['multi_rubro']` | Retorna `features.multi_rubro = true`, permitiendo acceso a Rubros en backend y UI manteniendo plan Básico. |
| 12 | Aislamiento Vertical: Presupuestos en Retail | Comercio Retail con `allowed_addons = ['quotes', 'logistics']` | Retorna `features.quotes = false` y `features.logistics = false`. La condición `!$isHardwareStore` anula el override. |
| 13 | Actualización de Plan con Addons Preexistentes | Licencia con `allowed_addons = ['mobile_app']` que se actualiza de Básico a Premium | Retorna todos los módulos de Premium en `true` y conserva `mobile_app = true` gracias a `array_merge`. |
| 14 | Degradación de Plan con Addons Preexistentes | Licencia con `allowed_addons = ['suppliers']` que se degrada de Premium a Básico | Mantiene `features.suppliers = true` de forma persistente mientras que los demás módulos Premium pasan a `false`. |
| 15 | Eliminación de Rubro del Sistema | Petición `DELETE /api/catalog/rubros/{system_id}` con token de administrador en plan Premium | Retorna HTTP 422 impidiendo la eliminación del rubro raíz del sistema. |
| 16 | Eliminación de Rubro con Categorías | Petición `DELETE /api/catalog/rubros/{id}` de un rubro que tiene categorías asociadas | Retorna HTTP 422 impidiendo la eliminación para preservar la integridad referencial. |
| 17 | Categoría en Básico con Rubro Explícito | Petición `POST /api/catalog/categories` enviando `{"name": "Pinturas", "rubro_id": 99}` en plan Básico | El backend sobrescribe `rubro_id` asociándolo al Rubro del Sistema sin arrojar error al usuario. |
| 18 | Categoría en Premium con Rubro Válido | Petición `POST /api/catalog/categories` enviando `{"name": "Esmaltes", "rubro_id": 2}` en plan Premium | El backend crea la categoría vinculada al Rubro 2 especificado. |
| 19 | Red Local Caída (Offline Grace Period) | Backend cliente intenta `syncHeartbeat` sin conexión al servidor de licencias | Si la última sincronización ocurrió hace menos de 72 horas, conserva silenciosamente el plan activo en memoria. |
| 20 | Grace Period Vencido en SaaS | Backend cliente intenta `syncHeartbeat` tras más de 72 horas de desconexión en SaaS | Actualiza `business_settings.app_plan` a `'blocked'`, requiriendo reconexión a Internet para reanudar. |

---

## 10. Matriz de Pruebas de Verificación Propuestas (PHPUnit / Pest / Scripts Aislados)

Para validar de forma empírica y reproducible el comportamiento de `pos-license-server` respecto a las Fases 0 y 1, se detallan a continuación los scripts y tests automatizados requeridos:

### Test Suite 1: Verificación de Emisión de Planes Estándar (`LicenseValidationPlanTest.php`)
1. **`test_standard_basic_retail_plan_emits_only_base_features`**:
   - Crear licencia con `plan = 'basico'`, `business_type = 'retail'`, `allowed_addons = []`.
   - Invocar `POST /api/validate`.
   - Aseverar HTTP 200 OK.
   - Aseverar `plan == 'basic'`, `plan_espanol == 'basico'`.
   - Aseverar `features.fast_pos == true`, `features.z_reports == true`.
   - Aseverar `features.multi_caja == false`, `features.multi_rubro == false`, `features.suppliers == false`, `features.arca_afip == false`, `features.mercadopago_qr == false`.
2. **`test_standard_premium_retail_plan_emits_all_premium_and_phase_features`**:
   - Crear licencia con `plan = 'premium'`, `business_type = 'retail'`, `allowed_addons = []`.
   - Invocar `POST /api/validate`.
   - Aseverar HTTP 200 OK.
   - Aseverar `plan == 'pro'`, `plan_espanol == 'premium'`.
   - Aseverar `features.multi_rubro == true`, `features.mercadopago_qr == true`, `features.arca_afip == true`, `features.suppliers == true`, `features.multi_caja == true`.
   - Aseverar `features.quotes == false`, `features.logistics == false` (aislamiento de ferretería).

### Test Suite 2: Verificación de Override / Excepciones Flexibles (`LicenseValidationOverrideTest.php`)
1. **`test_basic_plan_with_suppliers_override_enables_only_suppliers`**:
   - Crear licencia con `plan = 'basico'`, `business_type = 'retail'`, `allowed_addons = ['suppliers']`.
   - Invocar `POST /api/validate`.
   - Aseverar HTTP 200 OK.
   - Aseverar `features.suppliers == true`.
   - Aseverar `features.multi_rubro == false`, `features.multi_caja == false`, `features.checks == false`.
2. **`test_basic_plan_with_multi_rubro_override_enables_multi_rubro`**:
   - Crear licencia con `plan = 'basico'`, `business_type = 'retail'`, `allowed_addons = ['multi_rubro']`.
   - Invocar `POST /api/validate`.
   - Aseverar HTTP 200 OK.
   - Aseverar `features.multi_rubro == true`.
   - Aseverar `features.multi_caja == false`.
3. **`test_retail_business_type_blocks_hardware_exclusive_addons_even_if_in_allowed_addons`**:
   - Crear licencia con `business_type = 'retail'`, `allowed_addons = ['quotes', 'logistics']`.
   - Invocar `POST /api/validate`.
   - Aseverar `features.quotes == false` y `features.logistics == false`.

### Test Suite 3: Verificación de DRM, Hardware Lock y Expiración (`LicenseValidationDrmTest.php`)
1. **`test_first_activation_binds_installation_id`**:
   - Licencia nueva con `installation_id = null`.
   - Invocar `POST /api/validate` con `installation_id = 'UUID-TEST-100'`.
   - Aseverar HTTP 200 OK.
   - Verificar en BD que `licenses.installation_id == 'UUID-TEST-100'`.
2. **`test_subsequent_request_with_different_installation_id_is_rejected`**:
   - Licencia vinculada a `'UUID-TEST-100'`.
   - Invocar `POST /api/validate` con `installation_id = 'UUID-PIRATED-200'`.
   - Aseverar HTTP 403 Forbidden y mensaje de vinculación a otra instalación.
3. **`test_expired_saas_license_is_blocked`**:
   - Licencia SaaS con `expiration_date = yesterday`.
   - Invocar `POST /api/validate`.
   - Aseverar HTTP 403 Forbidden y estado `'expired'`.
4. **`test_lifetime_license_with_past_date_is_not_blocked`**:
   - Licencia Lifetime con `expiration_date = yesterday`.
   - Invocar `POST /api/validate`.
   - Aseverar HTTP 200 OK y estado `'active'`.
