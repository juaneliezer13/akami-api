<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Seeder.php';

use Akami\Database;
use Akami\Auth;
use Akami\Seeder;

echo "🌱 Iniciando Seeding de Akami K-Beauty...\n";

$db = Database::getConnection();
$auth = new Auth($db);

$success = Seeder::runSeed($db);

if ($success) {
    $count = $db->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $bCount = $db->query('SELECT COUNT(*) FROM brands')->fetchColumn();
    $cCount = $db->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    echo "🎉 Seeding completado exitosamente: {$count} productos, {$bCount} marcas y {$cCount} categorías en la base de datos.\n";
} else {
    echo "❌ Error al ejecutar el seeding.\n";
}
