<script setup lang="ts">
import {
  computed,
  onMounted,
  reactive,
  ref,
} from 'vue'

import {
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  CircleUserRound,
  Eye,
  FileClock,
  FilterX,
  Globe2,
  RefreshCw,
  Search,
} from 'lucide-vue-next'

import Swal from 'sweetalert2'

import { PERMISSIONS } from '@/config/permissions'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { operationLogsService } from '@/modules/operation-logs/services/operation-logs.service'
import OperationLogDetailDialog from '@/modules/operation-logs/components/OperationLogDetailDialog.vue'
import { usersService } from '@/modules/users/services/users.service'
import { getApiErrorMessage } from '@/utils/api-error'

import type {
  OperationLog,
  OperationLogsQuery,
} from '@/modules/operation-logs/types/operation-log.types'
import type { User } from '@/modules/users/types/user.types'
import type { PaginationMeta } from '@/types/api'

interface Filters {
  userId: number | ''
  module: string
  action: string
  dateFrom: string
  dateTo: string
  perPage: number
}

const authStore = useAuthStore()

const logs = ref<OperationLog[]>([])
const users = ref<User[]>([])

const loading = ref(false)
const usersLoading = ref(false)

const selectedLog = ref<OperationLog | null>(null)
const detailOpen = ref(false)

const filters = reactive<Filters>({
  userId: '',
  module: '',
  action: '',
  dateFrom: '',
  dateTo: '',
  perPage: 20,
})

const pagination = ref<PaginationMeta>({
  current_page: 1,
  from: null,
  last_page: 1,
  per_page: 20,
  to: null,
  total: 0,
})

const availableModules = computed<string[]>(() => {
  return Array.from(
    new Set(logs.value.map((log) => log.module)),
  )
    .filter(Boolean)
    .sort((a, b) => a.localeCompare(b, 'es'))
})

const availableActions = computed<string[]>(() => {
  return Array.from(
    new Set(logs.value.map((log) => log.action)),
  )
    .filter(Boolean)
    .sort((a, b) => a.localeCompare(b, 'es'))
})

