<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  reactive,
  ref,
  watch,
} from 'vue'

import {
  Activity,
  Boxes,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Clock3,
  DollarSign,
  FilterX,
  LoaderCircle,
  Pencil,
  Play,
  Search,
  UserPlus,
  UsersRound,
  X,
} from 'lucide-vue-next'

import Swal from 'sweetalert2'

import { PERMISSIONS } from '@/config/permissions'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { employeesService } from '@/modules/employees/services/employees.service'
import OperationProgressDialog from '@/modules/production-operation-logs/components/OperationProgressDialog.vue'
import { productionOperationLogsService } from '@/modules/production-operation-logs/services/production-operation-logs.service'
import {
  getApiErrorMessage,
  getValidationErrors,
} from '@/utils/api-error'

import type { Employee } from '@/modules/employees/types/employee.types'

import type {
  OperationProgressMode,
  ProductionOperationLog,
  ProductionOperationLogsQuery,
  ProductionOperationLogStatus,
} from '@/modules/production-operation-logs/types/production-operation-log.types'

import type { ProductionMovement } from '@/modules/production-movements/types/production-movement.types'
import type { PaginationMeta } from '@/types/api'

const props = defineProps<{
  open: boolean
  movement: ProductionMovement | null
}>()

const emit = defineEmits<{
  close: []
  changed: []
}>()

interface Filters {
  employeeId: number | ''
  status: ProductionOperationLogStatus | ''
  perPage: number
}

const authStore = useAuthStore()

const logs = ref<ProductionOperationLog[]>([])
const employees = ref<Employee[]>([])

const loading = ref(false)
const employeesLoading = ref(false)
const assigning = ref(false)

const assignmentVisible = ref(false)
const assignmentEmployeeId = ref<number | ''>('')
const assignmentNotes = ref('')

const formError = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const progressOpen = ref(false)
const progressLog = ref<ProductionOperationLog | null>(
  null,
)

const progressMode =
  ref<OperationProgressMode>('update')

const movementStatusOverride = ref<string | null>(
  null,
)

