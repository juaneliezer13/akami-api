## 🌸 Akami K-Beauty API — Release v1.1.0

### 🚀 Desacoplamiento de Infraestructura y Puerto 15001

Esta versión dota al microservicio backend de una infraestructura completamente autónoma mediante Docker Compose, permitiendo levantarlo y operarlo por separado en el puerto 15001.

---

### ✨ Cambios Principales

#### 1. 🌐 Reasignación de Puerto
* **Puerto Externo:** Expuesto en el puerto host **`15001`** (mapeado al contenedor `8000`), configurable mediante variable de entorno `BACK_PORT`.
* **Healthcheck Directo:** Disponible en `http://localhost:15001/api/health`.

#### 2. 🐳 Orquestación Docker Autónoma
* **`docker-compose.yml` autónomo:** Permite levantar exclusivamente el backend (`cd akami-api && docker compose up -d`).
* **`docker-compose.server.yml`:** Optimizado para despliegue en servidor AWS EC2 con la imagen oficial de Docker Hub (`juaneliezer13/akami-api:latest`).
* **`.env.example`:** Plantilla incluida para personalización rápida de variables de entorno (`BACK_PORT`, `DB_DIR`, `UPLOADS_DIR`).

#### 3. 🛡️ Integridad de Código y Datos
* Cero cambios en la lógica de negocio, controladores o esquema SQLite.
* Persistencia garantizada con 83 productos y 19 marcas reconciliadas.
