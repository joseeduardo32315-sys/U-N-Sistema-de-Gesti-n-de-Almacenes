<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  reactive,
  ref,
  watch,
} from 'vue'

import {
  BadgeDollarSign,
  LoaderCircle,
  X,
} from 'lucide-vue-next'

import { employeeCompensationsService } from '@/modules/payroll-settings/services/employee-compensations.service'
import {
  getApiErrorMessage,
  getValidationErrors,
} from '@/utils/api-error'

import type { Employee } from '@/modules/employees/types/employee.types'

import type {
  CreateEmployeeCompensationPayload,
  EmployeeCompensation,
  EmployeePaymentType,
  PaymentFrequency,
  PayrollRuleStatus,
  UpdateEmployeeCompensationPayload,
} from '@/modules/payroll-settings/types/employee-compensation.types'

const props = defineProps<{
  open: boolean
  compensation: EmployeeCompensation | null
  employees: Employee[]
}>()

const emit = defineEmits<{
  close: []
  saved: [
    compensation: EmployeeCompensation,
    message: string,
  ]
}>()

interface CompensationForm {
  employeeId: number | ''
  paymentType: EmployeePaymentType
  paymentFrequency: PaymentFrequency
  fixedAmount: string
  effectiveFrom: string
  effectiveTo: string
  status: PayrollRuleStatus
  notes: string
}

const form = reactive<CompensationForm>({
  employeeId: '',
  paymentType: 'piecework',
  paymentFrequency: 'weekly',
  fixedAmount: '',
  effectiveFrom: '',
  effectiveTo: '',
  status: 'active',
  notes: '',
})

const submitting = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const isEditing = computed<boolean>(() => {
  return props.compensation !== null
})

const isFixed = computed<boolean>(() => {
  return form.paymentType === 'fixed'
})