const filters = reactive<Filters>({
  employeeId: '',
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

const currentMovementStatus = computed<string>(() => {
  return (
    movementStatusOverride.value ??
    props.movement?.status ??
    ''
  )
})

const canLoadEmployees = computed<boolean>(() => {
  return authStore.can(PERMISSIONS.employees.view)
})

const canAssign = computed<boolean>(() => {
  const allowedStatuses = [
    'received',
    'in_progress',
    'partially_completed',
  ]

  return (
    allowedStatuses.includes(
      currentMovementStatus.value,
    ) &&
    authStore.can(PERMISSIONS.processes.assign) &&
    canLoadEmployees.value
  )
})

const canUpdate = computed<boolean>(() => {
  return authStore.can(
    PERMISSIONS.processes.updateStatus,
  )
})

const showPayroll = computed<boolean>(() => {
  return authStore.can(PERMISSIONS.payroll.view)
})

const totalProcessed = computed<number>(() => {
  return logs.value.reduce(
    (total, log) =>
      total + Number(log.quantity_processed || 0),
    0,
  )
})

const effectiveQuantity = computed<number>(() => {
  return props.movement?.effective_quantity ?? 0
})

const remainingQuantity = computed<number>(() => {
  return Math.max(
    effectiveQuantity.value - totalProcessed.value,
    0,
  )
})

const progressPercentage = computed<number>(() => {
  if (effectiveQuantity.value <= 0) {
    return 0
  }

  return Math.min(
    Math.round(
      (totalProcessed.value /
        effectiveQuantity.value) *
        100,
    ),
    100,
  )
})

const activeAssignedEmployeeIds =
  computed<Set<number>>(() => {
    const ids = logs.value
      .filter((log) =>
        ['pending', 'in_progress'].includes(
          log.status,
        ),
      )
      .map((log) => log.employee?.id)
      .filter(
        (id): id is number =>
          typeof id === 'number',
      )

    return new Set(ids)
  })

const availableEmployees = computed<Employee[]>(() => {
  return employees.value.filter(
    (employee) =>
      !activeAssignedEmployeeIds.value.has(
        employee.id,
      ),
  )
})

function formatDateTime(
  value: string | null,
): string {
  if (!value) {
    return 'No registrada'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

function formatMoney(
  value: string | null | undefined,
): string {
  const amount = Number(value ?? 0)

  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
  }).format(
    Number.isFinite(amount) ? amount : 0,
  )
}

function statusClass(
  status: ProductionOperationLogStatus,
): string {
  const classes: Record<
    ProductionOperationLogStatus,
    string
  > = {
    pending: 'text-amber-800 bg-amber-50 border border-amber-200/80',
    in_progress: 'text-sky-800 bg-sky-50 border border-sky-200/80',
    completed: 'text-emerald-800 bg-emerald-50 border border-emerald-200/80',
    cancelled: 'text-rose-800 bg-rose-50 border border-rose-200/80',
    with_incident: 'text-rose-800 bg-rose-50 border border-rose-200/80',
  }

  return classes[status]
}

function firstFieldError(field: string): string {
  return fieldErrors.value[field]?.[0] ?? ''
}

function setLocalError(
  field: string,
  message: string,
): void {
  fieldErrors.value[field] = [message]
}

function operationCanBeUpdated(
  log: ProductionOperationLog,
): boolean {
  return (
    canUpdate.value &&
    ['pending', 'in_progress'].includes(log.status)
  )
}

function maximumQuantityFor(
  selectedLog: ProductionOperationLog,
): number {
  const otherWorkersQuantity = logs.value
    .filter((log) => log.id !== selectedLog.id)
    .reduce(
      (total, log) =>
        total + Number(log.quantity_processed || 0),
      0,
    )

  return Math.max(
    effectiveQuantity.value - otherWorkersQuantity,
    selectedLog.quantity_processed,
  )
}

function updateMovementStatus(
  log: ProductionOperationLog,
): void {
  if (log.production_movement?.status) {
    movementStatusOverride.value =
      log.production_movement.status
  }
}

async function loadLogs(page = 1): Promise<void> {
  if (!props.movement) {
    return
  }

  loading.value = true

  try {
    const query: ProductionOperationLogsQuery = {
      employee_id: filters.employeeId,
      status: filters.status,
      per_page: filters.perPage,
      page,
    }

    const response =
      await productionOperationLogsService.list(
        props.movement.id,
        query,
      )

    logs.value = response.data
    pagination.value = response.meta
  } catch (error) {
    formError.value = getApiErrorMessage(
      error,
      'No fue posible cargar los avances.',
    )
  } finally {
    loading.value = false
  }
}

async function loadEmployees(): Promise<void> {
  if (
    !props.movement?.to_area?.id ||
    !canLoadEmployees.value
  ) {
    employees.value = []
    return
  }

  employeesLoading.value = true

  try {
    const response = await employeesService.list({
      area_id: props.movement.to_area.id,
      status: 'active',
      per_page: 100,
      page: 1,
    })

    employees.value = response.data
  } catch {
    employees.value = []
  } finally {
    employeesLoading.value = false
  }
}

function applyFilters(): void {
  void loadLogs(1)
}

function clearFilters(): void {
  filters.employeeId = ''
  filters.status = ''
  filters.perPage = 15

  void loadLogs(1)
}

function resetAssignment(): void {
  assignmentEmployeeId.value = ''
  assignmentNotes.value = ''
  fieldErrors.value = {}
}

function validateAssignment(): boolean {
  fieldErrors.value = {}
  formError.value = ''

  if (!assignmentEmployeeId.value) {
    setLocalError(
      'employee_id',
      'Selecciona un trabajador.',
    )
  }

  if (assignmentNotes.value.length > 3000) {
    setLocalError(
      'notes',
      'Las notas no pueden superar 3000 caracteres.',
    )
  }

  return Object.keys(fieldErrors.value).length === 0
}

async function assignEmployee(): Promise<void> {
  if (
    !props.movement ||
    !canAssign.value ||
    !validateAssignment() ||
    !assignmentEmployeeId.value
  ) {
    return
  }

  assigning.value = true
  formError.value = ''

  try {
    const response =
      await productionOperationLogsService.assign(
        props.movement.id,
        {
          employee_id: assignmentEmployeeId.value,
          notes:
            assignmentNotes.value.trim() || null,
        },
      )

    updateMovementStatus(response.data)
    resetAssignment()
    assignmentVisible.value = false

    await Swal.fire({
      title: response.message,
      icon: 'success',
      timer: 1600,
      showConfirmButton: false,
    })

    await loadLogs(1)
    emit('changed')
  } catch (error) {
    fieldErrors.value = getValidationErrors(error)

    formError.value = getApiErrorMessage(
      error,
      'No fue posible asignar al trabajador.',
    )
  } finally {
    assigning.value = false
  }
}

function openProgress(
  log: ProductionOperationLog,
  mode: OperationProgressMode,
): void {
  progressLog.value = log
  progressMode.value = mode
  progressOpen.value = true
}

function closeProgress(): void {
  progressOpen.value = false
  progressLog.value = null
}

async function handleProgressSaved(
  log: ProductionOperationLog,
  message: string,
): Promise<void> {
  updateMovementStatus(log)
  closeProgress()

  await Swal.fire({
    title: message,
    icon: 'success',
    timer: 1600,
    showConfirmButton: false,
  })

  await loadLogs(pagination.value.current_page)
  emit('changed')
}

function previousPage(): void {
  if (pagination.value.current_page > 1) {
    void loadLogs(
      pagination.value.current_page - 1,
    )
  }
}

function nextPage(): void {
  if (
    pagination.value.current_page <
    pagination.value.last_page
  ) {
    void loadLogs(
      pagination.value.current_page + 1,
    )
  }
}

function requestClose(): void {
  if (!assigning.value) {
    emit('close')
  }
}

function resetDialog(): void {
  logs.value = []
  employees.value = []

  filters.employeeId = ''
  filters.status = ''
  filters.perPage = 15

  pagination.value = {
    current_page: 1,
    from: null,
    last_page: 1,
    per_page: 15,
    to: null,
    total: 0,
  }

  assignmentVisible.value = false
  resetAssignment()

  formError.value = ''
  movementStatusOverride.value = null
}

watch(
  () => props.open,
  async (open) => {
    document.body.style.overflow =
      open ? 'hidden' : ''

    if (open && props.movement) {
      resetDialog()

      await Promise.all([
        loadLogs(),
        loadEmployees(),
      ])
    }

    if (!open) {
      resetDialog()
    }
  },
)

watch(
  () => props.movement?.id,
  async () => {
    if (props.open && props.movement) {
      resetDialog()

      await Promise.all([
        loadLogs(),
        loadEmployees(),
      ])
    }
  },
)

onBeforeUnmount(() => {
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open && movement"
      class="fixed inset-0 z-[115] flex items-stretch sm:items-center justify-center bg-slate-950/62 backdrop-blur-xs sm:p-6"
      @click.self="requestClose"
    >
      <section
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:w-[min(100%,64rem)] overflow-hidden bg-white sm:rounded-xl sm:shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="operations-dialog-title"
      >
        <header class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 p-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 border-b border-slate-200">
          <div class="flex w-11 h-11 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-md">
            <Activity :size="23" aria-hidden="true" />
          </div>

          <span class="grid">
            <small class="text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Control de producción</small>

            <h2 id="operations-dialog-title" class="m-0 text-xl font-bold text-slate-900">
              Trabajadores y avances
            </h2>
          </span>

          <button
            type="button"
            class="inline-flex w-[2.75rem] min-h-[2.75rem] items-center justify-center p-0 text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-md border-0 cursor-pointer transition-colors disabled:opacity-50"
            aria-label="Cerrar avances"
            :disabled="assigning"
            @click="requestClose"
          >
            <X :size="22" aria-hidden="true" />
          </button>
        </header>

        <div class="grid gap-5 overflow-y-auto p-5 px-4 sm:px-6">
          <div
            v-if="formError"
            class="p-4 text-rose-700 bg-rose-50 border border-rose-200 rounded-md text-sm leading-relaxed"
            role="alert"
          >
            {{ formError }}
          </div>

          <section class="grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] items-center gap-3 p-4 bg-brand-green-50/70 border border-brand-green-200/80 rounded-xl">
            <div class="grid">
              <strong class="text-slate-900 font-bold text-base truncate">
                {{
                  movement.garment_cut?.code ??
                  `Movimiento #${movement.id}`
                }}
              </strong>

              <span class="text-slate-600 text-xs truncate">
                {{ movement.target_type_label }}
              </span>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-slate-600 text-sm">
              <span class="font-medium truncate">
                {{ movement.from_area?.name ?? 'Origen' }}
              </span>

              <span class="text-slate-400">→</span>

              <span class="font-medium truncate">
                {{ movement.to_area?.name ?? 'Destino' }}
              </span>
            </div>

            <em class="self-start sm:self-center px-3 py-1 bg-white text-slate-800 rounded-full text-xs font-bold shadow-xs not-italic">
              {{
                movementStatusOverride
                  ? currentMovementStatus
                  : movement.status_label
              }}
            </em>
          </section>

          <section class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <article class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-lg">
              <Boxes :size="22" class="text-brand-green-800 shrink-0" aria-hidden="true" />

              <span class="grid">
                <small class="text-slate-500 text-xs">Cantidad efectiva</small>
                <strong class="text-slate-900 font-mono font-bold text-base">{{ effectiveQuantity }}</strong>
              </span>
            </article>

            <article class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-lg">
              <CheckCircle2
                :size="22"
                class="text-brand-green-800 shrink-0"
                aria-hidden="true"
              />

              <span class="grid">
                <small class="text-slate-500 text-xs">Piezas procesadas</small>
                <strong class="text-slate-900 font-mono font-bold text-base">{{ totalProcessed }}</strong>
              </span>
            </article>

            <article class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-lg">
              <Clock3 :size="22" class="text-brand-green-800 shrink-0" aria-hidden="true" />

              <span class="grid">
                <small class="text-slate-500 text-xs">Piezas pendientes</small>
                <strong class="text-slate-900 font-mono font-bold text-base">{{ remainingQuantity }}</strong>
              </span>
            </article>

            <article class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-lg">
              <UsersRound :size="22" class="text-brand-green-800 shrink-0" aria-hidden="true" />

              <span class="grid">
                <small class="text-slate-500 text-xs">Asignaciones</small>
                <strong class="text-slate-900 font-mono font-bold text-base">{{ pagination.total }}</strong>
              </span>
            </article>
          </section>

          <section class="grid gap-2">
            <header class="flex justify-between items-center text-xs font-bold">
              <span class="text-slate-600">Progreso general</span>
              <strong class="text-slate-900 font-mono">{{ progressPercentage }}%</strong>
            </header>

            <div class="overflow-hidden h-2.5 bg-slate-100 rounded-full border border-slate-200">
              <span
                class="block h-full bg-brand-green-700 rounded-full transition-all duration-300"
                :style="{
                  width: `${progressPercentage}%`,
                }"
              />
            </div>
          </section>

          <aside
            v-if="currentMovementStatus === 'pending'"
            class="p-4 text-amber-800 bg-amber-50 border border-amber-200 rounded-xl text-xs leading-relaxed"
          >
            Debes confirmar la recepción del movimiento antes
            de asignar trabajadores.
          </aside>

          <aside
            v-else-if="
              authStore.can(
                PERMISSIONS.processes.assign,
              ) &&
              !canLoadEmployees
            "
            class="p-4 text-amber-800 bg-amber-50 border border-amber-200 rounded-xl text-xs leading-relaxed"
          >
            El usuario puede asignar operaciones, pero no
            dispone del permiso `employees.view` para consultar
            el catálogo de trabajadores.
          </aside>

          <section
            v-if="canAssign"
            class="grid gap-4"
          >
            <header class="flex items-center justify-between gap-3">
              <div>
                <p class="m-0 text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Responsables</p>
                <h3 class="m-0 mt-0.5 text-base font-bold text-slate-900">Asignar trabajador</h3>
              </div>

              <button
                type="button"
                class="inline-flex min-h-[2.75rem] items-center justify-center gap-2 px-3 text-brand-green-800 bg-brand-green-100 hover:bg-brand-green-200/80 border border-brand-green-200 rounded-md font-bold text-xs cursor-pointer transition-colors"
                @click="
                  assignmentVisible =
                    !assignmentVisible
                "
              >
                <UserPlus
                  :size="19"
                  aria-hidden="true"
                />

                {{
                  assignmentVisible
                    ? 'Ocultar formulario'
                    : 'Asignar trabajador'
                }}
              </button>
            </header>

            <form
              v-if="assignmentVisible"
              class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-slate-50 border border-slate-200 rounded-xl"
              novalidate
              @submit.prevent="assignEmployee"
            >
              <div class="grid gap-1.5">
                <label for="operation-employee" class="text-slate-900 text-sm font-bold">
                  Trabajador
                </label>

                <select
                  id="operation-employee"
                  v-model="assignmentEmployeeId"
                  :disabled="
                    employeesLoading || assigning
                  "
                  class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-500"
                  :class="{
                    'border-rose-700!': firstFieldError('employee_id'),
                  }"
                >
                  <option value="">
                    Selecciona un trabajador
                  </option>

                  <option
                    v-for="
                      employee in availableEmployees
                    "
                    :key="employee.id"
                    :value="employee.id"
                  >
                    {{ employee.name }}
                    ·
                    {{
                      employee.worker_type ===
                      'internal'
                        ? 'Interno'
                        : 'Externo'
                    }}
                  </option>
                </select>

                <small
                  v-if="firstFieldError('employee_id')"
                  class="text-rose-600 text-xs"
                >
                  {{ firstFieldError('employee_id') }}
                </small>
              </div>

              <div class="grid gap-1.5">
                <label for="assignment-notes" class="text-slate-900 text-sm font-bold">
                  Indicaciones
                </label>

                <textarea
                  id="assignment-notes"
                  v-model="assignmentNotes"
                  rows="3"
                  maxlength="3000"
                  placeholder="Instrucciones para el trabajador"
                  :disabled="assigning"
                  class="w-full p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 leading-relaxed resize-y"
                  :class="{
                    'border-rose-700!': firstFieldError('notes'),
                  }"
                />

                <div class="flex justify-between items-center gap-3">
                  <small
                    v-if="firstFieldError('notes')"
                    class="text-rose-600 text-xs"
                  >
                    {{ firstFieldError('notes') }}
                  </small>

                  <span class="ml-auto text-slate-400 text-xs">
                    {{ assignmentNotes.length }}/3000
                  </span>
                </div>
              </div>

              <button
                type="submit"
                class="col-span-full sm:justify-self-end inline-flex min-h-[3rem] sm:w-48 items-center justify-center gap-2 px-5 text-white bg-brand-orange-800 hover:bg-brand-orange-900 border-0 rounded-md font-[750] text-sm cursor-pointer transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="
                  assigning ||
                  employeesLoading ||
                  availableEmployees.length === 0
                "
              >
                <LoaderCircle
                  v-if="assigning"
                  :size="19"
                  class="animate-spin"
                  aria-hidden="true"
                />

                {{
                  assigning
                    ? 'Asignando...'
                    : 'Confirmar asignación'
                }}
              </button>
            </form>
          </section>

          <form
            class="grid grid-cols-1 sm:grid-cols-[repeat(3,minmax(0,1fr))_auto_auto] gap-2 p-4 bg-white border border-slate-200 rounded-xl"
            @submit.prevent="applyFilters"
          >
            <select
              v-model="filters.employeeId"
              aria-label="Filtrar por trabajador"
              class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
            >
              <option value="">
                Todos los trabajadores
              </option>

              <option
                v-for="employee in employees"
                :key="employee.id"
                :value="employee.id"
              >
                {{ employee.name }}
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
              <option value="pending">
                Pendientes
              </option>
              <option value="in_progress">
                En proceso
              </option>
              <option value="completed">
                Completadas
              </option>
              <option value="cancelled">
                Canceladas
              </option>
              <option value="with_incident">
                Con incidencia
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

            <button
              type="submit"
              class="inline-flex min-h-[3rem] items-center justify-center gap-2 px-4 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-bold text-sm cursor-pointer transition-colors border-0 disabled:opacity-50"
              :disabled="loading"
            >
              <Search :size="18" aria-hidden="true" />
              Consultar
            </button>

            <button
              type="button"
              class="inline-flex min-h-[3rem] items-center justify-center gap-2 px-4 text-slate-700 bg-white hover:bg-slate-50 border border-slate-300 rounded-md font-bold text-sm cursor-pointer transition-colors disabled:opacity-50"
              :disabled="loading"
              @click="clearFilters"
            >
              <FilterX
                :size="18"
                aria-hidden="true"
              />
              Limpiar
            </button>
          </form>

          <div
            v-if="loading"
            class="grid min-h-60 place-items-center align-content-center gap-3 p-6 text-center text-slate-600 bg-slate-50 border border-slate-200 rounded-xl"
          >
            <div class="w-10 h-10 border-3 border-brand-green-200 border-t-brand-green-700 rounded-full animate-spin" />
            <p class="m-0 text-sm font-medium">Cargando avances...</p>
          </div>

          <div
            v-else-if="logs.length === 0"
            class="grid min-h-60 place-items-center align-content-center gap-3 p-6 text-center text-slate-600 bg-slate-50 border border-slate-200 rounded-xl"
          >
            <UsersRound
              :size="42"
              class="text-slate-400"
              aria-hidden="true"
            />

            <h3 class="m-0 text-base font-bold text-slate-900">Sin trabajadores asignados</h3>

            <p class="m-0 text-sm text-slate-500 max-w-md">
              Asigna un trabajador para comenzar a registrar
              el avance de esta operación.
            </p>
          </div>

          <div
            v-else
            class="grid grid-cols-1 md:grid-cols-2 gap-4"
          >
            <article
              v-for="log in logs"
              :key="log.id"
              class="grid gap-4 p-4 bg-white border border-slate-200 rounded-xl shadow-xs"
            >
              <header class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3">
                <div class="flex w-11 h-11 items-center justify-center text-brand-green-900 bg-brand-green-200 rounded-full font-extrabold text-base">
                  {{
                    log.employee?.name
                      ?.charAt(0)
                      .toUpperCase() ?? '?'
                  }}
                </div>

                <span class="grid min-w-0">
                  <strong class="text-slate-900 font-bold text-sm truncate">
                    {{
                      log.employee?.name ??
                      'Trabajador no disponible'
                    }}
                  </strong>

                  <small class="text-slate-500 text-xs truncate">
                    {{
                      log.operation_process?.name ??
                      'Operación no disponible'
                    }}
                  </small>
                </span>

                <em
                  class="px-2.5 py-1 rounded-full text-xs font-bold not-italic"
                  :class="statusClass(log.status)"
                >
                  {{ log.status_label }}
                </em>
              </header>

              <section class="grid gap-1.5">
                <div class="flex justify-between items-center text-xs">
                  <span class="text-slate-500 font-medium">Avance individual</span>

                  <strong class="text-slate-900 font-mono font-bold">
                    {{ log.quantity_processed }}
                    piezas
                  </strong>
                </div>

                <div class="overflow-hidden h-2 bg-slate-100 rounded-full border border-slate-200">
                  <span
                    class="block h-full bg-brand-orange-800 rounded-full transition-all duration-300"
                    :style="{
                      width: `${
                        effectiveQuantity > 0
                          ? Math.min(
                              (log.quantity_processed /
                                effectiveQuantity) *
                                100,
                              100,
                            )
                          : 0
                      }%`,
                    }"
                  />
                </div>
              </section>

              <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 m-0 text-xs">
                <div class="flex justify-between items-center gap-2 p-2 bg-slate-50 rounded-md border border-slate-100">
                  <dt class="text-slate-500">Inicio</dt>
                  <dd class="m-0 font-bold text-slate-900 text-right truncate">
                    {{ formatDateTime(log.start_time) }}
                  </dd>
                </div>

                <div class="flex justify-between items-center gap-2 p-2 bg-slate-50 rounded-md border border-slate-100">
                  <dt class="text-slate-500">Finalización</dt>
                  <dd class="m-0 font-bold text-slate-900 text-right truncate">
                    {{ formatDateTime(log.end_time) }}
                  </dd>
                </div>

                <div
                  v-if="log.stitches_count > 0"
                  class="flex justify-between items-center gap-2 p-2 bg-slate-50 rounded-md border border-slate-100"
                >
                  <dt class="text-slate-500">Puntadas por pieza</dt>
                  <dd class="m-0 font-mono font-bold text-slate-900 text-right">{{ log.stitches_count }}</dd>
                </div>

                <div
                  v-if="log.applications_count > 0"
                  class="flex justify-between items-center gap-2 p-2 bg-slate-50 rounded-md border border-slate-100"
                >
                  <dt class="text-slate-500">Aplicaciones por pieza</dt>
                  <dd class="m-0 font-mono font-bold text-slate-900 text-right">
                    {{ log.applications_count }}
                  </dd>
                </div>
              </dl>

              <p
                v-if="log.notes"
                class="m-0 p-3 text-slate-600 bg-slate-50 border border-slate-100 rounded-md text-xs leading-relaxed"
              >
                {{ log.notes }}
              </p>

              <aside
                v-if="
                  showPayroll &&
                  log.payout_amount !== undefined
                "
                class="flex items-center gap-3 p-3 text-emerald-800 bg-emerald-50 border border-emerald-200/80 rounded-md text-xs"
              >
                <DollarSign
                  :size="19"
                  class="shrink-0"
                  aria-hidden="true"
                />

                <span class="grid flex-1 min-w-0">
                  <small class="text-emerald-700 font-medium">Pago calculado</small>

                  <strong class="font-mono font-bold text-sm">
                    {{
                      formatMoney(
                        log.payout_amount,
                      )
                    }}
                  </strong>
                </span>

                <em class="not-italic font-bold bg-white/80 px-2 py-0.5 rounded-md">
                  {{ log.payout_status ?? 'Pendiente' }}
                </em>
              </aside>

              <footer
                v-if="operationCanBeUpdated(log)"
                class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-3 border-t border-slate-200"
              >
                <button
                  v-if="log.status === 'pending'"
                  type="button"
                  class="inline-flex min-h-[2.5rem] items-center justify-center gap-2 px-3 text-blue-700 bg-blue-50 border border-blue-200/80 hover:bg-blue-100 rounded-md font-bold text-xs cursor-pointer transition-colors"
                  @click="
                    openProgress(log, 'start')
                  "
                >
                  <Play :size="18" aria-hidden="true" />
                  Iniciar
                </button>

                <button
                  type="button"
                  class="inline-flex min-h-[2.5rem] items-center justify-center gap-2 px-3 text-brand-orange-900 bg-brand-orange-100 border border-brand-orange-200 hover:bg-brand-orange-200/70 rounded-md font-bold text-xs cursor-pointer transition-colors"
                  @click="
                    openProgress(log, 'update')
                  "
                >
                  <Pencil
                    :size="18"
                    aria-hidden="true"
                  />
                  Registrar avance
                </button>

                <button
                  type="button"
                  class="inline-flex min-h-[2.5rem] items-center justify-center gap-2 px-3 text-emerald-800 bg-emerald-50 border border-emerald-200/80 hover:bg-emerald-100 rounded-md font-bold text-xs cursor-pointer transition-colors"
                  @click="
                    openProgress(log, 'complete')
                  "
                >
                  <CheckCircle2
                    :size="18"
                    aria-hidden="true"
                  />
                  Completar
                </button>
              </footer>
            </article>
          </div>

          <footer
            v-if="pagination.total > 0"
            class="grid grid-cols-1 sm:grid-cols-[1fr_auto] items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-600"
          >
            <p class="m-0 text-center sm:text-left">
              Mostrando
              <strong class="font-mono text-slate-900">{{ pagination.from ?? 0 }}</strong>
              a
              <strong class="font-mono text-slate-900">{{ pagination.to ?? 0 }}</strong>
              de
              <strong class="font-mono text-slate-900">{{ pagination.total }}</strong>
              asignaciones
            </p>

            <div class="grid grid-cols-[auto_1fr_auto] items-center gap-2">
              <button
                type="button"
                class="inline-flex min-h-[2.25rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-bold text-xs cursor-pointer transition-colors disabled:opacity-50"
                :disabled="
                  pagination.current_page <= 1 ||
                  loading
                "
                @click="previousPage"
              >
                <ChevronLeft
                  :size="18"
                  aria-hidden="true"
                />
                Anterior
              </button>

              <span class="text-center font-medium px-2">
                Página {{ pagination.current_page }}
                de {{ pagination.last_page }}
              </span>

              <button
                type="button"
                class="inline-flex min-h-[2.25rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-bold text-xs cursor-pointer transition-colors disabled:opacity-50"
                :disabled="
                  pagination.current_page >=
                    pagination.last_page ||
                  loading
                "
                @click="nextPage"
              >
                Siguiente
                <ChevronRight
                  :size="18"
                  aria-hidden="true"
                />
              </button>
            </div>
          </footer>
        </div>

        <footer class="p-4 sm:px-6 pb-[max(1rem,env(safe-area-inset-bottom))] border-t border-slate-200 flex justify-end">
          <button
            type="button"
            class="w-full sm:w-auto min-h-[3rem] px-6 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-[750] text-sm transition-colors border-0 cursor-pointer"
            @click="requestClose"
          >
            Cerrar
          </button>
        </footer>
      </section>
    </div>

    <OperationProgressDialog
      :open="progressOpen"
      :log="progressLog"
      :mode="progressMode"
      :maximum-quantity="
        progressLog
          ? maximumQuantityFor(progressLog)
          : 0
      "
      @close="closeProgress"
      @saved="handleProgressSaved"
    />
  </Teleport>
</template>