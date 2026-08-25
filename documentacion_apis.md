# Documentación Detallada de las APIs (uyn-backend)
## Sistema de Gestión de Almacenes - U.N.

Este documento contiene la especificación detallada de todas las APIs expuestas por el backend del sistema de gestión de almacenes (`uyn-backend`), desarrollado sobre el framework **Laravel** y expuesto a través de **Laravel Sanctum** para autenticación.

---

## Índice
1. [Especificación General y Autenticación](#1-especificación-general-y-autenticación)
2. [Módulo de Seguridad e Historial](#2-módulo-de-seguridad-e-historial)
   - [Autenticación (Auth)](#autenticación-auth)
   - [Logs de Operación (Operation Logs)](#logs-de-operación-operation-logs)
   - [Roles y Permisos (Roles & Permissions)](#roles-y-permisos-roles--permissions)
   - [Gestión de Usuarios (Users)](#gestión-de-usuarios-users)
3. [Módulo de Catálogos](#3-módulo-de-catálogos)
   - [Áreas (Areas)](#áreas-areas)
   - [Trabajadores / Empleados (Employees)](#trabajadores--empleados-employees)
   - [Modelos de Prenda (Garment Models)](#modelos-de-prenda-garment-models)
   - [Tallas (Sizes)](#tallas-sizes)
4. [Módulo de Órdenes y Cortes de Producción](#4-módulo-de-órdenes-y-cortes-de-producción)
   - [Órdenes de Producción (Production Orders)](#órdenes-de-producción-production-orders)
   - [Cortes de Prenda (Garment Cuts)](#cortes-de-prenda-garment-cuts)
   - [Clasificación de Cortes (Garment Cuts Classification)](#clasificación-de-cortes-garment-cuts-classification)
5. [Módulo de Flujo de Taller (Movimientos y Avances)](#5-módulo-de-flujo-de-taller-movimientos-y-avances)
   - [Procesos y Tipos de Pieza (Processes & Piece Types)](#procesos-y-tipos-de-pieza-processes--piece-types)
   - [Movimientos de Producción (Production Movements)](#movimientos-de-producción-production-movements)
   - [Avances de Operaciones / Destajos (Production Operation Logs)](#avances-de-operaciones--destajos-production-operation-logs)
6. [Módulo de Control de Calidad e Incidencias](#6-módulo-de-control-de-calidad-e-incidencias)
   - [Incidencias de Producción (Production Incidents)](#incidencias-de-producción-production-incidents)
7. [Módulo de Tarifas y Compensaciones (Nómina)](#7-módulo-de-tarifas-y-compensaciones-nómina)
   - [Compensaciones Fijas (Employee Compensations)](#compensaciones-fijas-employee-compensations)
   - [Tarifas de Destajo por Trabajador (Piecework Rates)](#tarifas-de-destajo-por-trabajador-piecework-rates)
   - [Configuración de Pagos de Bordado (Embroidery Payment Settings)](#configuración-de-pagos-de-bordado-embroidery-payment-settings)
8. [Módulo de Gestión de Nóminas](#8-módulo-de-gestión-de-nóminas)
   - [Periodos de Nómina (Payroll Periods)](#periodos-de-nómina-payroll-periods)
9. [Módulo de Reportes](#9-módulo-de-reportes)
10. [Módulo de Exportaciones (CSV)](#10-módulo-de-exportaciones-csv)

---

## 1. Especificación General y Autenticación

- **Ruta Base global**: `/api/v1`
- **Formato de Comunicación**: Las solicitudes que envían datos deben incluir el encabezado `Content-Type: application/json` (a menos que se envíen archivos como imágenes a través de `multipart/form-data`). Todas las respuestas se entregan en formato JSON con la estructura estándar de Laravel Resources (`data: { ... }`).
- **Esquema de Seguridad**:
  - Las rutas protegidas requieren el encabezado `Authorization: Bearer <access_token>`.
  - El middleware `active.user` valida que el usuario autenticado tenga el campo `status` igual a `active`. Si la cuenta está inactiva, se devuelve una respuesta `403 Forbidden`.
  - La autorización fina se gestiona mediante el paquete Spatie (middleware `permission:<permission_name>`).
  
### Respuestas de Error Comunes
* **401 Unauthorized**: Token inválido o no suministrado.
  ```json
  { "message": "Unauthenticated." }
  ```
* **403 Forbidden**: Sin permisos necesarios o usuario inactivo.
  ```json
  { "message": "Tu cuenta se encuentra inactiva. Contacta al administrador." }
  ```
* **404 Not Found**: El recurso solicitado no existe.
  ```json
  { "message": "Record not found." }
  ```
* **422 Unprocessable Entity**: Falló la validación de parámetros.
  ```json
  {
    "message": "The given data was invalid.",
    "errors": {
      "campo_ejemplo": ["El campo ejemplo es obligatorio."]
    }
  }
  ```

---

## 2. Módulo de Seguridad e Historial

### Autenticación (Auth)

#### 1. POST `/auth/login`
Inicia sesión en la plataforma y genera el token de acceso Sanctum.
* **Middleware**: `throttle:login` (limitación de tasa de intentos).
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `login` | String | Sí | Max: 120. Puede ser el `username` o `email` del usuario. |
  | `password` | String | Sí | Max: 255. Contraseña del usuario. |
  | `device_name` | String | No | Max: 100. Nombre identificador del dispositivo (ej. "web-chrome"). Por defecto toma "uyn-api". |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "message": "Inicio de sesión correcto.",
    "token_type": "Bearer",
    "access_token": "1|abcdef123456...",
    "user": {
      "id": 1,
      "name": "Administrador",
      "username": "admin",
      "email": "admin@uyn.com",
      "status": "active",
      "roles": ["admin"],
      "permissions": ["users.view", "users.create", ...],
      "created_at": "2026-07-15T02:00:00.000000Z",
      "updated_at": "2026-07-15T02:00:00.000000Z"
    }
  }
  ```
* **Respuesta Errónea Común (401 Unauthorized)**:
  ```json
  { "message": "Las credenciales proporcionadas son incorrectas." }
  ```

#### 2. GET `/auth/me`
Obtiene los datos del usuario autenticado en la sesión actual.
* **Middleware**: `auth:sanctum`, `active.user`.
* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": {
      "id": 1,
      "name": "Administrador",
      "username": "admin",
      "email": "admin@uyn.com",
      "status": "active",
      "roles": ["admin"],
      "permissions": ["users.view", ...],
      "created_at": "2026-07-15T02:00:00.000000Z",
      "updated_at": "2026-07-15T02:00:00.000000Z"
    }
  }
  ```

#### 3. POST `/auth/logout`
Invalida y elimina el token de acceso de la sesión actual.
* **Middleware**: `auth:sanctum`, `active.user`.
* **Respuesta Exitosa (200 OK)**:
  ```json
  { "message": "Sesión cerrada correctamente." }
  ```

---

### Logs de Operación (Operation Logs)

#### 4. GET `/operation-logs`
Obtiene el listado paginado del historial de auditoría de logs del sistema (creación, edición, activación, etc.).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:operation-logs.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `user_id` | Integer | No | Filtra por el ID de un usuario específico. Debe existir en `users.id`. |
  | `module` | String | No | Filtra por el módulo afectado (ej. "GarmentCut", "Employee", etc.). Max: 80. |
  | `action` | String | No | Filtra por la acción realizada (ej. "create", "update", "deactivate"). Max: 80. |
  | `date_from` | Date | No | Filtra logs creados a partir de esta fecha (YYYY-MM-DD). |
  | `date_to` | Date | No | Filtra logs creados hasta esta fecha. Debe ser posterior o igual a `date_from`. |
  | `per_page` | Integer | No | Cantidad de registros por página. Min: 1, Max: 100. Por defecto: 20. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 125,
        "module": "Employee",
        "action": "create",
        "description": "Se registró el trabajador Juan Pérez",
        "subject": {
          "type": "Employee",
          "id": 14
        },
        "old_values": null,
        "new_values": {
          "name": "Juan Pérez",
          "worker_type": "internal",
          "phone": "555123456",
          "status": "active"
        },
        "ip_address": "127.0.0.1",
        "user": {
          "id": 1,
          "name": "Administrador",
          "username": "admin",
          "email": "admin@uyn.com"
        },
        "created_at": "2026-07-15T02:10:00.000000Z"
      }
    ],
    "links": { "first": "...", "last": "...", "prev": null, "next": null },
    "meta": { "current_page": 1, "from": 1, "last_page": 1, "per_page": 20, "to": 1, "total": 1 }
  }
  ```

---

### Roles y Permisos (Roles & Permissions)

#### 5. GET `/roles`
Obtiene el listado de todos los roles configurados en la aplicación.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:roles.view`.
* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 1,
        "name": "admin",
        "guard_name": "web",
        "permissions": ["users.view", "users.create", "employees.view", ...],
        "created_at": "2026-07-15T02:00:00.000000Z",
        "updated_at": "2026-07-15T02:00:00.000000Z"
      }
    ]
  }
  ```

#### 6. GET `/permissions`
Obtiene todos los permisos disponibles agrupados por módulo.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:roles.view`.
* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 1,
        "name": "users.view",
        "module": "users",
        "action": "view",
        "guard_name": "web",
        "roles_count": 2,
        "created_at": "2026-07-15T02:00:00.000000Z",
        "updated_at": "2026-07-15T02:00:00.000000Z"
      }
    ]
  }
  ```

---

### Gestión de Usuarios (Users)

#### 7. GET `/users`
Retorna el listado paginado de usuarios con sus respectivos roles.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:users.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `search` | String | No | Búsqueda por `name`, `username` o `email` (coincidencia parcial). Max: 120. |
  | `status` | String | No | Filtrar por estado. Valores: `active`, `inactive`. |
  | `role` | String | No | Filtrar por nombre de rol. Debe existir en `roles.name` con guard_name `web`. |
  | `per_page` | Integer | No | Registros por página. Min: 1, Max: 100. Por defecto: 15. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 2,
        "name": "Coordinador de Taller",
        "username": "coordinador",
        "email": "coord@uyn.com",
        "status": "active",
        "roles": ["supervisor"],
        "permissions": ["employees.view", "cuts.view"],
        "created_at": "2026-07-15T02:00:00.000000Z",
        "updated_at": "2026-07-15T02:00:00.000000Z"
      }
    ],
    "meta": { "total": 1, "per_page": 15, ... }
  }
  ```

#### 8. POST `/users`
Registra un nuevo usuario en el sistema.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:users.create`. (Internamente valida mediante UserPolicy en el controlador).
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `name` | String | Sí | Max: 150. Nombre completo. |
  | `username` | String | Sí | Min: 3, Max: 50. Letras minúsculas, números, puntos, guiones y guiones bajos (`/^[a-z0-9._-]+$/`). Único en `users`. |
  | `email` | String | Sí | Max: 150. Formato email válido. Único en `users`. |
  | `password` | String | Sí | Min: 10. Debe incluir mayúsculas y minúsculas, números y símbolos. |
  | `password_confirmation`| String | Sí | Debe coincidir exactamente con `password`. |
  | `role` | String | Sí | Nombre de rol asignado. Debe existir en la tabla `roles`. |
  | `status` | String | No | Valores: `active`, `inactive`. Por defecto: `active`. |

* **Respuesta Exitosa (217 Created)**:
  ```json
  {
    "data": {
      "id": 3,
      "name": "Auxiliar Almacén",
      "username": "auxiliar.almacen",
      "email": "aux@uyn.com",
      "status": "active",
      "roles": ["auxiliar"],
      "permissions": ["cuts.view"],
      "created_at": "2026-07-15T02:20:00.000000Z",
      "updated_at": "2026-07-15T02:20:00.000000Z"
    },
    "message": "Usuario creado correctamente."
  }
  ```

#### 9. GET `/users/{user}`
Muestra la información de un usuario específico.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:users.view` (Valida a través de la política `view`).
* **Parámetros de Ruta**: `{user}` = ID numérico del usuario.
* **Respuesta Exitosa (200 OK)**:
  Igual a la estructura del UserResource.

#### 10. PUT/PATCH `/users/{user}`
Actualiza los datos de un usuario (incluyendo contraseña opcional y rol).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:users.update`.
* **Parámetros de Ruta**: `{user}` = ID numérico del usuario.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `name` | String | Sí | Max: 150. |
  | `username` | String | Sí | Min: 3, Max: 50. Regex minúsculas/números. Único en `users` excepto el usuario actual. |
  | `email` | String | Sí | Max: 150. Email válido. Único en `users` excepto el usuario actual. |
  | `password` | String | No | Min: 10. Mixto/números/símbolos. Si se envía, debe confirmarse. |
  | `password_confirmation`| String | No | Requerido si se envía `password`. |
  | `role` | String | Sí | Rol asignado. Debe existir en `roles`. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... },
    "message": "Usuario actualizado correctamente."
  }
  ```

#### 11. POST `/users/{user}/deactivate`
Cambia el estado de un usuario a `inactive`.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:users.deactivate`.
* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... "status": "inactive" ... },
    "message": "Usuario desactivado correctamente."
  }
  ```

#### 12. POST `/users/{user}/activate`
Cambia el estado de un usuario a `active`.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:users.activate`.
* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... "status": "active" ... },
    "message": "Usuario activado correctamente."
  }
  ```

---

## 3. Módulo de Catálogos

### Áreas (Areas)

#### 13. GET `/areas`
Obtiene el listado ordenado por flujo físico predeterminado del taller (`Corte` -> `Diseño` -> `Bordado` -> `Maquila` -> `Preparación` -> `Terminado` -> otros).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:employees.view`.
* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      { "id": 1, "name": "Corte" },
      { "id": 2, "name": "Diseño" },
      { "id": 3, "name": "Bordado" }
    ]
  }
  ```

---

### Trabajadores / Empleados (Employees)

#### 14. GET `/employees`
Obtiene un listado paginado de los trabajadores registrados.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:employees.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `search` | String | No | Coincidencia parcial por `name` o `phone`. Max: 150. |
  | `area_id` | Integer | No | Filtrar por ID de área. Debe existir en `areas.id`. |
  | `worker_type`| String | No | Filtrar por tipo. Valores: `internal` (interno), `external` (maquilero externo). |
  | `status` | String | No | Filtrar por estado. Valores: `active`, `inactive`. |
  | `per_page` | Integer | No | Registros por página. Min: 1, Max: 100. Por defecto: 15. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 5,
        "name": "María López",
        "worker_type": "internal",
        "worker_type_label": "Empleado interno",
        "phone": "555987654",
        "status": "active",
        "notes": "Costurera especializada",
        "area": {
          "id": 4,
          "name": "Maquila"
        },
        "created_at": "2026-07-15T02:00:00.000000Z",
        "updated_at": "2026-07-15T02:00:00.000000Z"
      }
    ],
    "meta": { "total": 1, ... }
  }
  ```

#### 15. POST `/employees`
Registra un nuevo trabajador en el sistema.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:employees.create`.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `name` | String | Sí | Max: 150. Nombre del empleado. |
  | `area_id` | Integer | Sí | ID de área asignada. Debe existir en `areas`. |
  | `worker_type`| String | Sí | Tipo de trabajador. Valores: `internal`, `external`. |
  | `phone` | String | Sí | Teléfono de contacto. Max: 30. |
  | `status` | String | No | Valores: `active`, `inactive`. Por defecto: `active`. |
  | `notes` | String | No | Observaciones o notas adicionales. Max: 2000. |

* **Respuesta Exitosa (201 Created)**:
  ```json
  {
    "data": { ... },
    "message": "Trabajador registrado correctamente."
  }
  ```

#### 16. GET `/employees/{employee}`
Muestra la información detallada de un trabajador.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:employees.view`.
* **Parámetros de Ruta**: `{employee}` = ID del trabajador.
* **Respuesta Exitosa (200 OK)**:
  Estructura estándar de `EmployeeResource`.

#### 17. PUT/PATCH `/employees/{employee}`
Actualiza parcialmente o totalmente la información de un trabajador.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:employees.update`.
* **Parámetros de Ruta**: `{employee}` = ID del trabajador.
* **Parámetros del Body (JSON)** (Todos opcionales si se utiliza PATCH, pero validados si se envían):
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `name` | String | No | Max: 150. |
  | `area_id` | Integer | No | Debe existir en `areas`. |
  | `worker_type`| String | No | Valores: `internal`, `external`. |
  | `phone` | String | No | Max: 30. |
  | `notes` | String | No | Max: 2000. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... },
    "message": "Trabajador actualizado correctamente."
  }
  ```

#### 18. POST `/employees/{employee}/deactivate`
Desactiva a un empleado (cambia su estado a inactivo).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:employees.deactivate`.
* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... "status": "inactive" ... },
    "message": "Trabajador desactivado correctamente."
  }
  ```

#### 19. POST `/employees/{employee}/activate`
Activa a un empleado (cambia su estado a activo).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:employees.activate`.
* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... "status": "active" ... },
    "message": "Trabajador activado correctamente."
  }
  ```

---

### Modelos de Prenda (Garment Models)

#### 20. GET `/garment-models`
Obtiene un listado paginado de los modelos de prendas (diseños base de ropa).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:garment-models.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `search` | String | No | Coincidencia parcial en `code`, `name` o `description`. Max: 150. |
  | `status` | String | No | Filtrar por estado. Valores: `active`, `inactive`. |
  | `per_page` | Integer | No | Registros por página. Min: 1, Max: 100. Por defecto: 15. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 3,
        "code": "CH-09",
        "name": "Chamarra Deportiva",
        "description": "Chamarra impermeable con forro interno",
        "size_range": "28-44",
        "image_path": "garment_models/ch-09.png",
        "image_url": "http://domain/storage/garment_models/ch-09.png",
        "status": "active",
        "status_label": "Activo",
        "created_at": "2026-07-15T02:00:00.000000Z",
        "updated_at": "2026-07-15T02:00:00.000000Z"
      }
    ]
  }
  ```

#### 21. POST `/garment-models`
Registra un nuevo modelo de prenda. Admite subida de archivos (imagen).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:garment-models.create`.
* **Parámetros del Body (Form-Data / Multipart)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `code` | String | Sí | Min: 2, Max: 50. Mayúsculas, números, puntos, guiones y guiones bajos (`/^[A-Z0-9._-]+$/`). Único en `garment_models`. |
  | `name` | String | Sí | Max: 150. Nombre del modelo de prenda. |
  | `description`| String | No | Notas o detalles técnicos. Max: 3000. |
  | `size_range` | String | No | Rango descriptivo de tallas (ej. "CH - EG"). Max: 100. |
  | `image` | File | No | Archivo de imagen válido. Mimes: `jpg`, `jpeg`, `png`, `webp`. Max: 5120 KB (5 MB). |
  | `status` | String | No | Valores: `active`, `inactive`. Por defecto: `active`. |

* **Respuesta Exitosa (201 Created)**:
  ```json
  {
    "data": { ... },
    "message": "Modelo de prenda registrado correctamente."
  }
  ```

#### 22. GET `/garment-models/{garment_model}`
Muestra la información de un modelo de prenda específico.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:garment-models.view`.
* **Parámetros de Ruta**: `{garment_model}` = ID numérico del modelo.
* **Respuesta Exitosa (200 OK)**:
  Estructura estándar de `GarmentModelResource`.

#### 23. PUT/PATCH `/garment-models/{garment_model}`
Actualiza la información de un modelo de prenda. Si se actualiza la imagen, se debe enviar a través de `POST` simulando PUT (usando el campo `_method = PUT` en el form-data) debido a restricciones de Laravel con multipart en peticiones PUT directas.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:garment-models.update`.
* **Parámetros de Ruta**: `{garment_model}` = ID del modelo.
* **Parámetros del Body**:
  Mismos campos que `POST`, pero validados de forma opcional (`sometimes`). El código valida unicidad ignorando el registro actual.

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... },
    "message": "Modelo de prenda actualizado correctamente."
  }
  ```

#### 24. POST `/garment-models/{garment_model}/deactivate`
Desactiva el modelo de prenda.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:garment-models.deactivate`.
* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... "status": "inactive" ... },
    "message": "Modelo de prenda desactivado correctamente."
  }
  ```

#### 25. POST `/garment-models/{garment_model}/activate`
Activa el modelo de prenda.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:garment-models.activate`.
* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... "status": "active" ... },
    "message": "Modelo de prenda activado correctamente."
  }
  ```

---

### Tallas (Sizes)

#### 26. GET `/sizes`
Obtiene la lista de tallas activas del sistema. Las tallas se ordenan numéricamente si es posible y luego por nombre.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:cuts.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `status` | String | No | Filtrar por estado. Valores: `active`, `inactive`, `all`. Por defecto: `active`. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 1,
        "name": "32",
        "description": "Talla 32",
        "status": "active",
        "status_label": "Activa"
      },
      {
        "id": 2,
        "name": "34",
        "description": "Talla 34",
        "status": "active",
        "status_label": "Activa"
      }
    ]
  }
  ```

---

## 4. Módulo de Órdenes y Cortes de Producción

### Órdenes de Producción (Production Orders)

Una orden de producción representa un pedido maestro que agrupa diferentes cortes de prendas.

#### 27. GET `/production-orders`
Obtiene el listado paginado de órdenes de producción.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:cuts.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `search` | String | No | Búsqueda por folio de orden (`order_code`), ubicación (`location`) o notas. Max: 150. |
  | `status` | String | No | Filtro por estado. Valores: `registered` (registrada), `in_progress` (en proceso), `completed` (completada), `cancelled` (cancelada). |
  | `priority` | String | No | Filtro por prioridad. Valores: `low` (baja), `normal`, `high` (alta), `urgent` (urgente). |
  | `date_from` | Date | No | Filtra por fecha de inicio a partir de YYYY-MM-DD. |
  | `date_to` | Date | No | Filtra por fecha de inicio hasta YYYY-MM-DD. Debe ser posterior o igual a `date_from`. |
  | `per_page` | Integer | No | Registros por página. Por defecto: 15. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 1,
        "order_code": "OP-2026-001",
        "location": "Almacén Principal Norte",
        "status": "in_progress",
        "status_label": "En proceso",
        "priority": "high",
        "priority_label": "Alta",
        "start_date": "2026-07-10",
        "end_date": "2026-07-20",
        "notes": "Pedido urgente para cliente corporativo",
        "created_by": {
          "id": 1,
          "name": "Administrador",
          "username": "admin"
        },
        "garment_cuts_count": 3,
        "created_at": "2026-07-10T12:00:00.000000Z",
        "updated_at": "2026-07-15T02:00:00.000000Z"
      }
    ]
  }
  ```

#### 28. POST `/production-orders`
Registra una nueva orden de producción.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:cuts.create`.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `order_code` | String | Sí | Folio único de la orden. Min: 3, Max: 50. Letras, números, puntos y guiones (`/^[A-Z0-9._-]+$/`). Único en `production_orders`. |
  | `location` | String | No | Ubicación o destino de la producción. Max: 150. |
  | `start_date` | Date | Sí | Fecha de inicio programada (YYYY-MM-DD). |
  | `end_date` | Date | No | Fecha estimada de finalización. Debe ser posterior o igual a `start_date`. |
  | `priority` | String | No | Prioridad de ejecución. Valores: `low`, `normal`, `high`, `urgent`. Por defecto: `normal`. |
  | `notes` | String | No | Notas aclaratorias. Max: 3000. |

* **Respuesta Exitosa (201 Created)**:
  ```json
  {
    "data": { ... },
    "message": "Orden de producción registrada correctamente."
  }
  ```

#### 29. GET `/production-orders/{production_order}`
Muestra los detalles de una orden de producción específica, incluyendo la lista de todos sus cortes asociados (`garment_cuts`), el modelo de prenda y el área física actual donde se encuentra cada corte.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:cuts.view`.
* **Parámetros de Ruta**: `{production_order}` = ID de la orden.
* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": {
      "id": 1,
      "order_code": "OP-2026-001",
      "location": "Almacén Principal Norte",
      "status": "in_progress",
      "status_label": "En proceso",
      "priority": "high",
      "priority_label": "Alta",
      "start_date": "2026-07-10",
      "end_date": "2026-07-20",
      "notes": "Pedido urgente para cliente corporativo",
      "created_by": {
        "id": 1,
        "name": "Administrador"
      },
      "garment_cuts_count": 2,
      "garment_cuts": [
        {
          "id": 10,
          "code": "C-CH-09-01",
          "description": "Corte inicial chamarras",
          "total_sizes": 3,
          "total_pieces": 150,
          "status": "in_progress",
          "garment_model": {
            "id": 3,
            "code": "CH-09",
            "name": "Chamarra Deportiva"
          },
          "current_area": {
            "id": 3,
            "name": "Bordado"
          }
        }
      ],
      "created_at": "...",
      "updated_at": "..."
    }
  }
  ```

#### 30. PUT/PATCH `/production-orders/{production_order}`
Actualiza los parámetros editables de una orden de producción.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:cuts.update`.
* **Parámetros de Ruta**: `{production_order}` = ID de la orden.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `location` | String | No | Max: 150. |
  | `start_date` | Date | No | YYYY-MM-DD. |
  | `end_date` | Date | No | YYYY-MM-DD. |
  | `priority` | String | No | Valores: `low`, `normal`, `high`, `urgent`. |
  | `notes` | String | No | Max: 3000. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... },
    "message": "Orden de producción actualizada correctamente."
  }
  ```

---

### Cortes de Prenda (Garment Cuts)

El corte representa un lote físico de prendas de un modelo específico que se procesará en el taller. Al crearse, se define la distribución de piezas por talla.

#### 31. GET `/garment-cuts`
Obtiene el listado paginado de lotes de corte registrados.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:cuts.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `search` | String | No | Coincidencia en folio de corte, descripción, notas, código/nombre de modelo, o código de orden de producción. Max: 150. |
  | `production_order_id`| Integer | No | Filtrar por ID de orden de producción. |
  | `garment_model_id` | Integer | No | Filtrar por ID del modelo de prenda. |
  | `current_area_id` | Integer | No | Filtrar por área física actual donde se encuentra el corte. |
  | `status` | String | No | Filtrar por estado. Valores: `registered`, `in_progress`, `partially_completed`, `completed`, `cancelled`, `with_incident`, `delayed`. |
  | `per_page` | Integer | No | Registros por página. Por defecto: 15. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 10,
        "code": "C-CH-09-01",
        "description": "Corte inicial chamarras",
        "total_sizes": 2,
        "base_pieces_per_size": null,
        "total_pieces": 150,
        "is_uniform_distribution": false,
        "status": "in_progress",
        "status_label": "En proceso",
        "notes": null,
        "production_order": {
          "id": 1,
          "order_code": "OP-2026-001",
          "status": "in_progress",
          "priority": "high",
          "start_date": "2026-07-10",
          "end_date": "2026-07-20"
        },
        "garment_model": {
          "id": 3,
          "code": "CH-09",
          "name": "Chamarra Deportiva",
          "status": "active"
        },
        "current_area": {
          "id": 3,
          "name": "Bordado"
        },
        "created_at": "...",
        "updated_at": "..."
      }
    ]
  }
  ```

#### 32. POST `/garment-cuts`
Registra un nuevo lote de corte y define su distribución de tallas.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:cuts.create`.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `production_order_id`| Integer | Sí | ID de la orden de producción. Debe existir en `production_orders`. |
  | `garment_model_id` | Integer | Sí | ID del modelo de prenda. Debe existir en `garment_models` y estar en estado `active`. |
  | `code` | String | Sí | Folio del corte. Min: 3, Max: 50. Letras, números, puntos y guiones. Único en `garment_cuts`. |
  | `description` | String | No | Descripción del lote. Max: 3000. |
  | `notes` | String | No | Observaciones. Max: 3000. |
  | `sizes` | Array | Sí | Min: 1 elemento, Max: 20 elementos. Listado de tallas y cantidad de piezas asociadas. |
  | `sizes.*.size_id` | Integer | Sí | ID de talla. Único dentro del arreglo `sizes`. Debe existir en `sizes` y estar en estado `active`. |
  | `sizes.*.total_pieces`| Integer | Sí | Cantidad de piezas cortadas para esta talla. Min: 1, Max: 1,000,000. |

* **Respuesta Exitosa (201 Created)**:
  ```json
  {
    "data": { ... },
    "message": "Corte registrado correctamente."
  }
  ```

#### 33. GET `/garment-cuts/{garment_cut}`
Muestra los detalles completos de un corte específico, incluyendo la distribución detallada de tallas, la configuración de complementos de corte (`complement`) y las rutas especiales de piezas asociadas (`special_process_pieces`).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:cuts.view`.
* **Parámetros de Ruta**: `{garment_cut}` = ID del corte.
* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": {
      "id": 10,
      "code": "C-CH-09-01",
      "description": "Corte inicial chamarras",
      "total_sizes": 2,
      "total_pieces": 150,
      "status": "in_progress",
      "status_label": "En proceso",
      "production_order": { ... },
      "garment_model": { ... },
      "current_area": { ... },
      "sizes": [
        {
          "id": 25,
          "size": { "id": 1, "name": "32" },
          "total_pieces": 50
        },
        {
          "id": 26,
          "size": { "id": 2, "name": "34" },
          "total_pieces": 100
        }
      ],
      "complement": {
        "id": 5,
        "garment_cut_id": 10,
        "status": "pending",
        "status_label": "Pendiente",
        "notes": "Requiere bies rojo",
        "current_area": { "id": 1, "name": "Corte" }
      },
      "special_process_pieces": [
        {
          "id": 8,
          "garment_cut_id": 10,
          "status": "in_progress",
          "status_label": "En proceso",
          "notes": "Bordado de logo en espalda",
          "piece_type": { "id": 2, "name": "Espalda" },
          "process": { "id": 3, "name": "Bordado", "flow_order": 3 },
          "current_area": { "id": 3, "name": "Bordado" }
        }
      ],
      "created_at": "...",
      "updated_at": "..."
    }
  }
  ```

#### 34. PUT/PATCH `/garment-cuts/{garment_cut}`
Actualiza la descripción, notas o la distribución de piezas por talla del corte (si el proceso aún lo permite).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:cuts.update`.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `description` | String | No | Max: 3000. |
  | `notes` | String | No | Max: 3000. |
  | `sizes` | Array | No | Arreglo de tallas con cantidades si se requiere actualizar las piezas. Min: 1. |
  | `sizes.*.size_id` | Integer | Sí (si envías sizes) | ID de talla activa y distinta en el arreglo. |
  | `sizes.*.total_pieces`| Integer | Sí (si envías sizes) | Cantidad de piezas. Min: 1. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... },
    "message": "Corte actualizado correctamente."
  }
  ```

---

### Clasificación de Cortes (Garment Cuts Classification)

Permite configurar los complementos y las piezas de un corte que requieren seguir rutas de procesos especiales (como Bordado o Estampado) antes de reunirse en el proceso principal de Ensamble/Maquila.

#### 35. GET `/garment-cuts/{garment_cut}/classification`
Obtiene la configuración actual de complementos y piezas especiales para un corte.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:processes.view`.
* **Parámetros de Ruta**: `{garment_cut}` = ID del corte.
* **Respuesta Exitosa (200 OK)**:
  Igual a la estructura detallada en `GET /garment-cuts/{garment_cut}`.

