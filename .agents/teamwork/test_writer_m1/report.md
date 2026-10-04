# Empirical Test Execution Report: Plan Validation Suite (Milestone 1)

**Agent**: `test_writer_m1`  
**Role**: QA Test Writer  
**Target Repository**: `pos-license-server` (Branch: `feature/fase-1-rubros-jerarquia`)  
**Target File**: `C:\laragon\www\pos-license-server\tests\Feature\PlanValidationTest.php`  
**Date**: 2026-10-03  
**Execution Environment**: PHP 8.3.30, Laravel 12.x, PHPUnit 12.5.14, SQLite `:memory:` with `RefreshDatabase`  

---

## 1. Executive Summary

A comprehensive automated test suite has been authored at `tests/Feature/PlanValidationTest.php` to perform empirical verification of plan tier validation, feature flag distribution, manual add-on overrides, vertical restrictions, DRM hardware locks, and SaaS vs Lifetime expiration handling on `pos-license-server`.

All 10 test cases passed cleanly with 81 assertions, zero failures, and zero errors.

---

## 2. Test Coverage & Specification Mapping

| # | Test Method Name | Requirement / Feature | Key Assertions | Result |
|---|---|---|---|---|
| 1 | `test_standard_basic_plan_flags_retail` | Standard Basic Plan (Retail) | HTTP 200, `plan='basic'`, `plan_espanol='basico'`. Base flags (`fast_pos`, `z_reports`) = `true`. All 11 premium flags = `false`. Hardware & addon flags = `false`. | **PASS** |
| 2 | `test_standard_premium_plan_flags_retail` | Standard Premium Plan (Retail) | HTTP 200, `plan='pro'`, `plan_espanol='premium'`. Base flags = `true`. All 11 premium flags = `true` (including Phase 0/1 flags: `multi_rubro`, `mercadopago_qr`, `arca_afip`). Hardware exclusive (`quotes`, `logistics`) = `false`. | **PASS** |
| 3 | `test_basic_plan_with_manual_override_suppliers` | Basic Plan Flexible Override (`suppliers`) | HTTP 200, `features.suppliers` = `true` via `allowed_addons=['suppliers']`. Base flags = `true`. Other premium flags remain `false`. | **PASS** |
| 4 | `test_discrepancy_check_spanish_key_proveedores` | Discrepancy: Spanish key `proveedores` | HTTP 200, `features.suppliers` is `false` because system expects canonical English key `'suppliers'`. Key `'proveedores'` is not recognized in features dictionary. | **PASS** |
| 5 | `test_vertical_restriction_enforcement_blocks_quotes_and_logistics_on_retail` | Vertical Isolation Enforcement | Retail license with `allowed_addons=['quotes', 'logistics']` forces `quotes=false` and `logistics=false`. Hardware store license natively enables `quotes=true` and `logistics=true`. | **PASS** |
| 6 | `test_drm_hardware_lock_enforcement` | DRM Hardware Lock (`installation_id`) | 1st activation binds `installation_id` (HTTP 200). 2nd activation with identical `installation_id` succeeds (HTTP 200). 3rd activation with mismatching `installation_id` rejected with HTTP 403 (`status='error'`, message `'Esta licencia ya está vinculada a otra instalación.'`). | **PASS** |
| 7 | `test_saas_expiration_vs_lifetime` | SaaS Expiration vs Lifetime Immunity | SaaS license with expired date is rejected with HTTP 403 (`status='expired'`). Lifetime license with past date is accepted with HTTP 200 (`status='active'`). | **PASS** |
| 8 | `test_suspended_license_returns_403_suspended` | License Lifecycle Security | Inactive license (`is_active=false`) is rejected with HTTP 403 (`status='suspended'`). | **PASS** |
| 9 | `test_invalid_license_key_returns_403_not_found` | API Key Security Verification | Non-existent `license_key` is rejected with HTTP 403 (`status='error'`, message `'Licencia no encontrada.'`). | **PASS** |
| 10 | `test_missing_fields_validation_error` | Request Payload Validation | Request omitting `license_key` or `installation_id` returns HTTP 422 Unprocessable Entity with validation errors. | **PASS** |

