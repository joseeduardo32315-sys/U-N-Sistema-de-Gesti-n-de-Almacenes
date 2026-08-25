<script setup lang="ts">
import {
  computed,
  onMounted,
  reactive,
  ref,
  watch,
} from 'vue'

import {
  Activity,
  ArrowRight,
  CheckCheck,
  ChevronLeft,
  ChevronRight,
  Eye,
  Factory,
  MapPin,
  PackageOpen,
  Plus,
  RotateCcw,
  Route,
  Search,
} from 'lucide-vue-next'

import Swal from 'sweetalert2'

import { PERMISSIONS } from '@/config/permissions'
import { areasService } from '@/modules/areas/services/areas.service'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { garmentCutsService } from '@/modules/garment-cuts/services/garment-cuts.service'
import { processesService } from '@/modules/processes/services/processes.service'
import ProductionMovementDetailDialog from '@/modules/production-movements/components/ProductionMovementDetailDialog.vue'
import ProductionMovementFormDialog from '@/modules/production-movements/components/ProductionMovementFormDialog.vue'
import { productionMovementsService } from '@/modules/production-movements/services/production-movements.service'
import { getApiErrorMessage } from '@/utils/api-error'
import ProductionOperationLogsDialog from '@/modules/production-operation-logs/components/ProductionOperationLogsDialog.vue'

import type { Area } from '@/modules/areas/types/area.types'
import type { GarmentCut } from '@/modules/garment-cuts/types/garment-cut.types'
import type { ProductionProcess } from '@/modules/processes/types/process.types'

import type {
  ProductionMovement,
  ProductionMovementArea,
  ProductionMovementStatus,
  ProductionMovementTargetType,
} from '@/modules/production-movements/types/production-movement.types'

import type { PaginationMeta } from '@/types/api'

interface Filters {
  search: string
  garmentCutId: number | ''
  targetType: ProductionMovementTargetType | ''
  processId: number | ''
  fromAreaId: number | ''
  toAreaId: number | ''
  status: ProductionMovementStatus | ''
  perPage: number
}

const authStore = useAuthStore()

const movements = ref<ProductionMovement[]>([])
const cuts = ref<GarmentCut[]>([])
const processes = ref<ProductionProcess[]>([])
const catalogAreas = ref<Area[]>([])

const loading = ref(false)
const catalogsLoading = ref(false)
const receivingId = ref<number | null>(null)
const detailLoadingId = ref<number | null>(null)

const formOpen = ref(false)
const detailOpen = ref(false)
const operationLogsOpen = ref(false)

const detailMovement =
  ref<ProductionMovement | null>(null)

const operationMovement =
  ref<ProductionMovement | null>(null)

type DepartmentTab =
  | 'all'
  | 'corte'
  | 'diseño'
  | 'bordado'
  | 'maquila'
  | 'terminado'
  | 'preparación'
  | 'almacén'

const activeDepartmentTab = ref<DepartmentTab>('all')
const initialCutId = ref<number | null>(null)
const initialTargetKey = ref<string | null>(null)

function getAreaIdByName(name: string): number | '' {
  const normalizedSearch = name.trim().toLowerCase()
  const area = catalogAreas.value.find((a) =>
    a.name.trim().toLowerCase().includes(normalizedSearch)
  )
  return area ? area.id : ''
}

interface QuickDispatch {
  label: string
  cutId: number
  targetKey: string
}