#### 36. PUT/PATCH `/garment-cuts/{garment_cut}/classification`
Configura y guarda los complementos y las piezas especiales que se derivarán del lote de corte.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:processes.classify`.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `complement_notes` | String | No | Notas sobre los complementos del corte (ej. forros, bies, hilos). Max: 3000. |
  | `special_process_pieces` | Array | Sí | Debe enviarse siempre (incluso vacío `[]`). Max: 20 piezas especiales. |
  | `special_process_pieces.*.piece_type_id` | Integer | Sí | ID del tipo de pieza (ej. Frente, Cuello). Único en el arreglo. Debe existir en `piece_types` y estar activo. |
  | `special_process_pieces.*.process_id` | Integer | Sí | ID del proceso especial donde se derivará (ej. Bordado). Debe existir en `processes`. |
  | `special_process_pieces.*.notes` | String | No | Instrucciones especiales para esta pieza. Max: 3000. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... },
    "message": "Clasificación del corte guardada correctamente."
  }
  ```

---

## 5. Módulo de Flujo de Taller (Movimientos y Avances)

### Procesos y Tipos de Pieza (Processes & Piece Types)

#### 37. GET `/processes`
Obtiene la lista de procesos principales del taller (ej. Corte, Bordado, Maquila) junto con sus suboperaciones asociadas. Se devuelven ordenados por su orden físico en el flujo de trabajo (`flow_order`).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:processes.view`.
* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 1,
        "name": "Corte",
        "flow_order": 1,
        "operations": [
          { "id": 1, "name": "Tendido y Marcado", "flow_order": 1 },
          { "id": 2, "name": "Habilitado de piezas", "flow_order": 2 }
        ]
      },
      {
        "id": 2,
        "name": "Bordado",
        "flow_order": 2,
        "operations": [
          { "id": 3, "name": "Ponchado", "flow_order": 1 },
          { "id": 4, "name": "Bordado de logo", "flow_order": 2 }
        ]
      }
    ]
  }
  ```

