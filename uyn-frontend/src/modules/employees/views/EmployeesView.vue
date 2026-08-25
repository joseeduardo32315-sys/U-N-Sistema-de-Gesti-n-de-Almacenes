<script setup lang="ts">
import {
  onMounted,
  reactive,
  ref,
} from 'vue'

import {
  Building2,
  ChevronLeft,
  ChevronRight,
  Pencil,
  Phone,
  Plus,
  Power,
  PowerOff,
  RotateCcw,
  Search,
  UserRoundCog,
  UsersRound,
} from 'lucide-vue-next'

import Swal from 'sweetalert2'

import { PERMISSIONS } from '@/config/permissions'
import { areasService } from '@/modules/areas/services/areas.service'
import EmployeeFormDialog from '@/modules/employees/components/EmployeeFormDialog.vue'
import { employeesService } from '@/modules/employees/services/employees.service'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { getApiErrorMessage } from '@/utils/api-error'

import type { Area } from '@/modules/areas/types/area.types'
import type {
  Employee,
  EmployeeStatus,
  WorkerType,
} from '@/modules/employees/types/employee.types'
import type { PaginationMeta } from '@/types/api'

interface Filters {
  search: string
  areaId: number | ''
  workerType: WorkerType | ''
  status: EmployeeStatus | ''
  perPage: number
}

const authStore = useAuthStore()

const employees = ref<Employee[]>([])
const areas = ref<Area[]>([])

const loading = ref(false)
const actionEmployeeId = ref<number | null>(null)

const formOpen = ref(false)
const selectedEmployee = ref<Employee | null>(null)

const filters = reactive<Filters>({
  search: '',
  areaId: '',
  workerType: '',
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

function formatDate(value: string): string {
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
  }).format(date)
}

function workerTypeLabel(employee: Employee): string {
  return (
    employee.worker_type_label ??
    (employee.worker_type === 'internal'
      ? 'Empleado interno'
      : 'Maquilero externo')
  )
}

function canCreate(): boolean {
  return authStore.can(PERMISSIONS.employees.create)
}

function canEdit(): boolean {
  return authStore.can(PERMISSIONS.employees.update)
}

function canActivate(employee: Employee): boolean {
  return (
    employee.status === 'inactive' &&
    authStore.can(PERMISSIONS.employees.activate)
  )
}

function canDeactivate(employee: Employee): boolean {
  return (
    employee.status === 'active' &&
    authStore.can(PERMISSIONS.employees.deactivate)
  )
}

async function loadEmployees(page = 1): Promise<void> {
  loading.value = true

  try {
    const response = await employeesService.list({
      search: filters.search.trim(),
      area_id: filters.areaId,
      worker_type: filters.workerType,
      status: filters.status,
      per_page: filters.perPage,
      page,
    })

    employees.value = response.data
    pagination.value = response.meta
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible cargar los empleados',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    loading.value = false
  }
}

