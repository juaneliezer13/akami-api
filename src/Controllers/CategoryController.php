<?php

namespace Akami\Controllers;

use PDO;

class CategoryController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function list(): array
    {
        $stmt = $this->db->query('
            SELECT c.id, c.name, c.created_at, COUNT(p.id) as product_count 
            FROM categories c 
            LEFT JOIN products p ON p.category = c.name 
            GROUP BY c.id, c.name 
            ORDER BY c.name ASC
        ');
        $cats = $stmt->fetchAll();

        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'product_count' => (int) $row['product_count'],
                'created_at' => $row['created_at'],
            ];
        }, $cats);
    }

    public function create(string $name): array
    {
        $name = trim($name);
        if (empty($name)) {
            throw new \InvalidArgumentException('El nombre de la categoría no puede estar vacío.');
        }

        $stmt = $this->db->prepare('INSERT INTO categories (name) VALUES (:name)');
        $stmt->execute([':name' => $name]);

        $id = (int) $this->db->lastInsertId();
        return [
            'id' => $id,
            'name' => $name,
            'product_count' => 0,
        ];
    }

    public function update(int $id, string $newName): bool
    {
        $newName = trim($newName);
        if (empty($newName)) {
            throw new \InvalidArgumentException('El nombre de la categoría no puede estar vacío.');
        }

        $stmt = $this->db->prepare('SELECT name FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $old = $stmt->fetchColumn();

        if (!$old) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $upd = $this->db->prepare('UPDATE categories SET name = :n WHERE id = :id');
            $upd->execute([':n' => $newName, ':id' => $id]);

            $updProd = $this->db->prepare('UPDATE products SET category = :n WHERE category = :old');
            $updProd->execute([':n' => $newName, ':old' => $old]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM categories WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