#### 38. GET `/piece-types`
Obtiene los tipos de piezas configuradas (ej. Espalda, Delantero, Manga, Cuello).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:processes.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `search` | String | No | Coincidencia en nombre o descripción. Max: 100. |
  | `status` | String | No | Valores: `active`, `inactive`, `all`. Por defecto: `active`. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      { "id": 1, "name": "Frente", "description": "Frente de prenda", "status": "active", "status_label": "Activo" }
    ]
  }
  ```

---

### Movimientos de Producción (Production Movements)

Gestiona la transferencia física de lotes de corte, complementos o piezas especiales entre las distintas áreas del taller y procesos productivos.

#### 39. GET `/production-movements`
Obtiene el historial paginado de movimientos de producción realizados.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:processes.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `garment_cut_id` | Integer | No | Filtra por ID de corte. Debe existir en `garment_cuts`. |
  | `target_type` | String | No | Filtra por el tipo de elemento movido. Valores: `cut` (corte principal), `complement` (complemento), `special_piece` (pieza especial). |
  | `process_id` | Integer | No | Filtra por ID de proceso productivo destino. |
  | `from_area_id` | Integer | No | Filtra por área de origen. |
  | `to_area_id` | Integer | No | Filtra por área de destino. |
  | `status` | String | No | Filtrar por estado del movimiento. Valores: `pending` (por recibir), `received` (recibido), `in_progress` (en proceso), `partially_completed`, `completed` (completado), `cancelled`, `with_incident`, `delayed`. |
  | `search` | String | No | Búsqueda por notas del movimiento o folio de corte. Max: 150. |
  | `per_page` | Integer | No | Registros por página. Por defecto: 15. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 18,
        "target_type": "special_piece",
        "target_type_label": "Pieza con proceso especial",
        "return_incident_id": null,
        "is_return_for_rework": false,
        "return_incident": null,
        "quantity": 150,
        "resolved_loss_quantity": 0,
        "effective_quantity": 150,
        "status": "pending",
        "status_label": "Pendiente de recepción",
        "start_time": null,
        "end_time": null,
        "notes": "Envío a bordadora externa",
        "garment_cut": {
          "id": 10,
          "code": "C-CH-09-01",
          "total_pieces": 150,
          "status": "in_progress"
        },
        "target": {
          "id": 8,
          "status": "pending",
          "piece_type": { "id": 2, "name": "Espalda" },
          "special_process": { "id": 3, "name": "Bordado" },
          "current_area": { "id": 1, "name": "Corte" }
        },
        "process": { "id": 2, "name": "Bordado" },
        "operation_process": { "id": 4, "name": "Bordado de logo" },
        "from_area": { "id": 1, "name": "Corte" },
        "to_area": { "id": 3, "name": "Bordado" },
        "created_by": { "id": 2, "name": "Coordinador" },
        "received_by": null,
        "operation_logs_count": 0,
        "created_at": "2026-07-15T02:18:00.000000Z",
        "updated_at": "2026-07-15T02:18:00.000000Z"
      }
    ],
    "meta": { "total": 1, ... }
  }
  ```