function formatDate(value: string): string {
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

function formatModule(module: string): string {
  return module
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .split(/[-_.\s]+/)
    .filter(Boolean)
    .map(
      (part) =>
        part.charAt(0).toLocaleUpperCase('es') +
        part.slice(1),
    )
    .join(' ')
}

function actionLabel(action: string): string {
  const labels: Record<string, string> = {
    create: 'Creación',
    update: 'Actualización',
    activate: 'Activación',
    deactivate: 'Desactivación',
    delete: 'Eliminación',
    login: 'Inicio de sesión',
    logout: 'Cierre de sesión',
    assign: 'Asignación',
    receive: 'Recepción',
    resolve: 'Resolución',
    generate: 'Generación',
    close: 'Cierre',
  }

  return labels[action] ?? formatModule(action)
}

function actionClass(action: string): string {
  const successActions = [
    'create',
    'activate',
    'receive',
    'resolve',
    'generate',
  ]

  const dangerActions = [
    'deactivate',
    'delete',
    'cancel',
  ]

  if (successActions.includes(action)) {
    return 'action-badge--success'
  }

  if (dangerActions.includes(action)) {
    return 'action-badge--danger'
  }

  return 'action-badge--neutral'
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

async function loadLogs(page = 1): Promise<void> {
  if (!validateDates()) {
    return
  }

  loading.value = true

  try {
    const query: OperationLogsQuery = {
      user_id: filters.userId,
      module: filters.module.trim(),
      action: filters.action.trim(),
      date_from: filters.dateFrom,
      date_to: filters.dateTo,
      per_page: filters.perPage,
      page,
    }

    const response = await operationLogsService.list(query)

    logs.value = response.data
    pagination.value = response.meta
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible cargar la bitácora',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    loading.value = false
  }
}

async function loadUsers(): Promise<void> {
  if (!authStore.can(PERMISSIONS.users.view)) {
    return
  }

  usersLoading.value = true

  try {
    const response = await usersService.list({
      per_page: 100,
      status: '',
      page: 1,
    })

    users.value = response.data
  } catch {
    users.value = []
  } finally {
    usersLoading.value = false
  }
}

function applyFilters(): void {
  void loadLogs(1)
}

function clearFilters(): void {
  filters.userId = ''
  filters.module = ''
  filters.action = ''
  filters.dateFrom = ''
  filters.dateTo = ''
  filters.perPage = 20

  void loadLogs(1)
}

function openDetail(log: OperationLog): void {
  selectedLog.value = log
  detailOpen.value = true
}

function closeDetail(): void {
  detailOpen.value = false
  selectedLog.value = null
}

function previousPage(): void {
  if (pagination.value.current_page > 1) {
    void loadLogs(pagination.value.current_page - 1)
  }
}

function nextPage(): void {
  if (
    pagination.value.current_page <
    pagination.value.last_page
  ) {
    void loadLogs(pagination.value.current_page + 1)
  }
}

onMounted(async () => {
  await Promise.all([
    loadLogs(),
    loadUsers(),
  ])
})
</script>

<template>
  <section class="grid gap-5">
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <p class="m-0 mb-2 text-brand-orange-800 text-xs font-extrabold tracking-widest uppercase">
          Auditoría del sistema
        </p>

        <h2 class="m-0 text-2xl font-bold text-slate-900">Bitácora de operaciones</h2>

        <p class="mt-2 mb-0 text-slate-600 leading-relaxed text-sm max-w-2xl">
          Consulta las acciones realizadas por los usuarios y
          los cambios aplicados a la información.
        </p>
      </div>

      <button
        type="button"
        class="inline-flex w-full sm:w-auto min-h-[3rem] items-center justify-center gap-2 px-5 text-brand-green-800 bg-brand-green-100 border border-brand-green-200 hover:bg-brand-green-200/80 rounded-md font-[750] text-sm cursor-pointer transition-colors"
        :disabled="loading"
        @click="loadLogs(pagination.current_page)"
      >
        <RefreshCw
          :size="19"
          :class="{
            'animate-spin': loading,
          }"
          aria-hidden="true"
        />

        Actualizar
      </button>
    </header>

    <form
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-4 bg-white border border-slate-200 rounded-lg"
      @submit.prevent="applyFilters"
    >
      <div class="grid gap-1.5">
        <label for="logs-module" class="text-slate-900 text-sm font-bold">
          Módulo
        </label>

        <input
          id="logs-module"
          v-model="filters.module"
          type="text"
          list="operation-log-modules"
          maxlength="80"
          placeholder="Ej. Employee"
          class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
        />

        <datalist id="operation-log-modules">
          <option
            v-for="module in availableModules"
            :key="module"
            :value="module"
          />
        </datalist>
      </div>

      <div class="grid gap-1.5">
        <label for="logs-action" class="text-slate-900 text-sm font-bold">
          Acción
        </label>

        <input
          id="logs-action"
          v-model="filters.action"
          type="text"
          list="operation-log-actions"
          maxlength="80"
          placeholder="Ej. create"
          class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
        />

        <datalist id="operation-log-actions">
          <option
            v-for="action in availableActions"
            :key="action"
            :value="action"
          />
        </datalist>
      </div>

      <div
        v-if="authStore.can(PERMISSIONS.users.view)"
        class="grid gap-1.5"
      >
        <label for="logs-user" class="text-slate-900 text-sm font-bold">
          Usuario
        </label>

        <select
          id="logs-user"
          v-model="filters.userId"
          :disabled="usersLoading"
          class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
        >
          <option value="">
            Todos los usuarios
          </option>

          <option
            v-for="user in users"
            :key="user.id"
            :value="user.id"
          >
            {{ user.name }} (@{{ user.username }})
          </option>
        </select>
      </div>

      <div class="grid gap-1.5">
        <label for="logs-date-from" class="text-slate-900 text-sm font-bold">
          Desde
        </label>

        <input
          id="logs-date-from"
          v-model="filters.dateFrom"
          type="date"
          class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
        />
      </div>

      <div class="grid gap-1.5">
        <label for="logs-date-to" class="text-slate-900 text-sm font-bold">
          Hasta
        </label>

        <input
          id="logs-date-to"
          v-model="filters.dateTo"
          type="date"
          :min="filters.dateFrom || undefined"
          class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
        />
      </div>

      <div class="grid gap-1.5">
        <label for="logs-per-page" class="text-slate-900 text-sm font-bold">
          Registros
        </label>

        <select
          id="logs-per-page"
          v-model="filters.perPage"
          class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
        >
          <option :value="20">
            20 por página
          </option>

          <option :value="50">
            50 por página
          </option>

          <option :value="100">
            100 por página
          </option>
        </select>
      </div>

      <div class="flex gap-2 sm:col-span-2 lg:col-span-2 self-end">
        <button
          type="submit"
          class="inline-flex min-h-[3rem] px-4 items-center justify-center gap-2 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-[750] text-sm transition-colors cursor-pointer border-0 disabled:opacity-60"
          :disabled="loading"
        >
          <Search :size="19" aria-hidden="true" />
          Consultar
        </button>

        <button
          type="button"
          class="inline-flex min-h-[3rem] px-4 items-center justify-center gap-2 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-[750] text-sm transition-colors cursor-pointer disabled:opacity-60"
          :disabled="loading"
          @click="clearFilters"
        >
          <FilterX :size="19" aria-hidden="true" />
          Limpiar
        </button>
      </div>
    </form>

    <div
      v-if="loading"
      class="grid place-content-center min-h-[18rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <div class="w-9 h-9 border-4 border-brand-green-100 border-t-brand-green-700 rounded-full animate-spin mx-auto" />
      <p class="text-slate-600 text-sm">Cargando registros...</p>
    </div>

    <div
      v-else-if="logs.length === 0"
      class="grid place-content-center min-h-[18rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <FileClock :size="44" class="text-slate-400 mx-auto" aria-hidden="true" />
      <h3 class="m-0 text-lg font-bold text-slate-900">No se encontraron registros</h3>
      <p class="m-0 text-slate-600 text-sm">Modifica los filtros de consulta.</p>
    </div>

    <template v-else>
      <div class="grid md:hidden gap-4">
        <article
          v-for="log in logs"
          :key="log.id"
          class="p-4 bg-white border border-slate-200 rounded-lg shadow-xs grid gap-3"
        >
          <header class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
              <div class="flex w-11 h-11 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-md shrink-0">
                <FileClock :size="21" aria-hidden="true" />
              </div>

              <div class="grid min-w-0">
                <strong class="text-slate-900 text-sm font-bold truncate">{{ formatModule(log.module) }}</strong>
                <span class="text-slate-400 text-xs">Registro #{{ log.id }}</span>
              </div>
            </div>

            <span
              class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold shrink-0"
              :class="{
                'text-emerald-800 bg-emerald-100': ['create', 'activate', 'receive', 'resolve', 'generate'].includes(log.action),
                'text-rose-800 bg-rose-100': ['deactivate', 'delete', 'cancel'].includes(log.action),
                'text-sky-800 bg-sky-100': !['create', 'activate', 'receive', 'resolve', 'generate', 'deactivate', 'delete', 'cancel'].includes(log.action),
              }"
            >
              {{ actionLabel(log.action) }}
            </span>
          </header>

          <p class="m-0 text-slate-600 text-sm leading-relaxed">
            {{ log.description }}
          </p>

          <dl class="grid gap-2 text-xs text-slate-500 m-0 pt-2 border-t border-slate-100">
            <div class="flex items-center gap-2 min-w-0">
              <CircleUserRound
                :size="17"
                class="shrink-0 text-slate-400"
                aria-hidden="true"
              />

              <dd class="m-0 truncate font-medium text-slate-700">
                {{ log.user?.name ?? 'Usuario no disponible' }}
              </dd>
            </div>

            <div class="flex items-center gap-2 min-w-0">
              <CalendarDays
                :size="17"
                class="shrink-0 text-slate-400"
                aria-hidden="true"
              />

              <dd class="m-0 text-slate-600">{{ formatDate(log.created_at) }}</dd>
            </div>

            <div class="flex items-center gap-2 min-w-0">
              <Globe2 :size="17" class="shrink-0 text-slate-400" aria-hidden="true" />

              <dd class="m-0 font-mono text-slate-600">{{ log.ip_address ?? 'Sin IP' }}</dd>
            </div>
          </dl>

          <button
            type="button"
            class="inline-flex min-h-[2.5rem] w-full items-center justify-center gap-2 px-3 text-brand-orange-900 bg-brand-orange-100 border border-brand-orange-200 hover:bg-brand-orange-200/80 rounded-md font-bold text-xs cursor-pointer transition-colors"
            @click="openDetail(log)"
          >
            <Eye :size="18" aria-hidden="true" />
            Ver detalle
          </button>
        </article>
      </div>

      <div class="hidden md:block">
        <div class="overflow-x-auto bg-white border border-slate-200 rounded-lg shadow-xs">
          <table class="w-full min-w-[66rem] border-collapse text-left text-sm">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-xs font-bold uppercase tracking-wider">
                <th class="p-4">Fecha</th>
                <th class="p-4">Usuario</th>
                <th class="p-4">Módulo</th>
                <th class="p-4">Acción</th>
                <th class="p-4">Descripción</th>
                <th class="p-4">IP</th>
                <th class="p-4 w-12" aria-label="Acciones" />
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-200">
              <tr
                v-for="log in logs"
                :key="log.id"
                class="hover:bg-brand-green-50/50 transition-colors"
              >
                <td class="p-4 text-slate-600 text-xs font-medium whitespace-nowrap">{{ formatDate(log.created_at) }}</td>

                <td class="p-4">
                  <div class="grid">
                    <strong class="text-slate-900 font-bold text-sm">
                      {{ log.user?.name ?? 'No disponible' }}
                    </strong>

                    <span v-if="log.user" class="text-slate-500 text-xs">
                      @{{ log.user.username }}
                    </span>
                  </div>
                </td>

                <td class="p-4 font-bold text-slate-900 text-xs whitespace-nowrap">{{ formatModule(log.module) }}</td>

                <td class="p-4 whitespace-nowrap">
                  <span
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold"
                    :class="{
                      'text-emerald-800 bg-emerald-100': ['create', 'activate', 'receive', 'resolve', 'generate'].includes(log.action),
                      'text-rose-800 bg-rose-100': ['deactivate', 'delete', 'cancel'].includes(log.action),
                      'text-sky-800 bg-sky-100': !['create', 'activate', 'receive', 'resolve', 'generate', 'deactivate', 'delete', 'cancel'].includes(log.action),
                    }"
                  >
                    {{ actionLabel(log.action) }}
                  </span>
                </td>

                <td class="p-4 max-w-xs text-slate-600 text-xs leading-relaxed truncate">
                  {{ log.description }}
                </td>

                <td class="p-4 font-mono text-slate-500 text-xs whitespace-nowrap">{{ log.ip_address ?? '—' }}</td>

                <td class="p-4">
                  <button
                    type="button"
                    class="inline-flex w-9 h-9 items-center justify-center text-brand-orange-900 bg-brand-orange-100 hover:bg-brand-orange-200/80 rounded-md border-0 cursor-pointer transition-colors"
                    title="Ver detalle"
                    @click="openDetail(log)"
                  >
                    <Eye :size="18" aria-hidden="true" />
                  </button>
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
          registros
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

    <OperationLogDetailDialog
      :open="detailOpen"
      :log="selectedLog"
      @close="closeDetail"
    />
  </section>
</template>