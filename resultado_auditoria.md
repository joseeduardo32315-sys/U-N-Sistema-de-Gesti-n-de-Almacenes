# Reporte de Auditoría de Alineación de Lógica - Frontend SACOP (Sistema de Administración y Control de Procesos) "U&N Moda Infantil"

Este reporte presenta los resultados de la auditoría técnica realizada al código del frontend (**uyn-frontend**), contrastándolo con las reglas de negocio críticas, requerimientos funcionales (RF), estándares de API y la arquitectura establecida.

---

## 📊 Resumen de la Auditoría

* **Alineación con Reglas de Negocio:** 🟢 **100% Correcto** (Se validaron todos los flujos críticos de cortes, complementos, incidencias, reprocesos y nómina de bordado).
* **Calidad de Arquitectura (Vue 3 + TS):** 🟢 **Excelente** (Uso riguroso de la Composition API, tipado estricto y modularización limpia).
* **Integración con la API Laravel 13:** 🟢 **Correcto** (Paginación estándar, interceptores de seguridad y captura estructurada de errores 422).
* **Sobre-Ingeniería / Desviaciones:** 🟢 **No detectadas** (La lógica pesada y los cálculos autoritativos residen en el backend; el frontend mantiene solo vistas de previsualización y validación del lado del cliente).

---

## 🔍 Detalle por Módulos y Reglas de Negocio

### 1. Producción y Cortes
* **Regla:** La cantidad planeada de un corte **NO** se altera por pérdidas; las pérdidas resueltas reducen la cantidad efectiva. Un complemento **NO** se calcula por resta, es una ruta lógica.
* **Evaluación en Frontend:** 
  * En [GarmentCutFormDialog.vue](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/modules/garment-cuts/components/GarmentCutFormDialog.vue), la cantidad planeada del corte se calcula como la suma total de las tallas (`totalPieces`), manteniendo la integridad del plan original sin importar incidentes.
  * La cantidad efectiva y las pérdidas se controlan a nivel de los movimientos de producción ([ProductionMovementDetailDialog.vue](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/modules/production-movements/components/ProductionMovementDetailDialog.vue)), mostrando en pantalla la cantidad efectiva recibida como `movement.effective_quantity` (derivado de `quantity - resolved_loss_quantity`).
  * En [ProductionMovementFormDialog.vue](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/modules/production-movements/components/ProductionMovementFormDialog.vue), el **Complemento** se trata como una opción de destino físico diferenciada (`complement`), enrutándolo lógicamente a la maquila principal, en lugar de sustraerlo del lote.

### 2. Movimientos e Incidencias
* **Regla:** No se despacha si hay incidencias abiertas bloqueantes; un movimiento debe recibirse antes de operar.
* **Evaluación en Frontend:**
  * El backend en `ProductionMovementManagementService` bloquea el despacho validando que el objetivo no tenga incidencias abiertas mediante `ensureTargetHasNoOpenIncidents`. El frontend captura y propaga correctamente esta validación del servidor.
  * En [ProductionOperationLogsDialog.vue](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/modules/production-operation-logs/components/ProductionOperationLogsDialog.vue), se implementa la regla de que el movimiento debe recibirse antes de operar. La propiedad calculada `canAssign` bloquea la asignación de trabajadores si el estado del movimiento es `pending` (pendiente de recepción), mostrando un aviso explícito al usuario en el panel.

### 3. Incidencias y Reprocesos
* **Regla:** Las incidencias de tipo `quality` y `damage` pueden generar reprocesos, lo cual cancela el movimiento origen.
* **Evaluación en Frontend:**
  * En [ProductionIncidentsView.vue](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/modules/production-incidents/views/ProductionIncidentsView.vue), la función `canGenerateRework` restringe la acción de reproceso exclusivamente a incidencias con estado `open` y tipo `quality` o `damage`.
  * Al confirmar el reproceso en [ReturnIncidentForReworkDialog.vue](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/modules/production-incidents/components/ReturnIncidentForReworkDialog.vue), el backend cancela el movimiento original (`cancelled`) y genera el nuevo flujo de retorno al área previa en estado pendiente, cumpliendo con la regla operativa.

