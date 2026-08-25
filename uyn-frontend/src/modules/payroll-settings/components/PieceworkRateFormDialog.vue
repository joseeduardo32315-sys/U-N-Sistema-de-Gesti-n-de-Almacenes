<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  reactive,
  ref,
  watch,
} from 'vue'

import {
  CircleDollarSign,
  LoaderCircle,
  X,
} from 'lucide-vue-next'

import { pieceworkRatesService } from '@/modules/payroll-settings/services/piecework-rates.service'
import {
  getApiErrorMessage,
  getValidationErrors,
} from '@/utils/api-error'

import type { Employee } from '@/modules/employees/types/employee.types'
import type { ProductionProcess } from '@/modules/processes/types/process.types'

import type {
  CreatePieceworkRatePayload,
  PieceworkRate,
  UpdatePieceworkRatePayload,
} from '@/modules/payroll-settings/types/piecework-rate.types'

import type { PayrollRuleStatus } from '@/modules/payroll-settings/types/employee-compensation.types'

interface OperationOption {
  id: number
  name: string
  processName: string
}

const props = defineProps<{
  open: boolean
  rate: PieceworkRate | null
  employees: Employee[]
  processes: ProductionProcess[]
}>()

const emit = defineEmits<{
  close: []
  saved: [
    rate: PieceworkRate,
    message: string,
  ]
}>()

interface RateForm {
  employeeId: number | ''
  operationProcessId: number | ''
  amountPerPiece: string
  effectiveFrom: string
  effectiveTo: string
  status: PayrollRuleStatus
  notes: string
}

const form = reactive<RateForm>({
  employeeId: '',
  operationProcessId: '',
  amountPerPiece: '',
  effectiveFrom: '',
  effectiveTo: '',
  status: 'active',
  notes: '',
})

const submitting = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const isEditing = computed<boolean>(() => {
  return props.rate !== null
})

const title = computed<string>(() => {
  return isEditing.value
    ? 'Editar tarifa de destajo'
    : 'Registrar tarifa de destajo'
})

