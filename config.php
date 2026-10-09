<?php
// config.php — Conexión usando PDO

$host       = "sql101.byethost14.com";
$usuario    = "b14_42161418";
$password   = "cartuchera"; // Pon tu clave real
$base_datos = "b14_42161418_Fruanoba"; 

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$base_datos;charset=utf8mb4",
        $usuario,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    exit(json_encode([
        'error' => 'Error de conexión a la base de datos: ' . $e->getMessage()
    ]));
}