#### 40. POST `/production-movements`
Registra el envío (salida) de un lote de producción (corte, complemento o pieza especial) hacia una nueva área y proceso.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:processes.assign`.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `garment_cut_id` | Integer | Sí | ID del corte asociado. Debe existir en `garment_cuts`. |
  | `target_type` | String | Sí | Tipo de lote a mover. Valores: `cut`, `complement`, `special_piece`. |
  | `special_process_piece_id`| Integer | Condicional | Requerido si `target_type` es `special_piece`. ID del registro de pieza especial. Debe existir en `special_process_pieces`. |
  | `complement_id` | Integer | Condicional | Requerido si `target_type` es `complement`. ID del registro de complemento. Debe existir en `garment_cut_complements`. |
  | `process_id` | Integer | Sí | ID del proceso destino. Debe existir en `processes`. |
  | `operation_process_id`| Integer | Sí | ID de la suboperación destino. Debe existir en `operation_processes`. |
  | `quantity` | Integer | Sí | Cantidad de piezas a enviar. Min: 1, Max: 1,000,000. |
  | `notes` | String | No | Observaciones del envío. Max: 3000. |

* **Respuesta Exitosa (201 Created)**:
  ```json
  {
    "data": { ... },
    "message": "Envío de producción registrado correctamente."
  }
  ```

#### 41. GET `/production-movements/{production_movement}`
Muestra los detalles de un movimiento específico, con la lista completa de avances de trabajadores (`operation_logs`) cargados para este lote.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:processes.view`.
* **Parámetros de Ruta**: `{production_movement}` = ID del movimiento.
* **Respuesta Exitosa (200 OK)**:
  Estructura similar a `GET /production-movements` con el arreglo de `operation_logs` precargado.

