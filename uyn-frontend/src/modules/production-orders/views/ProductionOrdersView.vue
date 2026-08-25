<script setup lang="ts">
import {
  onMounted,
  reactive,
  ref,
} from 'vue'

import {
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  ClipboardList,
  Eye,
  MapPin,
  Pencil,
  Plus,
  RotateCcw,
  Search,
  Shirt,
  UserRound,
} from 'lucide-vue-next'

import Swal from 'sweetalert2'

import { PERMISSIONS } from '@/config/permissions'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import ProductionOrderDetailDialog from '@/modules/production-orders/components/ProductionOrderDetailDialog.vue'
import ProductionOrderFormDialog from '@/modules/production-orders/components/ProductionOrderFormDialog.vue'
import { productionOrdersService } from '@/modules/production-orders/services/production-orders.service'
import { getApiErrorMessage } from '@/utils/api-error'

import type {
  ProductionOrder,
  ProductionOrderPriority,
  ProductionOrderStatus,
} from '@/modules/production-orders/types/production-order.types'
import type { PaginationMeta } from '@/types/api'

interface Filters {
  search: string
  status: ProductionOrderStatus | ''
  priority: ProductionOrderPriority | ''
  dateFrom: string
  dateTo: string
  perPage: number
}

const authStore = useAuthStore()

const orders = ref<ProductionOrder[]>([])
const loading = ref(false)
const detailLoadingId = ref<number | null>(null)

const formOpen = ref(false)
const detailOpen = ref(false)

const selectedOrder = ref<ProductionOrder | null>(null)
const detailOrder = ref<ProductionOrder | null>(null)

const filters = reactive<Filters>({
  search: '',
  status: '',
  priority: '',
  dateFrom: '',
  dateTo: '',
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

function formatDate(
  value: string | null,
): string {
  if (!value) {
    return 'No especificada'
  }

  const date = new Date(`${value}T00:00:00`)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
  }).format(date)
}

function statusClass(
  status: ProductionOrderStatus,
): string {
  const classes: Record<ProductionOrderStatus, string> = {
    registered: 'status-badge--registered',
    in_progress: 'status-badge--progress',
    completed: 'status-badge--completed',
    cancelled: 'status-badge--cancelled',
  }

  return classes[status]
}

function priorityClass(
  priority: ProductionOrderPriority,
): string {
  const classes: Record<
    ProductionOrderPriority,
    string
  > = {
    low: 'priority-badge--low',
    normal: 'priority-badge--normal',
    high: 'priority-badge--high',
    urgent: 'priority-badge--urgent',
  }

  return classes[priority]
}

function canCreate(): boolean {
  return authStore.can(PERMISSIONS.cuts.create)
}

function canEdit(): boolean {
  return authStore.can(PERMISSIONS.cuts.update)
}

