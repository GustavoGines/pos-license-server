# Reglas y Directrices Globales del Agente (Sistema POS)

Estas reglas definen el comportamiento fundamental y de por vida del agente (Antigravity) al trabajar en este proyecto. El agente siempre debe leer y respetar estas instrucciones antes de tomar decisiones.

## 1. Seguridad y Autorización (Reglas Estrictas)
*   **Git y Repositorios:** NO ejecutar comandos que muten el historial de control de versiones (`git commit`, `git push`, `git merge`, `git reset`, etc.) sin solicitar y recibir autorización explícita del usuario.
*   **Modificación de Código:** NO realizar cambios destructivos, refactorizaciones masivas o borrar archivos sin explicar previamente el plan y obtener permiso del usuario.
*   **Archivos Sensibles:** NUNCA visualizar, modificar, mostrar en el chat o exportar credenciales, contraseñas, tokens de APIs o el contenido de archivos `.env` sin una orden directa y consciente del usuario.
*   **Comandos Peligrosos:** Solicitar autorización antes de ejecutar comandos destructivos en la terminal (ej., borrado de carpetas, borrado de bases de datos `migrate:fresh`, comandos de sistema).

## 2. Desarrollo y Calidad del Sistema
*   **Backend y Servidor de Licencias (Laravel / PHP):**
    *   El ecosistema incluye la API principal (`pos-backend`) y el Servidor de Licencias (`pos-license-server`).
    *   Respetar la arquitectura establecida (Controladores, Servicios, Modelos) en ambos sistemas.
    *   Escribir código siguiendo el estándar PSR-12.
    *   No obviar la seguridad: validar siempre las peticiones (Form Requests) y evitar inyecciones SQL (usar Eloquent o bindings).
*   **Frontend (Flutter / Dart):**
    *   Mantener el código limpio siguiendo las reglas de linting de Dart.
    *   Asegurar que la interfaz (UI) sea responsiva y no genere errores de "overflow" en distintas resoluciones.
    *   Separar la lógica de negocio de la interfaz visual.
*   **Pruebas (Testing):** Al implementar una nueva funcionalidad clave (especialmente en el backend), proponer y/o escribir pruebas automatizadas para verificar que la lógica no rompe otras partes del sistema.

## 3. Flujo de Trabajo (Workflow)
*   **Planificación:** Ante una tarea compleja, siempre delinear un plan paso a paso y validarlo con el usuario antes de empezar a picar código.
*   **Pasos Pequeños y Seguros:** Hacer cambios incrementales. Probar o compilar cada parte para asegurar su funcionamiento antes de avanzar al siguiente punto.
*   **Documentación Viva:** Si se realizan cambios drásticos en la arquitectura o en la forma en que se comunican las APIs, recordar que se debe mantener actualizada la documentación (como el archivo `PROJECT.md`).

## 4. Comunicación
*   **Claridad y Concisión:** Evitar respuestas innecesariamente largas. Ir al grano con explicaciones técnicas claras.
*   **Transparencia ante Errores:** Si un comando o código falla, mostrar el error, explicar por qué ocurrió y proponer una solución. No intentar ocultar las fallas iterando silenciosamente sin límite.

## 5. Gestión de Memoria Continua (MEMORY.md)
*   **Lectura Obligatoria:** Al inicio de cada sesión o al retomar una tarea, el agente DEBE leer el archivo principal de memoria `[MEMORY.md](file:///C:/laragon/www/Sistema_POS/MEMORY.md)` para conocer el contexto actual, qué fue lo último que se hizo y cuáles son los problemas o tareas pendientes inmediatas.
*   **Actualización Constante:** Al finalizar una tarea, sesión de trabajo o hito importante, el agente DEBE actualizar dicho archivo `MEMORY.md`. 
*   **Mantenimiento Inteligente (Límite estricto ~50 líneas):** El archivo `MEMORY.md` debe mantenerse corto, ágil y altamente relevante (alrededor de 50 líneas como máximo). El agente, con criterio Senior, debe eliminar proactivamente el contexto obsoleto, las subtareas ya superadas o bugs resueltos que ya no aporten valor al presente, reemplazándolos con el nuevo contexto.
*   **Estructura de la Memoria:** El archivo debe priorizar:
    1.  **Estado Actual:** En qué parte del proyecto estamos exactamente ahora.
    2.  **Último Hito:** Breve resumen de lo último que funcionó o se modificó.
    3.  **Siguiente Paso Inmediato:** Lo que se debe hacer a continuación (Next Action).
    4.  **Decisiones Técnicas Activas:** Contexto técnico vital a recordar para no volver a cometer los mismos errores.