#### 42. POST `/production-movements/{production_movement}/receive`
Confirma la recepción del lote en el área de destino. Marca el estado del movimiento como `received` (o `in_progress`), registrando la fecha/hora de entrada.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:processes.update-status`.
* **Parámetros del Body (JSON)**:
  Este endpoint prohíbe el envío de parámetros en el body (`prohibited`). La confirmación se realiza de forma directa sobre la ruta para el movimiento provisto.
* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... "status": "received", "received_by": { ... } },
    "message": "Recepción de producción confirmada correctamente."
  }
  ```

---

### Avances de Operaciones / Destajos (Production Operation Logs)

Permite registrar qué trabajador realiza las operaciones físicas sobre un lote en movimiento, controlando la cantidad procesada para fines de productividad y cálculo automático de nómina de destajo.

#### 43. GET `/production-movements/{production_movement}/operation-logs`
Obtiene los registros de avances asociados a un movimiento específico.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:processes.view`.
* **Parámetros de Ruta**: `{production_movement}` = ID del movimiento.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `employee_id` | Integer | No | Filtrar por el ID del trabajador responsable del avance. |
  | `status` | String | No | Filtrar por estado de la operación. Valores: `pending`, `in_progress`, `completed`, `cancelled`, `with_incident`. |
  | `per_page` | Integer | No | Registros por página. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 14,
        "quantity_processed": 100,
        "stitches_count": 0,
        "applications_count": 0,
        "status": "completed",
        "status_label": "Completado",
        "start_time": "2026-07-15T02:00:00.000000Z",
        "end_time": "2026-07-15T02:15:00.000000Z",
        "notes": "Avance de costura",
        "payout_amount": "250.00",
        "payout_status": "generated",
        "employee": {
          "id": 5,
          "name": "María López",
          "worker_type": "internal",
          "status": "active"
        },
        "operation_process": {
          "id": 12,
          "name": "Costura delantera",
          "flow_order": 1
        },
        "created_at": "...",
        "updated_at": "..."
      }
    ]
  }
  ```
  *Nota: Los campos `payout_amount`, `payout_status` y `payout_snapshot` se ocultan si el usuario logueado no posee el permiso `payroll.view`.*

#### 44. POST `/production-movements/{production_movement}/operation-logs`
Asigna un trabajador a un movimiento de producción, creando un registro de avance en estado `pending` o `in_progress`.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:processes.assign`.
* **Parámetros de Ruta**: `{production_movement}` = ID del movimiento.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `employee_id` | Integer | Sí | ID del trabajador responsable. Debe existir en `employees` y estar en estado `active`. |
  | `notes` | String | No | Observaciones sobre la asignación. Max: 3000. |

* **Respuesta Exitosa (217 Created)**:
  ```json
  {
    "data": { ... },
    "message": "Trabajador asignado a la operación correctamente."
  }
  ```

#### 45. PUT/PATCH `/production-operation-logs/{production_operation_log}`
Actualiza el avance de trabajo registrado por un operario. Permite iniciar el cronómetro de la operación, guardar avances parciales de cantidad y finalizarla.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:processes.update-status`.
* **Parámetros de Ruta**: `{production_operation_log}` = ID del registro de avance.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `start` | Boolean | No | Si se envía como `true`, registra la hora de inicio actual (`start_time`) y cambia el estado a `in_progress`. |
  | `complete` | Boolean | No | Si se envía como `true`, registra la hora de finalización actual (`end_time`) y cambia el estado a `completed`. |
  | `quantity_processed`| Integer | No | Cantidad de piezas terminadas por este trabajador en este avance. Min: 0, Max: 1,000,000. |
  | `stitches_count` | Integer | No | Número de puntadas realizadas (exclusivo para procesos de Bordado con cálculo por puntada). Min: 0, Max: 100,000,000. |
  | `applications_count`| Integer | No | Número de aplicaciones colocadas (exclusivo para Bordado con cálculo mixto). Min: 0. |
  | `notes` | String | No | Observaciones. Max: 3000. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... },
    "message": "Avance de operación actualizado correctamente." // u "Operación completada correctamente."
  }
  ```

---

## 6. Módulo de Control de Calidad e Incidencias

### Incidencias de Producción (Production Incidents)

Permite documentar problemas presentados en los lotes de producción (daños, mermas, pérdidas de piezas, fallas de calidad o retrasos), asociándolos a un movimiento de producción y a un trabajador responsable si aplica.

#### 46. GET `/production-incidents`
Retorna el listado paginado de incidencias de producción registradas.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:incidents.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `garment_cut_id` | Integer | No | ID de corte para ver sus incidencias. |
  | `production_movement_id`| Integer| No | ID de un movimiento de producción específico. |
  | `responsible_employee_id`| Integer| No | ID de un trabajador responsable del fallo. |
  | `incident_type` | String | No | Filtrar tipo de problema. Valores: `damage` (daño o merma), `loss` (pérdida o faltante), `quality` (defecto de calidad), `delay` (retraso), `other`. |
  | `status` | String | No | Filtrar estado. Valores: `open` (abierta), `resolved` (resuelta), `cancelled` (cancelada). |
  | `search` | String | No | Búsqueda parcial por descripción, notas o folio de corte. Max: 150. |
  | `per_page` | Integer | No | Registros por página. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 4,
        "incident_type": "quality",
        "incident_type_label": "Defecto de calidad",
        "quantity_affected": 5,
        "description": "Costura fruncida en hombros",
        "status": "open",
        "status_label": "Abierta",
        "notes": "Pendiente evaluar si requiere costura nueva o se clasifica como merma",
        "resolved_at": null,
        "rework_movement": null,
        "garment_cut": {
          "id": 10,
          "code": "C-CH-09-01",
          "status": "with_incident"
        },
        "production_movement": {
          "id": 18,
          "target_type": "special_piece",
          "quantity": 150,
          "status": "with_incident",
          ...
        },
        "responsible_employee": {
          "id": 5,
          "name": "María López",
          "area": "Maquila"
        },
        "resolved_by": null,
        "created_at": "...",
        "updated_at": "..."
      }
    ]
  }
  ```

#### 47. POST `/production-incidents`
Registra una nueva incidencia sobre un movimiento de producción activo.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:incidents.create`.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `production_movement_id`| Integer| Sí | ID del movimiento de producción afectado. Debe existir en `production_movements`. |
  | `incident_type` | String | Sí | Tipo de incidencia. Valores: `damage`, `loss`, `quality`, `delay`, `other`. |
  | `quantity_affected` | Integer | Sí | Cantidad de piezas afectadas. Si el tipo es `damage`, `loss` o `quality` debe ser >= 1. Si el tipo es `delay` debe ser exactamente 0. |
  | `description` | String | Sí | Explicación detallada del problema. Max: 3000. |
  | `responsible_employee_id`| Integer| No | ID del empleado responsable. Debe existir en `employees` y estar activo. |
  | `notes` | String | No | Anotaciones extras. Max: 3000. |

* **Respuesta Exitosa (201 Created)**:
  ```json
  {
    "data": { ... },
    "message": "Incidencia de producción registrada correctamente."
  }
  ```

#### 48. GET `/production-incidents/{production_incident}`
Muestra los detalles de una incidencia específica.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:incidents.view`.
* **Parámetros de Ruta**: `{production_incident}` = ID del registro.
* **Respuesta Exitosa (200 OK)**:
  Estructura estándar de `ProductionIncidentResource`.

#### 49. PUT/PATCH `/production-incidents/{production_incident}`
Permite editar los datos descriptivos o el empleado responsable de una incidencia abierta.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:incidents.update`.
* **Parámetros de Ruta**: `{production_incident}` = ID del registro.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `description` | String | No | Max: 3000. |
  | `quantity_affected` | Integer | No | Min: 0 (debe cumplir reglas de tipo de incidencia al validar). |
  | `responsible_employee_id`| Integer| No | ID del empleado. Debe existir y estar activo. |
  | `notes` | String | No | Max: 3000. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... },
    "message": "Incidencia de producción actualizada correctamente."
  }
  ```

#### 50. POST `/production-incidents/{production_incident}/resolve`
Resuelve una incidencia abierta (ej. se acepta la pérdida de piezas o se soluciona de forma manual).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:incidents.close`.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `notes` | String | Sí | Explicación detallada de cómo se resolvió la incidencia. Min: 5, Max: 3000. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... "status": "resolved" ... },
    "message": "Incidencia de producción resuelta correctamente."
  }
  ```

