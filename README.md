# Fichajes

Sistema de control horario para equipos: registro de jornadas, gestión de horarios, ausencias y correcciones con arquitectura multi-tenant y auditoría completa.

## Características

- Registro de entrada, pausa, reanudación y salida (clock-in/out)
- Gestión de horarios de trabajo y asignaciones por empleado
- Solicitudes de ausencia con flujo de aprobación supervisor
- Correcciones de fichajes con trazabilidad
- Auditoría completa de todas las acciones del sistema
- Autenticación dual: panel web (email + contraseña) y kiosco (código empleado + PIN)
- Control de acceso por roles (RBAC): admin, supervisor, trabajador
- Multi-tenant lógico
- Protección anti-fuerza bruta en kiosco con bloqueo temporal
- API REST v1 por módulo

## Stack técnico

| Capa | Tecnología |
|------|-----------|
| Backend | PHP 8.2+ / Symfony 6.4 |
| ORM | Doctrine ORM 2 con migraciones |
| Frontend | Twig + Bootstrap 5 |
| Assets | Webpack Encore |
| Base de datos | SQLite (dev) / PostgreSQL (prod) |
| Tests | PHPUnit 10 |
| Contenedores | Docker Compose v2 |

## Requisitos

- PHP 8.2+ con extensiones: `pdo`, `pdo_sqlite` o `pdo_pgsql`, `xml`, `mbstring`, `intl`
- Composer >= 2
- Node.js 20+ y Yarn
- (Opcional) Symfony CLI
- (Opcional) Docker y Docker Compose

### Instalación de dependencias del sistema (Ubuntu/Debian)

```bash
sudo apt install php php-cli php-sqlite3 php-xml php-mbstring php-intl unzip
sudo apt install composer nodejs npm
npm i -g yarn
```

## Instalación

### Sin Docker

```bash
# 1. Clonar el repositorio
git clone <url-del-repo> fichajes
cd fichajes

# 2. Configurar entorno
cp .env.example .env
# Edita .env con tus valores (APP_SECRET, DATABASE_URL, ADMIN_EMAIL, ADMIN_PASSWORD)

# 3. Instalar dependencias
composer install
yarn install

# 4. Inicializar la base de datos
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate -n

# 5. Crear el primer usuario administrador
php bin/console app:bootstrap-admin

# 6. (Opcional) Cargar datos de demostración
php bin/console app:cargar-demo

# 7. Compilar assets e iniciar el servidor
php bin/console cache:clear
symfony server:start
# Sin Symfony CLI: php -S 127.0.0.1:8000 -t public
```

### Con Docker

```bash
cp .env.example .env
# Edita .env con tus valores

docker compose up -d
docker compose exec app php bin/console doctrine:migrations:migrate -n
docker compose exec app php bin/console app:bootstrap-admin
```

## Configuración

Copia `.env.example` como `.env` y ajusta los valores. Variables principales:

```dotenv
APP_ENV=prod                     # Entorno: dev | test | prod
APP_SECRET=<clave-aleatoria>     # php -r "echo bin2hex(random_bytes(16));"
DATABASE_URL="postgresql://usuario:contraseña@localhost:5432/fichajes"

ADMIN_EMAIL=admin@example.com    # Email del primer administrador
ADMIN_PASSWORD=<contraseña>      # Contraseña del primer administrador

HERRAMIENTA_FICHAJE_LIMITE_INTENTOS=5        # Intentos antes de bloqueo en kiosco
HERRAMIENTA_FICHAJE_VENTANA_SEGUNDOS=900     # Ventana de conteo de intentos (s)
HERRAMIENTA_FICHAJE_BLOQUEO_SEGUNDOS=900     # Duracion del bloqueo (s)
HERRAMIENTA_FICHAJE_TIMEOUT_INACTIVIDAD_SEGUNDOS=120  # Timeout de sesion kiosco (s)
HERRAMIENTA_FICHAJE_TENANT_KIOSKO=T1         # Tenant del kiosco por defecto

TRUSTED_PROXIES=private_ranges   # Activar si hay reverse proxy (nginx, traefik...)
```

Para generar un `APP_SECRET` seguro:
```bash
php -r "echo bin2hex(random_bytes(16));"
```

## Uso

### Panel de administración web

1. Abre `http://localhost:8000/login`
2. Inicia sesión con las credenciales configuradas en `.env`
3. Módulos disponibles desde el panel principal:

