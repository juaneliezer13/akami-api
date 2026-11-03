<?php

namespace Akami;

use PDO;

class Auth
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ensureAdminUser();
    }

    private function ensureAdminUser(): void
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => 'akami']);
        if (!$stmt->fetch()) {
            $hash = password_hash('akami2026', PASSWORD_BCRYPT);
            $insert = $this->db->prepare('INSERT INTO users (username, password_hash) VALUES (:u, :p)');
            $insert->execute([':u' => 'akami', ':p' => $hash]);
        }
    }

    public function login(string $username, string $password): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Generate token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + (86400 * 7)); // 7 days

        $insert = $this->db->prepare('INSERT INTO tokens (token, user_id, expires_at) VALUES (:t, :u, :e)');
        $insert->execute([
            ':t' => $token,
            ':u' => $user['id'],
            ':e' => $expiresAt,
        ]);

        return [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
            ],
            'expires_at' => $expiresAt,
        ];
    }

    public function logout(string $token): bool
    {
        $stmt = $this->db->prepare('DELETE FROM tokens WHERE token = :t');
        return $stmt->execute([':t' => $token]);
    }

    public function validateToken(?string $token): ?array
    {
        if (!$token) {
            return null;
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare('
            SELECT u.id, u.username, t.expires_at 
            FROM tokens t 
            JOIN users u ON u.id = t.user_id 
            WHERE t.token = :t AND t.expires_at > :now 
            LIMIT 1
        ');
        $stmt->execute([':t' => $token, ':now' => $now]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public static function getBearerToken(): ?string
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        }

        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
