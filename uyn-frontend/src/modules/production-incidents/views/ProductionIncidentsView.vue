<script setup lang="ts">
import {
  onMounted,
  reactive,
  ref,
} from 'vue'

import {
  AlertTriangle,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Eye,
  Pencil,
  Plus,
  RotateCcw,
  Search,
  Undo2,
  UserRound,
} from 'lucide-vue-next'

import Swal from 'sweetalert2'

import { PERMISSIONS } from '@/config/permissions'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { employeesService } from '@/modules/employees/services/employees.service'
import { garmentCutsService } from '@/modules/garment-cuts/services/garment-cuts.service'
import ProductionIncidentDetailDialog from '@/modules/production-incidents/components/ProductionIncidentDetailDialog.vue'
import ProductionIncidentFormDialog from '@/modules/production-incidents/components/ProductionIncidentFormDialog.vue'
import ResolveProductionIncidentDialog from '@/modules/production-incidents/components/ResolveProductionIncidentDialog.vue'
import { productionIncidentsService } from '@/modules/production-incidents/services/production-incidents.service'
import { productionMovementsService } from '@/modules/production-movements/services/production-movements.service'
import { getApiErrorMessage } from '@/utils/api-error'
import ReturnIncidentForReworkDialog from '@/modules/production-incidents/components/ReturnIncidentForReworkDialog.vue'

import type { Employee } from '@/modules/employees/types/employee.types'
import type { GarmentCut } from '@/modules/garment-cuts/types/garment-cut.types'
import type { ProductionMovement } from '@/modules/production-movements/types/production-movement.types'

import type {
  ProductionIncident,
  ProductionIncidentStatus,
  ProductionIncidentType,
} from '@/modules/production-incidents/types/production-incident.types'

import type { PaginationMeta } from '@/types/api'

interface Filters {
  incidentType: ProductionIncidentType | ''
  status: ProductionIncidentStatus | ''
  garmentCutId: number | ''
  productionMovementId: number | ''
  responsibleEmployeeId: number | ''
  from: string
  to: string
  perPage: number
}

const authStore = useAuthStore()

const incidents = ref<ProductionIncident[]>([])
const cuts = ref<GarmentCut[]>([])
const movements = ref<ProductionMovement[]>([])
const employees = ref<Employee[]>([])

const loading = ref(false)
const catalogsLoading = ref(false)
const detailLoadingId = ref<number | null>(null)

const formOpen = ref(false)
const detailOpen = ref(false)
const resolveOpen = ref(false)
const reworkOpen = ref(false)

const selectedIncident =
  ref<ProductionIncident | null>(null)

const detailIncident =
  ref<ProductionIncident | null>(null)

const resolveIncident =
  ref<ProductionIncident | null>(null)

const reworkIncident =
  ref<ProductionIncident | null>(null)

const filters = reactive<Filters>({
  incidentType: '',
  status: '',
  garmentCutId: '',
  productionMovementId: '',
  responsibleEmployeeId: '',
  from: '',
  to: '',
  perPage: 15,
})

const pagination = ref<PaginationMeta>({
  current_page: 1,
  from: null,
  last_page: 1,
  per_page: 15,
  to: null,
  total: 0,
})

