<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';

use Akami\Database;
use Akami\Auth;

echo "🌱 Iniciando Seeding de Akami K-Beauty...\n";

$jsonPath = dirname(__DIR__, 2) . '/database/productos.json';
if (!file_exists($jsonPath)) {
    die("❌ Error: No se encontró el archivo {$jsonPath}\n");
}

$raw = file_get_contents($jsonPath);
$products = json_decode($raw, true);

if (!is_array($products)) {
    die("❌ Error al decodificar {$jsonPath}\n");
}

$db = Database::getConnection();
$auth = new Auth($db);

// Limpiar datos previos si existen para asegurar correspondencia 1:1
$db->exec('DELETE FROM products;');
$db->exec('DELETE FROM brands;');
$db->exec('DELETE FROM categories;');
// Reset sqlite sequence
$db->exec("DELETE FROM sqlite_sequence WHERE name IN ('products', 'brands', 'categories');");

echo "🧹 Tablas limpias.\n";

// Extraer marcas y categorías únicas
$brands = [];
$categories = [];

foreach ($products as $p) {
    if (!empty($p['brand'])) {
        $brands[$p['brand']] = true;
    }
    if (!empty($p['category'])) {
        $categories[$p['category']] = true;
    }
}

// Insertar marcas
$brandStmt = $db->prepare('INSERT INTO brands (name) VALUES (:n)');
foreach (array_keys($brands) as $bName) {
    $brandStmt->execute([':n' => $bName]);
}
echo "✨ Insertadas " . count($brands) . " marcas oficiales.\n";

// Insertar categorías
$catStmt = $db->prepare('INSERT INTO categories (name) VALUES (:n)');
foreach (array_keys($categories) as $cName) {
    $catStmt->execute([':n' => $cName]);
}
echo "✨ Insertadas " . count($categories) . " categorías.\n";

// Insertar productos
$prodStmt = $db->prepare('
    INSERT INTO products (
        id, title, brand, category, price, image, gallery, description,
        skin_types, compatibility, attributes, ingredients, benefits,
        use_instructions, is_active, created_at, updated_at
    ) VALUES (
        :id, :title, :brand, :category, :price, :image, :gallery, :description,
        :skin_types, :compatibility, :attributes, :ingredients, :benefits,
        :use_instructions, :is_active, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
    )
');

$count = 0;
foreach ($products as $p) {
    $img = $p['image'] ?? '';
    // Ajustar prefijo de imagen dinámica a /uploads/products/
    if (str_starts_with($img, './images/products/')) {
        $filename = basename($img);
        $img = '/uploads/products/' . $filename;
    }

    $gallery = [];
    if (!empty($img)) {
        $gallery[] = $img;
    }

    $prodStmt->execute([
        ':id' => $p['id'],
        ':title' => $p['title'],
        ':brand' => $p['brand'],
        ':category' => $p['category'],
        ':price' => (float) ($p['price'] ?? 0),
        ':image' => $img,
        ':gallery' => json_encode($gallery, JSON_UNESCAPED_SLASHES),
        ':description' => $p['benefits'] ?? $p['description'] ?? '',
        ':skin_types' => json_encode($p['skinTypes'] ?? [], JSON_UNESCAPED_UNICODE),
        ':compatibility' => $p['compatibility'] ?? '',
        ':attributes' => json_encode($p['attributes'] ?? [], JSON_UNESCAPED_UNICODE),
        ':ingredients' => $p['ingredients'] ?? '',
        ':benefits' => $p['benefits'] ?? '',
        ':use_instructions' => $p['use'] ?? '',
        ':is_active' => 1,
    ]);
    $count++;
}

echo "🎉 Seeding completado exitosamente: {$count} productos registrados en la base de datos con estado visible.\n";
