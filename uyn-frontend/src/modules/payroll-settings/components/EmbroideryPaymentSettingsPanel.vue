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
  Pencil,
  Percent,
  Plus,
  RotateCcw,
  Search,
  Sparkles,
} from 'lucide-vue-next'

import Swal from 'sweetalert2'

import EmbroideryPaymentSettingFormDialog from '@/modules/payroll-settings/components/EmbroideryPaymentSettingFormDialog.vue'

import { embroideryPaymentSettingsService } from '@/modules/payroll-settings/services/embroidery-payment-settings.service'
import { getApiErrorMessage } from '@/utils/api-error'

import type { ProductionProcess } from '@/modules/processes/types/process.types'

import type {
  EmbroideryPaymentSetting,
} from '@/modules/payroll-settings/types/embroidery-payment-setting.types'

import type {
  PayrollRuleStatus,
} from '@/modules/payroll-settings/types/employee-compensation.types'

import type { PaginationMeta } from '@/types/api'

interface OperationOption {
  id: number
  name: string
  processName: string
}

interface Filters {
  operationProcessId: number | ''
  status: PayrollRuleStatus | 'all'
  activeOn: string
  perPage: number
}

const props = defineProps<{
  processes: ProductionProcess[]
  canManage: boolean
}>()

const settings =
  ref<EmbroideryPaymentSetting[]>([])

const loading = ref(false)
const formOpen = ref(false)

const selectedSetting =
  ref<EmbroideryPaymentSetting | null>(null)

const filters = reactive<Filters>({
  operationProcessId: '',
  status: 'all',
  activeOn: '',
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

function normalizeText(value?: string): string {
  return (
    value
      ?.trim()
      .toLocaleLowerCase('es')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '') ?? ''
  )
}

function isEmbroideryOperation(
  calculationType: string | undefined,
  processName: string,
  operationName: string,
): boolean {
  const normalizedType =
    normalizeText(calculationType)

  if (
    [
      'embroidery_formula',
      'stitches',
    ].includes(normalizedType)
  ) {
    return true
  }

  if (normalizedType) {
    return false
  }

  return (
    normalizeText(processName).includes(
      'bordado',
    ) ||
    normalizeText(operationName).includes(
      'bordado',
    )
  )
}

const operationOptions =
  computed<OperationOption[]>(() => {
    const operationMap =
      new Map<number, OperationOption>()

    for (const process of props.processes) {
      const operations = Array.isArray(
        process.operations,
      )
        ? process.operations
        : []

      for (const operation of operations) {
        if (
          !isEmbroideryOperation(
            operation.payroll_calculation_type,
            process.name,
            operation.name,
          )
        ) {
          continue
        }

        operationMap.set(operation.id, {
          id: operation.id,
          name: operation.name,
          processName: process.name,
        })
      }
    }

    /*
     * Conserva operaciones que ya existen en registros,
     * aunque el catálogo actual no las devuelva.
     */
    for (const setting of settings.value) {
      const operation = setting.operation_process

      operationMap.set(operation.id, {
        id: operation.id,
        name: operation.name,
        processName:
          operation.process?.name ?? 'Bordado',
      })
    }

    return Array.from(operationMap.values()).sort(
      (first, second) => {
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
      },
    )
  })

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

function formatMoney(
  value: string,
  digits = 4,
): string {
  const amount = Number(value)

  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(
    Number.isFinite(amount) ? amount : 0,
  )
}

function formatPercentage(value: string): string {
  const percentage = Number(value) * 100

  return new Intl.NumberFormat('es-MX', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 4,
  }).format(
    Number.isFinite(percentage)
      ? percentage
      : 0,
  )
}

function statusClass(
  status: PayrollRuleStatus,
): string {
  return status === 'active'
    ? 'text-emerald-800 bg-emerald-50 border border-emerald-200/80'
    : 'text-slate-600 bg-slate-100'
}