function validateDates(): boolean {
  if (
    filters.dateFrom &&
    filters.dateTo &&
    filters.dateTo < filters.dateFrom
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

async function loadOrders(page = 1): Promise<void> {
  if (!validateDates()) {
    return
  }

  loading.value = true

  try {
    const response =
      await productionOrdersService.list({
        search: filters.search.trim(),
        status: filters.status,
        priority: filters.priority,
        date_from: filters.dateFrom,
        date_to: filters.dateTo,
        per_page: filters.perPage,
        page,
      })

    orders.value = response.data
    pagination.value = response.meta
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible cargar las órdenes',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    loading.value = false
  }
}

function applyFilters(): void {
  void loadOrders(1)
}

function clearFilters(): void {
  filters.search = ''
  filters.status = ''
  filters.priority = ''
  filters.dateFrom = ''
  filters.dateTo = ''
  filters.perPage = 15

  void loadOrders(1)
}

function openCreateForm(): void {
  selectedOrder.value = null
  formOpen.value = true
}

function openEditForm(order: ProductionOrder): void {
  selectedOrder.value = order
  formOpen.value = true
}

function closeForm(): void {
  formOpen.value = false
  selectedOrder.value = null
}

async function openDetail(
  order: ProductionOrder,
): Promise<void> {
  detailLoadingId.value = order.id

  try {
    detailOrder.value =
      await productionOrdersService.show(order.id)

    detailOpen.value = true
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible consultar la orden',
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
  detailOrder.value = null
}

async function handleSaved(
  _order: ProductionOrder,
  message: string,
): Promise<void> {
  closeForm()

  await Swal.fire({
    title: message,
    icon: 'success',
    timer: 1700,
    showConfirmButton: false,
  })

  await loadOrders(pagination.value.current_page)
}

function previousPage(): void {
  if (pagination.value.current_page <= 1) {
    return
  }

  void loadOrders(pagination.value.current_page - 1)
}

function nextPage(): void {
  if (
    pagination.value.current_page >=
    pagination.value.last_page
  ) {
    return
  }

  void loadOrders(pagination.value.current_page + 1)
}

onMounted(() => {
  void loadOrders()
})
</script>

<template>
  <section class="grid gap-5">
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <p class="m-0 mb-2 text-brand-orange-800 text-xs font-extrabold tracking-widest uppercase">
          Planeación productiva
        </p>

        <h2 class="m-0 text-2xl font-bold text-slate-900">Órdenes de producción</h2>

        <p class="mt-2 mb-0 text-slate-600 leading-relaxed text-sm max-w-2xl">
          Administra pedidos maestros, fechas, prioridades y
          cortes asociados.
        </p>
      </div>

      <button
        v-if="canCreate()"
        type="button"
        class="inline-flex w-full sm:w-auto min-h-[3rem] items-center justify-center gap-2 px-5 text-white bg-brand-orange-800 hover:bg-brand-orange-900 rounded-md font-[750] text-sm cursor-pointer transition-colors border-0"
        @click="openCreateForm"
      >
        <Plus :size="20" aria-hidden="true" />
        Registrar orden
      </button>
    </header>

    <form
      class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-[1.5fr_repeat(5,1fr)_auto] gap-3 p-4 bg-white border border-slate-200 rounded-lg"
      @submit.prevent="applyFilters"
    >
      <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3 min-h-[3rem] px-3 bg-white border border-slate-300 rounded-md focus-within:border-brand-green-700 focus-within:ring-3 focus-within:ring-brand-green-700/13 transition-all">
        <Search :size="20" class="text-slate-400" aria-hidden="true" />

        <input
          v-model="filters.search"
          type="search"
          maxlength="150"
          placeholder="Buscar por folio, ubicación o notas"
          class="w-full min-w-0 bg-transparent border-0 outline-hidden text-slate-900 text-sm placeholder:text-slate-400"
        />
      </div>

      <select
        v-model="filters.status"
        aria-label="Filtrar por estado"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">Todos los estados</option>
        <option value="registered">Registradas</option>
        <option value="in_progress">En proceso</option>
        <option value="completed">Completadas</option>
        <option value="cancelled">Canceladas</option>
      </select>

      <select
        v-model="filters.priority"
        aria-label="Filtrar por prioridad"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">Todas las prioridades</option>
        <option value="low">Baja</option>
        <option value="normal">Normal</option>
        <option value="high">Alta</option>
        <option value="urgent">Urgente</option>
      </select>

      <input
        v-model="filters.dateFrom"
        type="date"
        aria-label="Fecha inicial"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      />

      <input
        v-model="filters.dateTo"
        type="date"
        :min="filters.dateFrom || undefined"
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
      class="grid place-content-center min-h-[18rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <div class="w-9 h-9 border-4 border-brand-green-100 border-t-brand-green-700 rounded-full animate-spin mx-auto" />
      <p class="text-slate-600 text-sm">Cargando órdenes...</p>
    </div>

    <div
      v-else-if="orders.length === 0"
      class="grid place-content-center min-h-[18rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <ClipboardList :size="44" class="text-slate-400 mx-auto" aria-hidden="true" />

      <h3 class="m-0 text-lg font-bold text-slate-900">No se encontraron órdenes</h3>

      <p class="m-0 text-slate-600 text-sm">
        Modifica los filtros o registra una nueva orden.
      </p>
    </div>

    <template v-else>
      <div class="grid md:hidden gap-4">
        <article
          v-for="order in orders"
          :key="order.id"
          class="p-4 bg-white border border-slate-200 rounded-lg shadow-xs grid gap-3"
        >
          <header class="flex items-center gap-3">
            <div class="flex w-11 h-11 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-md shrink-0">
              <ClipboardList
                :size="22"
                aria-hidden="true"
              />
            </div>

            <div class="grid min-w-0">
              <strong class="text-slate-900 font-mono font-bold text-base truncate">{{ order.order_code }}</strong>
              <span class="text-slate-500 text-xs truncate">
                {{ order.location || 'Sin ubicación' }}
              </span>
            </div>
          </header>

          <div class="flex flex-wrap gap-2 my-1">
            <span
              class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold"
              :class="{
                'text-slate-700 bg-slate-100': order.status === 'registered',
                'text-sky-800 bg-sky-100': order.status === 'in_progress',
                'text-emerald-800 bg-emerald-100': order.status === 'completed',
                'text-rose-800 bg-rose-100': order.status === 'cancelled',
              }"
            >
              {{ order.status_label }}
            </span>

            <span
              class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold"
              :class="{
                'text-slate-700 bg-slate-100': ['low', 'normal'].includes(order.priority),
                'text-brand-orange-900 bg-brand-orange-100': order.priority === 'high',
                'text-rose-800 bg-rose-100': order.priority === 'urgent',
              }"
            >
              {{ order.priority_label }}
            </span>
          </div>

          <dl class="grid gap-2 text-xs text-slate-600 m-0 pt-2 border-t border-slate-100">
            <div class="flex items-center gap-2 min-w-0">
              <CalendarDays :size="18" class="text-slate-400 shrink-0" aria-hidden="true" />

              <span>
                {{ formatDate(order.start_date) }}
                —
                {{ formatDate(order.end_date) }}
              </span>
            </div>

            <div class="flex items-center gap-2 min-w-0">
              <Shirt :size="18" class="text-slate-400 shrink-0" aria-hidden="true" />

              <span>
                {{ order.garment_cuts_count }}
                cortes asociados
              </span>
            </div>

            <div class="flex items-center gap-2 min-w-0">
              <UserRound :size="18" class="text-slate-400 shrink-0" aria-hidden="true" />

              <span class="truncate">
                {{
                  order.created_by?.name ??
                  'Usuario no disponible'
                }}
              </span>
            </div>
          </dl>

          <p
            v-if="order.notes"
            class="m-0 mt-2 p-3 text-slate-600 bg-slate-50 border border-slate-200/60 rounded-md text-xs leading-relaxed"
          >
            {{ order.notes }}
          </p>

          <footer class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-3 border-t border-slate-100 mt-2">
            <button
              type="button"
              class="inline-flex min-h-[2.5rem] items-center justify-center gap-2 px-3 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-bold text-xs cursor-pointer transition-colors disabled:opacity-50"
              :disabled="detailLoadingId === order.id"
              @click="openDetail(order)"
            >
              <Eye :size="18" aria-hidden="true" />
              Ver detalle
            </button>

            <button
              v-if="canEdit()"
              type="button"
              class="inline-flex min-h-[2.5rem] items-center justify-center gap-2 px-3 text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-md font-bold text-xs cursor-pointer transition-colors border-0"
              @click="openEditForm(order)"
            >
              <Pencil :size="18" aria-hidden="true" />
              Editar
            </button>
          </footer>
        </article>
      </div>

      <div class="hidden md:block">
        <div class="overflow-x-auto bg-white border border-slate-200 rounded-lg shadow-xs">
          <table class="w-full min-w-[68rem] border-collapse text-left text-sm">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-xs font-bold uppercase tracking-wider">
                <th class="p-4">Orden</th>
                <th class="p-4">Ubicación</th>
                <th class="p-4">Estado</th>
                <th class="p-4">Prioridad</th>
                <th class="p-4">Fechas</th>
                <th class="p-4">Cortes</th>
                <th class="p-4">Registrada por</th>
                <th class="p-4 w-20 text-right" aria-label="Acciones" />
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-200">
              <tr
                v-for="order in orders"
                :key="order.id"
                class="hover:bg-brand-green-50/50 transition-colors"
              >
                <td class="p-4 font-mono font-bold text-slate-900 whitespace-nowrap">
                  {{ order.order_code }}
                </td>

                <td class="p-4">
                  <div class="flex items-center gap-2 text-slate-700 text-xs">
                    <MapPin :size="17" class="text-slate-400 shrink-0" aria-hidden="true" />
                    {{ order.location || 'Sin ubicación' }}
                  </div>
                </td>

                <td class="p-4 whitespace-nowrap">
                  <span
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold"
                    :class="{
                      'text-slate-700 bg-slate-100': order.status === 'registered',
                      'text-sky-800 bg-sky-100': order.status === 'in_progress',
                      'text-emerald-800 bg-emerald-100': order.status === 'completed',
                      'text-rose-800 bg-rose-100': order.status === 'cancelled',
                    }"
                  >
                    {{ order.status_label }}
                  </span>
                </td>

                <td class="p-4 whitespace-nowrap">
                  <span
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold"
                    :class="{
                      'text-slate-700 bg-slate-100': ['low', 'normal'].includes(order.priority),
                      'text-brand-orange-900 bg-brand-orange-100': order.priority === 'high',
                      'text-rose-800 bg-rose-100': order.priority === 'urgent',
                    }"
                  >
                    {{ order.priority_label }}
                  </span>
                </td>

                <td class="p-4 whitespace-nowrap text-xs">
                  <div class="grid">
                    <span class="font-medium text-slate-900">{{ formatDate(order.start_date) }}</span>
                    <small class="text-slate-500">{{ formatDate(order.end_date) }}</small>
                  </div>
                </td>

                <td class="p-4 font-bold text-slate-900 text-xs whitespace-nowrap">{{ order.garment_cuts_count }}</td>

                <td class="p-4 text-slate-600 text-xs whitespace-nowrap">
                  {{ order.created_by?.name ?? 'No disponible' }}
                </td>

                <td class="p-4 text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      type="button"
                      class="inline-flex w-9 h-9 items-center justify-center text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-md border-0 cursor-pointer transition-colors disabled:opacity-50"
                      title="Ver detalle"
                      :disabled="detailLoadingId === order.id"
                      @click="openDetail(order)"
                    >
                      <Eye :size="18" aria-hidden="true" />
                    </button>

                    <button
                      v-if="canEdit()"
                      type="button"
                      class="inline-flex w-9 h-9 items-center justify-center text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-md border-0 cursor-pointer transition-colors"
                      title="Editar orden"
                      @click="openEditForm(order)"
                    >
                      <Pencil :size="18" aria-hidden="true" />
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
          órdenes
        </p>

        <div class="flex items-center gap-3">
          <button
            type="button"
            class="inline-flex min-h-[2.35rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 rounded-md font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
            :disabled="
              pagination.current_page <= 1 || loading
            "
            @click="previousPage"
          >
            <ChevronLeft :size="19" aria-hidden="true" />
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
            <ChevronRight :size="19" aria-hidden="true" />
          </button>
        </div>
      </footer>
    </template>

    <ProductionOrderFormDialog
      :open="formOpen"
      :order="selectedOrder"
      @close="closeForm"
      @saved="handleSaved"
    />

    <ProductionOrderDetailDialog
      :open="detailOpen"
      :order="detailOrder"
      @close="closeDetail"
    />
  </section>
</template>