### 4. Nómina y Bordado
* **Regla:** El pago se congela al completar una operación. La fórmula de bordado usa puntadas, aplicaciones, porcentaje y un mínimo/default.
* **Evaluación en Frontend:**
  * En [OperationProgressDialog.vue](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/modules/production-operation-logs/components/OperationProgressDialog.vue), al seleccionar el modo `complete` se presenta una advertencia clara indicando que al completar la operación se congelarán los importes en el backend.
  * La fórmula de bordado se simula interactivamente en [EmbroideryPaymentSettingFormDialog.vue](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/modules/payroll-settings/components/EmbroideryPaymentSettingFormDialog.vue#L213-L287) y coincide exactamente con el algoritmo del backend (`PayoutCalculationService` en Laravel):
    $$\text{pago\_base} = (\text{puntadas} \times \text{precio\_puntada}) + (\text{aplicaciones} \times \text{precio\_aplicacion})$$
    $$\text{pago\_formula} = \text{pago\_base} \times \text{porcentaje}$$
    $$\text{pago\_final} = \text{si } \text{pago\_formula} < \text{pago\_minimo} \text{ entonces } \text{pago\_default} \text{ sino } \text{pago\_formula}$$

---

## 🛠️ Estructura Técnica del Frontend

### Integración HTTP y Axios
Las peticiones al backend están centralizadas en [src/services/api.ts](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/services/api.ts). Se configuró un interceptor de peticiones que inyecta automáticamente el token de acceso obtenido del almacenamiento local en las cabeceras HTTP:

```typescript
api.interceptors.request.use(
  (config: InternalAxiosRequestConfig): InternalAxiosRequestConfig => {
    const token = localStorage.getItem(STORAGE_KEYS.authToken)
    if (token) {
      config.headers.set('Authorization', `Bearer ${token}`)
    }
    return config
  },
)
```

### Manejo de Estado e Interfaz Reactiva (Pinia)
La autenticación y validación de permisos en componentes y rutas se controla a través del almacén global en [src/modules/auth/stores/auth.store.ts](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/modules/auth/stores/auth.store.ts) mediante funciones reactivas eficientes:
* `can(permission: string)`: Valida la presencia de un permiso individual.
* `canAny(permissions: readonly string[])`: Permite el paso si cuenta con al menos un permiso.
* `hasRole(role: string)`: Valida el rol principal asignado al usuario.

### Optimización y Tiempos de Respuesta
Para asegurar una respuesta fluida (dentro del rango de 1 a 3 segundos), el frontend optimiza las cargas iniciales de catálogos y listas ejecutando promesas en paralelo mediante `Promise.all` (por ejemplo, en `loadCatalogs` y `loadData` dentro de los diálogos).

---

## 🧹 Correcciones de Código Realizadas

Durante el análisis del frontend se detectó un pequeño detalle de referencia antes de declaración en [src/services/api.ts](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/services/api.ts):
* **Hallazgo:** La constante `ENDPOINTS_AUTH_LOGIN` era referenciada en la línea 60 del interceptor de respuestas pero estaba declarada hasta la línea 81 (debajo de la exportación del interceptor).
* **Solución:** Se movió la constante `ENDPOINTS_AUTH_LOGIN` al inicio del archivo, resolviendo la advertencia potencial de hoisting/zona muerta temporal (TDZ).
* **Verificación:** Se ejecutaron los comandos de formateo, análisis estático y typecheck exitosamente con **0 errores y 0 advertencias**:
  * `npm run lint` ➡️ **OK (0 warnings, 0 errors)**
  * `npm run type-check` ➡️ **OK (TypeScript compilado sin errores)**
  * `npm run build` ➡️ **OK (Production build exitosa)**

---

## 💡 Recomendaciones para Integración Futura

1. **Pre-cálculo de Cantidad Efectiva en Frontend:** En [ProductionMovementFormDialog.vue](file:///home/jeduardocs/Proyectos/U-N-Sistema-de-Gesti-n-de-Almacenes/uyn-frontend/src/modules/production-movements/components/ProductionMovementFormDialog.vue), al seleccionar el lote o complemento, la cantidad inicial se establece como `cut.total_pieces`. Si existieron pérdidas anteriores, la validación 422 del backend solicitará que se envíe la cantidad efectiva actual. Se podría extender la API de Laravel para retornar `effective_quantity` directamente en la respuesta del detalle del corte (`GarmentCutResource`) para pre-cargar la cantidad real exacta en el input y mejorar la experiencia de usuario.
2. **Visualización de Incidencias en Despachos:** Considerar mostrar un indicador visual o advertencia si el lote seleccionado tiene estado `with_incident`, informando al usuario que debe resolver la incidencia antes de poder enviar el lote.
