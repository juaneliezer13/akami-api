<?php

namespace Akami\Controllers;

use PDO;

class BrandController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function list(): array
    {
        $stmt = $this->db->query('
            SELECT b.id, b.name, b.created_at, COUNT(p.id) as product_count 
            FROM brands b 
            LEFT JOIN products p ON p.brand = b.name 
            GROUP BY b.id, b.name 
            ORDER BY b.name ASC
        ');
        $brands = $stmt->fetchAll();

        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'product_count' => (int) $row['product_count'],
                'created_at' => $row['created_at'],
            ];
        }, $brands);
    }

    public function create(string $name): array
    {
        $name = trim($name);
        if (empty($name)) {
            throw new \InvalidArgumentException('El nombre de la marca no puede estar vacío.');
        }

        $stmt = $this->db->prepare('INSERT INTO brands (name) VALUES (:name)');
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
            throw new \InvalidArgumentException('El nombre de la marca no puede estar vacío.');
        }

        // Obtener nombre actual para actualizar productos vinculados
        $stmt = $this->db->prepare('SELECT name FROM brands WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $old = $stmt->fetchColumn();

        if (!$old) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $upd = $this->db->prepare('UPDATE brands SET name = :n WHERE id = :id');
            $upd->execute([':n' => $newName, ':id' => $id]);

            $updProd = $this->db->prepare('UPDATE products SET brand = :n WHERE brand = :old');
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
        $stmt = $this->db->prepare('DELETE FROM brands WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
