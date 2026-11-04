# 🌸 Akami K-Beauty — REST API Backend

Backend RESTful ligero en **PHP 8.2** con base de datos **SQLite** (PDO WAL mode) para la gestión del catálogo de cosmética coreana de **Akami K-Beauty** y el Mini CMS administrativo.

---

## 🚀 Características

- **Base de Datos Embebida SQLite**: Cero mantenimiento de servidores pesados de base de datos.
- **Autenticación Bearer Token**: Acceso administrativo protegido mediante hash Bcrypt.
- **Gestión Dinámica de Archivos**: Carga de imágenes en volumen montado (`/uploads/products/`) con validación de extensiones (JPG, PNG, WebP) y nombres normalizados.
- **Seeder Automático**: Inicialización automática con 83 productos y 19 marcas reconciliadas en el primer arranque.
- **Dockerizado**: Basado en `php:8.2-cli-alpine` con un footprint inferior a 35MB.

---

## 🔑 Credenciales Administrativas por Defecto

- **Usuario**: `akami`
- **Contraseña**: `akami2026`

---

## 📡 Endpoints de la API

### Rutas Públicas (Catálogo)
| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/api/health` | Estado del servicio y versión |
| `GET` | `/api/products` | Listado de productos visibles (`is_active = 1`) |
| `GET` | `/api/products/{id}` | Ficha de un producto específico |
| `GET` | `/api/brands` | Listado de marcas con conteo de productos |
| `GET` | `/api/categories` | Listado de categorías con conteo de productos |

### Autenticación
| Método | Endpoint | Descripción |
|---|---|---|
| `POST` | `/api/auth/login` | Inicia sesión (`username`, `password`) y retorna token Bearer |
| `GET` | `/api/auth/me` | Valida el token actual y retorna el usuario activo |
| `POST` | `/api/auth/logout` | Revoca el token actual |

### Rutas Administrativas (Requieren `Authorization: Bearer <token>`)
| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/api/admin/products` | Todos los productos (incluyendo ocultos) |
| `POST` | `/api/admin/products` | Crear nuevo producto (soporta `multipart/form-data`) |
| `PUT / POST` | `/api/admin/products/{id}` | Actualizar producto existente |
| `PATCH / POST` | `/api/admin/products/{id}/status` | Alternar visibilidad (`is_active: 0 / 1`) |
| `DELETE` | `/api/admin/products/{id}` | Eliminar producto |
| `POST` | `/api/admin/upload` | Subida independiente de imagen (`file` o `image`) |
| `POST` | `/api/admin/brands` | Crear marca |
| `PUT` | `/api/admin/brands/{id}` | Actualizar nombre de marca |
| `DELETE` | `/api/admin/brands/{id}` | Eliminar marca |
| `POST` | `/api/admin/categories` | Crear categoría |
| `PUT` | `/api/admin/categories/{id}` | Actualizar nombre de categoría |
| `DELETE` | `/api/admin/categories/{id}` | Eliminar categoría |

---

## 🐳 Despliegue con Docker Compose (Puerto 15001)

### Ejecución autónoma:
```bash
# Iniciar microservicio backend en puerto 15001
docker compose up -d

# Detener microservicio
docker compose down
```

### Configuración personalizada (.env):
Copia `.env.example` a `.env` para personalizar el puerto o las rutas de volumen:
```bash
BACK_PORT=15001
DB_DIR=../database
UPLOADS_DIR=../uploads
```

---

## 📄 Licencia
Privado / Propietario — Akami K-Beauty.
