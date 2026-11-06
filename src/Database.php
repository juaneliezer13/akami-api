<?php

namespace Akami;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        // Cargar config.php opcional (para entornos cPanel / Hosting compartido)
        $configFile = dirname(__DIR__) . '/config.php';
        if (file_exists($configFile)) {
            require_once $configFile;
        }

        $connectionType = defined('DB_CONNECTION') ? DB_CONNECTION : (getenv('DB_CONNECTION') ?: 'sqlite');

        if ($connectionType === 'mysql') {
            $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
            $port = defined('DB_PORT') ? DB_PORT : (getenv('DB_PORT') ?: '3306');
            $dbName = defined('DB_DATABASE') ? DB_DATABASE : (getenv('DB_DATABASE') ?: 'akami_db');
            $user = defined('DB_USERNAME') ? DB_USERNAME : (getenv('DB_USERNAME') ?: 'root');
            $pass = defined('DB_PASSWORD') ? DB_PASSWORD : (getenv('DB_PASSWORD') ?: '');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } else {
            // Default: SQLite
            $defaultPath = dirname(__DIR__, 2) . '/database/akami.sqlite';
            if (!file_exists($defaultPath) && file_exists(dirname(__DIR__) . '/../database/akami.sqlite')) {
                $defaultPath = dirname(__DIR__) . '/../database/akami.sqlite';
            }
            $dbPath = defined('DB_PATH') ? DB_PATH : (getenv('DB_PATH') ?: $defaultPath);
            $dir = dirname($dbPath);

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            self::$instance = new PDO("sqlite:{$dbPath}", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Optimización y modo WAL para SQLite
            self::$instance->exec('PRAGMA journal_mode = WAL;');
            self::$instance->exec('PRAGMA foreign_keys = ON;');

            self::initSchema(self::$instance);
            Seeder::seedIfEmpty(self::$instance);
        }

        return self::$instance;
    }

    private static function initSchema(PDO $db): void
    {
        $schema = <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS brands (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            brand TEXT NOT NULL,
            category TEXT NOT NULL,
            price REAL NOT NULL DEFAULT 0.0,
            image TEXT DEFAULT '',
            gallery TEXT DEFAULT '[]',
            description TEXT DEFAULT '',
            skin_types TEXT DEFAULT '[]',
            compatibility TEXT DEFAULT '',
            attributes TEXT DEFAULT '[]',
            ingredients TEXT DEFAULT '',
            benefits TEXT DEFAULT '',
            use_instructions TEXT DEFAULT '',
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS tokens (
            token TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
SQL;
        $db->exec($schema);
    }
}