function getQuickDispatchAction(
  movement: ProductionMovement,
): QuickDispatch | null {
  if (
    movement.status !== 'completed' &&
    movement.status !== 'partially_completed'
  ) {
    return null
  }

  const currentArea =
    movement.to_area?.name?.trim().toLowerCase() ?? ''
  const cut = movement.garment_cut
  if (!cut) return null

  // Fetch full cut details from cuts catalog to read special pieces and complement info
  const fullCut = cuts.value.find((c) => c.id === cut.id)

  // 1. Corte -> Diseño
  if (currentArea.includes('corte')) {
    return {
      label: 'Despachar a Diseño',
      cutId: cut.id,
      targetKey: 'cut',
    }
  }

  // 2. Diseño -> Bordado / Maquila
  if (
    currentArea.includes('diseño') ||
    currentArea.includes('diseno')
  ) {
    if (movement.target_type === 'cut') {
      const specialPieces = fullCut?.special_process_pieces
      if (specialPieces && specialPieces.length > 0) {
        const firstPiece = specialPieces[0]
        if (firstPiece) {
          return {
            label: 'Despachar Pieza Especial a Bordado',
            cutId: cut.id,
            targetKey: `special_piece:${firstPiece.id}`,
          }
        }
      } else if (fullCut?.complement) {
        return {
          label: 'Despachar a Maquila',
          cutId: cut.id,
          targetKey: `complement:${fullCut.complement.id}`,
        }
      } else {
        return {
          label: 'Despachar a Maquila',
          cutId: cut.id,
          targetKey: 'cut',
        }
      }
    }

    if (movement.target_type === 'special_piece' && movement.target) {
      return {
        label: 'Despachar a Bordado',
        cutId: cut.id,
        targetKey: `special_piece:${movement.target.id}`,
      }
    }

    if (movement.target_type === 'complement' && movement.target) {
      return {
        label: 'Despachar a Maquila',
        cutId: cut.id,
        targetKey: `complement:${movement.target.id}`,
      }
    }
  }

  // 3. Bordado -> Maquila
  if (currentArea.includes('bordado')) {
    if (movement.target_type === 'special_piece' && movement.target) {
      return {
        label: 'Despachar a Maquila (Unir)',
        cutId: cut.id,
        targetKey: `special_piece:${movement.target.id}`,
      }
    }
  }

  // 4. Maquila -> Terminado
  if (currentArea.includes('maquila')) {
    const targetKeyVal =
      movement.target_type === 'complement' && movement.target
        ? `complement:${movement.target.id}`
        : movement.target_type === 'special_piece' && movement.target
          ? `special_piece:${movement.target.id}`
          : 'cut'
    return {
      label: 'Despachar a Terminado',
      cutId: cut.id,
      targetKey: targetKeyVal,
    }
  }

  // 5. Terminado -> Preparación
  if (currentArea.includes('terminado')) {
    const targetKeyVal =
      movement.target_type === 'complement' && movement.target
        ? `complement:${movement.target.id}`
        : movement.target_type === 'special_piece' && movement.target
          ? `special_piece:${movement.target.id}`
          : 'cut'
    return {
      label: 'Despachar a Preparación',
      cutId: cut.id,
      targetKey: targetKeyVal,
    }
  }

  // 6. Preparación -> Almacén Final
  if (
    currentArea.includes('preparación') ||
    currentArea.includes('preparacion')
  ) {
    const targetKeyVal =
      movement.target_type === 'complement' && movement.target
        ? `complement:${movement.target.id}`
        : movement.target_type === 'special_piece' && movement.target
          ? `special_piece:${movement.target.id}`
          : 'cut'
    return {
      label: 'Enviar a Almacén',
      cutId: cut.id,
      targetKey: targetKeyVal,
    }
  }

  return null
}

watch(activeDepartmentTab, (newTab) => {
  if (newTab === 'all') {
    filters.toAreaId = ''
  } else if (newTab === 'corte') {
    filters.toAreaId = getAreaIdByName('corte')
  } else if (newTab === 'diseño') {
    filters.toAreaId = getAreaIdByName('diseño')
  } else if (newTab === 'bordado') {
    filters.toAreaId =
      getAreaIdByName('bordado') ||
      getAreaIdByName('especial')
  } else if (newTab === 'maquila') {
    filters.toAreaId = getAreaIdByName('maquila')
  } else if (newTab === 'terminado') {
    filters.toAreaId = getAreaIdByName('terminado')
  } else if (newTab === 'preparación') {
    filters.toAreaId =
      getAreaIdByName('preparación') ||
      getAreaIdByName('preparacion')
  } else if (newTab === 'almacén') {
    filters.toAreaId =
      getAreaIdByName('almacén') ||
      getAreaIdByName('almacen')
  }

  void loadMovements(1)
})