async function loadSettings(
  page = 1,
): Promise<void> {
  loading.value = true

  try {
    const response =
      await embroideryPaymentSettingsService.list({
        operation_process_id:
          filters.operationProcessId,

        status: filters.status,
        active_on: filters.activeOn,
        per_page: filters.perPage,
        page,
      })

    settings.value = response.data
    pagination.value = response.meta
  } catch (error) {
    await Swal.fire({
      title:
        'No fue posible cargar la configuración de Bordado',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    loading.value = false
  }
}

function applyFilters(): void {
  void loadSettings(1)
}

function clearFilters(): void {
  filters.operationProcessId = ''
  filters.status = 'all'
  filters.activeOn = ''
  filters.perPage = 15

  void loadSettings(1)
}

async function openCreateForm(): Promise<void> {
  if (operationOptions.value.length === 0) {
    await Swal.fire({
      title:
        'No hay operaciones de Bordado disponibles',
      text:
        'Verifica que el catálogo de procesos contenga una suboperación con tipo de cálculo embroidery_formula o stitches.',
      icon: 'warning',
      confirmButtonText: 'Aceptar',
    })

    return
  }

  selectedSetting.value = null
  formOpen.value = true
}

function openEditForm(
  setting: EmbroideryPaymentSetting,
): void {
  selectedSetting.value = setting
  formOpen.value = true
}

function closeForm(): void {
  formOpen.value = false
  selectedSetting.value = null
}

async function handleSaved(
  _setting: EmbroideryPaymentSetting,
  message: string,
): Promise<void> {
  closeForm()

  await Swal.fire({
    title: message,
    icon: 'success',
    timer: 1700,
    showConfirmButton: false,
  })

  await loadSettings(
    pagination.value.current_page,
  )
}

function previousPage(): void {
  if (pagination.value.current_page > 1) {
    void loadSettings(
      pagination.value.current_page - 1,
    )
  }
}

function nextPage(): void {
  if (
    pagination.value.current_page <
    pagination.value.last_page
  ) {
    void loadSettings(
      pagination.value.current_page + 1,
    )
  }
}

onMounted(() => {
  void loadSettings()
})
</script>

<template>
  <section class="grid gap-5">
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Pago especializado</p>

        <h3 class="m-0 text-xl font-bold text-slate-900">Configuración de Bordado</h3>

        <span class="block max-w-xl mt-2 text-slate-600 text-sm">
          Administra la fórmula aplicada a las operaciones
          calculadas por puntadas y aplicaciones.
        </span>
      </div>

      <button
        v-if="canManage"
        type="button"
        class="inline-flex w-full sm:w-auto min-h-[3rem] px-5 items-center justify-center gap-2 text-white bg-brand-orange-800 hover:bg-brand-orange-900 border-0 rounded-md font-[750] text-sm cursor-pointer transition-colors disabled:opacity-50"
        :disabled="loading"
        @click="openCreateForm"
      >
        <Plus :size="20" aria-hidden="true" />
        Registrar configuración
      </button>
    </header>

    <form
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 p-4 bg-white border border-slate-200 rounded-xl shadow-xs"
      @submit.prevent="applyFilters"
    >
      <select
        v-model="filters.operationProcessId"
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
        v-model="filters.status"
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
        v-model="filters.activeOn"
        type="date"
        aria-label="Vigente en fecha"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      />

      <div class="flex gap-2 justify-end">
        <button
          type="submit"
          class="inline-flex min-h-[3rem] px-4 items-center justify-center gap-2 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-bold text-sm cursor-pointer transition-colors border-0 disabled:opacity-50"
          :disabled="loading"
        >
          <Search :size="18" aria-hidden="true" />
          Consultar
        </button>

        <button
          type="button"
          class="inline-flex min-h-[3rem] px-4 items-center justify-center gap-2 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-bold text-sm cursor-pointer transition-colors disabled:opacity-50"
          :disabled="loading"
          @click="clearFilters"
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
      v-if="loading"
      class="grid place-content-center min-h-[18rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <div class="w-10 h-10 border-4 border-brand-green-100 border-t-brand-green-700 rounded-full animate-spin mx-auto" />
      <p class="m-0 text-slate-600 text-sm">Cargando configuraciones...</p>
    </div>

    <div
      v-else-if="settings.length === 0"
      class="grid place-content-center min-h-[18rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <Sparkles
        :size="44"
        class="text-slate-400 mx-auto"
        aria-hidden="true"
      />

      <h3 class="m-0 text-lg font-bold text-slate-900">No se encontraron configuraciones</h3>

      <p class="m-0 text-slate-600 text-sm max-w-md">
        Registra los parámetros de pago para las
        suboperaciones de Bordado.
      </p>
    </div>

    <div
      v-else
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
    >
      <article
        v-for="setting in settings"
        :key="setting.id"
        class="grid gap-4 p-4 bg-white border border-slate-200 rounded-xl shadow-xs"
      >
        <header class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3">
          <div class="flex w-11 h-11 items-center justify-center text-brand-orange-900 bg-brand-orange-100 rounded-md shrink-0">
            <Sparkles
              :size="22"
              aria-hidden="true"
            />
          </div>

          <span class="grid min-w-0">
            <strong class="text-slate-900 font-bold text-sm truncate">
              {{ setting.operation_process.name }}
            </strong>

            <small class="text-slate-500 text-xs truncate">
              {{
                setting.operation_process.process
                  ?.name ??
                'Bordado'
              }}
            </small>
          </span>

          <em
            class="px-2.5 py-1 rounded-full text-xs font-bold not-italic"
            :class="statusClass(setting.status)"
          >
            {{ setting.status_label }}
          </em>
        </header>

        <section class="flex items-center gap-3 p-4 text-brand-orange-900 bg-brand-orange-50/60 border border-brand-orange-100 rounded-xl">
          <Percent
            :size="24"
            class="shrink-0 text-brand-orange-800"
            aria-hidden="true"
          />

          <span class="grid">
            <small class="text-slate-500 text-xs font-medium">
              Porcentaje para el operador
            </small>

            <strong class="text-brand-orange-900 font-mono text-xl font-bold mt-0.5">
              {{
                formatPercentage(
                  setting.payment_percentage,
                )
              }}%
            </strong>
          </span>
        </section>

        <dl class="grid gap-2 m-0 text-xs border-t border-slate-100 pt-3">
          <div class="flex justify-between items-center gap-3">
            <dt class="text-slate-500">Precio por puntada</dt>

            <dd class="m-0 font-mono font-bold text-slate-900">
              {{
                formatMoney(
                  setting.stitch_price,
                  8,
                )
              }}
            </dd>
          </div>

          <div class="flex justify-between items-center gap-3">
            <dt class="text-slate-500">Precio por aplicación</dt>

            <dd class="m-0 font-mono font-bold text-slate-900">
              {{
                formatMoney(
                  setting.application_price,
                  4,
                )
              }}
            </dd>
          </div>

          <div class="flex justify-between items-center gap-3">
            <dt class="text-slate-500">Pago mínimo por pieza</dt>

            <dd class="m-0 font-mono font-bold text-slate-900">
              {{
                formatMoney(
                  setting.minimum_payment_per_piece,
                  4,
                )
              }}
            </dd>
          </div>

          <div class="flex justify-between items-center gap-3">
            <dt class="text-slate-500">Pago predeterminado</dt>

            <dd class="m-0 font-mono font-bold text-slate-900">
              {{
                formatMoney(
                  setting.default_payment_per_piece,
                  4,
                )
              }}
            </dd>
          </div>
        </dl>

        <section class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-100 rounded-md text-xs">
          <CalendarDays
            :size="19"
            class="text-slate-400 shrink-0"
            aria-hidden="true"
          />

          <span class="grid flex-1 min-w-0">
            <small class="text-slate-500">Vigencia</small>

            <strong class="text-slate-800 font-medium truncate">
              {{
                formatDate(
                  setting.effective_from,
                )
              }}
              —
              {{
                formatDate(
                  setting.effective_to,
                )
              }}
            </strong>
          </span>

          <em class="font-bold not-italic" :class="setting.is_current ? 'text-emerald-700' : 'text-slate-400'">
            {{
              setting.is_current
                ? 'Vigente'
                : 'Fuera de vigencia'
            }}
          </em>
        </section>

        <p v-if="setting.notes" class="m-0 p-3 text-slate-600 bg-slate-50 border border-slate-100 rounded-md text-xs leading-relaxed">
          {{ setting.notes }}
        </p>

        <footer v-if="canManage" class="pt-3 border-t border-slate-100">
          <button
            type="button"
            class="w-full inline-flex min-h-[2.5rem] items-center justify-center gap-2 px-3 text-brand-green-800 bg-brand-green-100 hover:bg-brand-green-200/80 border border-brand-green-200 rounded-md font-bold text-xs cursor-pointer transition-colors"
            @click="openEditForm(setting)"
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
      v-if="pagination.total > 0"
      class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 bg-white border border-slate-200 rounded-lg text-xs text-slate-600"
    >
      <p class="m-0 text-center sm:text-left">
        Mostrando
        <strong class="font-mono text-slate-900">{{ pagination.from ?? 0 }}</strong>
        a
        <strong class="font-mono text-slate-900">{{ pagination.to ?? 0 }}</strong>
        de
        <strong class="font-mono text-slate-900">{{ pagination.total }}</strong>
        configuraciones
      </p>

      <div class="flex items-center justify-center gap-2">
        <button
          type="button"
          class="inline-flex min-h-[2.25rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 rounded-md font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
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

        <span class="font-medium px-2">
          Página {{ pagination.current_page }}
          de {{ pagination.last_page }}
        </span>

        <button
          type="button"
          class="inline-flex min-h-[2.25rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 rounded-md font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
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

    <EmbroideryPaymentSettingFormDialog
      :open="formOpen"
      :setting="selectedSetting"
      :processes="processes"
      @close="closeForm"
      @saved="handleSaved"
    />
  </section>
</template>