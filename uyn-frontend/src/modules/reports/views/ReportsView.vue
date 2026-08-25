<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import {
  Activity,
  ChevronLeft,
  ChevronRight,
  Download,
  FilterX,
  RefreshCw,
  RotateCcw,
  Scissors,
  Search,
  Trash2,
} from 'lucide-vue-next'
import Swal from 'sweetalert2'

import { areasService } from '@/modules/areas/services/areas.service'
import { garmentModelsService } from '@/modules/garment-models/services/garment-models.service'
import { processesService } from '@/modules/processes/services/processes.service'
import { reportsService } from '@/modules/reports/services/reports.service'
import { getApiErrorMessage } from '@/utils/api-error'

import type { Area } from '@/modules/areas/types/area.types'
import type { GarmentModel } from '@/modules/garment-models/types/garment-model.types'
import type { ProductionProcess } from '@/modules/processes/types/process.types'
import type {
  ProductionCutReportItem,
  ProductionLossReportItem,
  ProductionProcessReportItem,
  ProductionReworkReportItem,
} from '@/modules/reports/types/reports.types'
import type { PaginationMeta } from '@/types/api'

type ReportTab = 'cuts' | 'processes' | 'losses' | 'reworks'

// State
const activeTab = ref<ReportTab>('cuts')
const loading = ref(false)

// Catalogs
const models = ref<GarmentModel[]>([])
const areas = ref<Area[]>([])
const processes = ref<ProductionProcess[]>([])
const catalogsLoading = ref(false)

// Data arrays
const cutsReport = ref<ProductionCutReportItem[]>([])
const processesReport = ref<ProductionProcessReportItem[]>([])
const lossesReport = ref<ProductionLossReportItem[]>([])
const reworksReport = ref<ProductionReworkReportItem[]>([])

// Query params & pagination
const pagination = ref<PaginationMeta>({
  current_page: 1,
  from: null,
  last_page: 1,
  per_page: 20,
  to: null,
  total: 0,
})

const filters = reactive({
  dateFrom: '',
  dateTo: '',
  status: '',
  currentAreaId: '' as number | '',
  garmentModelId: '' as number | '',
  processId: '' as number | '',
  groupBy: 'garment_cut' as 'garment_cut' | 'process' | 'responsible_employee',
  search: '',
  perPage: 20,
})

// Check dates validation
function validateDates(): boolean {
  if (filters.dateFrom && filters.dateTo && filters.dateTo < filters.dateFrom) {
    void Swal.fire({
      title: 'Rango de fechas no válido',
      text: 'La fecha final debe ser posterior o igual a la fecha inicial.',
      icon: 'warning',
      confirmButtonText: 'Aceptar',
    })
    return false
  }
  return true
}

// Clear filters
function clearFilters(): void {
  filters.dateFrom = ''
  filters.dateTo = ''
  filters.status = ''
  filters.currentAreaId = ''
  filters.garmentModelId = ''
  filters.processId = ''
  filters.groupBy = 'garment_cut'
  filters.search = ''
  filters.perPage = 20
  void loadReport(1)
}

// Load catalogs
async function loadCatalogs(): Promise<void> {
  catalogsLoading.value = true
  try {
    const [areasData, processesData, modelsData] = await Promise.all([
      areasService.list(),
      processesService.list(),
      garmentModelsService.list({ per_page: 100 }),
    ])
    areas.value = areasData
    processes.value = processesData
    models.value = modelsData.data
  } catch (error) {
    console.error('Error cargando catálogos:', error)
  } finally {
    catalogsLoading.value = false
  }
}

