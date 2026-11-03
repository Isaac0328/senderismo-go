# Instrucciones para agentes (Codex, Claude)

Antes de cambiar nada en este proyecto:

1. Lee `docs/REGISTRO_DE_CAMBIOS.md` completo (sobre todo "Estado actual" y las últimas entradas).
2. Vuelve a leer los archivos que vas a tocar: el otro agente puede haberlos cambiado.
3. Trabaja solo en local (`C:\xampp\htdocs\SG`). Producción solo con el visto bueno explícito del usuario, respaldando y comparando antes de subir.
4. Al terminar, sube la versión y agrega tu entrada al inicio del historial en `docs/REGISTRO_DE_CAMBIOS.md`, y actualiza "Estado actual".
5. Cambios de base de datos: migración nueva; nunca edites una migración ya aplicada.

No subas a producción `docs/`, `AGENTS.md`, `CLAUDE.md`, `.private/` ni `herramientas/`.

Para subir a producción sigue el paso a paso de la sección 1b de `docs/REGISTRO_DE_CAMBIOS.md` (respaldar, comparar, subir con `scp` al alias SSH `senderismo`, verificar con MD5, `php -l` y `curl`).
