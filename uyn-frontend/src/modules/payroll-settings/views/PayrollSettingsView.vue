<script setup lang="ts">
import {
  computed,
  onMounted,
  reactive,
  ref,
} from 'vue'

import {
  BadgeDollarSign,
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  CircleDollarSign,
  Pencil,
  Plus,
  RotateCcw,
  Search,
  Sparkles,
  UserRound,
} from 'lucide-vue-next'

import Swal from 'sweetalert2'

import { PERMISSIONS } from '@/config/permissions'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { employeesService } from '@/modules/employees/services/employees.service'
import { processesService } from '@/modules/processes/services/processes.service'

import EmbroideryPaymentSettingsPanel from '@/modules/payroll-settings/components/EmbroideryPaymentSettingsPanel.vue'
import EmployeeCompensationFormDialog from '@/modules/payroll-settings/components/EmployeeCompensationFormDialog.vue'
import PieceworkRateFormDialog from '@/modules/payroll-settings/components/PieceworkRateFormDialog.vue'
import CreatePayrollPeriodDialog from '@/modules/payroll-settings/components/CreatePayrollPeriodDialog.vue'
import PayrollPeriodDetailDialog from '@/modules/payroll-settings/components/PayrollPeriodDetailDialog.vue'

import { employeeCompensationsService } from '@/modules/payroll-settings/services/employee-compensations.service'
import { pieceworkRatesService } from '@/modules/payroll-settings/services/piecework-rates.service'
import { payrollPeriodsService } from '@/modules/payroll-settings/services/payroll-periods.service'

import { getApiErrorMessage } from '@/utils/api-error'

import type { Employee } from '@/modules/employees/types/employee.types'
import type { ProductionProcess } from '@/modules/processes/types/process.types'

import type {
  EmployeeCompensation,
  EmployeePaymentType,
  PayrollRuleStatus,
} from '@/modules/payroll-settings/types/employee-compensation.types'

import type { PieceworkRate } from '@/modules/payroll-settings/types/piecework-rate.types'
import type { PayrollPeriod, PayrollPeriodStatus, PayrollPeriodFrequency } from '@/modules/payroll-settings/types/payroll-period.types'
import type { PaginationMeta } from '@/types/api'

type PayrollTab =
  | 'compensations'
  | 'piecework-rates'
  | 'embroidery-settings'
  | 'payroll-periods'

interface CompensationFilters {
  employeeId: number | ''
  paymentType: EmployeePaymentType | ''
  status: PayrollRuleStatus | 'all'
  activeOn: string
  perPage: number
}

interface RateFilters {
  search: string
  employeeId: number | ''
  operationProcessId: number | ''
  status: PayrollRuleStatus | 'all'
  activeOn: string
  perPage: number
}

interface OperationOption {
  id: number
  name: string
  processName: string
}

const authStore = useAuthStore()

const activeTab =
  ref<PayrollTab>('compensations')

const compensations =
  ref<EmployeeCompensation[]>([])

const rates = ref<PieceworkRate[]>([])
const employees = ref<Employee[]>([])
const pieceworkEmployees = ref<Employee[]>([])
const processes = ref<ProductionProcess[]>([])

const compensationsLoading = ref(false)
const ratesLoading = ref(false)
const catalogsLoading = ref(false)

const compensationFormOpen = ref(false)
const rateFormOpen = ref(false)

const selectedCompensation =
  ref<EmployeeCompensation | null>(null)

const selectedRate =
  ref<PieceworkRate | null>(null)

const compensationFilters =
  reactive<CompensationFilters>({
    employeeId: '',
    paymentType: '',
    status: 'all',
    activeOn: '',
    perPage: 15,
  })

const rateFilters = reactive<RateFilters>({
  search: '',
  employeeId: '',
  operationProcessId: '',
  status: 'all',
  activeOn: '',
  perPage: 15,
})

const compensationPagination =
  ref<PaginationMeta>({
    current_page: 1,
    from: null,
    last_page: 1,
    per_page: 15,
    to: null,
    total: 0,
  })

const ratePagination = ref<PaginationMeta>({
  current_page: 1,
  from: null,
  last_page: 1,
  per_page: 15,
  to: null,
  total: 0,
})

const canManageCompensations =
  computed<boolean>(() => {
    return (
      authStore.can(PERMISSIONS.payroll.manage) &&
      authStore.can(PERMISSIONS.employees.view)
    )
  })

const canManageRates = computed<boolean>(() => {
  return (
    authStore.can(PERMISSIONS.payroll.manage) &&
    authStore.can(PERMISSIONS.employees.view) &&
    authStore.can(PERMISSIONS.processes.view)
  )
})

const canManageEmbroidery =
  computed<boolean>(() => {
    return (
      authStore.can(
        PERMISSIONS.payroll.manage,
      ) &&
      authStore.can(
        PERMISSIONS.processes.view,
      )
    )
  })

function isPieceworkOperation(
  calculationType?: string,
): boolean {
  const normalizedType =
    calculationType
      ?.trim()
      .toLocaleLowerCase('es') ?? ''

  if (!normalizedType) {
    return true
  }

  return [
    'standard',
    'per_piece',
  ].includes(normalizedType)
}

const operationOptions =
  computed<OperationOption[]>(() => {
    return processes.value
      .flatMap((process) => {
        const operations = Array.isArray(
          process.operations,
        )
          ? process.operations
          : []

        return operations
          .filter((operation) =>
            isPieceworkOperation(
              operation.payroll_calculation_type,
            ),
          )
          .map((operation) => ({
            id: operation.id,
            name: operation.name,
            processName: process.name,
          }))
      })
      .sort((first, second) => {
        const byProcess =
          first.processName.localeCompare(
            second.processName,
            'es',
          )

        if (byProcess !== 0) {
          return byProcess
        }

        return first.name.localeCompare(
          second.name,
          'es',
        )
      })
  })