function isPieceworkOperation(
  calculationType?: string,
): boolean {
  const normalizedType =
    calculationType
      ?.trim()
      .toLocaleLowerCase('es') ?? ''

  /*
   * GET /processes puede no devolver el tipo de cálculo.
   * En ese caso se muestra la operación y el backend
   * conserva la validación definitiva.
   */
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
    return props.processes
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

function resetForm(): void {
  form.employeeId =
    props.rate?.employee.id ?? ''

  form.operationProcessId =
    props.rate?.operation_process.id ?? ''

  form.amountPerPiece =
    props.rate?.amount_per_piece ?? ''

  form.effectiveFrom =
    props.rate?.effective_from ??
    localDate()

  form.effectiveTo =
    props.rate?.effective_to ?? ''

  form.status =
    props.rate?.status ?? 'active'

  form.notes =
    props.rate?.notes ?? ''

  formError.value = ''
  fieldErrors.value = {}
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

function validateForm(): boolean {
  fieldErrors.value = {}
  formError.value = ''

  if (!isEditing.value && !form.employeeId) {
    setLocalError(
      'employee_id',
      'Selecciona un trabajador.',
    )
  }

  if (
    !isEditing.value &&
    !form.operationProcessId
  ) {
    setLocalError(
      'operation_process_id',
      'Selecciona una operación.',
    )
  }

  if (!isEditing.value) {
    const amount = parseDecimalInput(
        form.amountPerPiece,
        )

    if (
      !Number.isFinite(amount) ||
      amount < 0.0001 ||
      amount > 99_999_999.9999
    ) {
      setLocalError(
        'amount_per_piece',
        'Ingresa una tarifa entre 0.0001 y 99,999,999.9999.',
      )
    }
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

  return Object.keys(fieldErrors.value).length === 0
}

async function handleSubmit(): Promise<void> {
  if (!validateForm()) {
    return
  }

  submitting.value = true
  formError.value = ''

  try {
    if (props.rate) {
      const payload: UpdatePieceworkRatePayload = {
        effective_to:
          form.effectiveTo || null,
        status: form.status,
        notes: form.notes.trim() || null,
      }

      const response =
        await pieceworkRatesService.update(
          props.rate.id,
          payload,
        )

      emit(
        'saved',
        response.data,
        response.message,
      )

      return
    }

    if (
      !form.employeeId ||
      !form.operationProcessId
    ) {
      return
    }

    const payload: CreatePieceworkRatePayload = {
      employee_id: form.employeeId,

      operation_process_id:
        form.operationProcessId,

      amount_per_piece:
        parseDecimalInput(
            form.amountPerPiece,
        ).toFixed(4),

      effective_from: form.effectiveFrom,
      effective_to: form.effectiveTo || null,
      notes: form.notes.trim() || null,
    }

    const response =
      await pieceworkRatesService.create(payload)

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
      'No fue posible guardar la tarifa.',
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

function parseDecimalInput(
  value: string,
): number {
  return Number(
    value
      .trim()
      .replace(',', '.'),
  )
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
  () => props.rate?.id,
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
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:max-w-2xl bg-white rounded-none sm:rounded-xl shadow-xl overflow-hidden"
        role="dialog"
        aria-modal="true"
        aria-labelledby="rate-dialog-title"
      >
        <header class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 p-4 sm:px-6 border-b border-slate-200">
          <div class="flex w-11 h-11 items-center justify-center text-brand-orange-900 bg-brand-orange-100 rounded-md shrink-0">
            <CircleDollarSign
              :size="23"
              aria-hidden="true"
            />
          </div>

          <span class="grid min-w-0">
            <small class="text-brand-orange-800 text-xs font-extrabold tracking-wider uppercase">Pago por pieza</small>

            <h2 id="rate-dialog-title" class="m-0 text-base font-bold text-slate-900 truncate">
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
          class="grid gap-5 overflow-y-auto p-5 sm:p-6"
          novalidate
          @submit.prevent="handleSubmit"
        >
          <div
            v-if="formError"
            class="p-3 bg-rose-50 text-rose-700 rounded-md text-sm font-bold border border-rose-200"
            role="alert"
          >
            {{ formError }}
          </div>

          <section
            v-if="isEditing && rate"
            class="grid gap-1 p-4 bg-brand-orange-50/70 border border-brand-orange-100 rounded-xl"
          >
            <strong class="text-slate-900 font-bold text-sm">{{ rate.employee.name }}</strong>

            <span class="text-slate-600 text-xs">
              {{
                rate.operation_process.process?.name ??
                'Proceso'
              }}
              ·
              {{ rate.operation_process.name }}
            </span>

            <small class="text-slate-500 text-xs font-mono">
              Tarifa registrada:
              ${{ rate.amount_per_piece }}
            </small>
          </section>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="grid gap-1.5">
              <label for="rate-employee" class="text-slate-900 text-sm font-bold">
                Trabajador
              </label>

              <select
                id="rate-employee"
                v-model="form.employeeId"
                :disabled="isEditing"
                class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-400"
                :class="{
                  'border-rose-700!':
                    firstFieldError('employee_id'),
                }"
              >
                <option value="">
                  Selecciona un trabajador
                </option>

                <option
                  v-for="employee in employees"
                  :key="employee.id"
                  :value="employee.id"
                >
                  {{ employee.name }}
                </option>
              </select>

              <small
                v-if="firstFieldError('employee_id')"
                class="text-rose-600 text-xs font-bold"
              >
                {{ firstFieldError('employee_id') }}
              </small>

              <small
                v-else-if="!isEditing"
                class="text-slate-500 text-xs"
              >
                Solo aparecen trabajadores con compensación
                vigente de destajo.
              </small>
            </div>

            <div class="grid gap-1.5">
              <label for="rate-operation" class="text-slate-900 text-sm font-bold">
                Suboperación
              </label>

              <select
                id="rate-operation"
                v-model="form.operationProcessId"
                :disabled="
                  isEditing ||
                  operationOptions.length === 0
                "
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
                      : 'No hay suboperaciones disponibles'
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
                v-else-if="
                  !isEditing &&
                  operationOptions.length === 0
                "
                class="text-slate-500 text-xs"
              >
                El catálogo de procesos no devolvió
                suboperaciones disponibles.
              </small>
            </div>

            <div class="grid gap-1.5">
              <label for="rate-amount" class="text-slate-900 text-sm font-bold">
                Importe por pieza
              </label>

              <input
                id="rate-amount"
                v-model="form.amountPerPiece"
                type="text"
                inputmode="decimal"
                placeholder="Ej. 0.7500"
                :disabled="isEditing"
                class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-400"
                :class="{
                  'border-rose-700!':
                    firstFieldError(
                      'amount_per_piece',
                    ),
                }"
              />

              <small
                v-if="
                  firstFieldError(
                    'amount_per_piece',
                  )
                "
                class="text-rose-600 text-xs font-bold"
              >
                {{
                  firstFieldError(
                    'amount_per_piece',
                  )
                }}
              </small>
            </div>

            <div class="grid gap-1.5">
              <label for="rate-from" class="text-slate-900 text-sm font-bold">
                Inicio de vigencia
              </label>

              <input
                id="rate-from"
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
                  firstFieldError('effective_from')
                "
                class="text-rose-600 text-xs font-bold"
              >
                {{
                  firstFieldError('effective_from')
                }}
              </small>
            </div>

            <div class="grid gap-1.5">
              <label for="rate-to" class="text-slate-900 text-sm font-bold">
                Fin de vigencia
              </label>

              <input
                id="rate-to"
                v-model="form.effectiveTo"
                type="date"
                :min="form.effectiveFrom || undefined"
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
                  firstFieldError('effective_to')
                "
                class="text-rose-600 text-xs font-bold"
              >
                {{
                  firstFieldError('effective_to')
                }}
              </small>
            </div>

            <div
              v-if="isEditing"
              class="grid gap-1.5"
            >
              <label for="rate-status" class="text-slate-900 text-sm font-bold">
                Estado
              </label>

              <select
                id="rate-status"
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

            <div class="grid gap-1.5 col-span-full">
              <label for="rate-notes" class="text-slate-900 text-sm font-bold">
                Notas
              </label>

              <textarea
                id="rate-notes"
                v-model="form.notes"
                rows="4"
                maxlength="3000"
                placeholder="Observaciones de la tarifa"
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

          <footer class="flex flex-col sm:flex-row justify-end gap-3 pt-5 border-t border-slate-200">
            <button
              type="button"
              class="inline-flex min-h-[3rem] sm:w-40 items-center justify-center gap-2 px-4 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-[750] text-sm cursor-pointer transition-colors disabled:opacity-50"
              :disabled="submitting"
              @click="requestClose"
            >
              Cancelar
            </button>

            <button
              type="submit"
              class="inline-flex min-h-[3rem] sm:w-44 items-center justify-center gap-2 px-4 text-white bg-brand-orange-800 hover:bg-brand-orange-900 border-0 rounded-md font-[750] text-sm cursor-pointer transition-colors disabled:opacity-50"
              :disabled="submitting"
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
                    : 'Registrar tarifa'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>