const filters = reactive<Filters>({
  search: '',
  garmentCutId: '',
  targetType: '',
  processId: '',
  fromAreaId: '',
  toAreaId: '',
  status: '',
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

const availableAreas =
  computed<ProductionMovementArea[]>(() => {
    const areaMap = new Map<
      number,
      ProductionMovementArea
    >()

    for (const area of catalogAreas.value) {
      areaMap.set(area.id, area)
    }

    for (const movement of movements.value) {
      if (movement.from_area) {
        areaMap.set(
          movement.from_area.id,
          movement.from_area,
        )
      }

      if (movement.to_area) {
        areaMap.set(
          movement.to_area.id,
          movement.to_area,
        )
      }
    }

    return Array.from(areaMap.values()).sort(
      (a, b) => a.name.localeCompare(b.name, 'es'),
    )
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

function statusClass(
  status: ProductionMovementStatus,
): string {
  const classes: Record<
    ProductionMovementStatus,
    string
  > = {
    pending: 'movement-status--pending',
    received: 'movement-status--received',
    in_progress: 'movement-status--progress',
    partially_completed:
      'movement-status--partial',
    completed: 'movement-status--completed',
    cancelled: 'movement-status--cancelled',
    with_incident:
      'movement-status--incident',
    delayed: 'movement-status--delayed',
  }

  return classes[status]
}

function targetTypeLabel(
  type: ProductionMovementTargetType,
): string {
  const labels: Record<
    ProductionMovementTargetType,
    string
  > = {
    cut: 'Corte completo',
    complement: 'Complemento',
    special_piece: 'Pieza especial',
  }

  return labels[type]
}

function canCreate(): boolean {
  return (
    authStore.can(PERMISSIONS.processes.assign) &&
    authStore.can(PERMISSIONS.cuts.view)
  )
}

function canReceive(
  movement: ProductionMovement,
): boolean {
  return (
    movement.status === 'pending' &&
    authStore.can(
      PERMISSIONS.processes.updateStatus,
    )
  )
}

async function loadMovements(
  page = 1,
): Promise<void> {
  loading.value = true

  try {
    const response =
      await productionMovementsService.list({
        search: filters.search.trim(),
        garment_cut_id: filters.garmentCutId,
        target_type: filters.targetType,
        process_id: filters.processId,
        from_area_id: filters.fromAreaId,
        to_area_id: filters.toAreaId,
        status: filters.status,
        per_page: filters.perPage,
        page,
      })

    movements.value = response.data
    pagination.value = response.meta
  } catch (error) {
    await Swal.fire({
      title:
        'No fue posible cargar los movimientos',
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
    const processesResponse =
      await processesService.list()

    processes.value = processesResponse

    if (
      authStore.can(PERMISSIONS.cuts.view)
    ) {
      const cutsResponse =
        await garmentCutsService.list({
          per_page: 100,
        })

      cuts.value = cutsResponse.data
    }

    if (
      authStore.can(
        PERMISSIONS.employees.view,
      )
    ) {
      try {
        catalogAreas.value =
          await areasService.list()
      } catch {
        catalogAreas.value = []
      }
    }
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
  void loadMovements(1)
}

function clearFilters(): void {
  filters.search = ''
  filters.garmentCutId = ''
  filters.targetType = ''
  filters.processId = ''
  filters.fromAreaId = ''
  filters.toAreaId = ''
  filters.status = ''
  filters.perPage = 15

  void loadMovements(1)
}

function openCreateForm(
  cutId: number | null = null,
  targetKeyVal: string | null = null,
): void {
  initialCutId.value = cutId
  initialTargetKey.value = targetKeyVal
  formOpen.value = true
}

function closeCreateForm(): void {
  formOpen.value = false
}

async function handleMovementSaved(
  _movement: ProductionMovement,
  message: string,
): Promise<void> {
  closeCreateForm()

  await Swal.fire({
    title: message,
    icon: 'success',
    timer: 1700,
    showConfirmButton: false,
  })

  await Promise.all([
    loadMovements(
      pagination.value.current_page,
    ),
    loadCatalogs(),
  ])
}

async function openDetail(
  movement: ProductionMovement,
): Promise<void> {
  detailLoadingId.value = movement.id

  try {
    detailMovement.value =
      await productionMovementsService.show(
        movement.id,
      )

    detailOpen.value = true
  } catch (error) {
    await Swal.fire({
      title:
        'No fue posible consultar el movimiento',
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
  detailMovement.value = null
}

async function receiveMovement(
  movement: ProductionMovement,
): Promise<void> {
  const confirmation = await Swal.fire({
    title: '¿Confirmar recepción?',
    html: `
      <p>
        Se confirmará la recepción del movimiento
        <strong>#${movement.id}</strong>
        en el área
        <strong>${
          movement.to_area?.name ??
          'de destino'
        }</strong>.
      </p>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, recibir',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#3f6b2a',
  })

  if (!confirmation.isConfirmed) {
    return
  }

  receivingId.value = movement.id

  try {
    const response =
      await productionMovementsService.receive(
        movement.id,
      )

    await Swal.fire({
      title: response.message,
      icon: 'success',
      timer: 1700,
      showConfirmButton: false,
    })

    await Promise.all([
      loadMovements(
        pagination.value.current_page,
      ),
      loadCatalogs(),
    ])
  } catch (error) {
    await Swal.fire({
      title:
        'No fue posible recibir el movimiento',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    receivingId.value = null
  }
}

function previousPage(): void {
  if (pagination.value.current_page > 1) {
    void loadMovements(
      pagination.value.current_page - 1,
    )
  }
}

function nextPage(): void {
  if (
    pagination.value.current_page <
    pagination.value.last_page
  ) {
    void loadMovements(
      pagination.value.current_page + 1,
    )
  }
}

function openOperationLogs(
  movement: ProductionMovement,
): void {
  operationMovement.value = movement
  operationLogsOpen.value = true
}

function closeOperationLogs(): void {
  operationLogsOpen.value = false
  operationMovement.value = null
}

async function handleOperationLogsChanged(): Promise<void> {
  await loadMovements(
    pagination.value.current_page,
  )

  if (!operationMovement.value) {
    return
  }

  try {
    operationMovement.value =
      await productionMovementsService.show(
        operationMovement.value.id,
      )
  } catch {
    /*
     * El listado principal ya fue actualizado.
     * No cerramos el diálogo por un fallo secundario.
     */
  }
}

onMounted(async () => {
  await Promise.all([
    loadMovements(),
    loadCatalogs(),
  ])
})
</script>

<template>
  <section class="grid gap-5">
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <p class="m-0 mb-2 text-brand-orange-800 text-xs font-extrabold tracking-widest uppercase">
          Flujo del taller
        </p>

        <h2 class="m-0 text-2xl font-bold text-slate-900">Movimientos y avances</h2>

        <p class="mt-2 mb-0 text-slate-600 leading-relaxed text-sm max-w-2xl">
          Controla despachos, recepciones, cantidades y
          rutas de los lotes de producción.
        </p>
      </div>

      <button
        v-if="canCreate()"
        type="button"
        class="inline-flex w-full sm:w-auto min-h-[3rem] items-center justify-center gap-2 px-5 text-white bg-brand-orange-800 hover:bg-brand-orange-900 rounded-md font-[750] text-sm cursor-pointer transition-colors border-0 disabled:opacity-60"
        :disabled="catalogsLoading"
        @click="() => openCreateForm()"
      >
        <Plus :size="20" aria-hidden="true" />
        Registrar despacho
      </button>
    </header>

    <nav
      class="flex overflow-x-auto gap-2 p-1.5 bg-slate-100/80 border border-slate-200/80 rounded-xl mb-4 scrollbar-none"
      aria-label="Departamentos de producción"
    >
      <button
        type="button"
        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold whitespace-nowrap cursor-pointer transition-colors border-0"
        :class="activeDepartmentTab === 'all' ? 'bg-white text-brand-green-800 shadow-xs' : 'text-slate-600 hover:bg-slate-200/60'"
        @click="activeDepartmentTab = 'all'"
      >
        <Route :size="20" aria-hidden="true" />
        Todos
      </button>

      <button
        type="button"
        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold whitespace-nowrap cursor-pointer transition-colors border-0"
        :class="activeDepartmentTab === 'corte' ? 'bg-white text-brand-green-800 shadow-xs' : 'text-slate-600 hover:bg-slate-200/60'"
        @click="activeDepartmentTab = 'corte'"
      >
        <Scissors :size="20" aria-hidden="true" />
        Corte
      </button>

      <button
        type="button"
        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold whitespace-nowrap cursor-pointer transition-colors border-0"
        :class="activeDepartmentTab === 'diseño' ? 'bg-white text-brand-green-800 shadow-xs' : 'text-slate-600 hover:bg-slate-200/60'"
        @click="activeDepartmentTab = 'diseño'"
      >
        <Activity :size="20" aria-hidden="true" />
        Diseño
      </button>

      <button
        type="button"
        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold whitespace-nowrap cursor-pointer transition-colors border-0"
        :class="activeDepartmentTab === 'bordado' ? 'bg-white text-brand-green-800 shadow-xs' : 'text-slate-600 hover:bg-slate-200/60'"
        @click="activeDepartmentTab = 'bordado'"
      >
        <Factory :size="20" aria-hidden="true" />
        Bordado
      </button>

      <button
        type="button"
        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold whitespace-nowrap cursor-pointer transition-colors border-0"
        :class="activeDepartmentTab === 'maquila' ? 'bg-white text-brand-green-800 shadow-xs' : 'text-slate-600 hover:bg-slate-200/60'"
        @click="activeDepartmentTab = 'maquila'"
      >
        <Factory :size="20" aria-hidden="true" />
        Maquila
      </button>

      <button
        type="button"
        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold whitespace-nowrap cursor-pointer transition-colors border-0"
        :class="activeDepartmentTab === 'terminado' ? 'bg-white text-brand-green-800 shadow-xs' : 'text-slate-600 hover:bg-slate-200/60'"
        @click="activeDepartmentTab = 'terminado'"
      >
        <CheckCheck :size="20" aria-hidden="true" />
        Terminado
      </button>

      <button
        type="button"
        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold whitespace-nowrap cursor-pointer transition-colors border-0"
        :class="activeDepartmentTab === 'preparación' ? 'bg-white text-brand-green-800 shadow-xs' : 'text-slate-600 hover:bg-slate-200/60'"
        @click="activeDepartmentTab = 'preparación'"
      >
        <PackageOpen :size="20" aria-hidden="true" />
        Preparación
      </button>

      <button
        type="button"
        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold whitespace-nowrap cursor-pointer transition-colors border-0"
        :class="activeDepartmentTab === 'almacén' ? 'bg-white text-brand-green-800 shadow-xs' : 'text-slate-600 hover:bg-slate-200/60'"
        @click="activeDepartmentTab = 'almacén'"
      >
        <Boxes :size="20" aria-hidden="true" />
        Almacén Final
      </button>
    </nav>

    <form
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 p-4 bg-white border border-slate-200 rounded-lg"
      @submit.prevent="applyFilters"
    >
      <div class="relative flex items-center col-span-1 sm:col-span-2 lg:col-span-4">
        <Search :size="20" class="absolute left-3 text-slate-400 pointer-events-none" aria-hidden="true" />

        <input
          v-model="filters.search"
          type="search"
          maxlength="150"
          placeholder="Buscar por corte o notas"
          class="w-full min-h-[3rem] pl-10 pr-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
        />
      </div>

      <select
        v-if="cuts.length > 0"
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
        v-model="filters.targetType"
        aria-label="Filtrar por tipo de lote"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">Todos los tipos</option>
        <option value="cut">Corte completo</option>
        <option value="complement">
          Complemento
        </option>
        <option value="special_piece">
          Pieza especial
        </option>
      </select>

      <select
        v-model="filters.processId"
        aria-label="Filtrar por proceso"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">Todos los procesos</option>

        <option
          v-for="process in processes"
          :key="process.id"
          :value="process.id"
        >
          {{ process.name }}
        </option>
      </select>

      <select
        v-model="filters.fromAreaId"
        aria-label="Filtrar por área de origen"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">Todas las áreas origen</option>

        <option
          v-for="area in availableAreas"
          :key="area.id"
          :value="area.id"
        >
          {{ area.name }}
        </option>
      </select>

      <select
        v-if="activeDepartmentTab === 'all'"
        v-model="filters.toAreaId"
        aria-label="Filtrar por área de destino"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">
          Todas las áreas destino
        </option>

        <option
          v-for="area in availableAreas"
          :key="area.id"
          :value="area.id"
        >
          {{ area.name }}
        </option>
      </select>

      <select
        v-model="filters.status"
        aria-label="Filtrar por estado"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">Todos los estados</option>
        <option value="pending">
          Pendientes de recepción
        </option>
        <option value="received">Recibidos</option>
        <option value="in_progress">
          En proceso
        </option>
        <option value="partially_completed">
          Parcialmente completados
        </option>
        <option value="completed">
          Completados
        </option>
        <option value="with_incident">
          Con incidencia
        </option>
        <option value="delayed">Retrasados</option>
        <option value="cancelled">Cancelados</option>
      </select>

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
      <p class="text-slate-600 text-sm">Cargando movimientos...</p>
    </div>

    <div
      v-else-if="movements.length === 0"
      class="grid place-content-center min-h-[18rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <Route :size="44" class="text-slate-400 mx-auto" aria-hidden="true" />

      <h3 class="m-0 text-lg font-bold text-slate-900">No se encontraron movimientos</h3>

      <p class="m-0 text-slate-600 text-sm">
        Registra el primer despacho o modifica los filtros.
      </p>
    </div>

    <template v-else>
      <div class="grid lg:hidden grid-cols-1 md:grid-cols-2 gap-4">
        <article
          v-for="movement in movements"
          :key="movement.id"
          class="grid gap-4 p-4 bg-white border border-slate-200 rounded-xl shadow-xs"
        >
          <header class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
              <div class="flex w-11 h-11 items-center justify-center text-brand-orange-800 bg-brand-orange-100 rounded-md shrink-0">
                <PackageOpen
                  :size="22"
                  aria-hidden="true"
                />
              </div>

              <span class="grid min-w-0">
                <strong class="text-slate-900 font-mono font-bold text-sm truncate">
                  {{
                    movement.garment_cut?.code ??
                    `Movimiento #${movement.id}`
                  }}
                </strong>

                <small class="text-slate-500 text-xs truncate">
                  {{
                    movement.target_type_label ||
                    targetTypeLabel(
                      movement.target_type,
                    )
                  }}
                </small>
              </span>
            </div>

            <em
              class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold not-italic shrink-0"
              :class="{
                'text-amber-800 bg-amber-100': movement.status === 'pending',
                'text-sky-800 bg-sky-100': movement.status === 'received',
                'text-indigo-800 bg-indigo-100': movement.status === 'in_progress',
                'text-brand-orange-900 bg-brand-orange-100': movement.status === 'partially_completed',
                'text-emerald-800 bg-emerald-100': movement.status === 'completed',
                'text-rose-800 bg-rose-100': ['cancelled', 'with_incident', 'delayed'].includes(movement.status),
              }"
            >
              {{ movement.status_label }}
            </em>
          </header>

          <div class="flex items-center justify-between p-3 bg-slate-50 border border-slate-100 rounded-lg text-xs font-bold text-slate-700">
            <span class="flex items-center gap-1.5">
              <MapPin :size="17" class="text-brand-green-700" aria-hidden="true" />

              {{
                movement.from_area?.name ??
                'Sin origen'
              }}
            </span>

            <ArrowRight
              :size="18"
              class="text-brand-orange-800 shrink-0 mx-2"
              aria-hidden="true"
            />

            <span class="flex items-center gap-1.5">
              <Factory :size="17" class="text-brand-green-700" aria-hidden="true" />

              {{
                movement.to_area?.name ??
                'Sin destino'
              }}
            </span>
          </div>

          <dl class="grid gap-2 m-0 text-xs border-t border-slate-100 pt-3">
            <div class="flex justify-between items-center gap-3">
              <dt class="text-slate-500">Proceso</dt>
              <dd class="m-0 font-bold text-slate-800 truncate">
                {{
                  movement.operation_process?.name ??
                  movement.process?.name ??
                  'No disponible'
                }}
              </dd>
            </div>

            <div class="flex justify-between items-center gap-3">
              <dt class="text-slate-500">Cantidad</dt>
              <dd class="m-0 font-bold text-slate-900 font-mono">
                {{ movement.effective_quantity }}
                <span class="text-slate-400 font-normal">de {{ movement.quantity }}</span>
              </dd>
            </div>

            <div class="flex justify-between items-center gap-3">
              <dt class="text-slate-500">Fecha</dt>
              <dd class="m-0 font-medium text-slate-700">
                {{
                  formatDateTime(
                    movement.created_at,
                  )
                }}
              </dd>
            </div>
          </dl>

          <p v-if="movement.notes" class="m-0 text-slate-600 text-xs leading-relaxed line-clamp-2 italic">
            {{ movement.notes }}
          </p>

          <footer class="grid grid-cols-[repeat(auto-fit,minmax(7rem,1fr))] gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              class="inline-flex min-h-[2.4rem] items-center justify-center gap-1.5 px-2.5 text-brand-orange-900 bg-brand-orange-100 border border-brand-orange-200 hover:bg-brand-orange-200/80 rounded-md font-bold text-xs cursor-pointer transition-colors disabled:opacity-50"
              :disabled="
                detailLoadingId === movement.id
              "
              @click="openDetail(movement)"
            >
              <Eye :size="18" aria-hidden="true" />
              Ver detalle
            </button>

            <button
              type="button"
              class="inline-flex min-h-[2.4rem] items-center justify-center gap-1.5 px-2.5 text-sky-800 bg-sky-100 border border-sky-200 hover:bg-sky-200/80 rounded-md font-bold text-xs cursor-pointer transition-colors"
              @click="openOperationLogs(movement)"
            >
              <Activity :size="18" aria-hidden="true" />
              Trabajadores y avances
            </button>

            <button
              v-if="canReceive(movement)"
              type="button"
              class="inline-flex min-h-[2.4rem] items-center justify-center gap-1.5 px-2.5 text-emerald-800 bg-emerald-100 border border-emerald-200 hover:bg-emerald-200/80 rounded-md font-bold text-xs cursor-pointer transition-colors disabled:opacity-50"
              :disabled="
                receivingId === movement.id
              "
              @click="receiveMovement(movement)"
            >
              <CheckCheck
                :size="18"
                aria-hidden="true"
              />

              {{
                receivingId === movement.id
                  ? 'Recibiendo...'
                  : 'Confirmar recepción'
              }}
            </button>

            <button
              v-if="getQuickDispatchAction(movement)"
              type="button"
              class="inline-flex min-h-[2.4rem] items-center justify-center gap-1.5 px-2.5 text-brand-orange-900 bg-brand-orange-100 border border-brand-orange-200 hover:bg-brand-orange-200/80 rounded-md font-bold text-xs cursor-pointer transition-colors"
              @click="
                openCreateForm(
                  getQuickDispatchAction(movement)!.cutId,
                  getQuickDispatchAction(movement)!.targetKey
                )
              "
            >
              <ArrowRight
                :size="18"
                aria-hidden="true"
              />
              {{ getQuickDispatchAction(movement)!.label }}
            </button>
          </footer>
        </article>
      </div>

      <div class="hidden lg:block bg-white border border-slate-200 rounded-xl overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-sm">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-700 text-xs font-bold uppercase tracking-wider">
                <th class="p-3.5 px-4">Corte / objetivo</th>
                <th class="p-3.5 px-4">Ruta</th>
                <th class="p-3.5 px-4">Proceso</th>
                <th class="p-3.5 px-4">Cantidad</th>
                <th class="p-3.5 px-4">Estado</th>
                <th class="p-3.5 px-4">Fecha</th>
                <th class="p-3.5 px-4 text-right" aria-label="Acciones" />
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-200 text-slate-800">
              <tr
                v-for="movement in movements"
                :key="movement.id"
                class="hover:bg-slate-50/80 transition-colors"
              >
                <td class="p-3.5 px-4">
                  <div class="grid">
                    <strong class="font-mono text-slate-900 font-bold">
                      {{
                        movement.garment_cut?.code ??
                        `#${movement.id}`
                      }}
                    </strong>

                    <span class="text-slate-500 text-xs">
                      {{ movement.target_type_label }}
                    </span>
                  </div>
                </td>

                <td class="p-3.5 px-4">
                  <div class="flex items-center gap-1.5 text-slate-700 text-xs font-medium">
                    <span class="truncate max-w-[7rem]">
                      {{
                        movement.from_area?.name ??
                        '—'
                      }}
                    </span>

                    <ArrowRight
                      :size="15"
                      class="text-brand-orange-800 shrink-0 mx-1"
                      aria-hidden="true"
                    />

                    <span class="truncate max-w-[7rem]">
                      {{
                        movement.to_area?.name ??
                        '—'
                      }}
                    </span>
                  </div>
                </td>

                <td class="p-3.5 px-4">
                  <div class="grid">
                    <strong class="text-slate-900 font-bold text-xs">
                      {{
                        movement.process?.name ??
                        'No disponible'
                      }}
                    </strong>

                    <span class="text-slate-500 text-xs">
                      {{
                        movement.operation_process
                          ?.name ??
                        'Sin operación'
                      }}
                    </span>
                  </div>
                </td>

                <td class="p-3.5 px-4 font-mono text-xs">
                  <strong class="text-slate-900 font-bold">
                    {{ movement.effective_quantity }}
                  </strong>
                  <span class="text-slate-400">/ {{ movement.quantity }}</span>
                </td>

                <td class="p-3.5 px-4">
                  <span
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold"
                    :class="{
                      'text-amber-800 bg-amber-100': movement.status === 'pending',
                      'text-sky-800 bg-sky-100': movement.status === 'received',
                      'text-indigo-800 bg-indigo-100': movement.status === 'in_progress',
                      'text-brand-orange-900 bg-brand-orange-100': movement.status === 'partially_completed',
                      'text-emerald-800 bg-emerald-100': movement.status === 'completed',
                      'text-rose-800 bg-rose-100': ['cancelled', 'with_incident', 'delayed'].includes(movement.status),
                    }"
                  >
                    {{ movement.status_label }}
                  </span>
                </td>

                <td class="p-3.5 px-4 text-slate-600 text-xs whitespace-nowrap">
                  {{
                    formatDateTime(
                      movement.created_at,
                    )
                  }}
                </td>

                <td class="p-3.5 px-4 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      type="button"
                      class="p-1.5 text-slate-600 hover:text-brand-orange-800 hover:bg-slate-100 rounded-md transition-colors border-0 cursor-pointer disabled:opacity-50"
                      title="Ver detalle"
                      :disabled="
                        detailLoadingId ===
                        movement.id
                      "
                      @click="openDetail(movement)"
                    >
                      <Eye
                        :size="18"
                        aria-hidden="true"
                      />
                    </button>

                    <button
                      type="button"
                      class="p-1.5 text-slate-600 hover:text-sky-700 hover:bg-slate-100 rounded-md transition-colors border-0 cursor-pointer"
                      title="Trabajadores y avances"
                      @click="openOperationLogs(movement)"
                    >
                      <Activity :size="18" aria-hidden="true" />
                    </button>

                    <button
                      v-if="canReceive(movement)"
                      type="button"
                      class="p-1.5 text-emerald-700 hover:text-emerald-800 hover:bg-emerald-50 rounded-md transition-colors border-0 cursor-pointer disabled:opacity-50"
                      title="Confirmar recepción"
                      :disabled="
                        receivingId === movement.id
                      "
                      @click="
                        receiveMovement(movement)
                      "
                    >
                      <CheckCheck
                        :size="18"
                        aria-hidden="true"
                      />
                    </button>

                    <button
                      v-if="getQuickDispatchAction(movement)"
                      type="button"
                      class="p-1.5 text-brand-orange-800 hover:bg-brand-orange-50 rounded-md transition-colors border-0 cursor-pointer"
                      :title="getQuickDispatchAction(movement)!.label"
                      @click="
                        openCreateForm(
                          getQuickDispatchAction(movement)!.cutId,
                          getQuickDispatchAction(movement)!.targetKey
                        )
                      "
                    >
                      <ArrowRight
                        :size="18"
                        aria-hidden="true"
                      />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <footer class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 bg-white border border-slate-200 rounded-lg text-xs text-slate-600">
        <p class="m-0">
          Mostrando
          <strong class="text-slate-900 font-bold">{{ pagination.from ?? 0 }}</strong>
          a
          <strong class="text-slate-900 font-bold">{{ pagination.to ?? 0 }}</strong>
          de
          <strong class="text-slate-900 font-bold">{{ pagination.total }}</strong>
          movimientos
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

    <ProductionMovementFormDialog
      :open="formOpen"
      :cuts="cuts"
      :processes="processes"
      :initial-cut-id="initialCutId"
      :initial-target-key="initialTargetKey"
      @close="closeCreateForm"
      @saved="handleMovementSaved"
    />

    <ProductionMovementDetailDialog
      :open="detailOpen"
      :movement="detailMovement"
      @close="closeDetail"
    />

    <ProductionOperationLogsDialog
      :open="operationLogsOpen"
      :movement="operationMovement"
      @close="closeOperationLogs"
      @changed="handleOperationLogsChanged"
    />
  </section>
</template>