#### 51. POST `/production-incidents/{production_incident}/return-for-rework`
Resuelve una incidencia por defectos de calidad ordenando la devolución de las piezas afectadas a un área previa para su reproceso (rework). Esto genera automáticamente un movimiento de producción inverso de reproceso.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:incidents.update`.
* **Parámetros de Ruta**: `{production_incident}` = ID del registro.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `operation_process_id`| Integer | Sí | ID de la suboperación del proceso anterior a la cual se regresará el lote para reparar. Debe existir en `operation_processes`. |
  | `notes` | String | Sí | Instrucciones exactas para el reproceso. Min: 5, Max: 3000. |

* **Respuesta Exitosa (201 Created)**:
  Retorna el recurso del nuevo movimiento de producción generado para el reproceso (`ProductionMovementResource`).
  ```json
  {
    "data": {
      "id": 19,
      "target_type": "special_piece",
      "quantity": 5,
      "is_return_for_rework": true,
      "return_incident_id": 4,
      "status": "pending",
      "process": { ... },
      "operation_process": { ... },
      "from_area": { "id": 4, "name": "Maquila" },
      "to_area": { "id": 3, "name": "Bordado" },
      ...
    },
    "message": "Devolución para reproceso registrada correctamente."
  }
  ```

---

## 7. Módulo de Tarifas y Compensaciones (Nómina)

### Compensaciones Fijas (Employee Compensations)

Define pagos fijos periódicos (sueldos fijos) que recibe un trabajador en lugar de o en combinación con el destajo.

#### 52. GET `/employee-compensations`
Obtiene un listado paginado de configuraciones de compensaciones fijas.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `employee_id` | Integer | No | ID del trabajador. |
  | `payment_type` | String | No | Filtrar tipo de pago. Valores: `piecework` (destajo puro), `fixed` (pago fijo). |
  | `status` | String | No | Filtrar por estado de la regla. Valores: `active`, `inactive`, `all`. Por defecto: `active`. |
  | `active_on` | Date | No | Filtra reglas que estuvieran vigentes en la fecha dada (YYYY-MM-DD). |
  | `per_page` | Integer | No | Registros por página. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 3,
        "payment_type": "fixed",
        "payment_type_label": "Pago fijo",
        "payment_frequency": "weekly",
        "payment_frequency_label": "Semanal",
        "fixed_amount": "1500.00",
        "effective_from": "2026-01-01",
        "effective_to": null,
        "status": "active",
        "status_label": "Activo",
        "is_current": true,
        "notes": "Sueldo base semanal garantía",
        "employee": {
          "id": 5,
          "name": "María López",
          "worker_type": "internal",
          "status": "active",
          "area": "Maquila"
        },
        "created_by": { "id": 1, "name": "Admin" },
        "created_at": "...",
        "updated_at": "..."
      }
    ]
  }
  ```

#### 53. POST `/employee-compensations`
Registra una nueva configuración de compensación para un trabajador.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.manage`.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `employee_id` | Integer | Sí | ID del trabajador. Debe existir en `employees` y estar activo. |
  | `payment_type` | String | Sí | Tipo de pago. Valores: `piecework`, `fixed`. |
  | `payment_frequency`| String | Condicional | Requerido si `payment_type` es `fixed`. Valores: `weekly`, `biweekly`, `monthly`. Prohibido si es `piecework`. |
  | `fixed_amount` | Numeric | Condicional | Requerido si `payment_type` es `fixed`. Min: 0.01. Monto monetario. Prohibido si es `piecework`. |
  | `effective_from` | Date | Sí | Fecha de inicio de vigencia de la regla (YYYY-MM-DD). |
  | `effective_to` | Date | No | Fecha final de vigencia. Debe ser >= `effective_from`. |
  | `notes` | String | No | Anotaciones. Max: 3000. |

* **Respuesta Exitosa (201 Created)**:
  ```json
  {
    "data": { ... },
    "message": "Compensación registrada correctamente."
  }
  ```

#### 54. GET `/employee-compensations/{employee_compensation}`
Obtiene los detalles de una compensación específica.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.view`.
* **Respuesta Exitosa (200 OK)**:
  Estructura estándar de `EmployeeCompensationResource`.

#### 55. PUT/PATCH `/employee-compensations/{employee_compensation}`
Permite inactivar la regla o definir/actualizar su fecha límite de vigencia (`effective_to`).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.manage`.
* **Parámetros de Ruta**: `{employee_compensation}` = ID del registro.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `effective_to` | Date | No | Fecha final de vigencia. Debe ser posterior o igual a la fecha de inicio original. |
  | `status` | String | No | Cambiar estado. Valores: `active`, `inactive`. |
  | `notes` | String | No | Max: 3000. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... },
    "message": "Compensación actualizada correctamente."
  }
  ```

---

### Tarifas de Destajo por Trabajador (Piecework Rates)

Configura los precios específicos que se le pagarán a un trabajador por cada pieza procesada de una suboperación en particular.

#### 56. GET `/piecework-rates`
Obtiene las tarifas de destajo vigentes y paginadas.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `employee_id` | Integer | No | Filtrar por trabajador. |
  | `operation_process_id`| Integer| No | Filtrar por la suboperación del proceso. |
  | `status` | String | No | Valores: `active`, `inactive`, `all`. Por defecto: `active`. |
  | `active_on` | Date | No | Fecha de vigencia de la tarifa (YYYY-MM-DD). |
  | `search` | String | No | Búsqueda por nombre de empleado, proceso, suboperación o notas. Max: 150. |
  | `per_page` | Integer | No | Registros por página. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 8,
        "amount_per_piece": "2.5000",
        "effective_from": "2026-01-01",
        "effective_to": null,
        "status": "active",
        "status_label": "Activa",
        "is_current": true,
        "notes": "Tarifa estándar costura delantera",
        "employee": {
          "id": 5,
          "name": "María López",
          "worker_type": "internal",
          "area": "Maquila"
        },
        "operation_process": {
          "id": 12,
          "name": "Costura delantera",
          "flow_order": 1,
          "process": { "id": 4, "name": "Maquila" }
        },
        "created_by": { "id": 1, "name": "Admin" },
        "created_at": "...",
        "updated_at": "..."
      }
    ]
  }
  ```

#### 57. POST `/piecework-rates`
Registra una nueva tarifa de destajo para un operario.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.manage`.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `employee_id` | Integer | Sí | ID del trabajador. Debe existir en `employees` y estar activo. |
  | `operation_process_id`| Integer| Sí | ID de la suboperación del proceso. Debe existir en `operation_processes`. |
  | `amount_per_piece`| Numeric | Sí | Tarifa monetaria a pagar por pieza terminada. Min: 0.0001, Max: 99,999,999.9999. |
  | `effective_from` | Date | Sí | Fecha de vigencia inicial. |
  | `effective_to` | Date | No | Fecha de vigencia final (opcional). Debe ser >= `effective_from`. |
  | `notes` | String | No | Observaciones. Max: 3000. |

* **Respuesta Exitosa (201 Created)**:
  ```json
  {
    "data": { ... },
    "message": "Tarifa por destajo registrada correctamente."
  }
  ```

#### 58. GET `/piecework-rates/{piecework_rate}`
Muestra los detalles de una tarifa.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.view`.
* **Respuesta Exitosa (200 OK)**:
  Estructura estándar de `PieceworkRateResource`.

#### 59. PUT/PATCH `/piecework-rates/{piecework_rate}`
Actualiza parámetros vigentes de una tarifa (ej. fecha de fin o cambio de estado).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.manage`.
* **Parámetros de Ruta**: `{piecework_rate}` = ID del registro.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `effective_to` | Date | No | Fecha final. Debe ser >= a la fecha de inicio original. |
  | `status` | String | No | Cambiar estado. Valores: `active`, `inactive`. |
  | `notes` | String | No | Observaciones. Max: 3000. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... },
    "message": "Tarifa por destajo actualizada correctamente."
  }
  ```

---

### Configuración de Pagos de Bordado (Embroidery Payment Settings)

Dado que el área de Bordado opera con un esquema de cálculo más complejo (por número de puntadas o por aplicación colocada), este catálogo define las variables de tarifas globales por suboperación de bordado.

#### 60. GET `/embroidery-payment-settings`
Obtiene las configuraciones de pagos de bordado.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `operation_process_id`| Integer| No | ID de la suboperación de bordado. |
  | `status` | String | No | Valores: `active`, `inactive`, `all`. Por defecto: `active`. |
  | `active_on` | Date | No | Fecha de vigencia. |
  | `per_page` | Integer | No | Registros por página. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 1,
        "stitch_price": "0.01500000",
        "application_price": "5.0000",
        "payment_percentage": "0.450000",
        "minimum_payment_per_piece": "1.5000",
        "default_payment_per_piece": "3.5000",
        "effective_from": "2026-01-01",
        "effective_to": null,
        "status": "active",
        "status_label": "Activa",
        "is_current": true,
        "notes": "Parámetros bordado plano estándar",
        "operation_process": {
          "id": 4,
          "name": "Bordado de logo",
          "payroll_calculation_type": "stitches",
          "process": { "id": 2, "name": "Bordado" }
        },
        "created_by": { "id": 1, "name": "Admin" },
        "created_at": "...",
        "updated_at": "..."
      }
    ]
  }
  ```

#### 61. POST `/embroidery-payment-settings`
Crea una nueva regla tarifaria de bordado.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.manage`.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `operation_process_id`| Integer| Sí | ID del subproceso. Debe existir en `operation_processes`. |
  | `stitch_price` | Numeric | Sí | Tarifa por cada puntada. Min: 0.00000001. |
  | `application_price` | Numeric | Sí | Tarifa por aplicación. Min: 0. |
  | `payment_percentage`| Numeric | Sí | Porcentaje de comisión para el operador (0.000001 a 1.000000, ej. 0.45 representa 45%). |
  | `minimum_payment_per_piece`| Numeric| Sí | Pago mínimo garantizado por pieza. Min: 0.0001. |
  | `default_payment_per_piece`| Numeric| Sí | Pago predeterminado por pieza (debe ser mayor o igual a `minimum_payment_per_piece`). |
  | `effective_from` | Date | Sí | Inicio de vigencia (YYYY-MM-DD). |
  | `effective_to` | Date | No | Fin de vigencia. Debe ser >= `effective_from`. |
  | `notes` | String | No | Notas. Max: 3000. |

* **Respuesta Exitosa (201 Created)**:
  ```json
  {
    "data": { ... },
    "message": "Configuración de pago de Bordado registrada correctamente."
  }
  ```

#### 62. GET `/embroidery-payment-settings/{embroidery_payment_setting}`
Muestra los detalles de una regla de bordado.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.view`.
* **Respuesta Exitosa (200 OK)**:
  Estructura estándar de `EmbroideryPaymentSettingResource`.

