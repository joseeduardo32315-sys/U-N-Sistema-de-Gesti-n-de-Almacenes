# Reporte de Auditoría de Alineación: IEEE 830 vs. Implementación Frontend

Este análisis evalúa el estado del frontend (**uyn-frontend**) frente a los requerimientos especificados en el documento **IEEE 830** y el **Planteamiento del Problema de U&N Moda Infantil**. Su objetivo es identificar brechas de desarrollo, sobre-ingeniería o inconsistencias y trazar un plan de acción correctivo.

---

## 🔍 Análisis Comparativo de Brechas (Gaps)

A continuación se detallan las áreas donde el frontend actual se desvía o está incompleto respecto a la especificación técnica y la problemática de la empresa.

### 1. Dashboard de Control Operativo (`RF-24`, `RF-30`)
* **Requerimiento en IEEE 830:** El sistema debe contar con un panel principal (Dashboard) que muestre indicadores visuales en tiempo real para la supervisión de la planta: cortes activos, cortes completados, lotes retrasados, piezas pendientes por procesar, incidencias abiertas y productividad por área.
* **Implementación Actual en Frontend:** La vista [DashboardView.vue](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/views/DashboardView.vue) es una pantalla de bienvenida estática. Solo muestra el nombre del usuario, su rol y un botón de cierre de sesión. Carece de llamadas a la API, tarjetas de indicadores y gráficos de avance.
* **Impacto en la Problemática:** No se resuelve la falta de visualización del avance físico y el monitoreo en tiempo real, manteniendo la ceguera operativa sobre los cuellos de botella que provocan pérdidas de tiempo.

### 2. Módulo de Reportes y Exportación (`RF-17`, `RF-18`, `RF-19`, `RF-25`)
* **Requerimiento en IEEE 830:** Contar con reportes detallados y consolidados de producción (por cortes), productividad (por trabajador/maquilero) y resúmenes económicos de apoyo al destajo. Deberá permitir filtrar por fechas y exportar en formatos PDF y Excel (CSV).
* **Implementación Actual en Frontend:** La carpeta `src/modules/reports` se encuentra vacía. La ruta `/reportes` en el enrutador [src/router/index.ts](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/router/index.ts) apunta al componente genérico de construcción [ModulePlaceholderView.vue](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/views/ModulePlaceholderView.vue). No hay pantallas de reportes desarrolladas.
* **Impacto en la Problemática:** El backend ya tiene desarrolladas y expuestas todas las APIs de reportes y exportación (endpoints `/reports/*` y `/reports/*/export`). Al no tener frontend para esto, la empresa sigue sin poder calcular eficientemente la nómina de destajo o evaluar la productividad.

### 3. Alertas de Retrasos en Tiempo Real (`RF-16`)
* **Requerimiento en IEEE 830:** El sistema debe marcar visualmente e identificar los cortes o etapas que hayan excedido su fecha estimada de entrega, alertando directamente al encargado de producción.
* **Implementación Actual en Frontend:** Aunque existe el estado de corte `delayed` (Retrasado), el sistema no cuenta con un sistema de banners, centro de notificaciones ni sección en la barra superior ([AppTopbar.vue](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/components/navigation/AppTopbar.vue)) para alertar activamente de los cortes vencidos.

### 4. Inconsistencia del Modelo de Datos: Telas y Rollos
* **Definición en IEEE 830 (Apéndice B - Modelo Preliminar):** Se mencionan entidades como `catálogo de telas`, `rollos`, `materiales utilizados` y `catálogo de rollos` dentro del modelo entidad-relación.
* **Mapeo con la API y Frontend:** Ni la API de Laravel ni el frontend manejan inventario de rollos de tela o consumo de metros. El control de producción inicia directamente en la creación del corte (`GarmentCut` y tallas).
* **Análisis de Desviación:** En la sección de *Limitaciones* de ambos documentos, se excluye el "control de inventarios avanzados de almacén y logística externa". Por lo tanto, la inclusión de telas y rollos en el Apéndice B del IEEE 830 es una **inconsistencia interna del documento de requerimientos**. El sistema real inicia en la fase física del corte ya realizado.

---

## 📋 Checklist de Alineación y Desarrollo

Para poner al corriente el frontend con lo especificado en la documentación IEEE 830 y resolver la problemática, se debe ejecutar la siguiente ruta de cambios:

### Módulo: Dashboard de Supervisión
- [ ] **Crear componentes visuales en Dashboard:**
  - Diseñar tarjetas métricas (KPIs) usando los estilos y tokens definidos en [tokens.css](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/assets/styles/tokens.css).
  - Incluir: Cortes Activos, Retrasados, Incidencias Abiertas, y Piezas Pendientes.
- [ ] **Integrar API en el Dashboard:**
  - Crear un servicio para recuperar estadísticas consolidadas.
  - Consumir el endpoint de Laravel `/reports/production-processes` y `/reports/production-losses` para pintar gráficos sencillos o tablas resumen de avance por área (Corte ➡️ Diseño ➡️ Bordado ➡️ Maquila).
- [ ] **Accesos Rápidos:**
  - Agregar botones directos para despachar movimientos o reportar incidencias.

### Módulo: Reportes y Exportación
- [ ] **Implementar Servicios de Reportes:**
  - Crear `src/modules/reports/services/reports.service.ts` para mapear las llamadas a los endpoints de la API (por ejemplo, `/reports/production-cuts`, `/reports/production-processes`, `/reports/payroll-periods/{id}`).
- [ ] **Diseñar Pantalla de Reportes (`ReportsView.vue`):**
  - Implementar filtros por rango de fechas (Desde/Hasta), Modelo, Proceso y Trabajador.
  - Crear tabla de resultados paginada usando la estructura estándar `PaginatedResponse<T>` de [src/types/api.ts](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/types/api.ts).
- [ ] **Integrar Descarga y Exportación:**
  - Crear el disparador de exportación para PDF y Excel.
  - Consumir las rutas de descarga (`/reports/*/export`) y utilizar el servicio de descarga existente en [src/services/download.service.ts](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/services/download.service.ts) para procesar el flujo binario (Blob).

### Módulo: Control de Alertas e Interfaz
- [ ] **Alertas Visuales de Vencimiento:**
  - Agregar en la cabecera ([AppTopbar.vue](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/components/navigation/AppTopbar.vue)) o en una sección destacada del Dashboard una lista de folios de cortes en estado `delayed` (fecha estimada superada) para que el Encargado de Producción los atienda de inmediato.

### Saneamiento de Documentación
- [ ] **Ajustar Apéndice B (IEEE 830):**
  - Modificar el Apéndice B del documento `IEEE830_UyN_Moda_Infantil(1).docx` para eliminar las referencias de las tablas de `catálogo de telas` y `rollos` que quedaron fuera de alcance en el análisis final, alineándolo con los endpoints reales de la API en Laravel.

---

> [!IMPORTANT]
> **Estado de Preparación Técnica:**
> El frontend ya cuenta con toda la infraestructura técnica base para realizar estos cambios: Axios configurado, enrutador protegido por permisos, estilos de interfaz premium listos, y constantes de permisos definidas. La base es sólida, por lo que el esfuerzo se centrará únicamente en construir las vistas y servicios faltantes.
