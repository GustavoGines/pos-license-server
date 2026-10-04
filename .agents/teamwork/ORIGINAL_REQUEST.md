# Original User Request

## 2026-10-03T21:59:39Z

# Teamwork Project Prompt — Draft

> Status: Launched
> Goal: Craft prompt → get user approval → delegate to teamwork_preview
> Requested team: Equipo completo (Full team)

Realizar una auditoría de QA exhaustiva del repositorio `pos-license-server` en su rama actual. El objetivo es identificar errores, bugs y evaluar la preparación del código para las fases 0 y 1 del `PLAN_IMPLEMENTACION.md`, con especial atención en la lógica de planes (Básico vs Premium) y la asignación flexible de módulos (ej. habilitar un módulo premium en un plan básico).

Working directory: C:\laragon\www\pos-license-server
Integrity mode: development

## Requirements

### R1. Auditoría de código y lógica de planes
El equipo debe analizar el código fuente del servidor de licencias para identificar vulnerabilidades o bugs, y evaluar si el estado actual cumple con los requisitos de las fases 0 y 1 del `PLAN_IMPLEMENTACION.md`. La revisión debe centrarse en cómo se procesan y emiten los planes (Básico vs Premium).

### R2. Validación de asignación flexible de módulos
El equipo debe verificar rigurosamente el mecanismo de override/excepciones que permite asignar un plan restrictivo (ej. Básico) pero habilitar módulos específicos de forma individual (ej. habilitar el módulo 'proveedores' aunque requiera Premium).

### R3. Pruebas de verificación ejecutables
El equipo debe crear scripts de prueba (PHPUnit, Pest o scripts PHP aislados) y ejecutarlos contra el código local para comprobar empíricamente que la lógica de módulos y planes funciona como se espera, en lugar de solo hacer una lectura estática.

### R4. Reporte final de auditoría
El equipo debe consolidar todos los hallazgos en un reporte detallado. No se deben aplicar las correcciones al código fuente del sistema; el objetivo es puramente diagnóstico y de auditoría.

## Acceptance Criteria

### Verificación de lógica de negocio
- [ ] Se ha creado y ejecutado al menos un script de prueba que verifica la asignación correcta de flags para un plan Básico estándar y un plan Premium estándar.
- [ ] Se ha creado y ejecutado al menos un script de prueba que verifica el caso de uso específico: un inquilino con plan Básico pero con un módulo extra (ej. 'proveedores') habilitado manualmente.

### Entregables
- [ ] Se ha generado un archivo `QA_AUDIT_REPORT.md` en la raíz del proyecto (`C:\laragon\www\pos-license-server`).
- [ ] El reporte detalla explícitamente los resultados de la ejecución de los scripts de prueba.
- [ ] El reporte lista claramente cualquier bug, error o discrepancia encontrada respecto a la fase 0 y 1 del plan de implementación.