#### 63. PUT/PATCH `/embroidery-payment-settings/{embroidery_payment_setting}`
Edita la regla (ej. fecha límite o estado).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.manage`.
* **Parámetros de Ruta**: `{embroidery_payment_setting}` = ID de la regla.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `effective_to` | Date | No | Fecha final. Debe ser >= a la de inicio. |
  | `status` | String | No | Valores: `active`, `inactive`. |
  | `notes` | String | No | Max: 3000. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... },
    "message": "Configuración de pago de Bordado actualizada correctamente."
  }
  ```

---

## 8. Módulo de Gestión de Nóminas

### Periodos de Nómina (Payroll Periods)

Controla la creación de los ciclos de corte de nómina (semanal, quincenal, mensual), procesa los avances acumulados por los trabajadores dentro de ese ciclo, calcula las comisiones y cierra el periodo para bloqueo de movimientos.

#### 64. GET `/payroll-periods`
Lista los periodos de nómina creados.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `frequency` | String | No | Frecuencia de corte. Valores: `weekly` (semanal), `biweekly` (quincenal), `monthly` (mensual). |
  | `status` | String | No | Estado del periodo. Valores: `draft` (borrador), `generated` (totales procesados), `closed` (cerrado y pagado), `cancelled` (cancelada), `all`. Por defecto: `all`. |
  | `from` | Date | No | Filtra periodos con fecha de fin >= a la dada. |
  | `to` | Date | No | Filtra periodos con fecha de inicio <= a la dada. |
  | `search` | String | No | Búsqueda por folio de nómina (`code`) o notas. |
  | `per_page` | Integer | No | Registros por página. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 4,
        "code": "NOM-2026-W28",
        "frequency": "weekly",
        "frequency_label": "Semanal",
        "start_date": "2026-07-06",
        "end_date": "2026-07-12",
        "payment_date": "2026-07-13",
        "status": "generated",
        "status_label": "Generada",
        "notes": "Nómina de la semana 28 del año",
        "generated_at": "2026-07-13T08:00:00.000000Z",
        "closed_at": null,
        "created_by": { "id": 1, "name": "Admin" },
        "generated_by": { "id": 1, "name": "Admin" },
        "closed_by": null,
        "employee_summaries_count": 8,
        "created_at": "..."
      }
    ]
  }
  ```

#### 65. POST `/payroll-periods`
Crea un nuevo periodo de nómina en estado `draft` (borrador).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.manage`.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `code` | String | Sí | Identificador/Folio del periodo. Max: 50. Único en `payroll_periods`. |
  | `frequency` | String | Sí | Frecuencia del ciclo. Valores: `weekly`, `biweekly`, `monthly`. |
  | `start_date` | Date | Sí | Fecha de inicio del ciclo (YYYY-MM-DD). |
  | `end_date` | Date | Sí | Fecha de fin del ciclo. Debe ser posterior o igual a `start_date`. |
  | `payment_date` | Date | No | Fecha programada de pago. Debe ser posterior o igual a `end_date`. |
  | `notes` | String | No | Observaciones. Max: 3000. |

* **Respuesta Exitosa (201 Created)**:
  ```json
  {
    "data": { ... },
    "message": "Periodo de nómina creado correctamente."
  }
  ```

#### 66. GET `/payroll-periods/{payroll_period}`
Muestra los detalles completos del periodo de nómina, incluyendo la sábana de pagos calculados para cada empleado (`employee_summaries`) y el desglose individual de sus conceptos (`details`) de destajo o sueldos fijos.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.view`.
* **Parámetros de Ruta**: `{payroll_period}` = ID del periodo de nómina.
* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": {
      "id": 4,
      "code": "NOM-2026-W28",
      "frequency": "weekly",
      "start_date": "2026-07-06",
      "end_date": "2026-07-12",
      "payment_date": "2026-07-13",
      "status": "generated",
      "status_label": "Generada",
      "employee_summaries": [
        {
          "id": 15,
          "payment_type": "piecework",
          "piecework_amount": "2350.00",
          "fixed_amount": "0.00",
          "total_amount": "2350.00",
          "status": "generated",
          "employee": {
            "id": 5,
            "name": "María López",
            "worker_type": "internal",
            "status": "active",
            "area": "Maquila"
          },
          "details": [
            {
              "id": 48,
              "source_type": "operation_log",
              "production_operation_log_id": 14,
              "employee_compensation_id": null,
              "description": "Trabajo en Costura delantera (100 piezas)",
              "quantity": 100,
              "unit_amount": "2.5000",
              "amount": "250.00",
              "occurred_at": "2026-07-15T02:15:00.000000Z",
              "calculation_snapshot": {
                "calculation_type": "piecework_standard",
                "rate_amount": 2.5
              }
            }
          ]
        }
      ]
    }
  }
  ```

#### 67. PUT/PATCH `/payroll-periods/{payroll_period}`
Actualiza las fechas y notas del periodo. Solo se permite en periodos que sigan en estado `draft`.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.manage`.
* **Parámetros de Ruta**: `{payroll_period}` = ID del periodo.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `payment_date` | Date | No | Fecha de pago. Debe ser >= a la fecha de fin del periodo. |
  | `notes` | String | No | Observaciones. Max: 3000. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... },
    "message": "Periodo de nómina actualizado correctamente."
  }
  ```

#### 68. POST `/payroll-periods/{payroll_period}/generate`
Procesa las transacciones de avances de producción y sueldos fijos dentro del periodo y genera los totales y desglose por trabajador. Pasa el estado de `draft` a `generated`.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.generate`.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `notes` | String | No | Notas sobre el procesamiento. Max: 3000. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... "status": "generated" ... },
    "message": "Periodo de nómina generado correctamente."
  }
  ```

#### 69. POST `/payroll-periods/{payroll_period}/close`
Cierra el periodo de nómina. Esto congela los importes, registra el pago de comisiones a los trabajadores, bloquea cualquier modificación y cambia el estado a `closed`.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:payroll.close`.
* **Parámetros del Body (JSON)**:
  | Campo | Tipo | Requerido | Reglas de Validación / Descripción |
  | :--- | :--- | :--- | :--- |
  | `notes` | String | No | Comentarios finales del cierre. Max: 3000. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": { ... "status": "closed" ... },
    "message": "Periodo de nómina cerrado correctamente."
  }
  ```

---

## 9. Módulo de Reportes

Permite obtener resúmenes estadísticos agregados útiles para el análisis del negocio (rendimiento de producción, mermas, incidencias y nóminas).

#### 70. GET `/reports/payroll-periods/{payroll_period}`
Obtiene el resumen consolidado del costo total de nómina del periodo para la administración.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:reports.view`.
* **Parámetros de Ruta**: `{payroll_period}` = ID del periodo de nómina.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `payment_type` | String | No | Filtrar totales por tipo de pago: `piecework`, `fixed`, `mixed`, `all`. Por defecto: `all`. |
  | `employee_id` | Integer | No | Filtrar el reporte a un trabajador específico. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": {
      "period": {
        "id": 4,
        "code": "NOM-2026-W28",
        "frequency_label": "Semanal",
        "start_date": "2026-07-06",
        "end_date": "2026-07-12",
        "status_label": "Generada"
      },
      "totals": {
        "workers_count": 8,
        "piecework_workers_count": 6,
        "fixed_workers_count": 1,
        "mixed_workers_count": 1,
        "piecework_amount": "14500.00",
        "fixed_amount": "1500.00",
        "grand_total": "16000.00",
        "details_count": 48
      },
      "employees": [
        {
          "summary_id": 15,
          "employee": { "id": 5, "name": "María López" },
          "payment_type": "piecework",
          "piecework_amount": "2350.00",
          "fixed_amount": "0.00",
          "total_amount": "2350.00",
          "status": "generated"
        }
      ]
    }
  }
  ```

#### 71. GET `/reports/payroll-employees`
Obtiene el reporte histórico de pagos recibidos por cada trabajador dentro de un lapso de fechas.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:reports.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `from` | Date | Sí | Fecha inicial de búsqueda (YYYY-MM-DD). |
  | `to` | Date | Sí | Fecha final. Debe ser >= `from`. |
  | `employee_id` | Integer | No | ID de un trabajador específico. |
  | `payment_type` | String | No | Valores: `piecework`, `fixed`, `mixed`, `all`. |
  | `status` | String | No | Filtrar resúmenes por estado del pago: `generated`, `paid`, `all`. |
  | `per_page` | Integer | No | Registros por página. |

* **Respuesta Exitosa (200 OK)**:
  Estructura similar al reporte del periodo pero encapsulado bajo una paginación en el nodo de `payments`.

