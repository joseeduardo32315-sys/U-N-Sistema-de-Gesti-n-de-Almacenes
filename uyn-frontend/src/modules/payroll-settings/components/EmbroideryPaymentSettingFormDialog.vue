<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  reactive,
  ref,
  watch,
} from 'vue'

import {
  Calculator,
  LoaderCircle,
  Percent,
  Sparkles,
  X,
} from 'lucide-vue-next'

import { embroideryPaymentSettingsService } from '@/modules/payroll-settings/services/embroidery-payment-settings.service'

import {
  getApiErrorMessage,
  getValidationErrors,
} from '@/utils/api-error'

import type { ProductionProcess } from '@/modules/processes/types/process.types'

import type {
  CreateEmbroideryPaymentSettingPayload,
  EmbroideryPaymentSetting,
  UpdateEmbroideryPaymentSettingPayload,
} from '@/modules/payroll-settings/types/embroidery-payment-setting.types'

import type { PayrollRuleStatus } from '@/modules/payroll-settings/types/employee-compensation.types'

interface EmbroideryOperationOption {
  id: number
  name: string
  processName: string
  calculationType?: string
}

interface EmbroiderySettingForm {
  operationProcessId: number | ''

  stitchPrice: string
  applicationPrice: string
  paymentPercentage: string

  minimumPaymentPerPiece: string
  defaultPaymentPerPiece: string

  effectiveFrom: string
  effectiveTo: string

  status: PayrollRuleStatus
  notes: string
}

const props = defineProps<{
  open: boolean
  setting: EmbroideryPaymentSetting | null
  processes: ProductionProcess[]
}>()

const emit = defineEmits<{
  close: []
  saved: [
    setting: EmbroideryPaymentSetting,
    message: string,
  ]
}>()

const form = reactive<EmbroiderySettingForm>({
  operationProcessId: '',

  stitchPrice: '0.00010000',
  applicationPrice: '1.0000',
  paymentPercentage: '30',

  minimumPaymentPerPiece: '0.7500',
  defaultPaymentPerPiece: '0.7500',

  effectiveFrom: '',
  effectiveTo: '',

  status: 'active',
  notes: '',
})

const previewStitches = ref('8000')
const previewApplications = ref('1')
const previewQuantity = ref('100')

const submitting = ref(false)
const formError = ref('')
const fieldErrors =
  ref<Record<string, string[]>>({})

const isEditing = computed<boolean>(() => {
  return props.setting !== null
})

