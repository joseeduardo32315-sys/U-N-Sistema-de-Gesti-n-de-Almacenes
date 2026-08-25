<script setup lang="ts">
import {
  computed,
  onMounted,
  reactive,
  ref,
} from 'vue'

import {
  ChevronLeft,
  ChevronRight,
  Eye,
  MapPin,
  Pencil,
  Plus,
  RotateCcw,
  Route,
  Scissors,
  Search,
  Shirt,
} from 'lucide-vue-next'

import Swal from 'sweetalert2'

import { PERMISSIONS } from '@/config/permissions'
import { areasService } from '@/modules/areas/services/areas.service'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import GarmentCutDetailDialog from '@/modules/garment-cuts/components/GarmentCutDetailDialog.vue'
import GarmentCutFormDialog from '@/modules/garment-cuts/components/GarmentCutFormDialog.vue'
import { garmentCutsService } from '@/modules/garment-cuts/services/garment-cuts.service'
import { garmentModelsService } from '@/modules/garment-models/services/garment-models.service'
import { productionOrdersService } from '@/modules/production-orders/services/production-orders.service'
import { sizesService } from '@/modules/sizes/services/sizes.service'
import { getApiErrorMessage } from '@/utils/api-error'
import GarmentCutClassificationDialog from '@/modules/garment-cut-classification/components/GarmentCutClassificationDialog.vue'

import type { Area } from '@/modules/areas/types/area.types'
import type {
  GarmentCut,
  GarmentCutStatus,
} from '@/modules/garment-cuts/types/garment-cut.types'
import type { GarmentModel } from '@/modules/garment-models/types/garment-model.types'
import type { ProductionOrder } from '@/modules/production-orders/types/production-order.types'
import type { Size } from '@/modules/sizes/types/size.types'
import type { PaginationMeta } from '@/types/api'

interface Filters {
  search: string
  productionOrderId: number | ''
  garmentModelId: number | ''
  currentAreaId: number | ''
  status: GarmentCutStatus | ''
  perPage: number
}

const authStore = useAuthStore()

const cuts = ref<GarmentCut[]>([])
const orders = ref<ProductionOrder[]>([])
const models = ref<GarmentModel[]>([])
const areas = ref<Area[]>([])
const sizes = ref<Size[]>([])

const loading = ref(false)
const detailLoadingId = ref<number | null>(null)
const editLoadingId = ref<number | null>(null)

const formOpen = ref(false)
const detailOpen = ref(false)
const classificationOpen = ref(false)

const selectedCut = ref<GarmentCut | null>(null)
const detailCut = ref<GarmentCut | null>(null)
  const classificationCut = ref<GarmentCut | null>(
  null,
)