// Load data based on active tab
async function loadReport(page = 1): Promise<void> {
  if (!validateDates()) return
  loading.value = true

  try {
    const baseQuery = {
      from: filters.dateFrom,
      to: filters.dateTo,
      per_page: filters.perPage,
      page,
    }

    if (activeTab.value === 'cuts') {
      const response = await reportsService.getProductionCuts({
        ...baseQuery,
        status: filters.status || undefined,
        current_area_id: filters.currentAreaId || undefined,
        garment_model_id: filters.garmentModelId || undefined,
        search: filters.search.trim() || undefined,
      })
      cutsReport.value = response.data
      pagination.value = response.meta
    } else if (activeTab.value === 'processes') {
      const response = await reportsService.getProductionProcesses({
        ...baseQuery,
        process_id: filters.processId || undefined,
        status: filters.status || undefined,
      })
      processesReport.value = response.data
    } else if (activeTab.value === 'losses') {
      const response = await reportsService.getProductionLosses({
        ...baseQuery,
        group_by: filters.groupBy,
        status: filters.status || undefined,
      })
      lossesReport.value = response.data
    } else if (activeTab.value === 'reworks') {
      const response = await reportsService.getProductionReworks({
        ...baseQuery,
        process_id: filters.processId || undefined,
      })
      reworksReport.value = response.data
    }
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible cargar el reporte',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    loading.value = false
  }
}

