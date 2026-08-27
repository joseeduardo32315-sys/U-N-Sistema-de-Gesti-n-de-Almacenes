<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  Activity,
  AlertTriangle,
  ArrowRight,
  BarChart3,
  CheckCircle,
  Layers,
  LayoutDashboard,
  LogOut,
  PackageCheck,
  PlusCircle,
  RefreshCw,
  Scissors,
  TriangleAlert,
  Workflow,
} from 'lucide-vue-next'

import logoUyn from '@/assets/images/logo-uyn.png'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { garmentCutsService } from '@/modules/garment-cuts/services/garment-cuts.service'
import { productionOrdersService } from '@/modules/production-orders/services/production-orders.service'
import { productionIncidentsService } from '@/modules/production-incidents/services/production-incidents.service'
import { productionMovementsService } from '@/modules/production-movements/services/production-movements.service'
import { reportsService } from '@/modules/reports/services/reports.service'

import type { GarmentCut } from '@/modules/garment-cuts/types/garment-cut.types'
import type { ProductionOrder } from '@/modules/production-orders/types/production-order.types'
import type { ProductionIncident } from '@/modules/production-incidents/types/production-incident.types'
import type { ProductionMovement } from '@/modules/production-movements/types/production-movement.types'
import type { ProductionProcessReportItem } from '@/modules/reports/types/reports.types'

const router = useRouter()
const authStore = useAuthStore()

// State metrics
const loading = ref(true)
const refreshing = ref(false)

const cuts = ref<GarmentCut[]>([])
const recentOrders = ref<ProductionOrder[]>([])
const openIncidents = ref<ProductionIncident[]>([])
const recentMovements = ref<ProductionMovement[]>([])
const processStats = ref<ProductionProcessReportItem[]>([])

const totalOrdersCount = ref(0)
const totalOpenIncidentsCount = ref(0)

// Computed KPIs
const activeCutsCount = computed(() => {
  return cuts.value.filter((c) =>
    ['registered', 'in_progress', 'partially_completed', 'with_incident', 'delayed'].includes(c.status),
  ).length
})

const completedCutsCount = computed(() => {
  return cuts.value.filter((c) => c.status === 'completed').length
})

const totalPlannedPieces = computed(() => {
  return cuts.value.reduce((sum, c) => sum + (Number(c.total_pieces) || 0), 0)
})

const totalEffectivePieces = computed(() => {
  return cuts.value.reduce((sum, c) => {
    return sum + (Number(c.total_pieces) || 0)
  }, 0)
})

const totalLossPieces = computed(() => {
  return Math.max(0, totalPlannedPieces.value - totalEffectivePieces.value)
})

const yieldPercentage = computed(() => {
  if (totalPlannedPieces.value === 0) return 100
  const percentage = (totalEffectivePieces.value / totalPlannedPieces.value) * 100
  return Math.min(100, Math.max(0, Math.round(percentage * 10) / 10))
})

async function handleLogout(): Promise<void> {
  await authStore.logout()
  await router.replace({ name: 'login' })
}