#### 72. GET `/reports/production-cuts`
Reporte de rendimiento y avance de lotes de cortes de prenda.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:reports.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `from` | Date | No | Fecha de creación inicial. |
  | `to` | Date | No | Fecha de creación final. Debe ser >= `from`. |
  | `status` | String | No | Estado del corte: `registered`, `in_progress`, `completed`, `cancelled`, `all`. |
  | `current_area_id` | Integer | No | Filtrar por área actual. |
  | `garment_model_id`| Integer| No | Filtrar por modelo de prenda. |
  | `search` | String | No | Búsqueda por folio de corte. Max: 150. |
  | `per_page` | Integer | No | Registros por página. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "id": 10,
        "status": "in_progress",
        "status_label": "En proceso",
        "total_sizes": 2,
        "total_pieces": 150,
        "effective_pieces": 145, // Excluye mermas confirmadas
        "current_area": { "id": 3, "name": "Bordado" },
        "garment_model": { "id": 3, "code": "CH-09", "name": "Chamarra Deportiva" },
        "production_order": { "id": 1, "code": "OP-2026-001", "status": "in_progress" },
        "movement_summary": {
          "movements_count": 3,
          "dispatched_quantity": 150,
          "received_quantity": 150,
          "completed_quantity": 0,
          "processed_quantity": 100,
          "resolved_loss_quantity": 5, // 5 piezas perdidas/dañadas resueltas
          "open_incidents_count": 1
        },
        "progress": {
          "completed_percentage": 0,
          "processed_percentage": 68.97 // 100/145 piezas
        }
      }
    ]
  }
  ```

#### 73. GET `/reports/production-processes`
Reporte analítico agrupado por Proceso y Suboperaciones. Muestra cuellos de botella e inventario acumulado en cada paso del taller.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:reports.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `from` | Date | No | Fecha inicial de movimientos. |
  | `to` | Date | No | Fecha final. |
  | `process_id` | Integer | No | Filtrar por proceso base. |
  | `operation_process_id`| Integer| No | Filtrar por suboperación. |
  | `target_type` | String | No | Filtrar por tipo de lote: `cut`, `complement`, `special_piece`. |
  | `status` | String | No | Estado del movimiento en el proceso: `pending`, `received`, `in_progress`, `completed`, `with_incident`, `delayed`, `cancelled`, `all`. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "process": { "id": 4, "name": "Maquila", "flow_order": 4 },
        "operation_process": { "id": 12, "name": "Costura delantera", "flow_order": 1, "payroll_calculation_type": "standard" },
        "stats": {
          "movements_count": 5,
          "dispatched_quantity": 500,
          "received_quantity": 480,
          "in_progress_quantity": 280,
          "completed_quantity": 200,
          "processed_quantity": 200,
          "resolved_loss_quantity": 0,
          "open_incidents_count": 0
        }
      }
    ]
  }
  ```

#### 74. GET `/reports/production-movements`
Reporte plano detallado de la trazabilidad y avance de transferencias de material.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:reports.view`.
* **Parámetros Query (URL)**:
  Mismos filtros que `/reports/production-processes` más filtros por áreas física origen/destino (`from_area_id`, `to_area_id`) y corte (`garment_cut_id`).
* **Respuesta Exitosa (200 OK)**:
  Listado paginado de transferencias y sus avances de trabajo porcentuales.

#### 75. GET `/reports/production-incidents`
Reporte detallado de incidencias y su estatus de resolución.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:reports.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `from` | Date | No | Fecha inicio del incidente. |
  | `to` | Date | No | Fecha fin. |
  | `incident_type` | String | No | Tipo: `damage`, `loss`, `quality`, `delay`, `other`, `all`. |
  | `status` | String | No | Estado: `open`, `resolved`, `cancelled`, `all`. |
  | `garment_cut_id` | Integer | No | ID de corte afectado. |
  | `responsible_employee_id`| Integer| No | ID de trabajador implicado. |

* **Respuesta Exitosa (200 OK)**:
  Similar a `GET /production-incidents` pero formateado para resúmenes estadísticos.

#### 76. GET `/reports/production-losses`
Reporte enfocado exclusivamente en mermas y pérdidas físicas de prendas, agrupado por Corte, Proceso o Trabajador responsable.
* **Middleware**: `auth:sanctum`, `active.user`, `permission:reports.view`.
* **Parámetros Query (URL)**:
  | Campo | Tipo | Requerido | Descripción / Validación |
  | :--- | :--- | :--- | :--- |
  | `from` | Date | No | Fecha de inicio. |
  | `to` | Date | No | Fecha fin. |
  | `status` | String | No | Estado del incidente: `open`, `resolved`, `cancelled`, `all`. |
  | `group_by` | String | No | Nivel de agrupación: `garment_cut` (por corte), `process` (por proceso), `responsible_employee` (por empleado). |
  | `garment_cut_id` | Integer | No | Filtrar a un corte. |
  | `process_id` | Integer | No | Filtrar a un proceso. |
  | `responsible_employee_id`| Integer| No | Filtrar a un trabajador. |

* **Respuesta Exitosa (200 OK)**:
  ```json
  {
    "data": [
      {
        "group": {
          "type": "garment_cut",
          "id": 10,
          "name": "Corte inicial chamarras",
          "code": "C-CH-09-01"
        },
        "stats": {
          "incidents_count": 2,
          "open_incidents_count": 1,
          "resolved_incidents_count": 1,
          "cancelled_incidents_count": 0,
          "affected_quantity": 10,
          "resolved_loss_quantity": 5
        }
      }
    ]
  }
  ```

#### 77. GET `/reports/production-reworks`
Reporte enfocado en re-procesamientos de costura/bordado originados por fallos de calidad (ayuda a medir la tasa de retrabajo).
* **Middleware**: `auth:sanctum`, `active.user`, `permission:reports.view`.
* **Parámetros Query (URL)**:
  Mismos filtros que el reporte de pérdidas (sin agrupación).
* **Respuesta Exitosa (200 OK)**:
  Retorna un listado de los incidentes que requirieron reproceso, enlazando el movimiento de envío original y el nuevo movimiento de reproceso generado para evaluar tiempos y cantidades.

---

## 10. Módulo de Exportaciones (CSV)

Todos estos endpoints consumen los mismos filtros que sus respectivos reportes en la sección anterior, pero en lugar de estructurar un JSON, retornan una respuesta de descarga de flujo de datos (`StreamedResponse`) de un archivo separado por comas **.csv** con codificación UTF-8.

- **Middleware Común**: `auth:sanctum`, `active.user`, `permission:reports.export`.

#### 78. GET `/reports/payroll-periods/{payroll_period}/export`
Exporta la sábana de nómina completa del periodo detallada por fila de concepto de pago.
* **Permisos**: `reports.export`, `payroll.view`.
* **Campos exportados en CSV**:
  `Periodo, Frecuencia, Fecha inicio, Fecha fin, Fecha pago, Estado periodo, ID trabajador, Trabajador, Área, Tipo trabajador, Tipo pago, Estado resumen, Total destajo, Total fijo, Total trabajador, Origen detalle, Descripción detalle, Cantidad, Importe unitario, Importe detalle, Fecha detalle`

#### 79. GET `/reports/payroll-employees/export`
Exporta el histórico acumulado de pagos a empleados en el lapso definido.
* **Permisos**: `reports.export`, `payroll.view`.
* **Campos exportados**:
  `ID trabajador, Trabajador, Área, Tipo trabajador, Periodo, Frecuencia, Fecha inicio, Fecha fin, Fecha pago, Estado periodo, Tipo pago, Estado resumen, Total destajo, Total fijo, Total trabajador, Cantidad de detalles`

#### 80. GET `/reports/production-cuts/export`
Exporta el reporte de avance de los lotes de corte.
* **Campos exportados**:
  `ID corte, Modelo, Nombre modelo, Orden producción, Estado corte, Área actual, Total tallas, Piezas por talla, Piezas planeadas, Pérdidas resueltas, Piezas efectivas, Movimientos, Cantidad enviada, Cantidad recibida, Cantidad completada, Cantidad procesada, Incidencias abiertas, Avance completado %, Avance procesado %, Fecha creación`

#### 81. GET `/reports/production-processes/export`
Exporta el reporte de inventario acumulado por proceso y suboperación.
* **Campos exportados**:
  `ID proceso, Proceso, Orden flujo proceso, ID operación, Operación, Orden flujo operación, Tipo cálculo nómina, Movimientos, Cantidad enviada, Cantidad recibida, Cantidad en proceso, Cantidad completada, Cantidad procesada, Pérdidas resueltas, Incidencias abiertas`

#### 82. GET `/reports/production-movements/export`
Exporta el listado detallado de transferencias y rendimiento de lotes.
* **Campos exportados**:
  `ID movimiento, ID corte, Modelo, Nombre modelo, Tipo objetivo, Proceso, Operación, Área origen, Área destino, Estado movimiento, Cantidad original, Pérdidas resueltas, Cantidad efectiva, Trabajadores, Cantidad procesada, Incidencias abiertas, Avance %, Fecha inicio, Fecha creación`

#### 83. GET `/reports/production-incidents/export`
Exporta la lista histórica de incidencias del taller.
* **Campos exportados**:
  `ID incidencia, Tipo incidencia, Estado, Cantidad afectada, Descripción, Notas resolución, ID corte, Modelo, Nombre modelo, ID movimiento, Tipo objetivo, Proceso, Operación, Área origen, Área destino, Trabajador responsable, Área trabajador, Resuelto por, Tiene reproceso, Fecha resolución, Fecha creación`

#### 84. GET `/reports/production-losses/export`
Exporta el reporte de mermas y piezas perdidas según el nivel de agrupación solicitado.
* **Campos exportados**:
  `Tipo agrupación, ID grupo, Nombre grupo, Código grupo, Incidencias totales, Incidencias abiertas, Incidencias resueltas, Incidencias canceladas, Cantidad afectada, Pérdida resuelta`

#### 85. GET `/reports/production-reworks/export`
Exporta el listado y detalles de devoluciones para reproceso.
* **Campos exportados**:
  `ID incidencia, Tipo incidencia, Estado incidencia, Cantidad afectada, Descripción, ID corte, Modelo, Nombre modelo, ID movimiento origen, Estado movimiento origen, Proceso origen, Área origen inicial, Área origen destino, ID movimiento reproceso, Estado reproceso, Proceso reproceso, Operación reproceso, Área reproceso origen, Área reproceso destino, Trabajador responsable, Fecha creación, Fecha resolución`