function localDate(): string {
  const date = new Date()
  const offset = date.getTimezoneOffset()

  return new Date(
    date.getTime() - offset * 60_000,
  )
    .toISOString()
    .slice(0, 10)
}

function formatDate(
  value: string | null,
): string {
  if (!value) {
    return 'Sin fecha final'
  }

  const date = new Date(`${value}T00:00:00`)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
  }).format(date)
}

// Payroll Periods State & Functions
const periods = ref<PayrollPeriod[]>([])
const periodsLoading = ref(false)
const createPeriodOpen = ref(false)
const detailPeriodOpen = ref(false)
const selectedPeriodId = ref<number | null>(null)

const periodFilters = reactive({
  status: 'all' as PayrollPeriodStatus | 'all',
  frequency: '' as PayrollPeriodFrequency | '',
  search: '',
  perPage: 15,
})

const periodPagination = ref<PaginationMeta>({
  current_page: 1,
  from: null,
  last_page: 1,
  per_page: 15,
  to: null,
  total: 0,
})

async function loadPeriods(page = 1): Promise<void> {
  periodsLoading.value = true
  try {
    const response = await payrollPeriodsService.list({
      status: periodFilters.status || undefined,
      frequency: periodFilters.frequency || undefined,
      search: periodFilters.search.trim() || undefined,
      per_page: periodFilters.perPage,
      page,
    })
    periods.value = response.data
    periodPagination.value = response.meta
  } catch (error) {
    void Swal.fire({
      title: 'Error al cargar periodos de nómina',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    periodsLoading.value = false
  }
}

function clearPeriodFilters(): void {
  periodFilters.status = 'all'
  periodFilters.frequency = ''
  periodFilters.search = ''
  periodFilters.perPage = 15
  void loadPeriods(1)
}

function previousPeriodPage(): void {
  if (periodPagination.value.current_page > 1) {
    void loadPeriods(periodPagination.value.current_page - 1)
  }
}

function nextPeriodPage(): void {
  if (periodPagination.value.current_page < periodPagination.value.last_page) {
    void loadPeriods(periodPagination.value.current_page + 1)
  }
}

function openCreatePeriod(): void {
  createPeriodOpen.value = true
}

function closeCreatePeriod(): void {
  createPeriodOpen.value = false
}

function handlePeriodSaved(newPeriod: PayrollPeriod, message: string): void {
  createPeriodOpen.value = false
  void Swal.fire({
    title: 'Éxito',
    text: message,
    icon: 'success',
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
  })
  void loadPeriods(1)
}

function openPeriodDetail(id: number): void {
  selectedPeriodId.value = id
  detailPeriodOpen.value = true
}

function closePeriodDetail(): void {
  detailPeriodOpen.value = false
  selectedPeriodId.value = null
}

function handlePeriodUpdated(): void {
  void loadPeriods(periodPagination.value.current_page)
}

function periodStatusClass(status: string): string {
  const classes: Record<string, string> = {
    draft: 'text-slate-700 bg-slate-100',
    generated: 'text-amber-800 bg-amber-50 border border-amber-200/80',
    closed: 'text-emerald-800 bg-emerald-50 border border-emerald-200/80',
    cancelled: 'text-slate-600 bg-slate-100',
  }
  return classes[status] ?? 'text-slate-600 bg-slate-100'
}

function formatMoney(
  value: string | null,
  digits = 2,
): string {
  const amount = Number(value ?? 0)

  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(
    Number.isFinite(amount) ? amount : 0,
  )
}

function employeeArea(
  area:
    | string
    | { name: string }
    | null,
): string {
  if (typeof area === 'string') {
    return area
  }

  return area?.name ?? 'Sin área'
}

function compensationStatusClass(
  status: PayrollRuleStatus,
): string {
  return status === 'active'
    ? 'text-emerald-800 bg-emerald-50 border border-emerald-200/80'
    : 'text-slate-600 bg-slate-100'
}

async function loadCompensations(
  page = 1,
): Promise<void> {
  compensationsLoading.value = true

  try {
    const response =
      await employeeCompensationsService.list({
        employee_id:
          compensationFilters.employeeId,

        payment_type:
          compensationFilters.paymentType,

        status: compensationFilters.status,

        active_on:
          compensationFilters.activeOn,

        per_page:
          compensationFilters.perPage,

        page,
      })

    compensations.value = response.data
    compensationPagination.value =
      response.meta
  } catch (error) {
    await Swal.fire({
      title:
        'No fue posible cargar los esquemas de pago',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    compensationsLoading.value = false
  }
}

async function loadRates(
  page = 1,
): Promise<void> {
  ratesLoading.value = true

  try {
    const response =
      await pieceworkRatesService.list({
        search: rateFilters.search.trim(),

        employee_id:
          rateFilters.employeeId,

        operation_process_id:
          rateFilters.operationProcessId,

        status: rateFilters.status,

        active_on:
          rateFilters.activeOn,

        per_page: rateFilters.perPage,
        page,
      })

    rates.value = response.data
    ratePagination.value = response.meta
  } catch (error) {
    await Swal.fire({
      title:
        'No fue posible cargar las tarifas',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    ratesLoading.value = false
  }
}

async function loadCatalogs(): Promise<void> {
  catalogsLoading.value = true

  employees.value = []
  pieceworkEmployees.value = []
  processes.value = []

  try {
    const canViewEmployees = authStore.can(
      PERMISSIONS.employees.view,
    )

    const canViewProcesses = authStore.can(
      PERMISSIONS.processes.view,
    )

    const [
      employeesResponse,
      processesResponse,
    ] = await Promise.all([
      canViewEmployees
        ? employeesService.list({
            status: 'active',
            per_page: 100,
            page: 1,
          })
        : Promise.resolve(null),

      canViewProcesses
        ? processesService.list()
        : Promise.resolve(null),
    ])

    employees.value =
      employeesResponse?.data ?? []

    processes.value =
      processesResponse ?? []

    if (!canViewEmployees) {
      return
    }

    const compensationsResponse =
      await employeeCompensationsService.list({
        payment_type: 'piecework',
        status: 'active',
        active_on: localDate(),
        per_page: 100,
        page: 1,
      })

    const pieceworkEmployeeIds = new Set(
      compensationsResponse.data
        .filter(
          (compensation) =>
            compensation.is_current,
        )
        .map(
          (compensation) =>
            compensation.employee.id,
        ),
    )

    pieceworkEmployees.value =
      employees.value.filter((employee) =>
        pieceworkEmployeeIds.has(employee.id) &&
        employee.area?.name?.trim().toLocaleLowerCase('es') !== 'bordado'
      )
  } catch (error) {
    await Swal.fire({
      title:
        'No fue posible cargar los catálogos de nómina',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    catalogsLoading.value = false
  }
}

function applyCompensationFilters(): void {
  void loadCompensations(1)
}

function clearCompensationFilters(): void {
  compensationFilters.employeeId = ''
  compensationFilters.paymentType = ''
  compensationFilters.status = 'all'
  compensationFilters.activeOn = ''
  compensationFilters.perPage = 15

  void loadCompensations(1)
}

function applyRateFilters(): void {
  void loadRates(1)
}

function clearRateFilters(): void {
  rateFilters.search = ''
  rateFilters.employeeId = ''
  rateFilters.operationProcessId = ''
  rateFilters.status = 'all'
  rateFilters.activeOn = ''
  rateFilters.perPage = 15

  void loadRates(1)
}

function openCreateCompensation(): void {
  selectedCompensation.value = null
  compensationFormOpen.value = true
}

function openEditCompensation(
  compensation: EmployeeCompensation,
): void {
  selectedCompensation.value = compensation
  compensationFormOpen.value = true
}

function closeCompensationForm(): void {
  compensationFormOpen.value = false
  selectedCompensation.value = null
}

function openCreateRate(): void {
  selectedRate.value = null
  rateFormOpen.value = true
}

function openEditRate(rate: PieceworkRate): void {
  selectedRate.value = rate
  rateFormOpen.value = true
}

function closeRateForm(): void {
  rateFormOpen.value = false
  selectedRate.value = null
}

async function handleCompensationSaved(
  _compensation: EmployeeCompensation,
  message: string,
): Promise<void> {
  closeCompensationForm()

  await Swal.fire({
    title: message,
    icon: 'success',
    timer: 1700,
    showConfirmButton: false,
  })

  await Promise.all([
    loadCompensations(
      compensationPagination.value.current_page,
    ),
    loadCatalogs(),
  ])
}

async function handleRateSaved(
  _rate: PieceworkRate,
  message: string,
): Promise<void> {
  closeRateForm()

  await Swal.fire({
    title: message,
    icon: 'success',
    timer: 1700,
    showConfirmButton: false,
  })

  await loadRates(
    ratePagination.value.current_page,
  )
}

function previousCompensationPage(): void {
  if (
    compensationPagination.value.current_page > 1
  ) {
    void loadCompensations(
      compensationPagination.value.current_page -
        1,
    )
  }
}

function nextCompensationPage(): void {
  if (
    compensationPagination.value.current_page <
    compensationPagination.value.last_page
  ) {
    void loadCompensations(
      compensationPagination.value.current_page +
        1,
    )
  }
}

function previousRatePage(): void {
  if (ratePagination.value.current_page > 1) {
    void loadRates(
      ratePagination.value.current_page - 1,
    )
  }
}

function nextRatePage(): void {
  if (
    ratePagination.value.current_page <
    ratePagination.value.last_page
  ) {
    void loadRates(
      ratePagination.value.current_page + 1,
    )
  }
}

onMounted(async () => {
  await Promise.all([
    loadCompensations(),
    loadRates(),
    loadPeriods(),
    loadCatalogs(),
  ])
})
</script>

<template>
  <section class="grid gap-5">
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <p class="m-0 mb-2 text-brand-orange-800 text-xs font-extrabold tracking-widest uppercase">
          Control administrativo
        </p>

        <h2 class="m-0 text-xl font-bold text-slate-900">Configuración de nómina</h2>

        <p class="max-w-2xl m-0 mt-2 text-slate-600 text-sm">
          Define los esquemas de pago y las tarifas por
          operación aplicables a cada trabajador.
        </p>
      </div>

      <button
        v-if="
          activeTab === 'compensations' &&
          canManageCompensations
        "
        type="button"
        class="inline-flex w-full sm:w-auto min-h-[3rem] px-5 items-center justify-center gap-2 text-white bg-brand-orange-800 hover:bg-brand-orange-900 border-0 rounded-md font-[750] text-sm cursor-pointer transition-colors disabled:opacity-50"
        :disabled="catalogsLoading"
        @click="openCreateCompensation"
      >
        <Plus :size="20" aria-hidden="true" />
        Registrar esquema
      </button>

      <button
        v-else-if="
          activeTab === 'piecework-rates' &&
          canManageRates
        "
        type="button"
        class="inline-flex w-full sm:w-auto min-h-[3rem] px-5 items-center justify-center gap-2 text-white bg-brand-orange-800 hover:bg-brand-orange-900 border-0 rounded-md font-[750] text-sm cursor-pointer transition-colors disabled:opacity-50"
        :disabled="
          catalogsLoading ||
          pieceworkEmployees.length === 0
        "
        @click="openCreateRate"
      >
        <Plus :size="20" aria-hidden="true" />
        Registrar tarifa
      </button>

      <button
        v-else-if="
          activeTab === 'payroll-periods' &&
          authStore.can(PERMISSIONS.payroll.manage)
        "
        type="button"
        class="inline-flex w-full sm:w-auto min-h-[3rem] px-5 items-center justify-center gap-2 text-white bg-brand-orange-800 hover:bg-brand-orange-900 border-0 rounded-md font-[750] text-sm cursor-pointer transition-colors disabled:opacity-50"
        @click="openCreatePeriod"
      >
        <Plus :size="20" aria-hidden="true" />
        Nuevo periodo
      </button>
    </header>

    <nav
      class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 p-1.5 bg-slate-100/80 border border-slate-200/80 rounded-xl"
      aria-label="Configuración de nómina"
    >
      <button
        type="button"
        class="flex min-h-[3rem] items-center justify-center gap-2 px-3 text-slate-600 hover:bg-slate-200/60 rounded-md font-[750] text-sm cursor-pointer transition-all border-0"
        :class="{
          'bg-white! text-brand-green-800! shadow-xs': activeTab === 'compensations',
        }"
        @click="activeTab = 'compensations'"
      >
        <BadgeDollarSign
          :size="20"
          aria-hidden="true"
        />

        Esquemas de pago

        <span class="inline-flex min-w-[1.75rem] min-h-[1.75rem] items-center justify-center bg-slate-100 text-slate-700 rounded-full text-xs font-bold px-1">
          {{ compensationPagination.total }}
        </span>
      </button>

      <button
        type="button"
        class="flex min-h-[3rem] items-center justify-center gap-2 px-3 text-slate-600 hover:bg-slate-200/60 rounded-md font-[750] text-sm cursor-pointer transition-all border-0"
        :class="{
          'bg-white! text-brand-green-800! shadow-xs': activeTab === 'piecework-rates',
        }"
        @click="activeTab = 'piecework-rates'"
      >
        <CircleDollarSign
          :size="20"
          aria-hidden="true"
        />

        Tarifas de destajo

        <span class="inline-flex min-w-[1.75rem] min-h-[1.75rem] items-center justify-center bg-slate-100 text-slate-700 rounded-full text-xs font-bold px-1">{{ ratePagination.total }}</span>
      </button>

      <button
        type="button"
        class="flex min-h-[3rem] items-center justify-center gap-2 px-3 text-slate-600 hover:bg-slate-200/60 rounded-md font-[750] text-sm cursor-pointer transition-all border-0"
        :class="{
          'bg-white! text-brand-green-800! shadow-xs': activeTab === 'embroidery-settings',
        }"
        @click="activeTab = 'embroidery-settings'"
      >
        <Sparkles
          :size="20"
          aria-hidden="true"
        />

        Fórmula de Bordado
      </button>

      <button
        type="button"
        class="flex min-h-[3rem] items-center justify-center gap-2 px-3 text-slate-600 hover:bg-slate-200/60 rounded-md font-[750] text-sm cursor-pointer transition-all border-0"
        :class="{
          'bg-white! text-brand-green-800! shadow-xs': activeTab === 'payroll-periods',
        }"
        @click="activeTab = 'payroll-periods'"
      >
        <CalendarDays
          :size="20"
          aria-hidden="true"
        />

        Periodos de Nómina

        <span class="inline-flex min-w-[1.75rem] min-h-[1.75rem] items-center justify-center bg-slate-100 text-slate-700 rounded-full text-xs font-bold px-1">{{ periodPagination.total }}</span>
      </button>
    </nav>

    <template v-if="activeTab === 'compensations'">
      <form
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 p-4 bg-white border border-slate-200 rounded-xl shadow-xs"
        @submit.prevent="applyCompensationFilters"
      >
        <select
          v-model="compensationFilters.employeeId"
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
          v-model="compensationFilters.paymentType"
          aria-label="Filtrar por tipo de pago"
          class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
        >
          <option value="">
            Todos los tipos
          </option>

          <option value="piecework">
            Destajo
          </option>

          <option value="fixed">
            Pago fijo
          </option>
        </select>

        <select
          v-model="compensationFilters.status"
          aria-label="Filtrar por estado"
          class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
        >
          <option value="all">
            Todos los estados
          </option>

          <option value="active">
            Activos
          </option>

          <option value="inactive">
            Inactivos
          </option>
        </select>

        <input
          v-model="compensationFilters.activeOn"
          type="date"
          aria-label="Vigente en fecha"
          class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
        />

        <select
          v-model="compensationFilters.perPage"
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

        <div class="flex gap-2 col-span-full justify-end">
          <button
            type="submit"
            class="inline-flex min-h-[3rem] px-4 items-center justify-center gap-2 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-bold text-sm cursor-pointer transition-colors border-0 disabled:opacity-50"
            :disabled="compensationsLoading"
          >
            <Search :size="18" aria-hidden="true" />
            Consultar
          </button>

          <button
            type="button"
            class="inline-flex min-h-[3rem] px-4 items-center justify-center gap-2 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-bold text-sm cursor-pointer transition-colors disabled:opacity-50"
            :disabled="compensationsLoading"
            @click="clearCompensationFilters"
          >
            <RotateCcw
              :size="18"
              aria-hidden="true"
            />
            Limpiar
          </button>
        </div>
      </form>

      <div
        v-if="compensationsLoading"
        class="grid place-content-center min-h-[18rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
      >
        <div class="w-10 h-10 border-4 border-brand-green-100 border-t-brand-green-700 rounded-full animate-spin mx-auto" />
        <p class="m-0 text-slate-600 text-sm">Cargando esquemas de pago...</p>
      </div>

      <div
        v-else-if="compensations.length === 0"
        class="grid place-content-center min-h-[18rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
      >
        <BadgeDollarSign
          :size="44"
          class="text-slate-400 mx-auto"
          aria-hidden="true"
        />

        <h3 class="m-0 text-lg font-bold text-slate-900">No se encontraron esquemas</h3>

        <p class="m-0 text-slate-600 text-sm max-w-md">
          Registra la forma en la que será remunerado cada
          trabajador.
        </p>
      </div>

      <div
        v-else
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
      >
        <article
          v-for="compensation in compensations"
          :key="compensation.id"
          class="grid gap-4 p-4 bg-white border border-slate-200 rounded-xl shadow-xs"
        >
          <header class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3">
            <div class="flex w-11 h-11 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-md shrink-0">
              <UserRound
                :size="21"
                aria-hidden="true"
              />
            </div>

            <span class="grid min-w-0">
              <strong class="text-slate-900 font-bold text-sm truncate">
                {{ compensation.employee.name }}
              </strong>

              <small class="text-slate-500 text-xs truncate">
                {{
                  employeeArea(
                    compensation.employee.area,
                  )
                }}
              </small>
            </span>

            <em
              class="px-2.5 py-1 rounded-full text-xs font-bold not-italic"
              :class="
                compensationStatusClass(
                  compensation.status,
                )
              "
            >
              {{ compensation.status_label }}
            </em>
          </header>

          <div class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-100 rounded-xl">
            <BadgeDollarSign
              :size="20"
              class="text-brand-green-800 shrink-0"
              aria-hidden="true"
            />

            <span class="grid">
              <small class="text-slate-500 text-xs">Esquema de pago</small>

              <strong class="text-slate-900 font-bold text-sm">
                {{ compensation.payment_type_label }}
              </strong>
            </span>
          </div>

          <dl class="grid gap-2 m-0 text-xs border-t border-slate-100 pt-3">
            <div v-if="compensation.payment_frequency" class="flex justify-between items-center gap-3">
              <dt class="text-slate-500">Frecuencia</dt>
              <dd class="m-0 font-bold text-slate-800">
                {{
                  compensation.payment_frequency_label
                }}
              </dd>
            </div>

            <div v-if="compensation.fixed_amount" class="flex justify-between items-center gap-3">
              <dt class="text-slate-500">Monto fijo</dt>
              <dd class="m-0 font-mono font-bold text-slate-900">
                {{
                  formatMoney(
                    compensation.fixed_amount,
                  )
                }}
              </dd>
            </div>

            <div class="flex justify-between items-center gap-3">
              <dt class="text-slate-500">Vigencia</dt>

              <dd class="m-0 font-medium text-slate-700">
                {{
                  formatDate(
                    compensation.effective_from,
                  )
                }}
                —
                {{
                  formatDate(
                    compensation.effective_to,
                  )
                }}
              </dd>
            </div>

            <div class="flex justify-between items-center gap-3">
              <dt class="text-slate-500">Vigente actualmente</dt>
              <dd class="m-0 font-bold text-slate-800">
                {{
                  compensation.is_current
                    ? 'Sí'
                    : 'No'
                }}
              </dd>
            </div>
          </dl>

          <p v-if="compensation.notes" class="m-0 p-3 text-slate-600 bg-slate-50 border border-slate-100 rounded-md text-xs leading-relaxed">
            {{ compensation.notes }}
          </p>

          <footer
            v-if="
              authStore.can(
                PERMISSIONS.payroll.manage,
              )
            "
            class="pt-3 border-t border-slate-100"
          >
            <button
              type="button"
              class="w-full inline-flex min-h-[2.5rem] items-center justify-center gap-2 px-3 text-brand-green-800 bg-brand-green-100 hover:bg-brand-green-200/80 border border-brand-green-200 rounded-md font-bold text-xs cursor-pointer transition-colors"
              @click="
                openEditCompensation(compensation)
              "
            >
              <Pencil
                :size="18"
                aria-hidden="true"
              />
              Editar vigencia
            </button>
          </footer>
        </article>
      </div>

      <footer
        v-if="compensationPagination.total > 0"
        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 bg-white border border-slate-200 rounded-lg text-xs text-slate-600"
      >
        <p class="m-0 text-center sm:text-left">
          Mostrando
          <strong class="font-mono text-slate-900">
            {{ compensationPagination.from ?? 0 }}
          </strong>
          a
          <strong class="font-mono text-slate-900">
            {{ compensationPagination.to ?? 0 }}
          </strong>
          de
          <strong class="font-mono text-slate-900">
            {{ compensationPagination.total }}
          </strong>
          esquemas
        </p>

        <div class="flex items-center justify-center gap-2">
          <button
            type="button"
            class="inline-flex min-h-[2.25rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 rounded-md font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
            :disabled="
              compensationPagination.current_page <=
                1 ||
              compensationsLoading
            "
            @click="previousCompensationPage"
          >
            <ChevronLeft
              :size="18"
              aria-hidden="true"
            />
            Anterior
          </button>

          <span class="font-medium px-2">
            Página
            {{ compensationPagination.current_page }}
            de
            {{ compensationPagination.last_page }}
          </span>

          <button
            type="button"
            class="inline-flex min-h-[2.25rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 rounded-md font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
            :disabled="
              compensationPagination.current_page >=
                compensationPagination.last_page ||
              compensationsLoading
            "
            @click="nextCompensationPage"
          >
            Siguiente
            <ChevronRight
              :size="18"
              aria-hidden="true"
            />
          </button>
        </div>
      </footer>
    </template>

    <template v-else-if="activeTab === 'piecework-rates'">
      <aside
        v-if="
          canManageRates &&
          pieceworkEmployees.length === 0
        "
        class="p-4 text-amber-800 bg-amber-50 border border-amber-200 rounded-xl text-xs leading-relaxed"
      >
        No existen trabajadores con una compensación de
        destajo vigente. Registra primero el esquema de pago
        del trabajador.
      </aside>

      <form
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 p-4 bg-white border border-slate-200 rounded-xl shadow-xs"
        @submit.prevent="applyRateFilters"
      >
        <div class="sm:col-span-2 flex items-center gap-2 px-3 bg-white border border-slate-300 rounded-md focus-within:border-brand-green-700 focus-within:ring-3 focus-within:ring-brand-green-700/13">
          <Search :size="19" class="text-slate-400 shrink-0" aria-hidden="true" />

          <input
            v-model="rateFilters.search"
            type="search"
            maxlength="150"
            placeholder="Buscar trabajador, proceso..."
            class="w-full min-h-[3rem] text-slate-900 bg-transparent text-sm border-0 outline-hidden"
          />
        </div>

        <select
          v-model="rateFilters.employeeId"
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
          v-model="rateFilters.operationProcessId"
          aria-label="Filtrar por operación"
          class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
        >
          <option value="">
            Todas las operaciones
          </option>

          <option
            v-for="operation in operationOptions"
            :key="operation.id"
            :value="operation.id"
          >
            {{ operation.processName }}
            ·
            {{ operation.name }}
          </option>
        </select>

        <select
          v-model="rateFilters.status"
          aria-label="Filtrar por estado"
          class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
        >
          <option value="all">
            Todos los estados
          </option>

          <option value="active">
            Activas
          </option>

          <option value="inactive">
            Inactivas
          </option>
        </select>

        <input
          v-model="rateFilters.activeOn"
          type="date"
          aria-label="Vigente en fecha"
          class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
        />

        <div class="flex gap-2 col-span-full justify-end">
          <button
            type="submit"
            class="inline-flex min-h-[3rem] px-4 items-center justify-center gap-2 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-bold text-sm cursor-pointer transition-colors border-0 disabled:opacity-50"
            :disabled="ratesLoading"
          >
            <Search :size="18" aria-hidden="true" />
            Consultar
          </button>

          <button
            type="button"
            class="inline-flex min-h-[3rem] px-4 items-center justify-center gap-2 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-bold text-sm cursor-pointer transition-colors disabled:opacity-50"
            :disabled="ratesLoading"
            @click="clearRateFilters"
          >
            <RotateCcw
              :size="18"
              aria-hidden="true"
            />
            Limpiar
          </button>
        </div>
      </form>

      <div
        v-if="ratesLoading"
        class="grid place-content-center min-h-[18rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
      >
        <div class="w-10 h-10 border-4 border-brand-green-100 border-t-brand-green-700 rounded-full animate-spin mx-auto" />
        <p class="m-0 text-slate-600 text-sm">Cargando tarifas...</p>
      </div>

      <div
        v-else-if="rates.length === 0"
        class="grid place-content-center min-h-[18rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
      >
        <CircleDollarSign
          :size="44"
          class="text-slate-400 mx-auto"
          aria-hidden="true"
        />

        <h3 class="m-0 text-lg font-bold text-slate-900">No se encontraron tarifas</h3>

        <p class="m-0 text-slate-600 text-sm max-w-md">
          Registra el importe por pieza para cada trabajador
          y suboperación.
        </p>
      </div>

      <div
        v-else
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
      >
        <article
          v-for="rate in rates"
          :key="rate.id"
          class="grid gap-4 p-4 bg-white border border-slate-200 rounded-xl shadow-xs"
        >
          <header class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3">
            <div class="flex w-11 h-11 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-md shrink-0">
              <UserRound
                :size="21"
                aria-hidden="true"
              />
            </div>

            <span class="grid min-w-0">
              <strong class="text-slate-900 font-bold text-sm truncate">{{ rate.employee.name }}</strong>

              <small class="text-slate-500 text-xs truncate">
                {{
                  employeeArea(rate.employee.area)
                }}
              </small>
            </span>

            <em
              class="px-2.5 py-1 rounded-full text-xs font-bold not-italic"
              :class="
                compensationStatusClass(
                  rate.status,
                )
              "
            >
              {{ rate.status_label }}
            </em>
          </header>

          <div class="p-4 bg-brand-orange-50/70 border border-brand-orange-100 rounded-xl">
            <span class="grid">
              <small class="text-brand-orange-800 text-xs font-bold uppercase">Tarifa por pieza</small>

              <strong class="text-brand-orange-900 font-mono text-2xl font-extrabold mt-0.5">
                {{
                  formatMoney(
                    rate.amount_per_piece,
                    4,
                  )
                }}
              </strong>
            </span>
          </div>

          <dl class="grid gap-2 m-0 text-xs border-t border-slate-100 pt-3">
            <div class="flex justify-between items-center gap-3">
              <dt class="text-slate-500">Proceso</dt>

              <dd class="m-0 font-bold text-slate-800 truncate">
                {{
                  rate.operation_process.process
                    ?.name ?? 'No disponible'
                }}
              </dd>
            </div>

            <div class="flex justify-between items-center gap-3">
              <dt class="text-slate-500">Operación</dt>

              <dd class="m-0 font-bold text-slate-900 truncate">
                {{ rate.operation_process.name }}
              </dd>
            </div>

            <div class="flex justify-between items-center gap-3">
              <dt class="text-slate-500 flex items-center gap-1">
                <CalendarDays
                  :size="15"
                  aria-hidden="true"
                />
                Vigencia
              </dt>

              <dd class="m-0 font-medium text-slate-700">
                {{
                  formatDate(rate.effective_from)
                }}
                —
                {{
                  formatDate(rate.effective_to)
                }}
              </dd>
            </div>

            <div class="flex justify-between items-center gap-3">
              <dt class="text-slate-500">Vigente actualmente</dt>

              <dd class="m-0 font-bold text-slate-800">
                {{ rate.is_current ? 'Sí' : 'No' }}
              </dd>
            </div>
          </dl>

          <p v-if="rate.notes" class="m-0 p-3 text-slate-600 bg-slate-50 border border-slate-100 rounded-md text-xs leading-relaxed">
            {{ rate.notes }}
          </p>

          <footer
            v-if="
              authStore.can(
                PERMISSIONS.payroll.manage,
              )
            "
            class="pt-3 border-t border-slate-100"
          >
            <button
              type="button"
              class="w-full inline-flex min-h-[2.5rem] items-center justify-center gap-2 px-3 text-brand-green-800 bg-brand-green-100 hover:bg-brand-green-200/80 border border-brand-green-200 rounded-md font-bold text-xs cursor-pointer transition-colors"
              @click="openEditRate(rate)"
            >
              <Pencil
                :size="18"
                aria-hidden="true"
              />
              Editar vigencia
            </button>
          </footer>
        </article>
      </div>

      <footer
        v-if="ratePagination.total > 0"
        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 bg-white border border-slate-200 rounded-lg text-xs text-slate-600"
      >
        <p class="m-0 text-center sm:text-left">
          Mostrando
          <strong class="font-mono text-slate-900">
            {{ ratePagination.from ?? 0 }}
          </strong>
          a
          <strong class="font-mono text-slate-900">
            {{ ratePagination.to ?? 0 }}
          </strong>
          de
          <strong class="font-mono text-slate-900">
            {{ ratePagination.total }}
          </strong>
          tarifas
        </p>

        <div class="flex items-center justify-center gap-2">
          <button
            type="button"
            class="inline-flex min-h-[2.25rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 rounded-md font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
            :disabled="
              ratePagination.current_page <= 1 ||
              ratesLoading
            "
            @click="previousRatePage"
          >
            <ChevronLeft
              :size="18"
              aria-hidden="true"
            />
            Anterior
          </button>

          <span class="font-medium px-2">
            Página {{ ratePagination.current_page }}
            de {{ ratePagination.last_page }}
          </span>

          <button
            type="button"
            class="inline-flex min-h-[2.25rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 rounded-md font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
            :disabled="
              ratePagination.current_page >=
                ratePagination.last_page ||
              ratesLoading
            "
            @click="nextRatePage"
          >
            Siguiente
            <ChevronRight
              :size="18"
              aria-hidden="true"
            />
          </button>
        </div>
      </footer>
    </template>

    <EmbroideryPaymentSettingsPanel
      v-else-if="activeTab === 'embroidery-settings'"
      :processes="processes"
      :can-manage="canManageEmbroidery"
    />

    <template v-else-if="activeTab === 'payroll-periods'">
      <!-- Payroll Periods Filters -->
      <form
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 p-4 bg-white border border-slate-200 rounded-xl shadow-xs"
        @submit.prevent="loadPeriods(1)"
      >
        <select
          v-model="periodFilters.frequency"
          aria-label="Filtrar por frecuencia"
          class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
        >
          <option value="">
            Todas las frecuencias
          </option>
          <option value="weekly">Semanal</option>
          <option value="biweekly">Quincenal</option>
          <option value="monthly">Mensual</option>
        </select>

        <select
          v-model="periodFilters.status"
          aria-label="Filtrar por estado"
          class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
        >
          <option value="all">
            Todos los estados
          </option>
          <option value="draft">Borrador</option>
          <option value="generated">Generada</option>
          <option value="closed">Cerrada</option>
          <option value="cancelled">Cancelada</option>
        </select>

        <input
          v-model="periodFilters.search"
          type="text"
          placeholder="Buscar folio (NOM...)"
          maxlength="150"
          aria-label="Buscar por folio"
          class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
        />

        <div class="flex gap-2 justify-end">
          <button
            type="submit"
            class="inline-flex min-h-[3rem] px-4 items-center justify-center gap-2 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-bold text-sm cursor-pointer transition-colors border-0 disabled:opacity-50"
            :disabled="periodsLoading"
          >
            <Search :size="18" aria-hidden="true" />
            Consultar
          </button>

          <button
            type="button"
            class="inline-flex min-h-[3rem] px-4 items-center justify-center gap-2 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-bold text-sm cursor-pointer transition-colors disabled:opacity-50"
            :disabled="periodsLoading"
            @click="clearPeriodFilters"
          >
            <RotateCcw
              :size="18"
              aria-hidden="true"
            />
            Limpiar
          </button>
        </div>
      </form>

      <!-- Loading State -->
      <div
        v-if="periodsLoading"
        class="grid place-content-center min-h-[18rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
      >
        <div class="w-10 h-10 border-4 border-brand-green-100 border-t-brand-green-700 rounded-full animate-spin mx-auto" />
        <p class="m-0 text-slate-600 text-sm">Cargando periodos de nómina...</p>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="periods.length === 0"
        class="grid place-content-center min-h-[18rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
      >
        <CalendarDays
          :size="44"
          class="text-slate-400 mx-auto"
          aria-hidden="true"
        />
        <h3 class="m-0 text-lg font-bold text-slate-900">No se encontraron periodos</h3>
        <p class="m-0 text-slate-600 text-sm max-w-md">
          Crea un nuevo ciclo de nómina para comenzar a procesar pagos.
        </p>
      </div>

      <!-- Periods Grid -->
      <div
        v-else
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
      >
        <article
          v-for="item in periods"
          :key="item.id"
          class="grid gap-4 p-4 bg-white border border-slate-200 rounded-xl shadow-xs"
        >
          <header class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3">
            <div class="flex w-11 h-11 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-md shrink-0">
              <CalendarDays
                :size="21"
                aria-hidden="true"
              />
            </div>

            <span class="grid min-w-0">
              <strong class="text-slate-900 font-mono font-bold text-sm truncate">{{ item.code }}</strong>
              <small class="text-slate-500 text-xs truncate">Frecuencia: {{ item.frequency_label }}</small>
            </span>

            <em
              class="px-2.5 py-1 rounded-full text-xs font-bold not-italic"
              :class="periodStatusClass(item.status)"
            >
              {{ item.status_label }}
            </em>
          </header>

          <dl class="grid gap-2 m-0 text-xs border-t border-slate-100 pt-3">
            <div class="flex justify-between items-center gap-3">
              <dt class="text-slate-500">Fecha de inicio</dt>
              <dd class="m-0 font-medium text-slate-800">{{ formatDate(item.start_date) }}</dd>
            </div>
            <div class="flex justify-between items-center gap-3">
              <dt class="text-slate-500">Fecha de término</dt>
              <dd class="m-0 font-medium text-slate-800">{{ formatDate(item.end_date) }}</dd>
            </div>
            <div v-if="item.payment_date" class="flex justify-between items-center gap-3">
              <dt class="text-slate-500">Fecha de pago</dt>
              <dd class="m-0 font-medium text-slate-800">{{ formatDate(item.payment_date) }}</dd>
            </div>
            <div class="flex justify-between items-center gap-3">
              <dt class="text-slate-500">Empleados en nómina</dt>
              <dd class="m-0 font-mono font-bold text-slate-900">{{ item.employee_summaries_count }}</dd>
            </div>
          </dl>

          <p v-if="item.notes" class="m-0 p-3 text-slate-600 bg-slate-50 border border-slate-100 rounded-md text-xs leading-relaxed">
            {{ item.notes }}
          </p>

          <footer class="pt-3 border-t border-slate-100">
            <button
              type="button"
              class="w-full inline-flex min-h-[2.5rem] items-center justify-center gap-2 px-3 text-brand-green-800 bg-brand-green-100 hover:bg-brand-green-200/80 border border-brand-green-200 rounded-md font-bold text-xs cursor-pointer transition-colors"
              @click="openPeriodDetail(item.id)"
            >
              <Search
                :size="18"
                aria-hidden="true"
              />
              Ver sábana de pagos
            </button>
          </footer>
        </article>
      </div>

      <!-- Pagination -->
      <footer
        v-if="periodPagination.total > 0"
        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 bg-white border border-slate-200 rounded-lg text-xs text-slate-600"
      >
        <p class="m-0 text-center sm:text-left">
          Mostrando
          <strong class="font-mono text-slate-900">{{ periodPagination.from ?? 0 }}</strong>
          a
          <strong class="font-mono text-slate-900">{{ periodPagination.to ?? 0 }}</strong>
          de
          <strong class="font-mono text-slate-900">{{ periodPagination.total }}</strong>
          periodos
        </p>

        <div class="flex items-center justify-center gap-2">
          <button
            type="button"
            class="inline-flex min-h-[2.25rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 rounded-md font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
            :disabled="periodPagination.current_page <= 1 || periodsLoading"
            @click="previousPeriodPage"
          >
            <ChevronLeft
              :size="18"
              aria-hidden="true"
            />
            Anterior
          </button>

          <span class="font-medium px-2">
            Página {{ periodPagination.current_page }} de
            {{ periodPagination.last_page }}
          </span>

          <button
            type="button"
            class="inline-flex min-h-[2.25rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 rounded-md font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
            :disabled="
              periodPagination.current_page >= periodPagination.last_page ||
              periodsLoading
            "
            @click="nextPeriodPage"
          >
            Siguiente
            <ChevronRight
              :size="18"
              aria-hidden="true"
            />
          </button>
        </div>
      </footer>
    </template>

    <EmployeeCompensationFormDialog
      :open="compensationFormOpen"
      :compensation="selectedCompensation"
      :employees="employees"
      @close="closeCompensationForm"
      @saved="handleCompensationSaved"
    />

    <PieceworkRateFormDialog
      :open="rateFormOpen"
      :rate="selectedRate"
      :employees="pieceworkEmployees"
      :processes="processes"
      @close="closeRateForm"
      @saved="handleRateSaved"
    />

    <CreatePayrollPeriodDialog
      :open="createPeriodOpen"
      @close="closeCreatePeriod"
      @saved="handlePeriodSaved"
    />

    <PayrollPeriodDetailDialog
      :open="detailPeriodOpen"
      :period-id="selectedPeriodId"
      @close="closePeriodDetail"
      @updated="handlePeriodUpdated"
    />
  </section>
</template>