// Export CSV trigger
async function exportCsv(): Promise<void> {
  if (!validateDates()) return
  loading.value = true

  try {
    const baseQuery = {
      from: filters.dateFrom,
      to: filters.dateTo,
    }

    if (activeTab.value === 'cuts') {
      await reportsService.exportProductionCuts({
        ...baseQuery,
        status: filters.status || undefined,
        current_area_id: filters.currentAreaId || undefined,
        garment_model_id: filters.garmentModelId || undefined,
        search: filters.search.trim() || undefined,
      })
    } else if (activeTab.value === 'processes') {
      await reportsService.exportProductionProcesses({
        ...baseQuery,
        process_id: filters.processId || undefined,
        status: filters.status || undefined,
      })
    } else if (activeTab.value === 'losses') {
      await reportsService.exportProductionLosses({
        ...baseQuery,
        group_by: filters.groupBy,
        status: filters.status || undefined,
      })
    } else if (activeTab.value === 'reworks') {
      await reportsService.exportProductionReworks({
        ...baseQuery,
        process_id: filters.processId || undefined,
      })
    }

    void Swal.fire({
      title: 'Exportación exitosa',
      text: 'El reporte se ha descargado correctamente.',
      icon: 'success',
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000,
    })
  } catch (error) {
    await Swal.fire({
      title: 'Error de exportación',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    loading.value = false
  }
}

// Watch active tab to load appropriate data
watch(activeTab, () => {
  clearFilters()
})

// Pagination navigation
function previousPage(): void {
  if (pagination.value.current_page > 1) {
    void loadReport(pagination.value.current_page - 1)
  }
}

function nextPage(): void {
  if (pagination.value.current_page < pagination.value.last_page) {
    void loadReport(pagination.value.current_page + 1)
  }
}

onMounted(async () => {
  await Promise.all([loadCatalogs(), loadReport()])
})
</script>

<template>
  <section class="grid gap-5">
    <header class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-4">
      <div>
        <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold tracking-wider uppercase">Control administrativo</p>
        <h2 class="m-0 text-2xl font-bold text-slate-900">Consulta y Reportes de Producción</h2>
        <p class="m-0 mt-1 text-slate-600 text-sm">Analiza el rendimiento, productividad, mermas y reprocesos del taller.</p>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <button
          type="button"
          class="inline-flex min-h-[2.75rem] items-center gap-2 px-4 text-white bg-brand-green-700 hover:bg-brand-green-800 border border-brand-green-700 rounded-md font-bold text-sm cursor-pointer transition-colors disabled:opacity-50"
          :disabled="loading"
          @click="exportCsv"
        >
          <Download :size="19" aria-hidden="true" />
          Exportar CSV
        </button>

        <button
          type="button"
          class="inline-flex min-h-[2.75rem] items-center gap-2 px-4 text-brand-green-800 bg-brand-green-100 hover:bg-brand-green-200/70 border border-brand-green-200 rounded-md font-bold text-sm cursor-pointer transition-colors disabled:opacity-50"
          :disabled="loading"
          @click="loadReport(activeTab === 'cuts' ? pagination.current_page : 1)"
        >
          <RefreshCw
            :size="19"
            :class="{ 'animate-spin': loading }"
            aria-hidden="true"
          />
          Actualizar
        </button>
      </div>
    </header>

    <!-- Tabs Navigation -->
    <nav class="flex flex-wrap gap-2 border-b-2 border-slate-200 pb-[2px]">
      <button
        type="button"
        class="inline-flex items-center gap-2 px-4 py-3 text-slate-600 font-bold border-b-2 border-transparent hover:text-brand-green-800 hover:bg-brand-green-50 rounded-t-md text-sm cursor-pointer transition-colors"
        :class="{ 'text-brand-green-700 border-b-brand-green-700 bg-brand-green-100/70!': activeTab === 'cuts' }"
        @click="activeTab = 'cuts'"
      >
        <Scissors :size="18" aria-hidden="true" />
        Rendimiento de Cortes
      </button>

      <button
        type="button"
        class="inline-flex items-center gap-2 px-4 py-3 text-slate-600 font-bold border-b-2 border-transparent hover:text-brand-green-800 hover:bg-brand-green-50 rounded-t-md text-sm cursor-pointer transition-colors"
        :class="{ 'text-brand-green-700 border-b-brand-green-700 bg-brand-green-100/70!': activeTab === 'processes' }"
        @click="activeTab = 'processes'"
      >
        <Activity :size="18" aria-hidden="true" />
        Eficiencia de Procesos
      </button>

      <button
        type="button"
        class="inline-flex items-center gap-2 px-4 py-3 text-slate-600 font-bold border-b-2 border-transparent hover:text-brand-green-800 hover:bg-brand-green-50 rounded-t-md text-sm cursor-pointer transition-colors"
        :class="{ 'text-brand-green-700 border-b-brand-green-700 bg-brand-green-100/70!': activeTab === 'losses' }"
        @click="activeTab = 'losses'"
      >
        <Trash2 :size="18" aria-hidden="true" />
        Mermas y Pérdidas
      </button>

      <button
        type="button"
        class="inline-flex items-center gap-2 px-4 py-3 text-slate-600 font-bold border-b-2 border-transparent hover:text-brand-green-800 hover:bg-brand-green-50 rounded-t-md text-sm cursor-pointer transition-colors"
        :class="{ 'text-brand-green-700 border-b-brand-green-700 bg-brand-green-100/70!': activeTab === 'reworks' }"
        @click="activeTab = 'reworks'"
      >
        <RotateCcw :size="18" aria-hidden="true" />
        Control de Reprocesos
      </button>
    </nav>

    <!-- Filters Panel -->
    <form class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-4 bg-white border border-slate-200 rounded-xl shadow-xs" @submit.prevent="loadReport(1)">
      <!-- Common Date Filters -->
      <div class="grid gap-1">
        <label for="filter-date-from" class="text-xs font-bold text-slate-900">Desde</label>
        <input id="filter-date-from" v-model="filters.dateFrom" type="date" class="min-h-[2.75rem] px-3 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13" />
      </div>

      <div class="grid gap-1">
        <label for="filter-date-to" class="text-xs font-bold text-slate-900">Hasta</label>
        <input id="filter-date-to" v-model="filters.dateTo" type="date" :min="filters.dateFrom || undefined" class="min-h-[2.75rem] px-3 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13" />
      </div>

      <!-- Tab Specific Filters -->
      <template v-if="activeTab === 'cuts'">
        <div class="grid gap-1">
          <label for="filter-cut-search" class="text-xs font-bold text-slate-900">Buscar Folio</label>
          <input
            id="filter-cut-search"
            v-model="filters.search"
            type="text"
            placeholder="Ej. C-CH-09..."
            maxlength="150"
            class="min-h-[2.75rem] px-3 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
          />
        </div>

        <div class="grid gap-1">
          <label for="filter-cut-status" class="text-xs font-bold text-slate-900">Estado del corte</label>
          <select id="filter-cut-status" v-model="filters.status" class="min-h-[2.75rem] px-3 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13">
            <option value="">Todos</option>
            <option value="registered">Registrado</option>
            <option value="in_progress">En proceso</option>
            <option value="completed">Completado</option>
            <option value="cancelled">Cancelado</option>
          </select>
        </div>

        <div class="grid gap-1">
          <label for="filter-cut-model" class="text-xs font-bold text-slate-900">Modelo de prenda</label>
          <select id="filter-cut-model" v-model="filters.garmentModelId" class="min-h-[2.75rem] px-3 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13">
            <option value="">Todos los modelos</option>
            <option v-for="model in models" :key="model.id" :value="model.id">
              {{ model.code }} - {{ model.name }}
            </option>
          </select>
        </div>

        <div class="grid gap-1">
          <label for="filter-cut-area" class="text-xs font-bold text-slate-900">Área actual</label>
          <select id="filter-cut-area" v-model="filters.currentAreaId" class="min-h-[2.75rem] px-3 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13">
            <option value="">Todas las áreas</option>
            <option v-for="area in areas" :key="area.id" :value="area.id">
              {{ area.name }}
            </option>
          </select>
        </div>
      </template>

      <template v-else-if="activeTab === 'processes'">
        <div class="grid gap-1">
          <label for="filter-process-base" class="text-xs font-bold text-slate-900">Proceso base</label>
          <select id="filter-process-base" v-model="filters.processId" class="min-h-[2.75rem] px-3 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13">
            <option value="">Todos los procesos</option>
            <option v-for="p in processes" :key="p.id" :value="p.id">
              {{ p.name }}
            </option>
          </select>
        </div>

        <div class="grid gap-1">
          <label for="filter-process-status" class="text-xs font-bold text-slate-900">Estado transferencia</label>
          <select id="filter-process-status" v-model="filters.status" class="min-h-[2.75rem] px-3 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13">
            <option value="">Todos</option>
            <option value="pending">Pendiente</option>
            <option value="received">Recibido</option>
            <option value="in_progress">En proceso</option>
            <option value="completed">Completado</option>
            <option value="with_incident">Con incidencia</option>
            <option value="delayed">Retrasado</option>
          </select>
        </div>
      </template>

      <template v-else-if="activeTab === 'losses'">
        <div class="grid gap-1">
          <label for="filter-loss-groupby" class="text-xs font-bold text-slate-900">Agrupar por</label>
          <select id="filter-loss-groupby" v-model="filters.groupBy" class="min-h-[2.75rem] px-3 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13">
            <option value="garment_cut">Corte de prenda</option>
            <option value="process">Proceso base</option>
            <option value="responsible_employee">Trabajador responsable</option>
          </select>
        </div>

        <div class="grid gap-1">
          <label for="filter-loss-status" class="text-xs font-bold text-slate-900">Estado del incidente</label>
          <select id="filter-loss-status" v-model="filters.status" class="min-h-[2.75rem] px-3 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13">
            <option value="">Todos</option>
            <option value="open">Abierto</option>
            <option value="resolved">Resuelto</option>
            <option value="cancelled">Cancelado</option>
          </select>
        </div>
      </template>

      <template v-else-if="activeTab === 'reworks'">
        <div class="grid gap-1">
          <label for="filter-rework-process" class="text-xs font-bold text-slate-900">Proceso base</label>
          <select id="filter-rework-process" v-model="filters.processId" class="min-h-[2.75rem] px-3 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13">
            <option value="">Todos los procesos</option>
            <option v-for="p in processes" :key="p.id" :value="p.id">
              {{ p.name }}
            </option>
          </select>
        </div>
      </template>

      <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-1">
        <button type="submit" class="flex-1 inline-flex min-h-[2.75rem] items-center justify-center gap-2 px-4 text-white bg-brand-green-700 hover:bg-brand-green-800 border border-brand-green-700 rounded-md font-[750] text-sm cursor-pointer transition-colors disabled:opacity-50" :disabled="loading">
          <Search :size="18" aria-hidden="true" />
          Filtrar
        </button>

        <button type="button" class="flex-1 inline-flex min-h-[2.75rem] items-center justify-center gap-2 px-4 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-[750] text-sm cursor-pointer transition-colors disabled:opacity-50" :disabled="loading" @click="clearFilters">
          <FilterX :size="18" aria-hidden="true" />
          Limpiar
        </button>
      </div>
    </form>

    <!-- Loading State -->
    <div v-if="loading" class="grid place-content-center min-h-[20rem] text-center gap-3 bg-white border border-slate-200 rounded-xl">
      <div class="w-10 h-10 border-4 border-brand-green-100 border-t-brand-green-700 rounded-full animate-spin mx-auto" />
      <p class="m-0 text-slate-600 text-sm">Cargando información del reporte...</p>
    </div>

    <!-- Data Tables -->
    <template v-else>
      <!-- TAB 1: RENDIMIENTO DE CORTES -->
      <div v-if="activeTab === 'cuts'" class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-brand-green-50/80 text-brand-green-950 border-b border-slate-200 font-bold text-xs uppercase">
              <th class="p-3 px-4">Folio</th>
              <th class="p-3 px-4">Modelo</th>
              <th class="p-3 px-4">Orden Maestra</th>
              <th class="p-3 px-4">Área Actual</th>
              <th class="p-3 px-4">Piezas Plan.</th>
              <th class="p-3 px-4">Piezas Efec.</th>
              <th class="p-3 px-4">Mermas Res.</th>
              <th class="p-3 px-4">Progreso</th>
              <th class="p-3 px-4">Estado</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="item in cutsReport" :key="item.id" class="hover:bg-slate-50/60 transition-colors">
              <td class="p-3 px-4 font-bold text-slate-900 font-mono">{{ item.production_order?.code }}-{{ item.id }}</td>
              <td class="p-3 px-4 text-slate-700">{{ item.garment_model?.code ?? 'Sin modelo' }}</td>
              <td class="p-3 px-4 text-slate-700 font-mono">{{ item.production_order?.code ?? '—' }}</td>
              <td class="p-3 px-4 text-slate-700">{{ item.current_area?.name ?? 'Terminado' }}</td>
              <td class="p-3 px-4 font-mono">{{ item.total_pieces }}</td>
              <td class="p-3 px-4 font-mono">{{ item.effective_pieces }}</td>
              <td class="p-3 px-4 font-mono">{{ item.movement_summary?.resolved_loss_quantity ?? 0 }}</td>
              <td class="p-3 px-4">
                <div class="relative flex items-center w-full min-w-[6.25rem] h-5 bg-brand-green-100 rounded-sm overflow-hidden">
                  <div class="h-full bg-brand-green-700 transition-all duration-300" :style="{ width: `${item.progress.processed_percentage}%` }"></div>
                  <span class="absolute w-full text-center text-[0.75rem] font-bold text-brand-green-950">{{ item.progress.processed_percentage }}%</span>
                </div>
              </td>
              <td class="p-3 px-4">
                <span
                  class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold"
                  :class="{
                    'text-brand-green-900 bg-brand-green-100': item.status === 'registered',
                    'text-brand-orange-800 bg-brand-orange-50': item.status === 'in_progress',
                    'text-brand-green-950 bg-brand-green-200': item.status === 'completed',
                    'text-rose-700 bg-rose-50': item.status === 'cancelled',
                  }"
                >
                  {{ item.status_label }}
                </span>
              </td>
            </tr>
            <tr v-if="cutsReport.length === 0">
              <td colspan="9" class="text-center p-8 text-slate-500 text-sm">No se encontraron registros de cortes.</td>
            </tr>
          </tbody>
        </table>

        <!-- Cuts Pagination -->
        <footer v-if="cutsReport.length > 0" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 border-t border-slate-200 text-xs text-slate-600">
          <p class="m-0">
            Mostrando <strong class="font-mono text-slate-900">{{ pagination.from ?? 0 }}</strong> a <strong class="font-mono text-slate-900">{{ pagination.to ?? 0 }}</strong> de <strong class="font-mono text-slate-900">{{ pagination.total }}</strong> registros
          </p>
          <div class="flex items-center gap-2">
            <button type="button" class="inline-flex items-center gap-1 min-h-[2.25rem] px-3 border border-slate-300 rounded-md bg-white text-slate-700 font-bold hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer" :disabled="pagination.current_page <= 1" @click="previousPage">
              <ChevronLeft :size="19" aria-hidden="true" />
              Anterior
            </button>
            <span class="font-medium px-2">Página {{ pagination.current_page }} de {{ pagination.last_page }}</span>
            <button type="button" class="inline-flex items-center gap-1 min-h-[2.25rem] px-3 border border-slate-300 rounded-md bg-white text-slate-700 font-bold hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer" :disabled="pagination.current_page >= pagination.last_page" @click="nextPage">
              Siguiente
              <ChevronRight :size="19" aria-hidden="true" />
            </button>
          </div>
        </footer>
      </div>

      <!-- TAB 2: EFICIENCIA DE PROCESOS -->
      <div v-else-if="activeTab === 'processes'" class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-brand-green-50/80 text-brand-green-950 border-b border-slate-200 font-bold text-xs uppercase">
              <th class="p-3 px-4">Proceso Base</th>
              <th class="p-3 px-4">Suboperación / Proceso</th>
              <th class="p-3 px-4">Cálculo</th>
              <th class="p-3 px-4">Movs.</th>
              <th class="p-3 px-4">Piezas Desp.</th>
              <th class="p-3 px-4">Piezas Rec.</th>
              <th class="p-3 px-4">En Progreso</th>
              <th class="p-3 px-4">Mermas</th>
              <th class="p-3 px-4">Incidencias Ab.</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="(item, idx) in processesReport" :key="idx" class="hover:bg-slate-50/60 transition-colors">
              <td class="p-3 px-4 font-bold text-slate-900">{{ item.process.name }}</td>
              <td class="p-3 px-4 text-slate-700">{{ item.operation_process.name }}</td>
              <td class="p-3 px-4">
                <span
                  class="inline-block px-2 py-0.5 rounded-sm text-xs font-bold uppercase"
                  :class="{
                    'bg-brand-green-100 text-brand-green-900': item.operation_process.payroll_calculation_type === 'standard' || item.operation_process.payroll_calculation_type === 'per_piece',
                    'bg-brand-orange-100 text-brand-orange-950': item.operation_process.payroll_calculation_type === 'embroidery_formula' || item.operation_process.payroll_calculation_type === 'stitches',
                  }"
                >
                  {{ item.operation_process.payroll_calculation_type }}
                </span>
              </td>
              <td class="p-3 px-4 font-mono">{{ item.stats.movements_count }}</td>
              <td class="p-3 px-4 font-mono">{{ item.stats.dispatched_quantity }}</td>
              <td class="p-3 px-4 font-mono">{{ item.stats.received_quantity }}</td>
              <td class="p-3 px-4 font-mono">{{ item.stats.in_progress_quantity }}</td>
              <td class="p-3 px-4 font-mono text-rose-700 font-bold">{{ item.stats.resolved_loss_quantity }}</td>
              <td class="p-3 px-4 font-mono text-amber-700 font-bold">{{ item.stats.open_incidents_count }}</td>
            </tr>
            <tr v-if="processesReport.length === 0">
              <td colspan="9" class="text-center p-8 text-slate-500 text-sm">No hay datos de eficiencia de procesos para los filtros aplicados.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- TAB 3: MERMAS Y PERDIDAS -->
      <div v-else-if="activeTab === 'losses'" class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-brand-green-50/80 text-brand-green-950 border-b border-slate-200 font-bold text-xs uppercase">
              <th class="p-3 px-4">Grupo de Agrupamiento</th>
              <th class="p-3 px-4">Incidencias Totales</th>
              <th class="p-3 px-4">Abiertas</th>
              <th class="p-3 px-4">Resueltas</th>
              <th class="p-3 px-4">Canceladas</th>
              <th class="p-3 px-4">Piezas Afectadas</th>
              <th class="p-3 px-4">Mermas Confirmadas</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="(item, idx) in lossesReport" :key="idx" class="hover:bg-slate-50/60 transition-colors">
              <td class="p-3 px-4 font-bold text-slate-900">
                {{ item.group.code ? `[${item.group.code}] ` : '' }}{{ item.group.name }}
              </td>
              <td class="p-3 px-4 font-mono">{{ item.stats.incidents_count }}</td>
              <td class="p-3 px-4 font-mono text-amber-700 font-bold">{{ item.stats.open_incidents_count }}</td>
              <td class="p-3 px-4 font-mono text-emerald-700 font-bold">{{ item.stats.resolved_incidents_count }}</td>
              <td class="p-3 px-4 font-mono text-slate-500">{{ item.stats.cancelled_incidents_count }}</td>
              <td class="p-3 px-4 font-mono">{{ item.stats.affected_quantity }}</td>
              <td class="p-3 px-4 font-mono text-rose-700 font-bold">{{ item.stats.resolved_loss_quantity }}</td>
            </tr>
            <tr v-if="lossesReport.length === 0">
              <td colspan="7" class="text-center p-8 text-slate-500 text-sm">No se registraron mermas o pérdidas en este lapso.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- TAB 4: REPROCESOS -->
      <div v-else-if="activeTab === 'reworks'" class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-brand-green-50/80 text-brand-green-950 border-b border-slate-200 font-bold text-xs uppercase">
              <th class="p-3 px-4">Proceso Base</th>
              <th class="p-3 px-4">Suboperación de Destino</th>
              <th class="p-3 px-4">Incidencias Registradas</th>
              <th class="p-3 px-4">Reprocesos Generados</th>
              <th class="p-3 px-4">Piezas Re-procesadas</th>
              <th class="p-3 px-4">Tasa de Reproceso</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="(item, idx) in reworksReport" :key="idx" class="hover:bg-slate-50/60 transition-colors">
              <td class="p-3 px-4 font-bold text-slate-900">{{ item.process.name }}</td>
              <td class="p-3 px-4 text-slate-700">{{ item.operation_process.name }}</td>
              <td class="p-3 px-4 font-mono">{{ item.stats.incidents_count }}</td>
              <td class="p-3 px-4 font-mono">{{ item.stats.reworks_count }}</td>
              <td class="p-3 px-4 font-mono">{{ item.stats.rework_quantity }}</td>
              <td class="p-3 px-4">
                <span
                  class="inline-block px-2 py-1 rounded-md font-mono text-xs font-bold"
                  :class="item.stats.rework_percentage > 5 ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-brand-green-100 text-brand-green-900'"
                >
                  {{ item.stats.rework_percentage }}%
                </span>
              </td>
            </tr>
            <tr v-if="reworksReport.length === 0">
              <td colspan="6" class="text-center p-8 text-slate-500 text-sm">No se han registrado reprocesos.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </section>
</template>

