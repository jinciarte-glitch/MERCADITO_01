<?php
// includes/migraciones.php — marca de "migración ya hecha".
//
// Antes, cada request a la API corría decenas de CREATE TABLE IF NOT EXISTS y
// consultas a information_schema. Ahora, cuando una migración termina bien,
// se guarda un archivito (includes/.migrado-<nombre>) con su versión y los
// próximos requests lo saltean con una sola lectura de disco.
//
// - Para correr una migración de nuevo (por ej. después de agregarle algo):
//   subí el número de versión en la llamada, o borrá el archivo .migrado-<nombre>.
// - Si la carpeta includes/ no se puede escribir, no pasa nada: la migración
//   corre en cada request, como antes (más lento, pero igual de correcto).

function migracion_hecha($nombre, $version) {
    $archivo = __DIR__ . '/.migrado-' . $nombre;
    return is_file($archivo) && trim((string)@file_get_contents($archivo)) === (string)$version;
}

function migracion_marcar($nombre, $version) {
    @file_put_contents(__DIR__ . '/.migrado-' . $nombre, (string)$version, LOCK_EX);
}