| Módulo | Ruta | Descripción |
|--------|------|-------------|
| Trabajadores | `/app/trabajadores` | Catálogo de empleados |
| Horarios | `/app/horarios` | Creación y asignación de horarios |
| Fichajes | `/app/fichajes` | Registros de jornadas |
| Ausencias | `/app/ausencias` | Solicitudes y aprobaciones |
| Correcciones | `/app/correcciones` | Revisión de correcciones |
| Auditoría | `/app/auditoria` | Trazabilidad de acciones |

### Herramienta de fichaje (kiosco)

Diseñada para uso en tablet o pantalla de puesto fijo.

- **Vista estándar**: `GET /herramienta-fichaje`
- **Vista kiosco** (pantalla grande): `GET /herramienta-fichaje/kiosko`

Flujo de uso:
1. El empleado introduce su código de trabajador y clave de fichaje
2. Acciones disponibles según el estado de la jornada:
   - Sin jornada activa: **Iniciar jornada**
   - En jornada: **Pausar** o **Finalizar jornada**
   - En pausa: **Reanudar** o **Finalizar jornada**

Seguridad del kiosco:
- Bloqueo temporal tras superar el límite de intentos fallidos (configurable)
- Los intentos y bloqueos quedan registrados en auditoría
- Timeout de sesión por inactividad (configurable)
- Protección CSRF en todos los formularios

Credenciales de demo (tras ejecutar `app:cargar-demo`):
- Empleado `EMP-001` con PIN `1111`
- Empleado `EMP-002` con PIN `2222`

### API REST

Base: `/api/v1/`

| Recurso | Ruta |
|---------|------|
| Trabajadores | `/api/v1/trabajadores` |
| Horarios | `/api/v1/horarios` |
| Fichajes | `/api/v1/fichajes` |
| Ausencias | `/api/v1/ausencias` |
| Correcciones | `/api/v1/correcciones` |
| Health check | `GET /healthz` |

Todos los endpoints de la API requieren autenticación.

## Roles y permisos

| Rol | Descripción |
|-----|-------------|
| `ROLE_ADMIN` | Acceso total: usuarios, configuración y todos los módulos |
| `ROLE_SUPERVISOR` | Aprueba ausencias y correcciones, consulta auditoría de su equipo |
| `ROLE_TRABAJADOR` | Fichar, ver sus propios registros y solicitar ausencias |

## Desarrollo

### Tests

```bash
# Suite completa
php bin/phpunit

# Solo tests unitarios
php bin/phpunit tests/Unit/

# Tests funcionales web
php bin/phpunit tests/Web/OperativoFrontendTest.php

# Tests de API
php bin/phpunit tests/Api/
```

### Compilación de assets

```bash
yarn dev      # Compilación única (desarrollo)
yarn watch    # Modo watch
yarn build    # Build de producción (minificado)
```

### Migraciones de base de datos

```bash
# Crear migración a partir de cambios en entidades
php bin/console doctrine:migrations:diff

# Aplicar migraciones pendientes
php bin/console doctrine:migrations:migrate -n

# Ver estado de migraciones
php bin/console doctrine:migrations:status
```

## Arquitectura

El proyecto sigue una arquitectura modular inspirada en DDD. Cada módulo de negocio agrupa sus entidades, casos de uso y adaptadores de infraestructura de forma cohesiva.

```
src/
├── Controller/
│   ├── Api/V1/          # Endpoints REST por módulo
│   └── Web/             # Controladores de vistas Twig
├── Modulo/
│   ├── Acceso/          # Autenticación, usuarios y RBAC
│   ├── Fichajes/        # Registro de jornadas
│   ├── Horarios/        # Horarios de trabajo
│   ├── Ausencias/       # Solicitudes de ausencia
│   ├── Correcciones/    # Correcciones de fichajes
│   ├── Auditoria/       # Registro de auditoría
│   ├── Trabajadores/    # Catálogo de empleados
│   └── Plataforma/      # Multi-tenant y servicios transversales
├── Security/            # Voters y autenticación Symfony
└── Command/             # Comandos CLI de administración
```

Estructura interna de cada módulo:

```
Modulo/<Nombre>/
├── Domain/Entity/           # Entidades de dominio
├── Application/Servicio/    # Casos de uso
└── Infrastructure/
    └── Repository/          # Adaptadores de persistencia
```

## Docker

El `compose.yaml` define un servicio PostgreSQL 16. El `compose.override.yaml` expone el puerto 5432 en local para herramientas de base de datos.

```bash
docker compose up -d           # Iniciar servicios
docker compose down            # Detener y eliminar contenedores
docker compose logs -f         # Ver logs en tiempo real
```

Las credenciales de la base de datos se configuran mediante variables de entorno en `.env`. Ver `.env.example` para la lista completa.

## Licencia

MIT
