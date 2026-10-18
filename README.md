# Moodle API

API REST desarrollada con Laravel para un proyecto de plataforma educativa inspirado en Moodle.

El objetivo del proyecto es construir un backend que permita gestionar usuarios, cursos, tareas, entregas y comunicación entre profesores y alumnos. El desarrollo se realizará de forma incremental, incorporando nuevas funcionalidades y sus correspondientes pruebas automatizadas.

> **Estado del proyecto:** en desarrollo. Actualmente se ha trabajado en la estructura inicial de la base de datos y en el sistema de autenticación.

## Tecnologías

- **PHP** — lenguaje de programación.
- **Laravel** — framework backend.
- **MySQL** — sistema gestor de base de datos.
- **Laravel Sanctum** — autenticación mediante sesiones y cookies.
- **PHPUnit / Laravel Testing** — pruebas automatizadas.
- **Git y GitHub** — control de versiones y colaboración.
- **GitHub Actions** — integración continua (CI).

## Funcionalidades implementadas

### Base de datos
- Creación de las migraciones iniciales.
- Definición de la estructura inicial de las tablas del proyecto.

### Autenticación
- Inicio de sesión con validación de credenciales.
- Comprobación de las credenciales del usuario.
- Gestión de sesiones y regeneración del identificador tras iniciar sesión.
- Cierre de sesión.
- Protección de rutas mediante middleware de autenticación.
- Actualización de la fecha del último acceso del usuario.
- Pruebas automatizadas para comprobar el inicio de sesión correcto y el rechazo de credenciales inválidas.

### Integración continua
- Configuración inicial de un pipeline mediante GitHub Actions.
- Ejecución automatizada de las comprobaciones configuradas en el workflow.

## Requisitos previos

Para ejecutar el proyecto en local necesitarás:

- PHP compatible con la versión de Laravel utilizada.
- Composer.
- MySQL.
- Git.

Consulta `composer.json` para verificar las versiones exactas de PHP y las dependencias requeridas.

## Instalación y ejecución en local

### 1. Clonar el repositorio

```bash
git clone https://github.com/VictorMMZ/Moodle-API.git
cd Moodle-API
```

### 2. Instalar las dependencias

```bash
composer install
```

### 3. Configurar las variables de entorno

Copia el archivo de ejemplo:

```bash
cp .env.example .env
```

En Windows, también puedes copiar `.env.example` manualmente y renombrar la copia como `.env`.

Genera la clave de la aplicación:

```bash
php artisan key:generate
```

### 4. Configurar la base de datos

Crea una base de datos local en MySQL y configura las siguientes variables en `.env` con tus propios valores:

```env
APP_NAME="Moodle API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=moodle_api
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```

Adapta los valores a la configuración de tu entorno local.

### 5. Ejecutar las migraciones

```bash
php artisan migrate
```

Este comando crea las tablas definidas en las migraciones del proyecto.

Si necesitas reiniciar la base de datos durante el desarrollo, ten en cuenta que `php artisan migrate:fresh` elimina todas las tablas existentes antes de volver a ejecutar las migraciones. No lo utilices en una base de datos con información que quieras conservar.

### 6. Iniciar el servidor

```bash
php artisan serve
```

Por defecto, Laravel estará disponible en:

`http://localhost:8000`

El servidor integrado de Laravel está pensado para desarrollo local, no para producción.

## Pruebas automatizadas

Para ejecutar la suite de pruebas:

```bash
php artisan test
```

Para ejecutar un test concreto:

```bash
php artisan test --filter=NombreDelTest
```

Las pruebas de autenticación comprueban, entre otros casos, el comportamiento del inicio de sesión con credenciales válidas y el rechazo de credenciales incorrectas.

La configuración de pruebas puede requerir variables de entorno y una base de datos específicas. Utiliza un entorno de pruebas separado para evitar modificar accidentalmente los datos de desarrollo.

## Estructura de ramas

El proyecto utiliza un flujo de trabajo basado en ramas:

| Rama | Propósito |
|---|---|
| `main` | Versión estable del proyecto. |
| `develop` | Integración de funcionalidades completadas. |
| `feature/*` | Desarrollo de funcionalidades independientes. |

### Flujo de trabajo

1. Crear una rama `feature/*` desde `develop`.
2. Implementar la funcionalidad y sus pruebas.
3. Ejecutar los tests localmente.
4. Abrir un Pull Request hacia `develop`.
5. Comprobar el resultado del pipeline de integración continua.
6. Integrar los cambios cuando las comprobaciones sean satisfactorias.
7. Promover `develop` a `main` cuando exista una versión estable.

## Roadmap

Las siguientes funcionalidades están previstas, pero todavía no se consideran implementadas:

- [ ] Gestión completa de usuarios y roles.
- [ ] Gestión de cursos.
- [ ] Matriculación de alumnos.
- [ ] Creación y gestión de tareas.
- [ ] Entrega de ejercicios por parte de los alumnos.
- [ ] Corrección y retroalimentación del profesor.
- [ ] Mensajería entre usuarios.
- [ ] Sistema de notificaciones.
- [ ] Ampliación de las pruebas automatizadas.
- [ ] Documentación de los endpoints de la API.
- [ ] Integración con un frontend.

## Objetivo del proyecto

Desarrollar una API educativa con una estructura mantenible, autenticación segura, validación de datos, control de acceso y pruebas automatizadas.

El proyecto se construye de manera incremental, priorizando la calidad del código, la separación de responsabilidades y las buenas prácticas de desarrollo.