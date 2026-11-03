<?php
// Como participan los menores de edad en un sendero (senderos.menores_modo):
//   no_admite : el sendero no acepta menores.
//   igual     : pagan lo mismo que el adulto (monto_menor vacio = precio del adulto).
//   precio    : mismo paquete del adulto con su propio precio de menor en cada inversion.
//   gratis    : no pagan (monto_menor = 0).
//   propias   : tienen inversiones propias para menores (es_menor = 1).
// senderos.menores_misma_inversion se mantiene en 1 para todos menos "propias", asi
// el resto del sistema (precios, cobros, reportes) sigue funcionando igual.

if (!function_exists('sg_menores_modos')) {
    function sg_menores_modos(): array
    {
        return [
            'igual' => ['Pagan igual que el adulto', 'El menor paga el mismo precio de la inversion elegida.'],
            'precio' => ['Mismo paquete, precio de menor', 'Cada inversion tiene su precio para menores. Si lo dejas vacio, paga igual que el adulto.'],
            'gratis' => ['Gratis', 'Los menores pueden inscribirse y no pagan.'],
            'propias' => ['Inversiones propias para menores', 'Los menores eligen entre opciones creadas solo para ellos.'],
            'no_admite' => ['No se admiten menores', 'No se pueden agregar menores al inscribirse.'],
        ];
    }

    function sg_menores_modo_columna(mysqli $conn): bool
    {
        static $existe = null;
        if ($existe === null) {
            $res = mysqli_query($conn, "SHOW COLUMNS FROM senderos LIKE 'menores_modo'");
            $existe = $res && mysqli_num_rows($res) === 1;
        }
        return $existe;
    }

    /** Modo de un sendero leido de la base (con respaldo si aun no existe la columna). */
    function sg_menores_modo(array $sendero): string
    {
        $modo = (string) ($sendero['menores_modo'] ?? '');
        if (isset(sg_menores_modos()[$modo])) {
            return $modo;
        }
        return (int) ($sendero['menores_misma_inversion'] ?? 1) === 1 ? 'precio' : 'propias';
    }

    function sg_menores_admitidos(array $sendero): bool
    {
        return sg_menores_modo($sendero) !== 'no_admite';
    }

    /** Texto corto del precio de menor de una inversion del adulto, segun el modo. */
    function sg_menores_precio_texto(string $modo, ?float $montoMenor, float $montoAdulto): string
    {
        if ($modo === 'gratis' || ($montoMenor !== null && $montoMenor <= 0 && $modo === 'precio')) {
            return 'Menores: gratis';
        }
        if ($modo === 'igual' || $montoMenor === null) {
            return 'Menores: mismo precio';
        }
        return 'Menores: RD$ ' . number_format($montoMenor, 2);
    }
}