async function loadAreas(): Promise<void> {
  try {
    areas.value = await areasService.list()
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible cargar las áreas',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  }
}

function applyFilters(): void {
  void loadEmployees(1)
}

function clearFilters(): void {
  filters.search = ''
  filters.areaId = ''
  filters.workerType = ''
  filters.status = ''
  filters.perPage = 15

  void loadEmployees(1)
}

function openCreateForm(): void {
  selectedEmployee.value = null
  formOpen.value = true
}

function openEditForm(employee: Employee): void {
  selectedEmployee.value = employee
  formOpen.value = true
}

function closeForm(): void {
  formOpen.value = false
  selectedEmployee.value = null
}

async function handleSaved(
  _employee: Employee,
  message: string,
): Promise<void> {
  closeForm()

  await Swal.fire({
    title: message,
    icon: 'success',
    timer: 1700,
    showConfirmButton: false,
  })

  await loadEmployees(pagination.value.current_page)
}

async function toggleEmployeeStatus(
  employee: Employee,
): Promise<void> {
  const activating = employee.status === 'inactive'

  const confirmation = await Swal.fire({
    title: activating
      ? '¿Activar empleado?'
      : '¿Desactivar empleado?',
    text: activating
      ? `${employee.name} podrá asignarse nuevamente a operaciones.`
      : `${employee.name} dejará de estar disponible para nuevas asignaciones.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: activating
      ? 'Sí, activar'
      : 'Sí, desactivar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: activating
      ? '#3f6b2a'
      : '#b91c1c',
  })

  if (!confirmation.isConfirmed) {
    return
  }

  actionEmployeeId.value = employee.id

  try {
    const response = activating
      ? await employeesService.activate(employee.id)
      : await employeesService.deactivate(employee.id)

    await Swal.fire({
      title: response.message,
      icon: 'success',
      timer: 1600,
      showConfirmButton: false,
    })

    await loadEmployees(pagination.value.current_page)
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible cambiar el estado',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    actionEmployeeId.value = null
  }
}

function previousPage(): void {
  if (pagination.value.current_page > 1) {
    void loadEmployees(
      pagination.value.current_page - 1,
    )
  }
}

function nextPage(): void {
  if (
    pagination.value.current_page <
    pagination.value.last_page
  ) {
    void loadEmployees(
      pagination.value.current_page + 1,
    )
  }
}

onMounted(async () => {
  await Promise.all([
    loadEmployees(),
    loadAreas(),
  ])
})
</script>

<template>
  <section class="grid gap-5">
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <p class="m-0 mb-2 text-brand-orange-800 text-xs font-extrabold tracking-wider uppercase">
          Catálogo operativo
        </p>

        <h2 class="m-0 text-2xl font-bold text-slate-900">Empleados</h2>

        <p class="max-w-[45rem] mt-2 mb-0 text-slate-600 leading-relaxed text-sm">
          Administra empleados internos y maquileros externos
          disponibles para el flujo productivo.
        </p>
      </div>

      <button
        v-if="canCreate()"
        type="button"
        class="inline-flex w-full sm:w-auto min-h-[3rem] items-center justify-center gap-2 px-4 py-3 text-white bg-brand-orange-800 hover:bg-brand-orange-900 rounded-md font-[750] text-sm transition-colors cursor-pointer border-0"
        @click="openCreateForm"
      >
        <Plus :size="20" aria-hidden="true" />
        Registrar empleado
      </button>
    </header>

    <form
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1fr_1fr_auto] gap-3 p-4 bg-white border border-slate-200 rounded-lg"
      @submit.prevent="applyFilters"
    >
      <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3 min-h-[3rem] px-3 bg-white border border-slate-300 rounded-md focus-within:border-brand-green-700 focus-within:ring-3 focus-within:ring-brand-green-700/13 transition-all">
        <Search :size="20" class="text-slate-400" aria-hidden="true" />

        <input
          v-model="filters.search"
          type="search"
          maxlength="150"
          placeholder="Buscar por nombre o teléfono"
          class="w-full min-w-0 bg-transparent border-0 outline-hidden text-slate-900 text-sm placeholder:text-slate-400"
        />
      </div>

      <select
        v-model="filters.areaId"
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
        v-model="filters.workerType"
        aria-label="Filtrar por tipo"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">
          Todos los tipos
        </option>

        <option value="internal">
          Empleados internos
        </option>

        <option value="external">
          Maquileros externos
        </option>
      </select>

      <select
        v-model="filters.status"
        aria-label="Filtrar por estado"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">
          Todos los estados
        </option>

        <option value="active">
          Activos
        </option>

        <option value="inactive">
          Inactivos
        </option>
      </select>

      <select
        v-model="filters.perPage"
        aria-label="Registros por página"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option :value="10">
          10 por página
        </option>

        <option :value="15">
          15 por página
        </option>

        <option :value="25">
          25 por página
        </option>

        <option :value="50">
          50 por página
        </option>
      </select>

      <div class="flex gap-2">
        <button
          type="submit"
          class="inline-flex min-h-[3rem] items-center justify-center gap-2 px-4 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-bold text-sm transition-colors cursor-pointer border-0 disabled:opacity-50"
          :disabled="loading"
        >
          <Search :size="19" aria-hidden="true" />
          Buscar
        </button>

        <button
          type="button"
          class="inline-flex min-h-[3rem] items-center justify-center gap-2 px-4 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-bold text-sm transition-colors cursor-pointer disabled:opacity-50"
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
      <p class="text-slate-600 text-sm">Cargando empleados...</p>
    </div>

    <div
      v-else-if="employees.length === 0"
      class="grid place-content-center min-h-[16rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <UsersRound :size="44" class="text-slate-400 mx-auto" aria-hidden="true" />

      <h3 class="m-0 text-lg font-bold text-slate-900">No se encontraron empleados</h3>

      <p class="m-0 text-slate-600 text-sm">
        Modifica los filtros o registra un nuevo trabajador.
      </p>
    </div>

    <template v-else>
      <div class="grid gap-4 md:hidden">
        <article
          v-for="employee in employees"
          :key="employee.id"
          class="p-4 bg-white border border-slate-200 rounded-xl shadow-xs grid gap-3"
        >
          <header class="flex items-center gap-3">
            <div class="flex w-11 h-11 shrink-0 items-center justify-center text-brand-orange-900 bg-brand-orange-100 rounded-full text-base font-extrabold">
              {{ employee.name.charAt(0).toUpperCase() }}
            </div>

            <div class="grid min-w-0 flex-1">
              <strong class="truncate text-slate-900 font-bold text-sm">{{ employee.name }}</strong>
              <span class="truncate text-slate-500 text-xs">{{ workerTypeLabel(employee) }}</span>
            </div>

            <span
              class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold shrink-0"
              :class="{
                'text-emerald-800 bg-emerald-100':
                  employee.status === 'active',
                'text-slate-700 bg-slate-200':
                  employee.status === 'inactive',
              }"
            >
              {{
                employee.status === 'active'
                  ? 'Activo'
                  : 'Inactivo'
              }}
            </span>
          </header>

          <div class="grid gap-2 py-2 border-y border-slate-100 text-xs text-slate-600">
            <div class="flex items-center gap-2.5">
              <Building2 :size="18" class="text-slate-400" aria-hidden="true" />

              <span>
                {{ employee.area?.name ?? 'Sin área' }}
              </span>
            </div>

            <div class="flex items-center gap-2.5">
              <Phone :size="18" class="text-slate-400" aria-hidden="true" />

              <span>{{ employee.phone }}</span>
            </div>

            <div class="flex items-center gap-2.5">
              <UserRoundCog
                :size="18"
                class="text-slate-400"
                aria-hidden="true"
              />

              <span>
                Registrado {{ formatDate(employee.created_at) }}
              </span>
            </div>
          </div>

          <p
            v-if="employee.notes"
            class="m-0 p-2.5 bg-slate-50 border border-slate-200 rounded-md text-xs text-slate-600 leading-normal italic"
          >
            {{ employee.notes }}
          </p>

          <footer class="flex items-center gap-2 pt-1">
            <button
              v-if="canEdit()"
              type="button"
              class="flex-1 inline-flex min-h-[2.5rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-md font-bold text-xs transition-colors border-0 cursor-pointer"
              @click="openEditForm(employee)"
            >
              <Pencil :size="18" aria-hidden="true" />
              Editar
            </button>

            <button
              v-if="canDeactivate(employee)"
              type="button"
              class="flex-1 inline-flex min-h-[2.5rem] items-center justify-center gap-1.5 px-3 text-red-700 bg-red-100 hover:bg-red-200/70 rounded-md font-bold text-xs transition-colors border-0 cursor-pointer disabled:opacity-50"
              :disabled="
                actionEmployeeId === employee.id
              "
              @click="toggleEmployeeStatus(employee)"
            >
              <PowerOff :size="18" aria-hidden="true" />
              Desactivar
            </button>

            <button
              v-if="canActivate(employee)"
              type="button"
              class="flex-1 inline-flex min-h-[2.5rem] items-center justify-center gap-1.5 px-3 text-emerald-800 bg-emerald-100 hover:bg-emerald-200/70 rounded-md font-bold text-xs transition-colors border-0 cursor-pointer disabled:opacity-50"
              :disabled="
                actionEmployeeId === employee.id
              "
              @click="toggleEmployeeStatus(employee)"
            >
              <Power :size="18" aria-hidden="true" />
              Activar
            </button>
          </footer>
        </article>
      </div>

      <div class="hidden md:block">
        <div class="overflow-x-auto bg-white border border-slate-200 rounded-xl shadow-xs">
          <table class="w-full border-collapse text-left text-sm">
            <thead>
              <tr>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">Empleado</th>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">Tipo</th>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">Área</th>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">Teléfono</th>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">Estado</th>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">Registro</th>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider" aria-label="Acciones" />
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="employee in employees"
                :key="employee.id"
              >
                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">
                  <div class="flex items-center gap-3">
                    <div class="flex w-9 h-9 shrink-0 items-center justify-center text-brand-orange-900 bg-brand-orange-100 rounded-full text-sm font-extrabold">
                      {{
                        employee.name
                          .charAt(0)
                          .toUpperCase()
                      }}
                    </div>

                    <span class="grid min-w-0">
                      <strong class="truncate text-slate-900 font-bold">{{ employee.name }}</strong>
                      <small class="truncate text-slate-500 text-xs">
                        ID #{{ employee.id }}
                      </small>
                    </span>
                  </div>
                </td>

                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">{{ workerTypeLabel(employee) }}</td>

                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">
                  {{ employee.area?.name ?? 'Sin área' }}
                </td>

                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">{{ employee.phone }}</td>

                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">
                  <span
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold shrink-0"
                    :class="{
                      'text-emerald-800 bg-emerald-100':
                        employee.status === 'active',
                      'text-slate-700 bg-slate-200':
                        employee.status === 'inactive',
                    }"
                  >
                    {{
                      employee.status === 'active'
                        ? 'Activo'
                        : 'Inactivo'
                    }}
                  </span>
                </td>

                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">{{ formatDate(employee.created_at) }}</td>

                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">
                  <div class="flex items-center gap-1.5 justify-end">
                    <button
                      v-if="canEdit()"
                      type="button"
                      title="Editar empleado"
                      class="inline-flex w-9 h-9 items-center justify-center p-0 text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 rounded-md transition-colors border-0 cursor-pointer"
                      @click="openEditForm(employee)"
                    >
                      <Pencil
                        :size="18"
                        aria-hidden="true"
                      />
                    </button>

                    <button
                      v-if="canDeactivate(employee)"
                      type="button"
                      class="inline-flex w-9 h-9 items-center justify-center p-0 text-red-700 bg-red-50 hover:bg-red-100 hover:text-red-800 rounded-md transition-colors border-0 cursor-pointer disabled:opacity-50"
                      title="Desactivar empleado"
                      :disabled="
                        actionEmployeeId === employee.id
                      "
                      @click="toggleEmployeeStatus(employee)"
                    >
                      <PowerOff
                        :size="18"
                        aria-hidden="true"
                      />
                    </button>

                    <button
                      v-if="canActivate(employee)"
                      type="button"
                      class="inline-flex w-9 h-9 items-center justify-center p-0 text-emerald-800 bg-emerald-50 hover:bg-emerald-100 hover:text-emerald-900 rounded-md transition-colors border-0 cursor-pointer disabled:opacity-50"
                      title="Activar empleado"
                      :disabled="
                        actionEmployeeId === employee.id
                      "
                      @click="toggleEmployeeStatus(employee)"
                    >
                      <Power
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
          empleados
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

    <EmployeeFormDialog
      :open="formOpen"
      :employee="selectedEmployee"
      :areas="areas"
      @close="closeForm"
      @saved="handleSaved"
    />
  </section>
</template>