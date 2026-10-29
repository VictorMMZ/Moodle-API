# Moodle API

API REST desarrollada con Laravel para una plataforma educativa inspirada en Moodle. El proyecto permite gestionar usuarios, aulas virtuales, ejercicios, entregas y la comunicación entre profesores y alumnos.

El objetivo es desarrollar un backend que sirva como base para una plataforma educativa en la que los profesores puedan gestionar sus aulas y ejercicios, mientras que los alumnos puedan acceder a las actividades, entregar sus trabajos y comunicarse con otros usuarios.

> **Estado del proyecto:** en desarrollo avanzado. Las funcionalidades principales de autenticación, gestión de aulas, ejercicios, entregas y mensajería están implementadas. La siguiente funcionalidad pendiente es el sistema de notificaciones.

## Tecnologías

- **PHP** — lenguaje de programación.
- **Laravel 12** — framework backend.
- **MySQL** — sistema gestor de bases de datos.
- **Laravel Sanctum** — autenticación mediante sesiones y cookies.
- **Eloquent ORM** — gestión de modelos, relaciones y consultas a la base de datos.
- **PHPUnit / Laravel Testing** — pruebas automatizadas.
- **Git y GitHub** — control de versiones y gestión del código.
- **GitHub Actions** — integración continua (CI).

## Funcionalidades implementadas

### 1. Autenticación y gestión de usuarios

- Inicio de sesión mediante credenciales.
- Validación de las credenciales del usuario.
- Gestión de sesiones y regeneración del identificador de sesión tras el inicio de sesión.
- Cierre de sesión.
- Protección de rutas mediante autenticación.
- Actualización de la fecha del último acceso.
- Gestión de usuarios con diferentes roles: administrador, profesor y alumno.
- Control de acceso a las funcionalidades según el rol del usuario.

### 2. Gestión de aulas virtuales

Las aulas virtuales representan las asignaturas o cursos de la plataforma.

- Gestión de las aulas y sus relaciones con los profesores.
- Asociación de profesores con las aulas que administran.
- Incorporación de alumnos a las aulas.
- Búsqueda de alumnos por correo electrónico para concederles acceso a un aula.
- Validación del rol del alumno antes de incorporarlo.
- Control del estado de la inscripción para determinar si un alumno tiene acceso.
- Posibilidad de retirar alumnos de un aula.
- Control de permisos para que cada profesor gestione únicamente sus propias aulas, mientras que los administradores disponen de permisos globales.

### 3. Gestión de ejercicios

- Creación y gestión de ejercicios asociados a las aulas virtuales.
- Asociación de cada ejercicio con su aula correspondiente.
- Gestión del título y la puntuación máxima.
- Publicación de ejercicios.
- Control de acceso para impedir que los alumnos accedan a ejercicios que todavía no se han publicado.
- Restricción de acceso a los ejercicios según la inscripción activa del alumno.
- Control de permisos para profesores y administradores.

### 4. Entrega de ejercicios

Sistema que permite a los alumnos entregar sus trabajos y consultar el estado de sus entregas.

- Envío de respuestas mediante texto.
- Adjuntar archivos a las entregas.
- Validación de los archivos permitidos y de su tamaño.
- Almacenamiento de los archivos en el servidor.
- Registro de la fecha y hora de entrega.
- Consulta de entregas por alumno, ejercicio o permisos del usuario.
- Posibilidad de actualizar una entrega que todavía no ha sido calificada.
- Gestión del estado de las entregas.
- Preparación de la estructura para incorporar calificaciones, comentarios de los profesores y fechas de corrección.
- Restricción de acceso para que los alumnos gestionen únicamente sus propias entregas.
- Restricción de acceso de los profesores a las entregas de sus aulas.
- Protección de las entregas ya calificadas frente a modificaciones posteriores.

### 5. Sistema de mensajería

Implementación de la comunicación entre usuarios mediante mensajes privados.

- Envío de mensajes entre usuarios.
- Gestión del remitente y del destinatario.
- Asunto y contenido del mensaje.
- Registro de la fecha de lectura mediante el campo `read_at`.
- Relaciones entre los mensajes y los usuarios.
- Validación de los datos enviados a través de la API.
- Control de acceso para impedir que los usuarios consulten o gestionen mensajes ajenos.

### 6. Pruebas automatizadas

El proyecto incorpora pruebas de funcionalidad para comprobar el comportamiento de la API.

Entre los aspectos cubiertos se encuentran:

- Autenticación y validación de credenciales.
- Comportamiento de los endpoints.
- Validación de datos.
- Control de acceso y permisos.
- Funcionamiento de las distintas funcionalidades implementadas.

Las pruebas se amplían a medida que se incorporan nuevas funcionalidades.

### 7. Integración continua (CI)

Configuración de GitHub Actions para automatizar las comprobaciones del proyecto.

- Ejecución del workflow ante los eventos configurados en el repositorio.
- Instalación de dependencias mediante Composer.
- Ejecución de las comprobaciones y pruebas configuradas.
- Verificación automatizada de los cambios antes de integrarlos en las ramas principales de desarrollo.

## Funcionalidades pendientes

### Sistema de notificaciones

Es la siguiente funcionalidad prevista para completar las funcionalidades principales del backend.

El objetivo es informar a los usuarios de los acontecimientos relevantes de la plataforma.

Entre las funcionalidades previstas se encuentran:

- Notificar a los alumnos cuando se publique un nuevo ejercicio en sus aulas.
- Notificar las fechas límite de entrega de las actividades.
- Informar a los alumnos cuando sus entregas sean calificadas.
- Consultar las notificaciones recibidas.
- Marcar las notificaciones como leídas.
- Respetar los permisos y las relaciones entre usuarios, aulas y ejercicios.

Una vez implementado este sistema, se podrán revisar las funcionalidades integradas, ampliar las pruebas y preparar el proyecto para el desarrollo de la aplicación frontend.

## Requisitos previos

Para ejecutar el proyecto en local necesitarás:

- PHP compatible con Laravel 12.
- Composer.
- MySQL.
- Git.

Consulta `composer.json` para comprobar las versiones exactas de PHP y las dependencias necesarias.

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

Copia el archivo de ejemplo `.env.example` y crea tu archivo `.env`.

En Windows puedes realizar la copia manualmente o utilizar:

```bash
copy .env.example .env
```

Genera la clave de la aplicación:

```bash
php artisan key:generate
```

### 4. Configurar la base de datos

Crea una base de datos MySQL y configura las variables correspondientes en `.env`:

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

Adapta los valores a tu entorno local.

### 5. Ejecutar las migraciones

```bash
php artisan migrate
```

Este comando crea las tablas definidas en las migraciones del proyecto.

### 6. Iniciar el servidor

```bash
php artisan serve
```

Por defecto, la aplicación estará disponible en:

```text
http://localhost:8000
```

## Ejecutar las pruebas

Para ejecutar la suite de pruebas automatizadas:

```bash
php artisan test
```

Las pruebas permiten comprobar el comportamiento de los endpoints y verificar que los cambios no introduzcan regresiones en las funcionalidades existentes.

## Flujo de trabajo con Git

El proyecto sigue un flujo de trabajo basado en ramas para desarrollar e integrar las funcionalidades de forma progresiva.

- `main`: versión estable del proyecto.
- `develop`: rama de integración de las funcionalidades.
- `feature/*`: ramas dedicadas al desarrollo de nuevas funcionalidades.

El flujo habitual es:

1. Crear una rama `feature/*` a partir de `develop` actualizado.
2. Implementar la funcionalidad y sus pruebas.
3. Crear los commits correspondientes.
4. Subir la rama al repositorio remoto.
5. Abrir una Pull Request hacia `develop`.
6. Revisar los cambios y comprobar que las pruebas y el workflow de CI se ejecutan correctamente.
7. Integrar la funcionalidad en `develop`.
8. Incorporar los cambios a `main` cuando la versión integrada sea estable.

Ejemplo:

```bash
git switch develop
git pull origin develop
git switch -c feature/notifications
```

Después de desarrollar la funcionalidad:

```bash
git add .
git commit -m "feat: implement notifications"
git push -u origin feature/notifications
```

La integración se realiza mediante una Pull Request hacia `develop`.

## Objetivos del proyecto

Moodle-API tiene como objetivo consolidar conocimientos de desarrollo backend mediante la construcción de una API REST con una estructura organizada y funcionalidades propias de una plataforma educativa.

El proyecto permite trabajar con:

- Diseño y gestión de bases de datos relacionales.
- Modelos y relaciones mediante Eloquent ORM.
- Desarrollo de endpoints REST.
- Autenticación basada en sesiones.
- Autorización y control de acceso según roles.
- Validación de datos y archivos.
- Gestión de entregas y mensajería.
- Pruebas automatizadas.
- Control de versiones mediante Git.
- Integración continua con GitHub Actions.

El desarrollo se realiza de forma incremental, incorporando funcionalidades mediante ramas independientes, pruebas y Pull Requests.