---

## 3. Verbatim Execution Evidence

### Execution Command 1: Artisan Test Runner

```powershell
php artisan test tests/Feature/PlanValidationTest.php
```

**Exit Code**: `0`  
**Execution Time**: `7.82s`  
**Verbatim Output**:
```text
   PASS  Tests\Feature\PlanValidationTest
  ✓ standard basic plan flags retail                                                                             1.24s  
  ✓ standard premium plan flags retail                                                                           0.67s  
  ✓ basic plan with manual override suppliers                                                                    0.68s  
  ✓ discrepancy check spanish key proveedores                                                                    0.70s  
  ✓ vertical restriction enforcement blocks quotes and logistics on retail                                       0.68s  
  ✓ drm hardware lock enforcement                                                                                0.69s  
  ✓ saas expiration vs lifetime                                                                                  0.72s  
  ✓ suspended license returns 403 suspended                                                                      0.69s  
  ✓ invalid license key returns 403 not found                                                                    0.70s  
  ✓ missing fields validation error                                                                              0.72s  

  Tests:    10 passed (81 assertions)
  Duration: 7.82s
```

---

### Execution Command 2: Direct PHPUnit Runner

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/PlanValidationTest.php
```

**Exit Code**: `0`  
**Execution Time**: `7.515s`  
**Verbatim Output**:
```text
PHPUnit 12.5.14 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.30
Configuration: C:\laragon\www\pos-license-server\phpunit.xml

..........                                                        10 / 10 (100%)

Time: 00:07.515, Memory: 66.00 MB

OK (10 tests, 81 assertions)
```

---

### Execution Command 3: Full Repository Test Suite

```powershell
php artisan test
```

**Exit Code**: `0`  
**Execution Time**: `8.21s`  
**Verbatim Output**:
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

### Code Style Compliance (Laravel Pint)

```powershell
vendor/bin/pint --test tests/Feature/PlanValidationTest.php
```

**Exit Code**: `0`  
**Verbatim Output**:
```text
  .

  ──────────────────────────────────────────────────────────────────────────────────────────────────────────── Laravel  
    PASS   .................................................................................................... 1 file  
```

---

## 4. Key QA Findings & Discrepancies Escalate

1. **Flexible Module Overrides Work Reliably**:
   - The API correctly respects individual add-ons in `allowed_addons` (`$license->allowed_addons`), enabling granular feature toggles (e.g. `suppliers = true`) on restrictive plans (`basico`).
2. **Canonical English Naming vs Spanish Translation (`suppliers` vs `proveedores`)**:
   - The system strictly matches against the key `'suppliers'`. When an administrator or integration inputs `'proveedores'`, the module is ignored (`features.suppliers` remains `false`).
   - *Recommendation*: The implementing team should evaluate adding alias mapping (`'proveedores' => 'suppliers'`) in `LicenseValidationController::mapFeatures` or standardizing all layers exclusively on English keys.
3. **Vertical Isolation Takes Strict Precedence**:
   - The vertical restriction check runs prior to the add-on grant check: `quotes` and `logistics` are unconditionally forced to `false` if `business_type !== 'hardware_store'`, preventing cross-vertical leakages even if mistakenly configured in `allowed_addons`.
4. **DRM Hardware Locking is Atomically Bound**:
   - Unbound licenses (`installation_id = null`) bind on first activation; any subsequent activation from an alternative hardware device is blocked with HTTP 403.
5. **No Production Source Code Was Modified**:
   - All tests were authored in `tests/Feature/PlanValidationTest.php`. No changes were made to `app/`, `routes/`, `config/`, or `database/`.