const title = computed<string>(() => {
  return isEditing.value
    ? 'Editar configuración de Bordado'
    : 'Registrar configuración de Bordado'
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
  computed<EmbroideryOperationOption[]>(() => {
    return props.processes
      .flatMap((process) => {
        const operations = Array.isArray(
          process.operations,
        )
          ? process.operations
          : []

        return operations
          .filter((operation) =>
            isEmbroideryOperation(
              operation.payroll_calculation_type,
              process.name,
              operation.name,
            ),
          )
          .map((operation) => ({
            id: operation.id,
            name: operation.name,
            processName: process.name,
            calculationType:
              operation.payroll_calculation_type,
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

const selectedOperation =
  computed<
    EmbroideryOperationOption | undefined
  >(() => {
    return operationOptions.value.find(
      (operation) =>
        operation.id ===
        form.operationProcessId,
    )
  })

function parseDecimalInput(value: string): number {
  return Number(
    value
      .trim()
      .replace(',', '.'),
  )
}

const formulaPreview = computed(() => {
  const stitches = Math.max(
    parseDecimalInput(previewStitches.value) || 0,
    0,
  )

  const applications = Math.max(
    parseDecimalInput(
      previewApplications.value,
    ) || 0,
    0,
  )

  const quantity = Math.max(
    parseDecimalInput(previewQuantity.value) || 0,
    0,
  )

  const stitchPrice = Math.max(
    parseDecimalInput(form.stitchPrice) || 0,
    0,
  )

  const applicationPrice = Math.max(
    parseDecimalInput(
      form.applicationPrice,
    ) || 0,
    0,
  )

  const percentage = Math.max(
    parseDecimalInput(
      form.paymentPercentage,
    ) || 0,
    0,
  ) / 100

  const minimum = Math.max(
    parseDecimalInput(
      form.minimumPaymentPerPiece,
    ) || 0,
    0,
  )

  const defaultPayment = Math.max(
    parseDecimalInput(
      form.defaultPaymentPerPiece,
    ) || 0,
    0,
  )

  const basePerPiece =
    stitches * stitchPrice +
    applications * applicationPrice

  const formulaPerPiece =
    basePerPiece * percentage

  const finalPerPiece =
    formulaPerPiece < minimum
      ? defaultPayment
      : formulaPerPiece

  const operationTotal =
    finalPerPiece * quantity

  return {
    basePerPiece,
    formulaPerPiece,
    finalPerPiece,
    operationTotal,
    usesDefault:
      formulaPerPiece < minimum,
  }
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

function formatMoney(
  value: number,
  digits = 4,
): string {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(
    Number.isFinite(value) ? value : 0,
  )
}

function firstFieldError(field: string): string {
  return fieldErrors.value[field]?.at(0) ?? ''
}

function setLocalError(
  field: string,
  message: string,
): void {
  fieldErrors.value[field] = [message]
}

function resetForm(): void {
  const setting = props.setting

  form.operationProcessId =
    setting?.operation_process.id ?? ''

  form.stitchPrice =
    setting?.stitch_price ??
    '0.00010000'

  form.applicationPrice =
    setting?.application_price ??
    '1.0000'

  form.paymentPercentage = setting
    ? String(
        Number(
          setting.payment_percentage,
        ) * 100,
      )
    : '30'

  form.minimumPaymentPerPiece =
    setting?.minimum_payment_per_piece ??
    '0.7500'

  form.defaultPaymentPerPiece =
    setting?.default_payment_per_piece ??
    '0.7500'

  form.effectiveFrom =
    setting?.effective_from ??
    localDate()

  form.effectiveTo =
    setting?.effective_to ?? ''

  form.status =
    setting?.status ?? 'active'

  form.notes =
    setting?.notes ?? ''

  previewStitches.value = '8000'
  previewApplications.value = '1'
  previewQuantity.value = '100'

  formError.value = ''
  fieldErrors.value = {}

  if (
    !setting &&
    operationOptions.value.length === 1
  ) {
    form.operationProcessId =
      operationOptions.value[0]?.id ?? ''
  }
}

function validateCreateFields(): void {
  if (!form.operationProcessId) {
    setLocalError(
      'operation_process_id',
      'Selecciona una suboperación de Bordado.',
    )
  }

  const stitchPrice = parseDecimalInput(
    form.stitchPrice,
  )

  if (
    !Number.isFinite(stitchPrice) ||
    stitchPrice < 0.00000001
  ) {
    setLocalError(
      'stitch_price',
      'El precio por puntada debe ser mayor o igual a 0.00000001.',
    )
  }

  const applicationPrice =
    parseDecimalInput(
      form.applicationPrice,
    )

  if (
    !Number.isFinite(applicationPrice) ||
    applicationPrice < 0
  ) {
    setLocalError(
      'application_price',
      'El precio por aplicación debe ser igual o mayor a cero.',
    )
  }

  const percentage = parseDecimalInput(
    form.paymentPercentage,
  )

  if (
    !Number.isFinite(percentage) ||
    percentage <= 0 ||
    percentage > 100
  ) {
    setLocalError(
      'payment_percentage',
      'Ingresa un porcentaje mayor a 0 y menor o igual a 100.',
    )
  }

  const minimum = parseDecimalInput(
    form.minimumPaymentPerPiece,
  )

  if (
    !Number.isFinite(minimum) ||
    minimum < 0.0001
  ) {
    setLocalError(
      'minimum_payment_per_piece',
      'El pago mínimo debe ser mayor o igual a 0.0001.',
    )
  }

  const defaultPayment =
    parseDecimalInput(
      form.defaultPaymentPerPiece,
    )

  if (
    !Number.isFinite(defaultPayment) ||
    defaultPayment < 0.0001
  ) {
    setLocalError(
      'default_payment_per_piece',
      'El pago predeterminado debe ser mayor o igual a 0.0001.',
    )
  } else if (
    Number.isFinite(minimum) &&
    defaultPayment < minimum
  ) {
    setLocalError(
      'default_payment_per_piece',
      'El pago predeterminado debe ser igual o mayor al pago mínimo.',
    )
  }
}

function validateForm(): boolean {
  fieldErrors.value = {}
  formError.value = ''

  if (!isEditing.value) {
    validateCreateFields()
  }

  if (!form.effectiveFrom) {
    setLocalError(
      'effective_from',
      'Selecciona la fecha inicial.',
    )
  }

  if (
    form.effectiveFrom &&
    form.effectiveTo &&
    form.effectiveTo < form.effectiveFrom
  ) {
    setLocalError(
      'effective_to',
      'La fecha final debe ser igual o posterior a la fecha inicial.',
    )
  }

  if (form.notes.length > 3000) {
    setLocalError(
      'notes',
      'Las notas no pueden superar 3000 caracteres.',
    )
  }

  return (
    Object.keys(fieldErrors.value).length === 0
  )
}

function buildCreatePayload():
  CreateEmbroideryPaymentSettingPayload | null {
  if (!form.operationProcessId) {
    return null
  }

  return {
    operation_process_id:
      form.operationProcessId,

    stitch_price:
      parseDecimalInput(
        form.stitchPrice,
      ).toFixed(8),

    application_price:
      parseDecimalInput(
        form.applicationPrice,
      ).toFixed(4),

    payment_percentage: (
      parseDecimalInput(
        form.paymentPercentage,
      ) / 100
    ).toFixed(6),

    minimum_payment_per_piece:
      parseDecimalInput(
        form.minimumPaymentPerPiece,
      ).toFixed(4),

    default_payment_per_piece:
      parseDecimalInput(
        form.defaultPaymentPerPiece,
      ).toFixed(4),

    effective_from: form.effectiveFrom,
    effective_to:
      form.effectiveTo || null,

    notes:
      form.notes.trim() || null,
  }
}

async function handleSubmit(): Promise<void> {
  if (!validateForm()) {
    return
  }

  submitting.value = true
  formError.value = ''

  try {
    if (props.setting) {
      const payload:
        UpdateEmbroideryPaymentSettingPayload = {
          effective_to:
            form.effectiveTo || null,

          status: form.status,

          notes:
            form.notes.trim() || null,
        }

      const response =
        await embroideryPaymentSettingsService.update(
          props.setting.id,
          payload,
        )

      emit(
        'saved',
        response.data,
        response.message,
      )

      return
    }

    const payload = buildCreatePayload()

    if (!payload) {
      return
    }

    const response =
      await embroideryPaymentSettingsService.create(
        payload,
      )

    emit(
      'saved',
      response.data,
      response.message,
    )
  } catch (error) {
    fieldErrors.value =
      getValidationErrors(error)

    formError.value = getApiErrorMessage(
      error,
      'No fue posible guardar la configuración de Bordado.',
    )
  } finally {
    submitting.value = false
  }
}

function requestClose(): void {
  if (!submitting.value) {
    emit('close')
  }
}

watch(
  () => props.open,
  (open) => {
    document.body.style.overflow =
      open ? 'hidden' : ''

    if (open) {
      resetForm()
    }
  },
)

watch(
  () => props.setting?.id,
  () => {
    if (props.open) {
      resetForm()
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
      v-if="open"
      class="fixed inset-0 z-[125] flex items-stretch sm:items-center justify-center p-0 sm:p-6 bg-slate-950/65 backdrop-blur-xs"
      @click.self="requestClose"
    >
      <section
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:max-w-4xl bg-white rounded-none sm:rounded-xl shadow-xl overflow-hidden"
        role="dialog"
        aria-modal="true"
        aria-labelledby="embroidery-setting-title"
      >
        <header class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 p-4 sm:px-6 border-b border-slate-200">
          <div class="flex w-11 h-11 items-center justify-center text-brand-orange-900 bg-brand-orange-100 rounded-md shrink-0">
            <Sparkles
              :size="23"
              aria-hidden="true"
            />
          </div>

          <span class="grid min-w-0">
            <small class="text-brand-orange-800 text-xs font-extrabold tracking-wider uppercase">Fórmula de Bordado</small>

            <h2 id="embroidery-setting-title" class="m-0 text-base font-bold text-slate-900 truncate">
              {{ title }}
            </h2>
          </span>

          <button
            type="button"
            class="inline-flex w-10 h-10 items-center justify-center p-0 text-slate-500 hover:text-slate-700 bg-slate-100 border-0 rounded-md cursor-pointer transition-colors"
            aria-label="Cerrar formulario"
            :disabled="submitting"
            @click="requestClose"
          >
            <X :size="22" aria-hidden="true" />
          </button>
        </header>

        <form
          class="grid gap-6 overflow-y-auto p-5 sm:p-6"
          novalidate
          @submit.prevent="handleSubmit"
        >
          <div
            v-if="formError"
            class="p-3.5 bg-rose-50 text-rose-700 rounded-md text-sm font-bold border border-rose-200"
            role="alert"
          >
            {{ formError }}
          </div>

          <section
            v-if="isEditing && setting"
            class="grid gap-1 p-4 bg-brand-orange-50/60 border border-brand-orange-100 rounded-xl"
          >
            <strong class="text-slate-900 font-bold text-sm">
              {{
                setting.operation_process.process
                  ?.name ??
                'Bordado'
              }}
              ·
              {{ setting.operation_process.name }}
            </strong>

            <span class="text-slate-600 text-xs">
              Configuración vigente desde
              {{ setting.effective_from }}
            </span>

            <small class="text-slate-500 text-xs mt-2 leading-relaxed">
              Los parámetros de cálculo no pueden
              modificarse. Para usar nuevos importes,
              registra otra configuración con una nueva
              vigencia.
            </small>
          </section>

          <section class="grid gap-4">
            <header>
              <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Suboperación</p>
              <h3 class="m-0 text-base font-bold text-slate-900">Proceso al que aplica la fórmula</h3>
            </header>

            <div class="grid gap-2">
              <label for="embroidery-operation" class="text-slate-900 text-sm font-bold">
                Suboperación de Bordado
              </label>

              <select
                id="embroidery-operation"
                v-model="form.operationProcessId"
                :disabled="isEditing"
                class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-400"
                :class="{
                  'border-rose-700!':
                    firstFieldError(
                      'operation_process_id',
                    ),
                }"
              >
                <option value="">
                  {{
                    operationOptions.length > 0
                      ? 'Selecciona una operación'
                      : 'No hay operaciones disponibles'
                  }}
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

              <small
                v-if="
                  firstFieldError(
                    'operation_process_id',
                  )
                "
                class="text-rose-600 text-xs font-bold"
              >
                {{
                  firstFieldError(
                    'operation_process_id',
                  )
                }}
              </small>

              <small
                v-else-if="selectedOperation"
                class="text-slate-500 text-xs"
              >
                Tipo de cálculo:
                {{
                  selectedOperation.calculationType ??
                  'Bordado'
                }}
              </small>

              <small
                v-else-if="
                  operationOptions.length === 0
                "
                class="text-slate-500 text-xs"
              >
                Verifica que el catálogo de procesos
                contenga una operación de Bordado con tipo
                `embroidery_formula` o `stitches`.
              </small>
            </div>
          </section>

          <section class="grid gap-4 pt-5 border-t border-slate-200">
            <header>
              <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Importes base</p>
              <h3 class="m-0 text-base font-bold text-slate-900">Variables de la fórmula</h3>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="grid gap-2">
                <label for="embroidery-stitch-price" class="text-slate-900 text-sm font-bold">
                  Precio por puntada
                </label>

                <input
                  id="embroidery-stitch-price"
                  v-model="form.stitchPrice"
                  type="text"
                  inputmode="decimal"
                  placeholder="0.00010000"
                  :disabled="isEditing"
                  class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-400"
                  :class="{
                    'border-rose-700!':
                      firstFieldError(
                        'stitch_price',
                      ),
                  }"
                />

                <small
                  v-if="
                    firstFieldError(
                      'stitch_price',
                    )
                  "
                  class="text-rose-600 text-xs font-bold"
                >
                  {{
                    firstFieldError(
                      'stitch_price',
                    )
                  }}
                </small>
              </div>

              <div class="grid gap-2">
                <label for="embroidery-application-price" class="text-slate-900 text-sm font-bold">
                  Precio por aplicación
                </label>

                <input
                  id="embroidery-application-price"
                  v-model="form.applicationPrice"
                  type="text"
                  inputmode="decimal"
                  placeholder="1.0000"
                  :disabled="isEditing"
                  class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-400"
                  :class="{
                    'border-rose-700!':
                      firstFieldError(
                        'application_price',
                      ),
                  }"
                />

                <small
                  v-if="
                    firstFieldError(
                      'application_price',
                    )
                  "
                  class="text-rose-600 text-xs font-bold"
                >
                  {{
                    firstFieldError(
                      'application_price',
                    )
                  }}
                </small>
              </div>

              <div class="grid gap-2">
                <label for="embroidery-percentage" class="text-slate-900 text-sm font-bold">
                  Porcentaje para el operador
                </label>

                <div class="relative">
                  <input
                    id="embroidery-percentage"
                    v-model="form.paymentPercentage"
                    type="text"
                    inputmode="decimal"
                    placeholder="30"
                    :disabled="isEditing"
                    class="w-full min-h-[3rem] p-3 pr-12 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-400"
                    :class="{
                      'border-rose-700!':
                        firstFieldError(
                          'payment_percentage',
                        ),
                    }"
                  />

                  <Percent
                    :size="19"
                    class="absolute top-1/2 right-3 -translate-y-1/2 text-slate-400 pointer-events-none"
                    aria-hidden="true"
                  />
                </div>

                <small
                  v-if="
                    firstFieldError(
                      'payment_percentage',
                    )
                  "
                  class="text-rose-600 text-xs font-bold"
                >
                  {{
                    firstFieldError(
                      'payment_percentage',
                    )
                  }}
                </small>

                <small
                  v-else
                  class="text-slate-500 text-xs"
                >
                  Ejemplo: escribe 30 para representar
                  30%.
                </small>
              </div>

              <div class="grid gap-2">
                <label for="embroidery-minimum" class="text-slate-900 text-sm font-bold">
                  Pago mínimo por pieza
                </label>

                <input
                  id="embroidery-minimum"
                  v-model="
                    form.minimumPaymentPerPiece
                  "
                  type="text"
                  inputmode="decimal"
                  placeholder="0.7500"
                  :disabled="isEditing"
                  class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-400"
                  :class="{
                    'border-rose-700!':
                      firstFieldError(
                        'minimum_payment_per_piece',
                      ),
                  }"
                />

                <small
                  v-if="
                    firstFieldError(
                      'minimum_payment_per_piece',
                    )
                  "
                  class="text-rose-600 text-xs font-bold"
                >
                  {{
                    firstFieldError(
                      'minimum_payment_per_piece',
                    )
                  }}
                </small>
              </div>

              <div class="grid gap-2">
                <label for="embroidery-default" class="text-slate-900 text-sm font-bold">
                  Pago predeterminado por pieza
                </label>

                <input
                  id="embroidery-default"
                  v-model="
                    form.defaultPaymentPerPiece
                  "
                  type="text"
                  inputmode="decimal"
                  placeholder="0.7500"
                  :disabled="isEditing"
                  class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-400"
                  :class="{
                    'border-rose-700!':
                      firstFieldError(
                        'default_payment_per_piece',
                      ),
                  }"
                />

                <small
                  v-if="
                    firstFieldError(
                      'default_payment_per_piece',
                    )
                  "
                  class="text-rose-600 text-xs font-bold"
                >
                  {{
                    firstFieldError(
                      'default_payment_per_piece',
                    )
                  }}
                </small>

                <small
                  v-else
                  class="text-slate-500 text-xs"
                >
                  Debe ser igual o mayor al pago mínimo.
                </small>
              </div>
            </div>
          </section>

          <section
            v-if="!isEditing"
            class="grid gap-4 p-4 bg-gradient-to-br from-brand-orange-50/60 to-brand-green-50/60 border border-brand-orange-100 rounded-xl"
          >
            <header>
              <div class="flex items-center gap-3">
                <Calculator
                  :size="22"
                  class="text-brand-orange-900"
                  aria-hidden="true"
                />

                <span>
                  <p class="m-0 mb-0.5 text-brand-orange-800 text-xs font-extrabold uppercase">Simulador</p>
                  <h3 class="m-0 text-base font-bold text-slate-900">Vista previa del cálculo</h3>
                </span>
              </div>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div class="grid gap-1.5">
                <label for="preview-stitches" class="text-slate-900 text-xs font-bold">
                  Puntadas por pieza
                </label>

                <input
                  id="preview-stitches"
                  v-model="previewStitches"
                  type="number"
                  min="0"
                  step="1"
                  class="w-full min-h-[2.5rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700"
                />
              </div>

              <div class="grid gap-1.5">
                <label for="preview-applications" class="text-slate-900 text-xs font-bold">
                  Aplicaciones por pieza
                </label>

                <input
                  id="preview-applications"
                  v-model="previewApplications"
                  type="number"
                  min="0"
                  step="1"
                  class="w-full min-h-[2.5rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700"
                />
              </div>

              <div class="grid gap-1.5">
                <label for="preview-quantity" class="text-slate-900 text-xs font-bold">
                  Piezas procesadas
                </label>

                <input
                  id="preview-quantity"
                  v-model="previewQuantity"
                  type="number"
                  min="0"
                  step="1"
                  class="w-full min-h-[2.5rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700"
                />
              </div>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 m-0 text-xs">
              <div class="flex justify-between items-center gap-3 p-3 bg-white border border-slate-100 rounded-md">
                <dt class="text-slate-500">Base por pieza</dt>

                <dd class="m-0 font-mono font-bold text-slate-900">
                  {{
                    formatMoney(
                      formulaPreview.basePerPiece,
                      4,
                    )
                  }}
                </dd>
              </div>

              <div class="flex justify-between items-center gap-3 p-3 bg-white border border-slate-100 rounded-md">
                <dt class="text-slate-500">Resultado por porcentaje</dt>

                <dd class="m-0 font-mono font-bold text-slate-900">
                  {{
                    formatMoney(
                      formulaPreview.formulaPerPiece,
                      4,
                    )
                  }}
                </dd>
              </div>

              <div class="flex justify-between items-center gap-3 p-3 bg-white border border-slate-100 rounded-md">
                <dt class="text-slate-500">Pago final por pieza</dt>

                <dd class="m-0 font-mono font-extrabold text-brand-green-900">
                  {{
                    formatMoney(
                      formulaPreview.finalPerPiece,
                      4,
                    )
                  }}
                </dd>
              </div>

              <div class="flex justify-between items-center gap-3 p-3 bg-white border border-slate-100 rounded-md">
                <dt class="text-slate-500">Pago total de la operación</dt>

                <dd class="m-0 font-mono font-extrabold text-brand-orange-900">
                  {{
                    formatMoney(
                      formulaPreview.operationTotal,
                      2,
                    )
                  }}
                </dd>
              </div>
            </dl>

            <aside
              v-if="formulaPreview.usesDefault"
              class="p-3 text-amber-800 bg-amber-50 border border-amber-200 rounded-md text-xs leading-relaxed"
            >
              El resultado de la fórmula es menor al pago
              mínimo. Se aplicará el pago predeterminado
              por pieza.
            </aside>
          </section>

          <section class="grid gap-4 pt-5 border-t border-slate-200">
            <header>
              <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Vigencia</p>
              <h3 class="m-0 text-base font-bold text-slate-900">Periodo de aplicación</h3>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="grid gap-2">
                <label for="embroidery-effective-from" class="text-slate-900 text-sm font-bold">
                  Inicio de vigencia
                </label>

                <input
                  id="embroidery-effective-from"
                  v-model="form.effectiveFrom"
                  type="date"
                  :disabled="isEditing"
                  class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-400"
                  :class="{
                    'border-rose-700!':
                      firstFieldError(
                        'effective_from',
                      ),
                  }"
                />

                <small
                  v-if="
                    firstFieldError(
                      'effective_from',
                    )
                  "
                  class="text-rose-600 text-xs font-bold"
                >
                  {{
                    firstFieldError(
                      'effective_from',
                    )
                  }}
                </small>
              </div>

              <div class="grid gap-2">
                <label for="embroidery-effective-to" class="text-slate-900 text-sm font-bold">
                  Fin de vigencia
                </label>

                <input
                  id="embroidery-effective-to"
                  v-model="form.effectiveTo"
                  type="date"
                  :min="
                    form.effectiveFrom || undefined
                  "
                  class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
                  :class="{
                    'border-rose-700!':
                      firstFieldError(
                        'effective_to',
                      ),
                  }"
                />

                <small
                  v-if="
                    firstFieldError(
                      'effective_to',
                    )
                  "
                  class="text-rose-600 text-xs font-bold"
                >
                  {{
                    firstFieldError(
                      'effective_to',
                    )
                  }}
                </small>
              </div>

              <div
                v-if="isEditing"
                class="grid gap-2"
              >
                <label for="embroidery-status" class="text-slate-900 text-sm font-bold">
                  Estado
                </label>

                <select
                  id="embroidery-status"
                  v-model="form.status"
                  class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
                >
                  <option value="active">
                    Activa
                  </option>

                  <option value="inactive">
                    Inactiva
                  </option>
                </select>
              </div>

              <div class="grid gap-2 col-span-full">
                <label for="embroidery-notes" class="text-slate-900 text-sm font-bold">
                  Notas
                </label>

                <textarea
                  id="embroidery-notes"
                  v-model="form.notes"
                  rows="4"
                  maxlength="3000"
                  placeholder="Observaciones sobre la fórmula y su aplicación"
                  class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 resize-y"
                  :class="{
                    'border-rose-700!':
                      firstFieldError('notes'),
                  }"
                />

                <div class="flex justify-between items-center gap-3 text-xs">
                  <small
                    v-if="firstFieldError('notes')"
                    class="text-rose-600 font-bold"
                  >
                    {{ firstFieldError('notes') }}
                  </small>

                  <span class="ml-auto text-slate-500 font-mono">
                    {{ form.notes.length }}/3000
                  </span>
                </div>
              </div>
            </div>
          </section>

          <footer class="flex flex-col sm:flex-row justify-end gap-3 pt-5 border-t border-slate-200">
            <button
              type="button"
              class="inline-flex min-h-[3rem] sm:w-44 items-center justify-center gap-2 px-4 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-[750] text-sm cursor-pointer transition-colors disabled:opacity-50"
              :disabled="submitting"
              @click="requestClose"
            >
              Cancelar
            </button>

            <button
              type="submit"
              class="inline-flex min-h-[3rem] sm:w-48 items-center justify-center gap-2 px-4 text-white bg-brand-orange-800 hover:bg-brand-orange-900 border-0 rounded-md font-[750] text-sm cursor-pointer transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="
                submitting ||
                (!isEditing &&
                  operationOptions.length === 0)
              "
            >
              <LoaderCircle
                v-if="submitting"
                :size="20"
                class="animate-spin"
                aria-hidden="true"
              />

              {{
                submitting
                  ? 'Guardando...'
                  : isEditing
                    ? 'Guardar cambios'
                    : 'Registrar configuración'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>