function formatDateTime(value: string): string {
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

function incidentTypeClass(
  type: ProductionIncidentType,
): string {
  const classes: Record<
    ProductionIncidentType,
    string
  > = {
    damage: 'incident-type--damage',
    loss: 'incident-type--loss',
    quality: 'incident-type--quality',
    delay: 'incident-type--delay',
    other: 'incident-type--other',
  }

  return classes[type]
}

function statusClass(
  status: ProductionIncidentStatus,
): string {
  const classes: Record<
    ProductionIncidentStatus,
    string
  > = {
    open: 'incident-status--open',
    resolved: 'incident-status--resolved',
    cancelled: 'incident-status--cancelled',
  }

  return classes[status]
}

function canCreate(): boolean {
  return authStore.can(PERMISSIONS.incidents.create)
}

function canEdit(
  incident: ProductionIncident,
): boolean {
  return (
    incident.status === 'open' &&
    authStore.can(PERMISSIONS.incidents.update)
  )
}

function canResolve(
  incident: ProductionIncident,
): boolean {
  return (
    incident.status === 'open' &&
    authStore.can(PERMISSIONS.incidents.close)
  )
}

function validateDates(): boolean {
  if (
    filters.from &&
    filters.to &&
    filters.to < filters.from
  ) {
    void Swal.fire({
      title: 'Rango de fechas no válido',
      text: 'La fecha final debe ser igual o posterior a la fecha inicial.',
      icon: 'warning',
      confirmButtonText: 'Aceptar',
    })

    return false
  }

  return true
}

async function loadIncidents(
  page = 1,
): Promise<void> {
  if (!validateDates()) {
    return
  }

  loading.value = true

  try {
    const response =
      await productionIncidentsService.list({
        incident_type: filters.incidentType,
        status: filters.status,
        garment_cut_id: filters.garmentCutId,
        production_movement_id:
          filters.productionMovementId,
        responsible_employee_id:
          filters.responsibleEmployeeId,
        from: filters.from,
        to: filters.to,
        per_page: filters.perPage,
        page,
      })

    incidents.value = response.data
    pagination.value = response.meta
  } catch (error) {
    await Swal.fire({
      title:
        'No fue posible cargar las incidencias',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    loading.value = false
  }
}

async function loadCatalogs(): Promise<void> {
  catalogsLoading.value = true

  try {
    const [
      cutsResponse,
      movementsResponse,
      employeesResponse,
    ] = await Promise.all([
      garmentCutsService.list({
        per_page: 100,
      }),

      productionMovementsService.list({
        per_page: 100,
      }),

      employeesService.list({
        status: 'active',
        per_page: 100,
        page: 1,
      }),
    ])

    cuts.value = cutsResponse.data
    movements.value = movementsResponse.data
    employees.value = employeesResponse.data
  } catch (error) {
    await Swal.fire({
      title:
        'No fue posible cargar los catálogos',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    catalogsLoading.value = false
  }
}

function applyFilters(): void {
  void loadIncidents(1)
}

function clearFilters(): void {
  filters.incidentType = ''
  filters.status = ''
  filters.garmentCutId = ''
  filters.productionMovementId = ''
  filters.responsibleEmployeeId = ''
  filters.from = ''
  filters.to = ''
  filters.perPage = 15

  void loadIncidents(1)
}

function openCreateForm(): void {
  selectedIncident.value = null
  formOpen.value = true
}

function openEditForm(
  incident: ProductionIncident,
): void {
  selectedIncident.value = incident
  formOpen.value = true
}

function closeForm(): void {
  formOpen.value = false
  selectedIncident.value = null
}

async function openDetail(
  incident: ProductionIncident,
): Promise<void> {
  detailLoadingId.value = incident.id

  try {
    detailIncident.value =
      await productionIncidentsService.show(
        incident.id,
      )

    detailOpen.value = true
  } catch (error) {
    await Swal.fire({
      title:
        'No fue posible consultar la incidencia',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    detailLoadingId.value = null
  }
}

function closeDetail(): void {
  detailOpen.value = false
  detailIncident.value = null
}

function openResolveDialog(
  incident: ProductionIncident,
): void {
  resolveIncident.value = incident
  resolveOpen.value = true
}

function closeResolveDialog(): void {
  resolveOpen.value = false
  resolveIncident.value = null
}

function canGenerateRework(
  incident: ProductionIncident,
): boolean {
  const validIncidentType = [
    'quality',
    'damage',
  ].includes(incident.incident_type)

  return (
    incident.status === 'open' &&
    validIncidentType &&
    !incident.return_movement_id &&
    authStore.can(PERMISSIONS.incidents.update) &&
    authStore.can(PERMISSIONS.processes.view)
  )
}

function openReworkDialog(
  incident: ProductionIncident,
): void {
  reworkIncident.value = incident
  reworkOpen.value = true
}

function closeReworkDialog(): void {
  reworkOpen.value = false
  reworkIncident.value = null
}

async function handleReworkCreated(
  _movement: ProductionMovement,
  message: string,
): Promise<void> {
  closeReworkDialog()

  await Swal.fire({
    title: message,
    text: 'El nuevo movimiento quedó pendiente de recepción.',
    icon: 'success',
    timer: 2200,
    showConfirmButton: false,
  })

  await Promise.all([
    loadIncidents(
      pagination.value.current_page,
    ),
    loadCatalogs(),
  ])
}

async function handleSaved(
  _incident: ProductionIncident,
  message: string,
): Promise<void> {
  closeForm()

  await Swal.fire({
    title: message,
    icon: 'success',
    timer: 1700,
    showConfirmButton: false,
  })

  await Promise.all([
    loadIncidents(
      pagination.value.current_page,
    ),
    loadCatalogs(),
  ])
}

async function handleResolved(
  _incident: ProductionIncident,
  message: string,
): Promise<void> {
  closeResolveDialog()

  await Swal.fire({
    title: message,
    icon: 'success',
    timer: 1700,
    showConfirmButton: false,
  })

  await Promise.all([
    loadIncidents(
      pagination.value.current_page,
    ),
    loadCatalogs(),
  ])
}

function previousPage(): void {
  if (pagination.value.current_page > 1) {
    void loadIncidents(
      pagination.value.current_page - 1,
    )
  }
}

function nextPage(): void {
  if (
    pagination.value.current_page <
    pagination.value.last_page
  ) {
    void loadIncidents(
      pagination.value.current_page + 1,
    )
  }
}

onMounted(async () => {
  await Promise.all([
    loadIncidents(),
    loadCatalogs(),
  ])
})
</script>

<template>
  <section class="grid gap-5">
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <p class="m-0 mb-2 text-brand-orange-800 text-xs font-extrabold tracking-widest uppercase">
          Control de calidad
        </p>

        <h2 class="m-0 text-2xl font-bold text-slate-900">Incidencias de producción</h2>

        <p class="mt-2 mb-0 text-slate-600 leading-relaxed text-sm max-w-2xl">
          Registra daños, pérdidas, problemas de calidad,
          retrasos y sus correspondientes resoluciones.
        </p>
      </div>

      <button
        v-if="canCreate()"
        type="button"
        class="inline-flex w-full sm:w-auto min-h-[3rem] items-center justify-center gap-2 px-5 text-white bg-brand-orange-800 hover:bg-brand-orange-900 rounded-md font-[750] text-sm cursor-pointer transition-colors border-0 disabled:opacity-60"
        :disabled="catalogsLoading"
        @click="openCreateForm"
      >
        <Plus :size="20" aria-hidden="true" />
        Registrar incidencia
      </button>
    </header>

    <form
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 p-4 bg-white border border-slate-200 rounded-lg"
      @submit.prevent="applyFilters"
    >
      <select
        v-model="filters.incidentType"
        aria-label="Filtrar por tipo"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">Todos los tipos</option>
        <option value="damage">Daño</option>
        <option value="loss">Pérdida</option>
        <option value="quality">Calidad</option>
        <option value="delay">Retraso</option>
        <option value="other">Otro</option>
      </select>

      <select
        v-model="filters.status"
        aria-label="Filtrar por estado"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">Todos los estados</option>
        <option value="open">Abiertas</option>
        <option value="resolved">Resueltas</option>
        <option value="cancelled">Canceladas</option>
      </select>

      <select
        v-model="filters.garmentCutId"
        aria-label="Filtrar por corte"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">Todos los cortes</option>

        <option
          v-for="cut in cuts"
          :key="cut.id"
          :value="cut.id"
        >
          {{ cut.code }}
        </option>
      </select>

      <select
        v-model="filters.productionMovementId"
        aria-label="Filtrar por movimiento"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">
          Todos los movimientos
        </option>

        <option
          v-for="movement in movements"
          :key="movement.id"
          :value="movement.id"
        >
          #{{ movement.id }}
          ·
          {{ movement.garment_cut?.code }}
        </option>
      </select>

      <select
        v-model="filters.responsibleEmployeeId"
        aria-label="Filtrar por responsable"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">
          Todos los responsables
        </option>

        <option
          v-for="employee in employees"
          :key="employee.id"
          :value="employee.id"
        >
          {{ employee.name }}
        </option>
      </select>

      <input
        v-model="filters.from"
        type="date"
        aria-label="Fecha inicial"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      />

      <input
        v-model="filters.to"
        type="date"
        :min="filters.from || undefined"
        aria-label="Fecha final"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      />

      <select
        v-model="filters.perPage"
        aria-label="Registros por página"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option :value="10">10 por página</option>
        <option :value="15">15 por página</option>
        <option :value="25">25 por página</option>
        <option :value="50">50 por página</option>
      </select>

      <div class="flex gap-2 sm:col-span-2 lg:col-span-4 justify-end">
        <button
          type="submit"
          class="inline-flex min-h-[3rem] px-4 items-center justify-center gap-2 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-bold text-sm transition-colors cursor-pointer border-0 disabled:opacity-60"
          :disabled="loading"
        >
          <Search :size="19" aria-hidden="true" />
          Buscar
        </button>

        <button
          type="button"
          class="inline-flex min-h-[3rem] px-4 items-center justify-center gap-2 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-bold text-sm transition-colors cursor-pointer disabled:opacity-60"
          :disabled="loading"
          @click="clearFilters"
        >
          <RotateCcw
            :size="19"
            aria-hidden="true"
          />
          Limpiar
        </button>
      </div>
    </form>

    <div
      v-if="loading"
      class="grid place-content-center min-h-[18rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <div class="w-9 h-9 border-4 border-brand-green-100 border-t-brand-green-700 rounded-full animate-spin mx-auto" />
      <p class="text-slate-600 text-sm">Cargando incidencias...</p>
    </div>

    <div
      v-else-if="incidents.length === 0"
      class="grid place-content-center min-h-[18rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <AlertTriangle
        :size="44"
        class="text-slate-400 mx-auto"
        aria-hidden="true"
      />

      <h3 class="m-0 text-lg font-bold text-slate-900">No se encontraron incidencias</h3>

      <p class="m-0 text-slate-600 text-sm">
        Modifica los filtros o registra una incidencia.
      </p>
    </div>

    <template v-else>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <article
          v-for="incident in incidents"
          :key="incident.id"
          class="grid gap-4 p-4 bg-white border border-slate-200 rounded-xl shadow-xs"
        >
          <header class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
              <div class="flex w-11 h-11 items-center justify-center text-rose-800 bg-rose-100 rounded-md shrink-0">
                <AlertTriangle
                  :size="22"
                  aria-hidden="true"
                />
              </div>

              <span class="grid min-w-0">
                <strong class="text-slate-900 font-mono font-bold text-sm truncate">
                  {{
                    incident.garment_cut?.code ??
                    `Incidencia #${incident.id}`
                  }}
                </strong>

                <small class="text-slate-500 text-xs truncate">
                  Movimiento
                  {{
                    incident.production_movement
                      ? `#${incident.production_movement.id}`
                      : 'no disponible'
                  }}
                </small>
              </span>
            </div>

            <em
              class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold not-italic shrink-0"
              :class="{
                'text-rose-800 bg-rose-100': incident.status === 'open',
                'text-emerald-800 bg-emerald-100': incident.status === 'resolved',
                'text-slate-700 bg-slate-100': incident.status === 'cancelled',
              }"
            >
              {{ incident.status_label }}
            </em>
          </header>

          <div class="flex flex-wrap gap-2">
            <span
              class="px-2.5 py-1 rounded-full text-xs font-bold"
              :class="{
                'text-rose-800 bg-rose-100': ['damage', 'loss'].includes(incident.incident_type),
                'text-amber-800 bg-amber-100': incident.incident_type === 'quality',
                'text-brand-orange-900 bg-brand-orange-100': incident.incident_type === 'delay',
                'text-sky-800 bg-sky-100': incident.incident_type === 'other',
              }"
            >
              {{ incident.incident_type_label }}
            </span>

            <span class="px-2.5 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-bold">
              {{ incident.quantity_affected }}
              piezas afectadas
            </span>
          </div>

          <p class="m-0 text-slate-600 text-sm leading-relaxed line-clamp-3">{{ incident.description }}</p>

          <dl class="grid gap-2 m-0 text-xs border-t border-slate-100 pt-3">
            <div class="flex justify-between items-center gap-3">
              <dt class="flex items-center gap-1.5 text-slate-500">
                <UserRound
                  :size="17"
                  class="shrink-0 text-slate-400"
                  aria-hidden="true"
                />

                Responsable
              </dt>

              <dd class="m-0 font-bold text-slate-800 truncate">
                {{
                  incident.responsible_employee
                    ?.name ??
                  'No disponible'
                }}
              </dd>
            </div>

            <div class="flex justify-between items-center gap-3">
              <dt class="text-slate-500">Fecha</dt>

              <dd class="m-0 font-medium text-slate-700">
                {{
                  formatDateTime(
                    incident.created_at,
                  )
                }}
              </dd>
            </div>
          </dl>

          <footer class="grid grid-cols-[repeat(auto-fit,minmax(7rem,1fr))] gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              class="inline-flex min-h-[2.4rem] items-center justify-center gap-1.5 px-2.5 text-brand-orange-900 bg-brand-orange-100 border border-brand-orange-200 hover:bg-brand-orange-200/80 rounded-md font-bold text-xs cursor-pointer transition-colors disabled:opacity-50"
              :disabled="
                detailLoadingId === incident.id
              "
              @click="openDetail(incident)"
            >
              <Eye :size="18" aria-hidden="true" />
              Ver detalle
            </button>

            <button
              v-if="canEdit(incident)"
              type="button"
              class="inline-flex min-h-[2.4rem] items-center justify-center gap-1.5 px-2.5 text-sky-800 bg-sky-100 border border-sky-200 hover:bg-sky-200/80 rounded-md font-bold text-xs cursor-pointer transition-colors"
              @click="openEditForm(incident)"
            >
              <Pencil
                :size="18"
                aria-hidden="true"
              />
              Editar
            </button>

            <button
                v-if="canGenerateRework(incident)"
                type="button"
                class="inline-flex min-h-[2.4rem] items-center justify-center gap-1.5 px-2.5 text-brand-orange-900 bg-brand-orange-100 border border-brand-orange-200 hover:bg-brand-orange-200/80 rounded-md font-bold text-xs cursor-pointer transition-colors"
                @click="openReworkDialog(incident)"
            >
                <Undo2
                    :size="18"
                    aria-hidden="true"
                />

                Generar reproceso
            </button>

            <button
              v-if="canResolve(incident)"
              type="button"
              class="inline-flex min-h-[2.4rem] items-center justify-center gap-1.5 px-2.5 text-emerald-800 bg-emerald-100 border border-emerald-200 hover:bg-emerald-200/80 rounded-md font-bold text-xs cursor-pointer transition-colors"
              @click="
                openResolveDialog(incident)
              "
            >
              <CheckCircle2
                :size="18"
                aria-hidden="true"
              />
              Resolver
            </button>
          </footer>
        </article>
      </div>

      <footer class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 bg-white border border-slate-200 rounded-lg text-xs text-slate-600">
        <p class="m-0">
          Mostrando
          <strong class="text-slate-900 font-bold">{{ pagination.from ?? 0 }}</strong>
          a
          <strong class="text-slate-900 font-bold">{{ pagination.to ?? 0 }}</strong>
          de
          <strong class="text-slate-900 font-bold">{{ pagination.total }}</strong>
          incidencias
        </p>

        <div class="flex items-center gap-3">
          <button
            type="button"
            class="inline-flex min-h-[2.35rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 rounded-md font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
            :disabled="
              pagination.current_page <= 1 ||
              loading
            "
            @click="previousPage"
          >
            <ChevronLeft
              :size="19"
              aria-hidden="true"
            />
            Anterior
          </button>

          <span class="font-medium">
            Página {{ pagination.current_page }}
            de {{ pagination.last_page }}
          </span>

          <button
            type="button"
            class="inline-flex min-h-[2.35rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 rounded-md font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
            :disabled="
              pagination.current_page >=
                pagination.last_page ||
              loading
            "
            @click="nextPage"
          >
            Siguiente
            <ChevronRight
              :size="19"
              aria-hidden="true"
            />
          </button>
        </div>
      </footer>
    </template>

    <ProductionIncidentFormDialog
      :open="formOpen"
      :incident="selectedIncident"
      :cuts="cuts"
      @close="closeForm"
      @saved="handleSaved"
    />

    <ProductionIncidentDetailDialog
      :open="detailOpen"
      :incident="detailIncident"
      @close="closeDetail"
    />

    <ResolveProductionIncidentDialog
      :open="resolveOpen"
      :incident="resolveIncident"
      @close="closeResolveDialog"
      @resolved="handleResolved"
    />

    <ReturnIncidentForReworkDialog
        :open="reworkOpen"
        :incident="reworkIncident"
        @close="closeReworkDialog"
        @created="handleReworkCreated"
    />
  </section>
</template>