<?php

namespace Akami\Controllers;

use PDO;

class ProductController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Catálogo público: Solo productos activos (is_active = 1)
     */
    public function listPublic(array $params = []): array
    {
        $query = 'SELECT * FROM products WHERE is_active = 1';
        $bindings = [];

        if (!empty($params['brand'])) {
            $query .= ' AND brand = :brand';
            $bindings[':brand'] = $params['brand'];
        }

        if (!empty($params['category']) && $params['category'] !== 'Todos') {
            $query .= ' AND category = :category';
            $bindings[':category'] = $params['category'];
        }

        if (!empty($params['search'])) {
            $s = '%' . trim($params['search']) . '%';
            $query .= ' AND (title LIKE :s1 OR brand LIKE :s2 OR category LIKE :s3 OR benefits LIKE :s4 OR description LIKE :s5)';
            $bindings[':s1'] = $s;
            $bindings[':s2'] = $s;
            $bindings[':s3'] = $s;
            $bindings[':s4'] = $s;
            $bindings[':s5'] = $s;
        }

        $query .= ' ORDER BY id ASC';

        $stmt = $this->db->prepare($query);
        $stmt->execute($bindings);
        $items = $stmt->fetchAll();

        return array_map([$this, 'formatProduct'], $items);
    }

    /**
     * Listado administrativo para el Mini CMS: incluye activos e inactivos
     */
    public function listAdmin(array $params = []): array
    {
        $query = 'SELECT * FROM products WHERE 1=1';
        $bindings = [];

        if (isset($params['status']) && $params['status'] !== 'all') {
            if ($params['status'] === 'active' || $params['status'] === '1') {
                $query .= ' AND is_active = 1';
            } elseif ($params['status'] === 'hidden' || $params['status'] === '0') {
                $query .= ' AND is_active = 0';
            }
        }

        if (!empty($params['brand'])) {
            $query .= ' AND brand = :brand';
            $bindings[':brand'] = $params['brand'];
        }

        if (!empty($params['category'])) {
            $query .= ' AND category = :category';
            $bindings[':category'] = $params['category'];
        }

        if (!empty($params['search'])) {
            $s = '%' . trim($params['search']) . '%';
            $query .= ' AND (title LIKE :s1 OR brand LIKE :s2 OR category LIKE :s3)';
            $bindings[':s1'] = $s;
            $bindings[':s2'] = $s;
            $bindings[':s3'] = $s;
        }

        $query .= ' ORDER BY id DESC';

        $stmt = $this->db->prepare($query);
        $stmt->execute($bindings);
        $items = $stmt->fetchAll();

        return array_map([$this, 'formatProduct'], $items);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->formatProduct($row) : null;
    }

    public function create(array $data): array
    {
        $title = trim($data['title'] ?? $data['name'] ?? '');
        $brand = trim($data['brand'] ?? '');
        $category = trim($data['category'] ?? '');
        $price = (float) ($data['price'] ?? 0);
        $image = trim($data['image'] ?? '');
        $description = trim($data['description'] ?? $data['benefits'] ?? '');
        $isActive = isset($data['is_active']) ? (int) $data['is_active'] : 1;

        if (empty($title)) {
            throw new \InvalidArgumentException('El nombre del producto es obligatorio.');
        }
        if (empty($brand)) {
            throw new \InvalidArgumentException('La marca es obligatoria.');
        }
        if (empty($category)) {
            throw new \InvalidArgumentException('La categoría es obligatoria.');
        }

        // Asegurar que la marca y categoría existan en sus respectivas tablas
        $this->ensureBrandAndCategory($brand, $category);

        $skinTypes = isset($data['skinTypes']) && is_array($data['skinTypes']) ? json_encode($data['skinTypes']) : (string) ($data['skin_types'] ?? '[]');
        $attributes = isset($data['attributes']) && is_array($data['attributes']) ? json_encode($data['attributes']) : (string) ($data['attributes'] ?? '[]');
        $gallery = !empty($image) ? json_encode([$image]) : '[]';

        $stmt = $this->db->prepare('
            INSERT INTO products (
                title, brand, category, price, image, gallery, description,
                skin_types, compatibility, attributes, ingredients, benefits,
                use_instructions, is_active, created_at, updated_at
            ) VALUES (
                :title, :brand, :category, :price, :image, :gallery, :description,
                :skin_types, :compatibility, :attributes, :ingredients, :benefits,
                :use_instructions, :is_active, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ');

        $stmt->execute([
            ':title' => $title,
            ':brand' => $brand,
            ':category' => $category,
            ':price' => $price,
            ':image' => $image,
            ':gallery' => $gallery,
            ':description' => $description,
            ':skin_types' => $skinTypes,
            ':compatibility' => trim($data['compatibility'] ?? ''),
            ':attributes' => $attributes,
            ':ingredients' => trim($data['ingredients'] ?? ''),
            ':benefits' => $description,
            ':use_instructions' => trim($data['use'] ?? $data['use_instructions'] ?? ''),
            ':is_active' => $isActive,
        ]);

        $newId = (int) $this->db->lastInsertId();
        return $this->getById($newId);
    }

    public function update(int $id, array $data): ?array
    {
        $existing = $this->getById($id);
        if (!$existing) {
            return null;
        }

        $title = isset($data['title']) || isset($data['name']) ? trim($data['title'] ?? $data['name']) : $existing['title'];
        $brand = isset($data['brand']) ? trim($data['brand']) : $existing['brand'];
        $category = isset($data['category']) ? trim($data['category']) : $existing['category'];
        $price = isset($data['price']) ? (float) $data['price'] : $existing['price'];
        $image = array_key_exists('image', $data) ? trim($data['image']) : $existing['image'];
        $description = isset($data['description']) ? trim($data['description']) : (isset($data['benefits']) ? trim($data['benefits']) : $existing['description']);
        $isActive = array_key_exists('is_active', $data) ? (int) $data['is_active'] : $existing['is_active'];

        $this->ensureBrandAndCategory($brand, $category);

        $gallery = !empty($image) ? json_encode([$image]) : '[]';

        $stmt = $this->db->prepare('
            UPDATE products SET
                title = :title,
                brand = :brand,
                category = :category,
                price = :price,
                image = :image,
                gallery = :gallery,
                description = :description,
                benefits = :benefits,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');

        $stmt->execute([
            ':id' => $id,
            ':title' => $title,
            ':brand' => $brand,
            ':category' => $category,
            ':price' => $price,
            ':image' => $image,
            ':gallery' => $gallery,
            ':description' => $description,
            ':benefits' => $description,
            ':is_active' => $isActive,
        ]);

        return $this->getById($id);
    }

    public function setStatus(int $id, int $status): bool
    {
        $stmt = $this->db->prepare('UPDATE products SET is_active = :s, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        return $stmt->execute([':s' => $status ? 1 : 0, ':id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM products WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    private function ensureBrandAndCategory(string $brand, string $category): void
    {
        if (!empty($brand)) {
            $stmt = $this->db->prepare('INSERT OR IGNORE INTO brands (name) VALUES (:name)');
            $stmt->execute([':name' => $brand]);
        }

        if (!empty($category)) {
            $stmt = $this->db->prepare('INSERT OR IGNORE INTO categories (name) VALUES (:name)');
            $stmt->execute([':name' => $category]);
        }
    }

    private function formatProduct(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'brand' => $row['brand'],
            'title' => $row['title'],
            'category' => $row['category'],
            'price' => (float) $row['price'],
            'image' => $row['image'] ?: '',
            'gallery' => json_decode($row['gallery'] ?? '[]', true) ?: ($row['image'] ? [$row['image']] : []),
            'description' => $row['description'] ?: ($row['benefits'] ?: ''),
            'skinTypes' => json_decode($row['skin_types'] ?? '[]', true) ?: [],
            'compatibility' => $row['compatibility'] ?: '',
            'attributes' => json_decode($row['attributes'] ?? '[]', true) ?: [],
            'ingredients' => $row['ingredients'] ?: '',
            'benefits' => $row['benefits'] ?: ($row['description'] ?: ''),
            'use' => $row['use_instructions'] ?: '',
            'is_active' => (int) ($row['is_active'] ?? 1),
            'created_at' => $row['created_at'] ?? '',
            'updated_at' => $row['updated_at'] ?? '',
        ];
    }
}
