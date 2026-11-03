<?php

namespace Akami;

use PDO;

class Seeder
{
    public static function seedIfEmpty(PDO $db): void
    {
        $stmt = $db->query('SELECT COUNT(*) FROM products');
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            return; // Ya tiene datos
        }

        self::runSeed($db);
    }

    public static function runSeed(PDO $db): void
    {
        // Buscar archivo json en rutas posibles
        $candidates = [
            dirname(__DIR__, 2) . '/database/productos.json',
            '/var/www/database/productos.json',
            dirname(__DIR__) . '/database/productos.json',
        ];

        $jsonPath = null;
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $jsonPath = $candidate;
                break;
            }
        }

        if (!$jsonPath) {
            return;
        }

        $raw = file_get_contents($jsonPath);
        $products = json_decode($raw, true);
        if (!is_array($products)) {
            return;
        }

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

        $brandStmt = $db->prepare('INSERT OR IGNORE INTO brands (name) VALUES (:n)');
        foreach (array_keys($brands) as $bName) {
            $brandStmt->execute([':n' => $bName]);
        }

        $catStmt = $db->prepare('INSERT OR IGNORE INTO categories (name) VALUES (:n)');
        foreach (array_keys($categories) as $cName) {
            $catStmt->execute([':n' => $cName]);
        }

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

        foreach ($products as $p) {
            $img = $p['image'] ?? '';
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
        }
    }
}
