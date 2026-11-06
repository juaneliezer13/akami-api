<?php

declare(strict_types=1);

// Autoload simple
spl_autoload_register(function ($class) {
    $prefix = 'Akami\\';
    $baseDir = dirname(__DIR__) . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use Akami\Database;
use Akami\Auth;
use Akami\Controllers\ProductController;
use Akami\Controllers\BrandController;
use Akami\Controllers\CategoryController;
use Akami\Controllers\UploadController;

// 1. CORS & Headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: {$origin}");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. Helper functions
function jsonResponse($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $status = 400): void
{
    jsonResponse(['success' => false, 'error' => $message], $status);
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return $_POST;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? array_merge($_POST, $decoded) : $_POST;
}

// 3. Routing
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$method = $_SERVER['REQUEST_METHOD'];

// Method Override para servidores y entornos con verbos restringidos (LiteSpeed, mod_security, cPanel)
if ($method === 'POST') {
    $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? ($_POST['_method'] ?? '');
    if (!empty($override)) {
        $method = strtoupper(trim($override));
    }
}

// Normalizar prefijo /api si existe
if (str_starts_with($uri, '/api')) {
    $uri = substr($uri, 4);
}
if (empty($uri)) {
    $uri = '/';
}

try {
    $db = Database::getConnection();
    $auth = new Auth($db);
    $productCtrl = new ProductController($db);
    $brandCtrl = new BrandController($db);
    $categoryCtrl = new CategoryController($db);
    $uploadCtrl = new UploadController();

    // Helper para verificar autenticación
    $requireAuth = function () use ($auth): array {
        $token = Auth::getBearerToken();
        $user = $auth->validateToken($token);
        if (!$user) {
            jsonError('No autorizado. Token inválido o expirado.', 401);
        }
        return $user;
    };

    // -------------------------------------------------------------
    // RUTAS PÚBLICAS
    // -------------------------------------------------------------

    // Health check
    if ($uri === '/' || $uri === '/health') {
        jsonResponse(['status' => 'ok', 'service' => 'Akami K-Beauty REST API', 'version' => '1.0.0']);
    }

    // Login
    if ($uri === '/auth/login' && $method === 'POST') {
        $data = getJsonInput();
        $username = trim($data['username'] ?? $data['user'] ?? '');
        $password = trim($data['password'] ?? '');

        $result = $auth->login($username, $password);
        if (!$result) {
            jsonError('Credenciales incorrectas.', 401);
        }
        jsonResponse(['success' => true, 'data' => $result]);
    }

    // Validar token actual
    if ($uri === '/auth/me' && $method === 'GET') {
        $user = $requireAuth();
        jsonResponse(['success' => true, 'data' => $user]);
    }

    // Logout
    if ($uri === '/auth/logout' && $method === 'POST') {
        $token = Auth::getBearerToken();
        if ($token) {
            $auth->logout($token);
        }
        jsonResponse(['success' => true, 'message' => 'Sesión cerrada exitosamente.']);
    }

    // Catálogo público de productos
    if ($uri === '/products' && $method === 'GET') {
        $products = $productCtrl->listPublic($_GET);
        jsonResponse(['success' => true, 'data' => $products, 'count' => count($products)]);
    }

    // Producto por ID público
    if (preg_match('#^/products/(\d+)$#', $uri, $matches) && $method === 'GET') {
        $prod = $productCtrl->getById((int) $matches[1]);
        if (!$prod || !$prod['is_active']) {
            jsonError('Producto no encontrado.', 404);
        }
        jsonResponse(['success' => true, 'data' => $prod]);
    }

    // Marcas públicas
    if ($uri === '/brands' && $method === 'GET') {
        $brands = $brandCtrl->list();
        jsonResponse(['success' => true, 'data' => $brands]);
    }

    // Categorías públicas
    if ($uri === '/categories' && $method === 'GET') {
        $cats = $categoryCtrl->list();
        jsonResponse(['success' => true, 'data' => $cats]);
    }

    // -------------------------------------------------------------
    // RUTAS CMS ADMIN (PROTEGIDAS)
    // -------------------------------------------------------------

    // Listado admin de productos (activos y ocultos)
    if ($uri === '/admin/products' && $method === 'GET') {
        $requireAuth();
        $products = $productCtrl->listAdmin($_GET);
        jsonResponse(['success' => true, 'data' => $products, 'count' => count($products)]);
    }

    // Crear producto (JSON o Multipart)
    if ($uri === '/admin/products' && $method === 'POST') {
        $requireAuth();
        $data = getJsonInput();

        // Si se envió un archivo de imagen en la misma petición
        if (!empty($_FILES['image_file']) && isset($_FILES['image_file']['error'])) {
            if ($_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $uploadRes = $uploadCtrl->handleUpload($_FILES['image_file']);
                $data['image'] = $uploadRes['url'];
            } elseif ($_FILES['image_file']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['image_file']['error'] === UPLOAD_ERR_FORM_SIZE) {
                jsonError('La imagen supera el tamaño máximo permitido (25MB).', 400);
            } elseif ($_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                jsonError('Error al procesar la imagen (código: ' . $_FILES['image_file']['error'] . ').', 400);
            }
        }

        $created = $productCtrl->create($data);
        jsonResponse(['success' => true, 'data' => $created, 'message' => 'Producto creado exitosamente.'], 201);
    }

    // Modificar producto
    if (preg_match('#^/admin/products/(\d+)$#', $uri, $matches) && ($method === 'PUT' || $method === 'POST')) {
        $requireAuth();
        $id = (int) $matches[1];
        $data = getJsonInput();

        if (!empty($_FILES['image_file']) && isset($_FILES['image_file']['error'])) {
            if ($_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $uploadRes = $uploadCtrl->handleUpload($_FILES['image_file']);
                $data['image'] = $uploadRes['url'];
            } elseif ($_FILES['image_file']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['image_file']['error'] === UPLOAD_ERR_FORM_SIZE) {
                jsonError('La imagen supera el tamaño máximo permitido (25MB).', 400);
            } elseif ($_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                jsonError('Error al procesar la imagen (código: ' . $_FILES['image_file']['error'] . ').', 400);
            }
        }

        $updated = $productCtrl->update($id, $data);
        if (!$updated) {
            jsonError('Producto no encontrado.', 404);
        }
        jsonResponse(['success' => true, 'data' => $updated, 'message' => 'Producto actualizado exitosamente.']);
    }

    // Cambiar estado visible/oculto
    if (preg_match('#^/admin/products/(\d+)/status$#', $uri, $matches) && ($method === 'PATCH' || $method === 'POST')) {
        $requireAuth();
        $id = (int) $matches[1];
        $data = getJsonInput();
        $status = isset($data['is_active']) ? (int) $data['is_active'] : (isset($data['status']) ? (int) $data['status'] : 1);

        $ok = $productCtrl->setStatus($id, $status);
        if (!$ok) {
            jsonError('No se pudo actualizar el estado del producto.', 400);
        }
        jsonResponse(['success' => true, 'is_active' => $status, 'message' => $status ? 'Producto visible en catálogo.' : 'Producto ocultado.']);
    }

    // Eliminar producto (Soporta DELETE, POST /delete y Method-Override)
    if ((preg_match('#^/admin/products/(\d+)$#', $uri, $matches) && ($method === 'DELETE' || $method === 'POST'))
        || (preg_match('#^/admin/products/(\d+)/delete$#', $uri, $matches) && ($method === 'POST' || $method === 'DELETE'))) {
        $requireAuth();
        $id = (int) $matches[1];
        $ok = $productCtrl->delete($id);
        if (!$ok) {
            jsonError('No se pudo eliminar el producto.', 400);
        }
        jsonResponse(['success' => true, 'message' => 'Producto eliminado exitosamente.']);
    }

    // Subir imagen suelta (Upload standalone)
    if ($uri === '/admin/upload' && $method === 'POST') {
        $requireAuth();
        $fileKey = isset($_FILES['file']) ? 'file' : (isset($_FILES['image']) ? 'image' : null);
        if (!$fileKey || empty($_FILES[$fileKey])) {
            jsonError('No se encontró ningún archivo para subir en el campo "file" o "image".', 400);
        }

        $result = $uploadCtrl->handleUpload($_FILES[$fileKey]);
        jsonResponse(['success' => true, 'data' => $result]);
    }

    // Re-seed / Inicializar catálogo oficial desde el CMS
    if ($uri === '/admin/seed' && ($method === 'POST' || $method === 'GET')) {
        $requireAuth();
        $ok = \Akami\Seeder::runSeed($db);
        $count = (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn();
        $bCount = (int) $db->query('SELECT COUNT(*) FROM brands')->fetchColumn();
        $cCount = (int) $db->query('SELECT COUNT(*) FROM categories')->fetchColumn();
        jsonResponse([
            'success' => $ok,
            'message' => "Seeding completado: {$count} productos, {$bCount} marcas, {$cCount} categorías.",
            'products_count' => $count,
            'brands_count' => $bCount,
            'categories_count' => $cCount,
        ]);
    }

    // CRUD Marcas Admin
    if ($uri === '/admin/brands' && $method === 'POST') {
        $requireAuth();
        $data = getJsonInput();
        $brand = $brandCtrl->create($data['name'] ?? '');
        jsonResponse(['success' => true, 'data' => $brand], 201);
    }

    if (preg_match('#^/admin/brands/(\d+)$#', $uri, $matches) && ($method === 'PUT' || $method === 'POST')) {
        $requireAuth();
        $data = getJsonInput();
        $ok = $brandCtrl->update((int) $matches[1], $data['name'] ?? '');
        if (!$ok) {
            jsonError('Marca no encontrada.', 404);
        }
        jsonResponse(['success' => true, 'message' => 'Marca actualizada exitosamente.']);
    }

    if ((preg_match('#^/admin/brands/(\d+)$#', $uri, $matches) && ($method === 'DELETE' || $method === 'POST'))
        || (preg_match('#^/admin/brands/(\d+)/delete$#', $uri, $matches) && ($method === 'POST' || $method === 'DELETE'))) {
        $requireAuth();
        $ok = $brandCtrl->delete((int) $matches[1]);
        if (!$ok) {
            jsonError('No se pudo eliminar la marca.', 400);
        }
        jsonResponse(['success' => true, 'message' => 'Marca eliminada exitosamente.']);
    }

    // CRUD Categorías Admin
    if ($uri === '/admin/categories' && $method === 'POST') {
        $requireAuth();
        $data = getJsonInput();
        $cat = $categoryCtrl->create($data['name'] ?? '');
        jsonResponse(['success' => true, 'data' => $cat], 201);
    }

    if (preg_match('#^/admin/categories/(\d+)$#', $uri, $matches) && ($method === 'PUT' || $method === 'POST')) {
        $requireAuth();
        $data = getJsonInput();
        $ok = $categoryCtrl->update((int) $matches[1], $data['name'] ?? '');
        if (!$ok) {
            jsonError('Categoría no encontrada.', 404);
        }
        jsonResponse(['success' => true, 'message' => 'Categoría actualizada exitosamente.']);
    }

    if ((preg_match('#^/admin/categories/(\d+)$#', $uri, $matches) && ($method === 'DELETE' || $method === 'POST'))
        || (preg_match('#^/admin/categories/(\d+)/delete$#', $uri, $matches) && ($method === 'POST' || $method === 'DELETE'))) {
        $requireAuth();
        $ok = $categoryCtrl->delete((int) $matches[1]);
        if (!$ok) {
            jsonError('No se pudo eliminar la categoría.', 400);
        }
        jsonResponse(['success' => true, 'message' => 'Categoría eliminada exitosamente.']);
    }

    // 404
    jsonError('Ruta no encontrada.', 404);

} catch (\InvalidArgumentException $e) {
    jsonError($e->getMessage(), 422);
} catch (\Throwable $e) {
    jsonError('Error interno del servidor: ' . $e->getMessage(), 500);
}
