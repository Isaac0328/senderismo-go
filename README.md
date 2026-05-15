# Senderismo Go

Proyecto web en PHP y MySQL para gestionar senderos, usuarios, registros de participantes, catalogos administrativos y reportes de Senderismo Go.

## Requisitos locales

- XAMPP con Apache, PHP y MySQL.
- Base de datos MySQL llamada `sgbd`.
- Carpeta del proyecto dentro de `htdocs`, por ejemplo `C:\xampp\htdocs\SG`.

## Configuracion

El archivo `configuracion.php` detecta si el proyecto corre en local o en hosting. Para produccion se deben reemplazar los valores:

- `TU_DB_NAME_HOSTINGER`
- `TU_DB_USER_HOSTINGER`
- `TU_DB_PASS_HOSTINGER`

## Base de datos

El script versionado de estructura esta en `bd/estructura_sgbd.sql`.
Los respaldos completos con datos reales se mantienen fuera de Git por seguridad.

## Acceso local

Abrir en el navegador:

```text
http://localhost/SG/
```
