<?php

function sg_actualizar_senderos_vencidos(mysqli $conn): int
{
    static $ejecutado = false;

    if ($ejecutado) {
        return 0;
    }

    $ejecutado = true;

    $sql = "
        UPDATE senderos
        SET estado = 'visitado'
        WHERE activo = 1
          AND estado = 'pendiente'
          AND fecha_sendero IS NOT NULL
          AND fecha_sendero < CURDATE()
    ";

    if (!mysqli_query($conn, $sql)) {
        return 0;
    }

    return max(0, mysqli_affected_rows($conn));
}
