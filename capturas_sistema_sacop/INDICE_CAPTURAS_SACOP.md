# Índice de Evidencias Visuales y Pantallas del Sistema
## Sistema de Administración y Control de Procesos (SACOP)
**Organización:** U&N Moda Infantil  
**Resolución de capturas:** Retina HD (1440x900 @ 2x DPI, Formato PNG)  
**Ubicación:** `/capturas_sistema_sacop/`

---

## 1. Vistas Principales del Sistema

| Figura / Archivo | Módulo | Nombre de la Pantalla | Descripción Académica |
| :--- | :--- | :--- | :--- |
| `01_login.png` | Autenticación | Inicio de Sesión (Login) | Pantalla de ingreso y autenticación segura al sistema con credenciales de usuario y contraseña bajo protocolo JWT/Sanctum. |
| `02_error_403_acceso_restringido.png` | Seguridad | Error 403 - Acceso Restringido | Vista de control de seguridad cuando un usuario autenticado intenta acceder a una ruta o recurso sin los permisos requeridos. |
| `03_error_404_no_encontrado.png` | Navegación | Error 404 - Ruta no Encontrada | Pantalla amigable de error ante solicitudes a rutas inexistentes dentro de la SPA (Single Page Application). |
| `04_dashboard_principal.png` | Supervisión | Panel de Control de Producción | Dashboard ejecutivo y operativo con métricas en tiempo real: cortes activos, órdenes, incidencias, volumen planificado, rendimiento y transferencias recientes. |
| `05_usuarios_lista.png` | Administración | Gestión de Usuarios | Listado y administración de cuentas de usuario del sistema, filtrado por estado y asignación de roles operativos. |
| `06_roles_y_permisos.png` | Administración | Roles y Matriz de Permisos | Matriz de control de acceso basada en roles (RBAC) con desglose de los 40 permisos distribuidos en 13 módulos del sistema. |
| `07_empleados_lista.png` | Catálogos | Catálogo de Empleados | Gestión de operarios y trabajadores de taller, especialidades, esquemas de contratación y estado operativo. |
| `08_modelos_prenda_lista.png` | Catálogos | Catálogo de Modelos de Prenda | Catálogo maestro de estilos, prendas infantiles, especificaciones técnicas y trazabilidad de modelos fabricados. |
| `09_ordenes_produccion_lista.png` | Producción | Órdenes de Producción | Vista de planificación de órdenes de trabajo, seguimiento de folios, fechas de inicio y finalización programadas. |
| `10_cortes_produccion_lista.png` | Producción | Cortes de Producción (Lotes) | Vista de control de lotes de corte, desglose de piezas totales, complementos, piezas derivadas y estado en taller. |
| `11_movimientos_taller_lista.png` | Producción | Movimientos y Avances de Taller | Vista de transferencias interdepartamentales (Corte, Diseño, Bordado, Maquila, Preparación, Terminado) con filtrado por áreas. |
| `12_incidencias_lista.png` | Calidad | Control de Incidencias | Vista de control de eventos adversos (mermas, daños de calidad, retrasos y reprocesos) reportados en piso de producción. |
| `13_nomina_compensaciones.png` | Nómina | Esquemas de Compensación | Configuración de tipos de remuneración por trabajador (destajista, sueldo fijo o mixto) y vigencia temporal. |
| `14_nomina_tarifas_destajo.png` | Nómina | Tarifas de Destajo por Operación | Tabulador de precios unitarios asignados a cada suboperación de confección y maquila. |
| `15_nomina_formula_bordado.png` | Nómina | Configuración y Fórmula de Bordado | Parámetros matemáticos del cálculo de bordado: precio por puntada, precio por aplicación, porcentaje de operario y tarifa mínima. |
| `16_nomina_periodos_pago.png` | Nómina | Periodos de Nómina | Vista de apertura, cálculo, cierre y dispersión de periodos de nómina semanales o quincenales. |
| `17_reportes_rendimiento_cortes.png` | Analítica | Reporte de Rendimiento de Cortes | Análisis comparativo de piezas planificadas vs. piezas reales producidas por lote, calculando mermas y porcentaje de eficiencia. |
| `18_reportes_eficiencia_procesos.png` | Analítica | Reporte de Eficiencia de Procesos | Estadísticas de throughput, tiempo de ciclo y productividad por área productiva y suboperación. |
| `19_reportes_mermas_perdidas.png` | Analítica | Reporte de Mermas y Pérdidas | Desglose cuantitativo de mermas clasificadas por causa raíz, operario responsable y área de ocurrencia. |
| `20_reportes_control_reprocesos.png` | Analítica | Reporte de Control de Reprocesos | Trazabilidad de lotes retornados para retrabajo, costo asociado y horas hombre invertidas. |
| `21_bitacora_operaciones.png` | Auditoría | Bitácora de Auditoría del Sistema | Registro inmutable de eventos, modificaciones y acciones críticas realizadas por los usuarios en la plataforma. |

---

## 2. Componentes Relacionados y Diálogos Modales

