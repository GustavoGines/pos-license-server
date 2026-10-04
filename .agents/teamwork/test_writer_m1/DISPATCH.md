# Dispatch Assignment: Test Writer (Milestone 1)

**Assigned Agent**: test_writer_m1
**Role**: QA Test Writer
**Working Directory**: C:\laragon\www\pos-license-server\.agents\teamwork\test_writer_m1
**Reference Document**: C:\laragon\www\pos-license-server\.agents\teamwork\ORIGINAL_REQUEST.md
**Project Document**: C:\laragon\www\pos-license-server\PROJECT.md

## Objective
Create and execute empirical verification tests for `pos-license-server` on its current branch.
Target file for tests: `tests/Feature/PlanValidationTest.php` in the repository test suite.
Additionally, you may maintain a standalone runner or audit script if helpful.

## Required Test Cases
1. **Standard Basic Plan Flags**:
   - Verify `POST /api/validate` for standard Basic plan (`plan = 'basico'`, `business_type = 'retail'`).
   - Asserts HTTP 200, `plan = 'basic'`, `plan_espanol = 'basico'`.
   - Asserts base features are true (`fast_pos`, `z_reports`).
   - Asserts all premium features are false (`multi_caja`, `multi_rubro`, `suppliers`, `mercadopago_qr`, `arca_afip`, etc.).
2. **Standard Premium Plan Flags**:
   - Verify `POST /api/validate` for standard Premium plan (`plan = 'premium'`, `business_type = 'retail'`).
   - Asserts HTTP 200, `plan = 'pro'`, `plan_espanol = 'premium'`.
   - Asserts all Phase 0/1 premium features are true (`multi_rubro`, `mercadopago_qr`, `arca_afip`, `suppliers`, `multi_caja`, etc.).
   - Asserts vertical-exclusive features (`quotes`, `logistics`) are false for retail.
3. **Basic Plan with Manual Override (extra module: 'suppliers')**:
   - Verify `POST /api/validate` for Basic plan with `allowed_addons = ['suppliers']`.
   - Asserts `features.suppliers = true`, while maintaining other premium features (`multi_rubro`, `multi_caja`, etc.) as false.
4. **Discrepancy Check (Spanish key 'proveedores')**:
   - Verify behavior when colloquial key `'proveedores'` is provided in `allowed_addons`.
   - Asserts that without alias mapping, `'suppliers'` remains false and `'proveedores'` is not recognized.
5. **Vertical Restriction Enforcement**:
   - Verify that for retail, even if `quotes` or `logistics` are placed in `allowed_addons`, they are forced to false.
6. **DRM & Hardware Lock**:
   - First activation binds `installation_id`.
   - Subsequent activation with mismatched `installation_id` is rejected with HTTP 403.
7. **SaaS Expiration vs Lifetime**:
   - Expired SaaS license is rejected with HTTP 403 (`status = 'expired'`).
   - Expired date on Lifetime license is accepted (active).

## Execution Requirement
- Run the test suite using `php artisan test tests/Feature/PlanValidationTest.php` (or `php vendor/phpunit/phpunit/phpunit tests/Feature/PlanValidationTest.php`).
- Ensure all tests use `RefreshDatabase` with the SQLite `:memory:` configuration from `phpunit.xml`.
- Capture full execution command and terminal output verbatim in your report.

## Constraint
DO NOT modify any production source code in `app/`, `routes/`, `config/`, or `database/`. Only create tests under `tests/Feature/PlanValidationTest.php` and metadata under your working directory.

## Output Requirements
Write your detailed execution report to `C:\laragon\www\pos-license-server\.agents\teamwork\test_writer_m1\report.md` and complete `handoff.md`.


## 2026-10-03T22:10:12Z
You are assigned as test_writer_m1.
Your working directory is: C:\laragon\www\pos-license-server\.agents\teamwork\test_writer_m1
Your dispatch assignment is in: C:\laragon\www\pos-license-server\.agents\teamwork\test_writer_m1\DISPATCH.md
MANDATORY: Read C:\laragon\www\pos-license-server\.agents\teamwork\ORIGINAL_REQUEST.md before starting work.
Also read C:\laragon\www\pos-license-server\PROJECT.md.

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Task:
Create and run empirical verification tests for repository pos-license-server on its current branch.
Write the test suite at: C:\laragon\www\pos-license-server\tests\Feature\PlanValidationTest.php
Ensure the test suite covers:
1. Standard Basic Plan flags (Retail): asserts HTTP 200, plan 'basic' / 'basico', base features true (fast_pos, z_reports), premium features false (multi_caja, multi_rubro, suppliers, mercadopago_qr, arca_afip, etc.).
2. Standard Premium Plan flags (Retail): asserts HTTP 200, plan 'pro' / 'premium', base features true, all 11 premium features true (including Phase 0/1 flags multi_rubro, mercadopago_qr, arca_afip), hardware exclusive features (quotes, logistics) false.
3. Basic Plan with Manual Override (extra module 'suppliers'): asserts features.suppliers is true, while other premium features remain false.
4. Discrepancy test with colloquial Spanish key 'proveedores': asserts that passing 'proveedores' in allowed_addons is currently ignored because the system expects 'suppliers'.
5. Vertical restriction enforcement: retail blocking quotes and logistics even if placed in allowed_addons.
6. DRM & Hardware Lock: first activation binds installation_id; subsequent activation with different installation_id is rejected with HTTP 403.
7. SaaS expiration vs Lifetime: expired SaaS rejected with HTTP 403 ('expired'); expired date on Lifetime accepted.

Execution Requirement:
- Run the tests via: `php artisan test tests/Feature/PlanValidationTest.php` (or direct PHPUnit).
- Ensure all tests pass cleanly using RefreshDatabase with the preconfigured SQLite :memory:.
- Record the exact execution command, exit code, and terminal output verbatim in your report.

Constraint:
DO NOT modify any production source code in app/, routes/, config/, or database/. Only create tests/Feature/PlanValidationTest.php and your agent metadata.

Write your report to: C:\laragon\www\pos-license-server\.agents\teamwork\test_writer_m1\report.md and complete handoff.md.
Send a message back when completed.
