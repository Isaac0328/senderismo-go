<?php

if (!function_exists('sg_migraciones_directorio')) {
    function sg_migraciones_directorio(): string
    {
        return dirname(__DIR__) . '/migraciones';
    }

    function sg_migraciones_asegurar_control(mysqli $conn): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS sistema_migraciones (
                id VARCHAR(160) NOT NULL PRIMARY KEY,
                descripcion VARCHAR(255) NOT NULL,
                checksum CHAR(64) NOT NULL,
                aplicada_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";

        if (!mysqli_query($conn, $sql)) {
            throw new RuntimeException('No se pudo preparar el control de migraciones: ' . mysqli_error($conn));
        }
    }

    function sg_migraciones_cargar(): array
    {
        $directorio = sg_migraciones_directorio();
        $archivos = array_merge(
            glob($directorio . '/*.php') ?: [],
            glob($directorio . '/CARGADAS/*.php') ?: []
        );
        sort($archivos, SORT_NATURAL | SORT_FLAG_CASE);

        $migraciones = [];
        foreach ($archivos as $archivo) {
            $migracion = require $archivo;
            if (!is_array($migracion) || empty($migracion['id']) || empty($migracion['descripcion']) || !isset($migracion['up']) || !is_callable($migracion['up'])) {
                throw new RuntimeException('La migracion ' . basename($archivo) . ' no tiene una estructura valida.');
            }

            $id = trim((string) $migracion['id']);
            if (!preg_match('/^[0-9]{8}_[0-9]{3}_[a-z0-9_]+$/', $id)) {
                throw new RuntimeException('El identificador de migracion no es valido: ' . $id);
            }

            if (isset($migraciones[$id])) {
                throw new RuntimeException('Hay dos migraciones con el mismo identificador: ' . $id);
            }

            $migracion['archivo'] = $archivo;
            $migracion['checksum'] = hash_file('sha256', $archivo);
            $migraciones[$id] = $migracion;
        }

        return $migraciones;
    }

    function sg_migraciones_aplicadas(mysqli $conn): array
    {
        sg_migraciones_asegurar_control($conn);
        $resultado = mysqli_query($conn, 'SELECT id, checksum, aplicada_at FROM sistema_migraciones ORDER BY id ASC');
        if (!$resultado) {
            throw new RuntimeException('No se pudo leer el historial de migraciones: ' . mysqli_error($conn));
        }

        $aplicadas = [];
        while ($fila = mysqli_fetch_assoc($resultado)) {
            $aplicadas[$fila['id']] = $fila;
        }
        mysqli_free_result($resultado);

        return $aplicadas;
    }

    function sg_migraciones_pendientes(mysqli $conn): array
    {
        $migraciones = sg_migraciones_cargar();
        $aplicadas = sg_migraciones_aplicadas($conn);
        $pendientes = [];

        foreach ($migraciones as $id => $migracion) {
            if (!isset($aplicadas[$id])) {
                $pendientes[$id] = $migracion;
                continue;
            }

            if (!hash_equals((string) $aplicadas[$id]['checksum'], (string) $migracion['checksum'])) {
                throw new RuntimeException('La migracion ya aplicada ' . $id . ' fue modificada. Crea una nueva migracion en vez de cambiarla.');
            }
        }

        return $pendientes;
    }

    function sg_migraciones_archivar_aplicadas(mysqli $conn): array
    {
        $archivadas = [];
        $errores = [];
        $migraciones = sg_migraciones_cargar();
        $aplicadas = sg_migraciones_aplicadas($conn);
        $directorioRaiz = realpath(sg_migraciones_directorio());
        $directorioCargadas = sg_migraciones_directorio() . '/CARGADAS';

        foreach ($migraciones as $id => $migracion) {
            if (!isset($aplicadas[$id])) {
                continue;
            }

            $origen = $migracion['archivo'];
            if (realpath(dirname($origen)) !== $directorioRaiz) {
                continue;
            }

            if (!hash_equals((string) $aplicadas[$id]['checksum'], (string) $migracion['checksum'])) {
                $errores[] = basename($origen) . ': el archivo no coincide con el checksum registrado.';
                continue;
            }

            if (!is_dir($directorioCargadas) && !@mkdir($directorioCargadas, 0755, true) && !is_dir($directorioCargadas)) {
                $errores[] = 'No se pudo crear migraciones/CARGADAS.';
                break;
            }

            $destino = $directorioCargadas . '/' . basename($origen);
            if (is_file($destino)) {
                if (!hash_equals((string) $migracion['checksum'], (string) hash_file('sha256', $destino))) {
                    $errores[] = basename($origen) . ': ya existe en CARGADAS con contenido diferente.';
                    continue;
                }
                if (!@unlink($origen)) {
                    $errores[] = basename($origen) . ': no se pudo retirar el duplicado.';
                    continue;
                }
            } elseif (!@rename($origen, $destino)) {
                $errores[] = basename($origen) . ': no se pudo mover a CARGADAS.';
                continue;
            }

            $archivadas[] = basename($origen);
        }

        return ['archivadas' => $archivadas, 'errores' => $errores];
    }

    function sg_migraciones_aplicar(mysqli $conn): array
    {
        $pendientes = sg_migraciones_pendientes($conn);
        $aplicadas = [];

        foreach ($pendientes as $id => $migracion) {
            try {
                $migracion['up']($conn);
            } catch (Throwable $error) {
                throw new RuntimeException('La migracion ' . $id . ' no se pudo aplicar: ' . $error->getMessage(), 0, $error);
            }

            $stmt = mysqli_prepare($conn, 'INSERT INTO sistema_migraciones (id, descripcion, checksum) VALUES (?, ?, ?)');
            if (!$stmt) {
                throw new RuntimeException('No se pudo registrar la migracion ' . $id . ': ' . mysqli_error($conn));
            }

            $descripcion = trim((string) $migracion['descripcion']);
            $checksum = $migracion['checksum'];
            mysqli_stmt_bind_param($stmt, 'sss', $id, $descripcion, $checksum);
            $ejecutada = mysqli_stmt_execute($stmt);
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);

            if (!$ejecutada) {
                throw new RuntimeException('La migracion ' . $id . ' se aplico, pero no se pudo registrar: ' . $error);
            }

            $aplicadas[] = $migracion;
        }

        return $aplicadas;
    }
}