| Figura / Archivo | Módulo | Componente Modal | Descripción Académica |
| :--- | :--- | :--- | :--- |
| `22_modal_usuario_nuevo.png` | Usuarios | `UserFormDialog` | Formulario modal para el registro y alta de nuevos usuarios con validación reactiva de campos y asignación de rol. |
| `23_modal_empleado_nuevo.png` | Empleados | `EmployeeFormDialog` | Formulario modal para registrar empleados, datos personales, número de seguro/identificación y área de asignación. |
| `24_modal_modelo_prenda_nuevo.png` | Modelos | `GarmentModelFormDialog` | Formulario modal para registrar nuevos modelos de prendas infantiles y sus especificaciones de diseño. |
| `25_modal_orden_produccion_nueva.png` | Órdenes | `ProductionOrderFormDialog` | Formulario modal para apertura de órdenes de producción con definición de folios, prioridad y fechas. |
| `26_modal_orden_produccion_detalle.png` | Órdenes | `ProductionOrderDetailDialog` | Vista detallada en modal de una orden de producción, mostrando cortes asociados, estatus global y avance. |
| `27_modal_corte_produccion_nuevo.png` | Cortes | `GarmentCutFormDialog` | Formulario modal para registro de corte, asignación de orden, cantidad de piezas, largo de tela y notas de tendido. |
| `28_modal_corte_produccion_detalle.png` | Cortes | `GarmentCutDetailDialog` | Modal de auditoría del corte: piezas totales, trazabilidad de piezas derivadas y balance de mermas. |
| `29_modal_corte_clasificacion.png` | Cortes | `GarmentCutClassificationDialog` | Modal de configuración de la ruta productiva: bifurcación entre complemento del corte y piezas derivadas con proceso especial. |
| `30_modal_movimiento_despachar.png` | Movimientos | `ProductionMovementFormDialog` | Formulario modal para despachar y transferir lotes de corte o piezas especiales entre áreas de la fábrica. |
| `31_modal_movimiento_detalle.png` | Movimientos | `ProductionMovementDetailDialog` | Modal informativo del movimiento con cronometría de transferencias, emisor, receptor y estado de recepción. |
| `32_modal_operaciones_asignacion.png` | Movimientos | `ProductionOperationLogsDialog` | Modal interactivo para asignar trabajadores a la operación, supervisar el avance individual y calcular pagos por destajo. |
| `33_modal_operaciones_avance.png` | Movimientos | `OperationProgressDialog` | Modal para el registro de avance cuantitativo de piezas, cálculo paramétrico de bordado (puntadas y aplicaciones) y notas. |
| `34_modal_incidencia_reportar.png` | Incidencias | `ProductionIncidentFormDialog` | Formulario modal para documentar incidencias operativas, tipo de defecto, cantidad de piezas afectadas y evidencias. |
| `35_modal_incidencia_detalle.png` | Incidencias | `ProductionIncidentDetailDialog` | Modal con el expediente completo de la incidencia, impacto en piezas e historial de cambios. |
| `36_modal_incidencia_resolver.png` | Incidencias | `ResolveProductionIncidentDialog` | Formulario modal para determinar la resolución de la incidencia (aprobación de merma, ajuste de inventario o cierre). |
| `37_modal_incidencia_reproceso.png` | Incidencias | `ReturnIncidentForReworkDialog` | Formulario modal para generar una orden de movimiento inverso (retorno a taller) para corrección y reproceso. |
| `38_modal_nomina_esquema_nuevo.png` | Nómina | `EmployeeCompensationFormDialog` | Formulario modal para establecer o actualizar el esquema de pago de un operario y su vigencia contractual. |
| `39_modal_nomina_tarifa_destajo_nueva.png` | Nómina | `PieceworkRateFormDialog` | Formulario modal para registrar tarifas unitarias aplicadas a suboperaciones de destajo. |
| `40_modal_nomina_tarifa_bordado_nueva.png` | Nómina | `EmbroideryPaymentSettingFormDialog` | Formulario modal de fórmula de bordado con simulador interactivo de cálculo en tiempo real. |
| `41_modal_nomina_periodo_nuevo.png` | Nómina | `CreatePayrollPeriodDialog` | Formulario modal para apertura de un nuevo periodo de corte de nómina semanal o quincenal. |
| `42_modal_nomina_periodo_detalle.png` | Nómina | `PayrollPeriodDetailDialog` | Modal de desglose y sábana de pagos calculados para cada empleado del periodo con opción de cierre y exportación CSV. |
| `43_modal_bitacora_detalle.png` | Auditoría | `OperationLogDetailDialog` | Modal de auditoría granular con inspección de payloads JSON de cambios antes y después de cada transacción. |

---

### Instrucciones para Inclusión en Documentos Académicos
1. Las imágenes están organizadas y nombradas de forma numérica secuencial (`01_` a `43_`) facilitando su referencia cruzada como **Figura 1**, **Figura 2**, etc.
2. Cada archivo posee una densidad de píxeles @ 2x que asegura una óptima nitidez tanto en formatos digitales (PDF) como en impresión.
3. Se han retirado elementos ajenos al entorno de producción (badges de depuración o barras duplicadas), garantizando un estándar estético formal y profesional.