const title = computed<string>(() => {
  return isEditing.value
    ? 'Editar esquema de pago'
    : 'Registrar esquema de pago'
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
    props.compensation?.employee.id ?? ''

  form.paymentType =
    props.compensation?.payment_type ??
    'piecework'

  form.paymentFrequency =
    props.compensation?.payment_frequency ??
    'weekly'

  form.fixedAmount =
    props.compensation?.fixed_amount ?? ''

  form.effectiveFrom =
    props.compensation?.effective_from ??
    localDate()

  form.effectiveTo =
    props.compensation?.effective_to ?? ''

  form.status =
    props.compensation?.status ?? 'active'

  form.notes =
    props.compensation?.notes ?? ''

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

  if (!form.effectiveFrom) {
    setLocalError(
      'effective_from',
      'Selecciona la fecha de inicio.',
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

  if (!isEditing.value && isFixed.value) {
    const amount = Number(form.fixedAmount)

    if (
      !Number.isFinite(amount) ||
      amount < 0.01
    ) {
      setLocalError(
        'fixed_amount',
        'Ingresa un monto fijo mayor o igual a $0.01.',
      )
    }
  }

  if (form.notes.length > 3000) {
    setLocalError(
      'notes',
      'Las notas no pueden superar 3000 caracteres.',
    )
  }

  return Object.keys(fieldErrors.value).length === 0
}

function buildCreatePayload():
  CreateEmployeeCompensationPayload | null {
  if (!form.employeeId) {
    return null
  }

  const payload: CreateEmployeeCompensationPayload = {
    employee_id: form.employeeId,
    payment_type: form.paymentType,
    effective_from: form.effectiveFrom,
    effective_to: form.effectiveTo || null,
    notes: form.notes.trim() || null,
  }

  if (form.paymentType === 'fixed') {
    payload.payment_frequency =
      form.paymentFrequency

    payload.fixed_amount =
      Number(form.fixedAmount).toFixed(2)
  }

  return payload
}

async function handleSubmit(): Promise<void> {
  if (!validateForm()) {
    return
  }

  submitting.value = true
  formError.value = ''

  try {
    if (props.compensation) {
      const payload:
        UpdateEmployeeCompensationPayload = {
          effective_to:
            form.effectiveTo || null,
          status: form.status,
          notes: form.notes.trim() || null,
        }

      const response =
        await employeeCompensationsService.update(
          props.compensation.id,
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
      await employeeCompensationsService.create(
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
      'No fue posible guardar el esquema de pago.',
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
  () => props.compensation?.id,
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
        aria-labelledby="compensation-title"
      >
        <header class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 p-4 sm:px-6 border-b border-slate-200">
          <div class="flex w-11 h-11 items-center justify-center text-emerald-700 bg-emerald-100/70 rounded-md shrink-0">
            <BadgeDollarSign
              :size="23"
              aria-hidden="true"
            />
          </div>

          <span class="grid min-w-0">
            <small class="text-brand-orange-800 text-xs font-extrabold tracking-wider uppercase">Configuración de nómina</small>

            <h2 id="compensation-title" class="m-0 text-base font-bold text-slate-900 truncate">
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
            v-if="isEditing && compensation"
            class="grid gap-1 p-4 bg-brand-green-50/70 border border-brand-green-100 rounded-xl"
          >
            <strong class="text-slate-900 font-bold text-sm">
              {{ compensation.employee.name }}
            </strong>

            <span class="text-slate-600 text-xs">
              {{ compensation.payment_type_label }}
            </span>

            <small class="text-slate-500 text-xs">
              Vigente desde
              {{ compensation.effective_from }}
            </small>
          </section>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="grid gap-1.5">
              <label for="compensation-employee" class="text-slate-900 text-sm font-bold">
                Trabajador
              </label>

              <select
                id="compensation-employee"
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
                  ·
                  {{
                    employee.worker_type ===
                    'external'
                      ? 'Externo'
                      : 'Interno'
                  }}
                </option>
              </select>

              <small
                v-if="firstFieldError('employee_id')"
                class="text-rose-600 text-xs font-bold"
              >
                {{ firstFieldError('employee_id') }}
              </small>
            </div>

            <div class="grid gap-1.5">
              <label for="compensation-type" class="text-slate-900 text-sm font-bold">
                Tipo de pago
              </label>

              <select
                id="compensation-type"
                v-model="form.paymentType"
                :disabled="isEditing"
                class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-400"
              >
                <option value="piecework">
                  Destajo
                </option>

                <option value="fixed">
                  Pago fijo
                </option>
              </select>
            </div>

            <template v-if="isFixed">
              <div class="grid gap-1.5">
                <label for="compensation-frequency" class="text-slate-900 text-sm font-bold">
                  Frecuencia
                </label>

                <select
                  id="compensation-frequency"
                  v-model="form.paymentFrequency"
                  :disabled="isEditing"
                  class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-400"
                >
                  <option value="weekly">
                    Semanal
                  </option>

                  <option value="biweekly">
                    Quincenal
                  </option>

                  <option value="monthly">
                    Mensual
                  </option>
                </select>
              </div>

              <div class="grid gap-1.5">
                <label for="compensation-amount" class="text-slate-900 text-sm font-bold">
                  Monto fijo
                </label>

                <input
                  id="compensation-amount"
                  v-model="form.fixedAmount"
                  type="number"
                  min="0.01"
                  step="0.01"
                  placeholder="Ej. 2500.00"
                  :disabled="isEditing"
                  class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-400"
                  :class="{
                    'border-rose-700!':
                      firstFieldError(
                        'fixed_amount',
                      ),
                  }"
                />

                <small
                  v-if="
                    firstFieldError('fixed_amount')
                  "
                  class="text-rose-600 text-xs font-bold"
                >
                  {{
                    firstFieldError('fixed_amount')
                  }}
                </small>
              </div>
            </template>

            <div class="grid gap-1.5">
              <label for="compensation-from" class="text-slate-900 text-sm font-bold">
                Inicio de vigencia
              </label>

              <input
                id="compensation-from"
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
              <label for="compensation-to" class="text-slate-900 text-sm font-bold">
                Fin de vigencia
              </label>

              <input
                id="compensation-to"
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
              <label for="compensation-status" class="text-slate-900 text-sm font-bold">
                Estado
              </label>

              <select
                id="compensation-status"
                v-model="form.status"
                class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
              >
                <option value="active">
                  Activo
                </option>

                <option value="inactive">
                  Inactivo
                </option>
              </select>
            </div>

            <div class="grid gap-1.5 col-span-full">
              <label for="compensation-notes" class="text-slate-900 text-sm font-bold">
                Notas
              </label>

              <textarea
                id="compensation-notes"
                v-model="form.notes"
                rows="4"
                maxlength="3000"
                placeholder="Observaciones sobre el esquema de pago"
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

          <aside
            v-if="
              !isEditing &&
              form.paymentType === 'piecework'
            "
            class="p-4 text-sky-800 bg-sky-50 border border-sky-200/80 rounded-xl text-xs leading-relaxed"
          >
            Después de registrar la compensación por
            destajo deberás definir las tarifas por
            suboperación para este trabajador.
          </aside>

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
                    : 'Registrar esquema'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>