function formatDate(dateStr?: string | null): string {
  if (!dateStr) return '—'
  const date = new Date(dateStr)
  if (Number.isNaN(date.getTime())) return dateStr
  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

async function loadDashboardData(isRefresh = false): Promise<void> {
  if (isRefresh) {
    refreshing.value = true
  } else {
    loading.value = true
  }

  try {
    const [
      cutsRes,
      ordersRes,
      incidentsRes,
      movementsRes,
      processesRes,
    ] = await Promise.allSettled([
      garmentCutsService.list({ per_page: 150 }),
      productionOrdersService.list({ per_page: 5 }),
      productionIncidentsService.list({ status: 'open', per_page: 5 }),
      productionMovementsService.list({ per_page: 5 }),
      reportsService.getProductionProcesses(),
    ])

    // Cuts
    if (cutsRes.status === 'fulfilled') {
      cuts.value = cutsRes.value.data
    } else {
      try {
        const reportCuts = await reportsService.getProductionCuts({ per_page: 150 })
        cuts.value = reportCuts.data as unknown as GarmentCut[]
      } catch (err) {
        console.error('Error fallback getProductionCuts:', err)
      }
    }

    // Orders
    if (ordersRes.status === 'fulfilled') {
      recentOrders.value = ordersRes.value.data
      totalOrdersCount.value = ordersRes.value.meta.total
    }

    // Incidents
    if (incidentsRes.status === 'fulfilled') {
      openIncidents.value = incidentsRes.value.data
      totalOpenIncidentsCount.value = incidentsRes.value.meta.total
    }

    // Movements
    if (movementsRes.status === 'fulfilled') {
      recentMovements.value = movementsRes.value.data
    }

    // Process stats
    if (processesRes.status === 'fulfilled') {
      processStats.value = processesRes.value.data.slice(0, 6)
    }
  } catch (error) {
    console.error('Error cargando datos del dashboard:', error)
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

onMounted(() => {
  void loadDashboardData()
})
</script>

<template>
  <div class="grid gap-5">
    <!-- Welcome & Refresh Header -->
    <section class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 border border-slate-200 rounded-xl shadow-xs">
      <div class="flex items-center gap-4">
        <div class="flex w-14 h-14 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-lg shrink-0">
          <LayoutDashboard :size="28" aria-hidden="true" />
        </div>
        <div>
          <p class="m-0 text-slate-500 text-xs font-bold uppercase tracking-wider">Supervisión operativa</p>
          <h1 class="m-0 text-xl font-bold text-slate-900">Panel de Control de Producción</h1>
          <span class="inline-block text-brand-orange-800 text-xs font-bold mt-0.5">Estado general del taller en tiempo real</span>
        </div>
      </div>

        <button
          type="button"
          class="inline-flex min-h-[2.75rem] items-center justify-center gap-2 px-4 text-brand-green-800 bg-brand-green-100 hover:bg-brand-green-200/70 border border-brand-green-200 rounded-md font-bold text-sm cursor-pointer transition-colors disabled:opacity-50"
          :disabled="loading || refreshing"
          @click="loadDashboardData(true)"
        >
          <RefreshCw
            :size="19"
            :class="{ 'animate-spin': refreshing }"
            aria-hidden="true"
          />
          Actualizar datos
        </button>
      </section>

      <!-- Loading State -->
      <div v-if="loading" class="grid place-content-center min-h-[20rem] text-center gap-3 bg-white border border-slate-200 rounded-xl">
        <div class="w-10 h-10 border-4 border-brand-green-100 border-t-brand-green-700 rounded-full animate-spin mx-auto"></div>
        <p class="text-slate-600 text-sm font-medium">Cargando información actualizada del taller...</p>
      </div>

      <template v-else>
        <!-- KPI Cards Grid -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Cortes Activos -->
          <article class="p-5 bg-white border border-slate-200 rounded-xl shadow-xs grid gap-3 hover:border-brand-green-300 transition-colors">
            <header class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2.5 text-brand-green-700">
                <Scissors :size="22" aria-hidden="true" />
                <h3 class="m-0 text-sm font-bold text-slate-700">Cortes Activos</h3>
              </div>
              <span class="px-2 py-0.5 bg-brand-green-100 text-brand-green-900 text-xs font-bold rounded-full">En taller</span>
            </header>
            <p class="m-0 text-4xl font-extrabold text-slate-900 leading-none font-mono">{{ activeCutsCount }}</p>
            <footer class="text-xs text-slate-500 flex justify-between items-center border-t border-slate-100 pt-2">
              <span>Lotes en proceso</span>
              <strong class="text-slate-700 font-mono">{{ totalPlannedPieces }} pzs</strong>
            </footer>
          </article>

          <!-- Órdenes de Producción -->
          <article class="p-5 bg-white border border-slate-200 rounded-xl shadow-xs grid gap-3 hover:border-brand-orange-300 transition-colors">
            <header class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2.5 text-brand-orange-800">
                <Layers :size="22" aria-hidden="true" />
                <h3 class="m-0 text-sm font-bold text-slate-700">Órdenes de Producción</h3>
              </div>
              <span class="px-2 py-0.5 bg-brand-orange-100 text-brand-orange-950 text-xs font-bold rounded-full">Activas</span>
            </header>
            <p class="m-0 text-4xl font-extrabold text-slate-900 leading-none font-mono">{{ totalOrdersCount }}</p>
            <footer class="text-xs text-slate-500 flex justify-between items-center border-t border-slate-100 pt-2">
              <span>Órdenes registradas</span>
              <strong class="text-slate-700 font-mono">{{ recentOrders.length }} recientes</strong>
            </footer>
          </article>

          <!-- Incidencias Abiertas -->
          <article class="p-5 bg-white border border-slate-200 rounded-xl shadow-xs grid gap-3 hover:border-rose-300 transition-colors">
            <header class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2.5 text-rose-700">
                <AlertTriangle :size="22" aria-hidden="true" />
                <h3 class="m-0 text-sm font-bold text-slate-700">Incidencias Abiertas</h3>
              </div>
              <span class="px-2 py-0.5 bg-rose-100 text-rose-800 text-xs font-bold rounded-full">Atención</span>
            </header>
            <p class="m-0 text-4xl font-extrabold text-slate-900 leading-none font-mono" :class="{ 'text-rose-700': totalOpenIncidentsCount > 0 }">
              {{ totalOpenIncidentsCount }}
            </p>
            <footer class="text-xs text-slate-500 flex justify-between items-center border-t border-slate-100 pt-2">
              <span>Pendientes de resolver</span>
              <router-link to="/incidencias" class="text-rose-700 font-bold hover:underline">Ver todas</router-link>
            </footer>
          </article>

          <!-- Cortes Completados -->
          <article class="p-5 bg-white border border-slate-200 rounded-xl shadow-xs grid gap-3 hover:border-emerald-300 transition-colors">
            <header class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2.5 text-emerald-700">
                <CheckCircle :size="22" aria-hidden="true" />
                <h3 class="m-0 text-sm font-bold text-slate-700">Cortes Completados</h3>
              </div>
              <span class="px-2 py-0.5 bg-emerald-100 text-emerald-900 text-xs font-bold rounded-full">Finalizados</span>
            </header>
            <p class="m-0 text-4xl font-extrabold text-slate-900 leading-none font-mono">{{ completedCutsCount }}</p>
            <footer class="text-xs text-slate-500 flex justify-between items-center border-t border-slate-100 pt-2">
              <span>Entregados a Terminado</span>
              <strong class="text-emerald-800 font-mono">{{ completedCutsCount }} lotes</strong>
            </footer>
          </article>
        </section>

        <!-- Volume & Yield Summary Card -->
        <section class="p-5 bg-brand-green-50/70 border border-brand-green-100 rounded-xl grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="grid gap-1">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Volumen Planificado</span>
            <strong class="text-2xl font-mono font-bold text-slate-900">{{ totalPlannedPieces.toLocaleString('es-MX') }} pzs</strong>
            <small class="text-xs text-slate-600">Sumatoria total de cortes registrados</small>
          </div>

          <div class="grid gap-1 border-t sm:border-t-0 sm:border-l border-brand-green-200 pt-3 sm:pt-0 sm:pl-4">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Producción Efectiva</span>
            <div class="flex items-baseline gap-2">
              <strong class="text-2xl font-mono font-bold text-brand-green-800">{{ totalEffectivePieces.toLocaleString('es-MX') }} pzs</strong>
              <span class="text-xs font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">{{ yieldPercentage }}% rendimiento</span>
            </div>
            <small class="text-xs text-slate-600">Piezas confirmadas sin merma</small>
          </div>

          <div class="grid gap-1 border-t sm:border-t-0 sm:border-l border-brand-green-200 pt-3 sm:pt-0 sm:pl-4">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Mermas Confirmadas</span>
            <strong class="text-2xl font-mono font-bold text-rose-700">{{ totalLossPieces.toLocaleString('es-MX') }} pzs</strong>
            <small class="text-xs text-slate-600">Diferencia por incidencias y mermas</small>
          </div>
        </section>

        <!-- Main Dashboard Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-[2fr_1fr] gap-5">
          <!-- Left Column -->
          <div class="grid gap-5">
            <!-- Process Efficiency Summary -->
            <section class="bg-white border border-slate-200 rounded-xl shadow-xs p-5 grid gap-4">
              <header class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div class="flex items-center gap-2.5">
                  <Activity :size="20" aria-hidden="true" class="text-brand-green-700" />
                  <h2 class="m-0 text-base font-bold text-slate-900">Eficiencia por Áreas y Suboperaciones</h2>
                </div>
                <router-link to="/reportes" class="text-xs font-bold text-brand-green-700 hover:underline">Ver reporte completo →</router-link>
              </header>

              <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm min-w-[32rem]">
                  <thead>
                    <tr class="bg-brand-green-50/80 text-brand-green-950 border-b border-slate-200 font-bold text-xs uppercase">
                      <th class="p-3 px-4">Proceso</th>
                      <th class="p-3 px-4">Suboperación</th>
                      <th class="p-3 px-4">Movs</th>
                      <th class="p-3 px-4">Despachado</th>
                      <th class="p-3 px-4">Recibido</th>
                      <th class="p-3 px-4">Mermas</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-200">
                    <tr v-for="(item, idx) in processStats" :key="idx" class="hover:bg-slate-50/60 transition-colors">
                      <td class="p-3 px-4 font-bold text-slate-900">{{ item.process.name }}</td>
                      <td class="p-3 px-4 text-slate-700">{{ item.operation_process.name }}</td>
                      <td class="p-3 px-4 font-mono">{{ item.stats.movements_count }}</td>
                      <td class="p-3 px-4 font-mono">{{ item.stats.dispatched_quantity }}</td>
                      <td class="p-3 px-4 font-mono">{{ item.stats.received_quantity }}</td>
                      <td class="p-3 px-4 font-mono">
                        <span :class="item.stats.resolved_loss_quantity > 0 ? 'text-rose-700 font-bold' : 'text-slate-500'">
                          {{ item.stats.resolved_loss_quantity }}
                        </span>
                      </td>
                    </tr>
                    <tr v-if="processStats.length === 0">
                      <td colspan="6" class="text-center p-6 text-slate-500 text-sm">No hay registros de eficiencia en las áreas.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>

            <!-- Recent Movements / Transfers -->
            <section class="bg-white border border-slate-200 rounded-xl shadow-xs p-5 grid gap-4">
              <header class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div class="flex items-center gap-2.5">
                  <Workflow :size="20" aria-hidden="true" class="text-brand-green-700" />
                  <h2 class="m-0 text-base font-bold text-slate-900">Últimas Transferencias de Producción</h2>
                </div>
                <router-link to="/produccion/movimientos" class="text-xs font-bold text-brand-green-700 hover:underline">Ver todos →</router-link>
              </header>

              <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm min-w-[32rem]">
                  <thead>
                    <tr class="bg-brand-green-50/80 text-brand-green-950 border-b border-slate-200 font-bold text-xs uppercase">
                      <th class="p-3 px-4">Corte / Lote</th>
                      <th class="p-3 px-4">Flujo de Área</th>
                      <th class="p-3 px-4">Cantidad</th>
                      <th class="p-3 px-4">Estado</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-200">
                    <tr v-for="m in recentMovements" :key="m.id" class="hover:bg-slate-50/60 transition-colors">
                      <td class="p-3 px-4 font-mono font-bold text-slate-900">{{ m.garment_cut?.code ?? '—' }}</td>
                      <td class="p-3 px-4 text-xs text-slate-700">
                        <span class="font-bold">{{ m.from_area?.name ?? 'Inicio' }}</span>
                        →
                        <span class="font-bold text-brand-green-900">{{ m.to_area?.name ?? 'Fin' }}</span>
                      </td>
                      <td class="p-3 px-4 font-mono font-bold">{{ m.quantity }} pzs</td>
                      <td class="p-3 px-4">
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold"
                          :class="{
                            'bg-amber-50 text-amber-800 border border-amber-200': m.status === 'pending',
                            'bg-sky-50 text-sky-800 border border-sky-200': m.status === 'in_progress',
                            'bg-emerald-50 text-emerald-800 border border-emerald-200': m.status === 'completed',
                            'bg-rose-50 text-rose-700 border border-rose-200': m.status === 'with_incident',
                          }"
                        >
                          {{ m.status_label }}
                        </span>
                      </td>
                    </tr>
                    <tr v-if="recentMovements.length === 0">
                      <td colspan="4" class="text-center p-6 text-slate-500 text-sm">No hay transferencias recientes registradas.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>
          </div>

          <!-- Right Column: Quick Actions & Open Incidents List -->
          <div class="grid gap-5 content-start">
            <!-- Quick Actions -->
            <section class="bg-white border border-slate-200 rounded-xl shadow-xs p-5 grid gap-3">
              <header class="flex items-center gap-2.5 border-b border-slate-200 pb-3">
                <PackageCheck :size="20" aria-hidden="true" class="text-brand-orange-800" />
                <h2 class="m-0 text-base font-bold text-slate-900">Acciones Rápidas</h2>
              </header>

              <div class="grid gap-2">
                <router-link to="/produccion/cortes" class="flex items-center min-h-[3rem] px-3.5 bg-brand-green-50 text-brand-green-950 border border-brand-green-100 rounded-lg font-bold text-xs no-underline hover:bg-brand-green-100 hover:border-brand-green-200 hover:translate-x-1 transition-all group">
                  <PlusCircle :size="17" aria-hidden="true" class="mr-2.5 text-brand-green-800 shrink-0" />
                  <span>Registrar Lote de Corte</span>
                  <ArrowRight :size="15" aria-hidden="true" class="ml-auto opacity-60 group-hover:opacity-100 shrink-0" />
                </router-link>

                <router-link to="/produccion/movimientos" class="flex items-center min-h-[3rem] px-3.5 bg-brand-green-50 text-brand-green-950 border border-brand-green-100 rounded-lg font-bold text-xs no-underline hover:bg-brand-green-100 hover:border-brand-green-200 hover:translate-x-1 transition-all group">
                  <Workflow :size="17" aria-hidden="true" class="mr-2.5 text-brand-green-800 shrink-0" />
                  <span>Registrar Despacho / Transferencia</span>
                  <ArrowRight :size="15" aria-hidden="true" class="ml-auto opacity-60 group-hover:opacity-100 shrink-0" />
                </router-link>

                <router-link to="/incidencias" class="flex items-center min-h-[3rem] px-3.5 bg-rose-50 text-rose-800 border border-rose-100 rounded-lg font-bold text-xs no-underline hover:bg-rose-100 hover:translate-x-1 transition-all group">
                  <TriangleAlert :size="17" aria-hidden="true" class="mr-2.5 text-rose-700 shrink-0" />
                  <span>Reportar Incidencia</span>
                  <ArrowRight :size="15" aria-hidden="true" class="ml-auto opacity-60 group-hover:opacity-100 shrink-0" />
                </router-link>

                <router-link to="/reportes" class="flex items-center min-h-[3rem] px-3.5 bg-brand-orange-50 text-brand-orange-950 border border-brand-orange-100 rounded-lg font-bold text-xs no-underline hover:bg-brand-orange-100 hover:translate-x-1 transition-all group">
                  <BarChart3 :size="17" aria-hidden="true" class="mr-2.5 text-brand-orange-800 shrink-0" />
                  <span>Consultar Reportes y Excel</span>
                  <ArrowRight :size="15" aria-hidden="true" class="ml-auto opacity-60 group-hover:opacity-100 shrink-0" />
                </router-link>
              </div>
            </section>

            <!-- Pending Incidents Feed -->
            <section class="bg-white border border-slate-200 rounded-xl shadow-xs p-5 grid gap-3">
              <header class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div class="flex items-center gap-2.5">
                  <AlertTriangle :size="20" aria-hidden="true" class="text-rose-700" />
                  <h2 class="m-0 text-base font-bold text-slate-900">Incidencias Recientes</h2>
                </div>
                <span class="text-xs font-mono font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded-full border border-rose-200">{{ totalOpenIncidentsCount }} abiertas</span>
              </header>

              <div v-if="openIncidents.length > 0" class="grid gap-2">
                <article v-for="inc in openIncidents" :key="inc.id" class="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs grid gap-1">
                  <div class="flex justify-between items-center">
                    <strong class="text-slate-900 font-bold font-mono">{{ inc.garment_cut?.code ?? 'Sin corte' }}</strong>
                    <span class="text-[0.7rem] font-bold uppercase text-amber-800 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">{{ inc.incident_type_label }}</span>
                  </div>
                  <p class="m-0 text-slate-600 line-clamp-2">{{ inc.description }}</p>
                  <footer class="flex justify-between items-center text-[0.7rem] text-slate-400 pt-1 border-t border-slate-100">
                    <span>Afectadas: <strong>{{ inc.quantity_affected }} pzs</strong></span>
                    <span>{{ formatDate(inc.created_at) }}</span>
                  </footer>
                </article>
              </div>

              <div v-else class="text-center p-6 text-slate-500 text-xs bg-slate-50 rounded-lg">
                <CheckCircle :size="28" class="text-emerald-600 mx-auto mb-1" aria-hidden="true" />
                No hay incidencias abiertas reportadas.
              </div>
            </section>
          </div>
        </div>
      </template>
    </div>
</template>