const filters = reactive<Filters>({
  search: '',
  productionOrderId: '',
  garmentModelId: '',
  currentAreaId: '',
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

const canLoadModels = computed<boolean>(() => {
  return authStore.can(
    PERMISSIONS.garmentModels.view,
  )
})

const canLoadAreas = computed<boolean>(() => {
  return authStore.can(
    PERMISSIONS.employees.view,
  )
})

function statusClass(
  status: GarmentCutStatus,
): string {
  const classes: Record<GarmentCutStatus, string> = {
    registered: 'cut-status--registered',
    in_progress: 'cut-status--progress',
    partially_completed: 'cut-status--partial',
    completed: 'cut-status--completed',
    cancelled: 'cut-status--cancelled',
    with_incident: 'cut-status--incident',
    delayed: 'cut-status--delayed',
  }

  return classes[status]
}

function canCreate(): boolean {
  return (
    authStore.can(PERMISSIONS.cuts.create) &&
    canLoadModels.value
  )
}

function canEdit(): boolean {
  return authStore.can(PERMISSIONS.cuts.update)
}

async function loadCuts(page = 1): Promise<void> {
  loading.value = true

  try {
    const response = await garmentCutsService.list({
      search: filters.search.trim(),
      production_order_id:
        filters.productionOrderId,
      garment_model_id:
        filters.garmentModelId,
      current_area_id:
        filters.currentAreaId,
      status: filters.status,
      per_page: filters.perPage,
      page,
    })

    cuts.value = response.data
    pagination.value = response.meta
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible cargar los cortes',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    loading.value = false
  }
}

async function loadCatalogs(): Promise<void> {
  try {
    const [
      ordersResponse,
      sizesResponse,
    ] = await Promise.all([
      productionOrdersService.list({
        per_page: 100,
      }),
      sizesService.list({
        status: 'active',
      }),
    ])

    orders.value = ordersResponse.data
    sizes.value = sizesResponse

    if (canLoadModels.value) {
      const modelsResponse =
        await garmentModelsService.list({
          per_page: 100,
        })

      models.value = modelsResponse.data
    }

    if (canLoadAreas.value) {
      areas.value = await areasService.list()
    }
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible cargar los catálogos',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  }
}

function applyFilters(): void {
  void loadCuts(1)
}

function clearFilters(): void {
  filters.search = ''
  filters.productionOrderId = ''
  filters.garmentModelId = ''
  filters.currentAreaId = ''
  filters.status = ''
  filters.perPage = 15

  void loadCuts(1)
}

function openCreateForm(): void {
  selectedCut.value = null
  formOpen.value = true
}

async function openEditForm(
  cut: GarmentCut,
): Promise<void> {
  editLoadingId.value = cut.id

  try {
    selectedCut.value =
      await garmentCutsService.show(cut.id)

    formOpen.value = true
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible consultar el corte',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    editLoadingId.value = null
  }
}

function closeForm(): void {
  formOpen.value = false
  selectedCut.value = null
}

async function openDetail(
  cut: GarmentCut,
): Promise<void> {
  detailLoadingId.value = cut.id

  try {
    detailCut.value =
      await garmentCutsService.show(cut.id)

    detailOpen.value = true
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible consultar el corte',
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
  detailCut.value = null
}

async function handleSaved(
  _cut: GarmentCut,
  message: string,
): Promise<void> {
  closeForm()

  await Swal.fire({
    title: message,
    icon: 'success',
    timer: 1700,
    showConfirmButton: false,
  })

  await loadCuts(pagination.value.current_page)
}

function previousPage(): void {
  if (pagination.value.current_page > 1) {
    void loadCuts(
      pagination.value.current_page - 1,
    )
  }
}

function nextPage(): void {
  if (
    pagination.value.current_page <
    pagination.value.last_page
  ) {
    void loadCuts(
      pagination.value.current_page + 1,
    )
  }
}

function canViewClassification(): boolean {
  return authStore.can(
    PERMISSIONS.processes.view,
  )
}

async function openClassification(
  cut: GarmentCut,
): Promise<void> {
  if (cut.status !== 'in_progress') {
    await Swal.fire({
      title: 'El corte todavía no está en proceso',
      text: 'Primero debes enviar el corte desde Corte hacia Diseño y recibir el movimiento.',
      icon: 'warning',
      confirmButtonText: 'Aceptar',
    })

    return
  }

  if (normalizedAreaName(cut) !== 'diseño') {
    await Swal.fire({
      title: 'El corte no está en Diseño',
      text: `El corte se encuentra actualmente en ${
        cut.current_area?.name ?? 'un área no definida'
      }. La clasificación debe realizarse en Diseño.`,
      icon: 'warning',
      confirmButtonText: 'Aceptar',
    })

    return
  }

  classificationCut.value = cut
  classificationOpen.value = true
}

function closeClassification(): void {
  classificationOpen.value = false
  classificationCut.value = null
}

async function handleClassificationSaved(
  _cut: GarmentCut,
  message: string,
): Promise<void> {
  closeClassification()

  await Swal.fire({
    title: message,
    icon: 'success',
    timer: 1700,
    showConfirmButton: false,
  })

  await loadCuts(pagination.value.current_page)
}

function normalizedAreaName(
  cut: GarmentCut,
): string {
  return (
    cut.current_area?.name
      ?.trim()
      .toLocaleLowerCase('es') ?? ''
  )
}

function cutCanBeClassified(
  cut: GarmentCut,
): boolean {
  return (
    cut.status === 'in_progress' &&
    normalizedAreaName(cut) === 'diseño'
  )
}

onMounted(async () => {
  await Promise.all([
    loadCuts(),
    loadCatalogs(),
  ])
})
</script>

<template>
  <section class="grid gap-5">
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <p class="m-0 mb-2 text-brand-orange-800 text-xs font-extrabold tracking-wider uppercase">
          Control productivo
        </p>

        <h2 class="m-0 text-2xl font-bold text-slate-900">Cortes de producción</h2>

        <p class="mt-2 mb-0 text-slate-600 leading-relaxed text-sm max-w-2xl">
          Administra lotes, modelos, tallas, cantidades y
          ubicación actual dentro del taller.
        </p>
      </div>

      <button
        v-if="canCreate()"
        type="button"
        class="inline-flex w-full sm:w-auto min-h-[3rem] items-center justify-center gap-2 px-4 text-white bg-brand-orange-800 hover:bg-brand-orange-900 rounded-md font-[750] text-sm cursor-pointer transition-colors border-0"
        @click="openCreateForm"
      >
        <Plus :size="20" aria-hidden="true" />
        Registrar corte
      </button>
    </header>

    <form
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1.5fr_repeat(5,1fr)_auto] gap-3 p-4 bg-white border border-slate-200 rounded-lg"
      @submit.prevent="applyFilters"
    >
      <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3 min-h-[3rem] px-3 bg-white border border-slate-300 rounded-md focus-within:border-brand-green-700 focus-within:ring-3 focus-within:ring-brand-green-700/13 transition-all">
        <Search :size="20" class="text-slate-400" aria-hidden="true" />

        <input
          v-model="filters.search"
          type="search"
          maxlength="150"
          placeholder="Buscar por corte, modelo, orden o notas"
          class="w-full min-w-0 bg-transparent border-0 outline-hidden text-slate-900 text-sm placeholder:text-slate-400"
        />
      </div>

      <select
        v-model="filters.productionOrderId"
        aria-label="Filtrar por orden"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">
          Todas las órdenes
        </option>

        <option
          v-for="order in orders"
          :key="order.id"
          :value="order.id"
        >
          {{ order.order_code }}
        </option>
      </select>

      <select
        v-if="models.length > 0"
        v-model="filters.garmentModelId"
        aria-label="Filtrar por modelo"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">
          Todos los modelos
        </option>

        <option
          v-for="model in models"
          :key="model.id"
          :value="model.id"
        >
          {{ model.code }}
        </option>
      </select>

      <select
        v-if="areas.length > 0"
        v-model="filters.currentAreaId"
        aria-label="Filtrar por área"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">
          Todas las áreas
        </option>

        <option
          v-for="area in areas"
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
        <option value="registered">Registrados</option>
        <option value="in_progress">En proceso</option>
        <option value="partially_completed">
          Parcialmente completados
        </option>
        <option value="completed">Completados</option>
        <option value="cancelled">Cancelados</option>
        <option value="with_incident">
          Con incidencia
        </option>
        <option value="delayed">Retrasados</option>
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

      <div class="flex gap-2">
        <button
          type="submit"
          class="inline-flex min-h-[3rem] items-center justify-center gap-2 px-4 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-bold text-sm transition-colors cursor-pointer border-0 disabled:opacity-60"
          :disabled="loading"
        >
          <Search :size="19" aria-hidden="true" />
          Buscar
        </button>

        <button
          type="button"
          class="inline-flex min-h-[3rem] items-center justify-center gap-2 px-4 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-bold text-sm transition-colors cursor-pointer disabled:opacity-60"
          :disabled="loading"
          @click="clearFilters"
        >
          <RotateCcw :size="19" aria-hidden="true" />
          Limpiar
        </button>
      </div>
    </form>

    <div
      v-if="loading"
      class="grid place-content-center min-h-[16rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <div class="w-9 h-9 border-4 border-brand-green-100 border-t-brand-green-700 rounded-full animate-spin mx-auto" />
      <p class="text-slate-600 text-sm">Cargando cortes...</p>
    </div>

    <div
      v-else-if="cuts.length === 0"
      class="grid place-content-center min-h-[16rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <Scissors :size="44" class="text-slate-400 mx-auto" aria-hidden="true" />

      <h3 class="m-0 text-lg font-bold text-slate-900">No se encontraron cortes</h3>

      <p class="m-0 text-slate-600 text-sm">
        Modifica los filtros o registra un nuevo corte.
      </p>
    </div>

    <template v-else>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <article
          v-for="cut in cuts"
          :key="cut.id"
          class="flex flex-col p-5 bg-white border border-slate-200 rounded-xl shadow-xs gap-3"
        >
          <header class="flex items-start justify-between gap-3 pb-3 border-b border-slate-100">
            <div class="flex items-center gap-3 min-w-0">
              <div class="flex w-10 h-10 shrink-0 items-center justify-center text-brand-orange-800 bg-brand-orange-100 rounded-lg">
                <Scissors
                  :size="22"
                  aria-hidden="true"
                />
              </div>

              <span class="grid min-w-0">
                <strong class="truncate font-mono font-bold text-slate-900 text-base">{{ cut.code }}</strong>

                <small class="truncate text-slate-500 text-xs">
                  {{
                    cut.production_order?.order_code ??
                    'Sin orden'
                  }}
                </small>
              </span>
            </div>

            <em
              class="not-italic inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold shrink-0"
              :class="{
                'text-slate-700 bg-slate-100': cut.status === 'registered',
                'text-sky-800 bg-sky-100': cut.status === 'in_progress',
                'text-indigo-800 bg-indigo-100': cut.status === 'partially_completed',
                'text-emerald-800 bg-emerald-100': cut.status === 'completed',
                'text-rose-800 bg-rose-100': cut.status === 'cancelled',
                'text-amber-800 bg-amber-100': cut.status === 'with_incident',
                'text-orange-800 bg-orange-100': cut.status === 'delayed',
              }"
            >
              {{ cut.status_label }}
            </em>
          </header>

          <div class="flex items-center gap-2.5 p-3 bg-slate-50 border border-slate-200/60 rounded-lg">
            <Shirt :size="19" class="text-brand-green-800 shrink-0" aria-hidden="true" />

            <span class="grid min-w-0">
              <strong class="truncate text-slate-900 font-bold text-xs">
                {{
                  cut.garment_model?.code ??
                  'Sin modelo'
                }}
              </strong>

              <small class="truncate text-slate-500 text-[11px]">
                {{ cut.garment_model?.name }}
              </small>
            </span>
          </div>

          <div class="grid grid-cols-2 gap-2 p-3 bg-slate-50 border border-slate-200/60 rounded-lg text-center">
            <div>
              <span class="block text-slate-400 text-[11px]">Total de piezas</span>
              <strong class="text-slate-900 font-extrabold text-base">{{ cut.total_pieces }}</strong>
            </div>

            <div>
              <span class="block text-slate-400 text-[11px]">Tallas</span>
              <strong class="text-slate-900 font-extrabold text-base">{{ cut.total_sizes }}</strong>
            </div>
          </div>

          <div class="flex items-center gap-2 text-xs text-slate-600">
            <MapPin :size="18" class="text-slate-400 shrink-0" aria-hidden="true" />

            <span class="truncate font-medium">
              {{
                cut.current_area?.name ??
                'Sin área asignada'
              }}
            </span>
          </div>

          <p v-if="cut.description" class="m-0 text-xs text-slate-500 line-clamp-2 leading-relaxed">
            {{ cut.description }}
          </p>

          <footer class="flex flex-wrap items-center gap-2 pt-3 border-t border-slate-100 mt-auto">
            <button
              type="button"
              class="flex-1 inline-flex min-h-[2.35rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-bold text-xs transition-colors cursor-pointer disabled:opacity-50"
              :disabled="
                detailLoadingId === cut.id
              "
              @click="openDetail(cut)"
            >
              <Eye :size="18" aria-hidden="true" />
              Ver detalle
            </button>

            <button
              v-if="canViewClassification()"
              type="button"
              class="flex-1 inline-flex min-h-[2.35rem] items-center justify-center gap-1.5 px-3 rounded-md font-bold text-xs transition-colors border-0 cursor-pointer"
              :class="
                cutCanBeClassified(cut)
                  ? 'text-brand-orange-900 bg-brand-orange-100 hover:bg-brand-orange-200/80'
                  : 'text-slate-400 bg-slate-100 cursor-not-allowed'
              "
              :title="
                cutCanBeClassified(cut)
                  ? 'Clasificar corte'
                  : 'El corte debe estar en proceso y en Diseño'
              "
              @click="openClassification(cut)"
            >
              <Route
                :size="18"
                aria-hidden="true"
              />

              Clasificación
            </button>

            <button
              v-if="canEdit()"
              type="button"
              class="flex-1 inline-flex min-h-[2.35rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-md font-bold text-xs transition-colors border-0 cursor-pointer disabled:opacity-50"
              :disabled="editLoadingId === cut.id"
              @click="openEditForm(cut)"
            >
              <Pencil :size="18" aria-hidden="true" />
              Editar
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
          cortes
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

    <GarmentCutFormDialog
      :open="formOpen"
      :cut="selectedCut"
      :orders="orders"
      :models="models"
      :sizes="sizes"
      @close="closeForm"
      @saved="handleSaved"
    />

    <GarmentCutDetailDialog
      :open="detailOpen"
      :cut="detailCut"
      @close="closeDetail"
    />

    <GarmentCutClassificationDialog
      :open="classificationOpen"
      :cut="classificationCut"
      @close="closeClassification"
      @saved="handleClassificationSaved"
    />
  </section>
</template>