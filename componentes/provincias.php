<?php

function sg_provincias_activas(mysqli $conn): array
{
    $result = mysqli_query($conn, 'SELECT id, nombre FROM provincias WHERE activo = 1 ORDER BY nombre');
    if (!$result) {
        return [];
    }
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
    return $rows;
}

function sg_provincia_para_sendero(mysqli $conn, string $nombre, int $senderoId = 0): string
{
    $nombre = trim($nombre);
    if ($nombre === '') {
        return '';
    }
    $stmt = mysqli_prepare($conn, 'SELECT nombre FROM provincias WHERE nombre = ? AND activo = 1 LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Aplica la actualizacion del catalogo de provincias antes de guardar.');
    }
    mysqli_stmt_bind_param($stmt, 's', $nombre);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('No se pudo consultar el catalogo de provincias.');
    }
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($row) {
        return (string) $row['nombre'];
    }

    // Los valores historicos pueden conservarse, pero no asignarse a otra ruta.
    if ($senderoId > 0) {
        $stmt = mysqli_prepare($conn, 'SELECT provincia FROM senderos WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('No se pudo consultar el sendero.');
        }
        mysqli_stmt_bind_param($stmt, 'i', $senderoId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new RuntimeException('No se pudo consultar el sendero.');
        }
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($row && trim((string) $row['provincia']) === $nombre) {
            return (string) $row['provincia'];
        }
    }
    throw new InvalidArgumentException('Selecciona una provincia activa del listado.');
}
