<script setup lang="ts">
import { onBeforeUnmount, reactive, ref, watch } from 'vue'
import { CalendarDays, LoaderCircle, X } from 'lucide-vue-next'
import { payrollPeriodsService } from '@/modules/payroll-settings/services/payroll-periods.service'
import { getApiErrorMessage, getValidationErrors } from '@/utils/api-error'

import type { PayrollPeriod, PayrollPeriodFrequency } from '@/modules/payroll-settings/types/payroll-period.types'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  close: []
  saved: [period: PayrollPeriod, message: string]
}>()

interface PeriodForm {
  code: string
  frequency: PayrollPeriodFrequency
  startDate: string
  endDate: string
  paymentDate: string
  notes: string
}

const form = reactive<PeriodForm>({
  code: '',
  frequency: 'weekly',
  startDate: '',
  endDate: '',
  paymentDate: '',
  notes: '',
})

const submitting = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

function resetForm(): void {
  form.code = ''
  form.frequency = 'weekly'
  form.startDate = ''
  form.endDate = ''
  form.paymentDate = ''
  form.notes = ''
  formError.value = ''
  fieldErrors.value = {}
}

function firstFieldError(field: string): string | null {
  return fieldErrors.value[field]?.[0] ?? null
}

function requestClose(): void {
  if (!submitting.value) {
    emit('close')
  }
}

async function handleSubmit(): Promise<void> {
  if (submitting.value) return
  submitting.value = true
  formError.value = ''
  fieldErrors.value = {}

  try {
    const payload = {
      code: form.code.trim(),
      frequency: form.frequency,
      start_date: form.startDate,
      end_date: form.endDate,
      payment_date: form.paymentDate || undefined,
      notes: form.notes.trim() || undefined,
    }

    const response = await payrollPeriodsService.create(payload)
    emit('saved', response.data, response.message ?? 'Periodo creado correctamente.')
    resetForm()
  } catch (error) {
    fieldErrors.value = getValidationErrors(error)
    formError.value = getApiErrorMessage(error, 'No fue posible registrar el periodo de nómina.')
  } finally {
    submitting.value = false
  }
}

watch(
  () => props.open,
  (open) => {
    document.body.style.overflow = open ? 'hidden' : ''
    if (open) {
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
    <div v-if="open" class="fixed inset-0 z-[1000] flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs" @click.self="requestClose">
      <section
        class="w-full max-w-xl bg-white rounded-xl shadow-xl overflow-hidden flex flex-col"
        role="dialog"
        aria-modal="true"
        aria-labelledby="period-dialog-title"
      >
        <header class="flex items-center gap-3 p-4 sm:px-5 border-b border-slate-200">
          <div class="flex w-11 h-11 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-lg shrink-0">
            <CalendarDays :size="23" aria-hidden="true" />
          </div>
          <span class="grid flex-1 min-w-0">
            <small class="text-slate-500 text-xs font-bold uppercase tracking-wider">Periodo de nómina</small>
            <h2 id="period-dialog-title" class="m-0 text-base font-bold text-slate-900 truncate">Nuevo periodo de pago</h2>
          </span>

          <button
            type="button"
            class="text-slate-500 hover:text-slate-700 bg-transparent border-0 cursor-pointer p-1 transition-colors disabled:opacity-50"
            aria-label="Cerrar formulario"
            :disabled="submitting"
            @click="requestClose"
          >
            <X :size="22" aria-hidden="true" />
          </button>
        </header>

        <form class="p-5 grid gap-4" novalidate @submit.prevent="handleSubmit">
          <div v-if="formError" class="p-3 bg-rose-50 text-rose-700 rounded-md text-sm font-bold border border-rose-200" role="alert">
            {{ formError }}
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label for="period-code" class="text-slate-900 text-xs font-bold">Folio / Código único</label>
              <input
                id="period-code"
                v-model="form.code"
                type="text"
                required
                maxlength="50"
                placeholder="Ej. NOM-2026-W28"
                class="min-h-[2.75rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/12"
                :class="{ 'border-rose-700!': firstFieldError('code') }"
              />
              <small v-if="firstFieldError('code')" class="text-rose-600 text-xs font-bold">
                {{ firstFieldError('code') }}
              </small>
            </div>

            <div class="flex flex-col gap-1">
              <label for="period-frequency" class="text-slate-900 text-xs font-bold">Frecuencia de ciclo</label>
              <select
                id="period-frequency"
                v-model="form.frequency"
                class="min-h-[2.75rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/12"
                :class="{ 'border-rose-700!': firstFieldError('frequency') }"
              >
                <option value="weekly">Semanal</option>
                <option value="biweekly">Quincenal</option>
                <option value="monthly">Mensual</option>
              </select>
              <small v-if="firstFieldError('frequency')" class="text-rose-600 text-xs font-bold">
                {{ firstFieldError('frequency') }}
              </small>
            </div>

            <div class="flex flex-col gap-1">
              <label for="period-start" class="text-slate-900 text-xs font-bold">Fecha de inicio</label>
              <input
                id="period-start"
                v-model="form.startDate"
                type="date"
                required
                class="min-h-[2.75rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/12"
                :class="{ 'border-rose-700!': firstFieldError('start_date') }"
              />
              <small v-if="firstFieldError('start_date')" class="text-rose-600 text-xs font-bold">
                {{ firstFieldError('start_date') }}
              </small>
            </div>

            <div class="flex flex-col gap-1">
              <label for="period-end" class="text-slate-900 text-xs font-bold">Fecha de término</label>
              <input
                id="period-end"
                v-model="form.endDate"
                type="date"
                required
                class="min-h-[2.75rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/12"
                :class="{ 'border-rose-700!': firstFieldError('end_date') }"
              />
              <small v-if="firstFieldError('end_date')" class="text-rose-600 text-xs font-bold">
                {{ firstFieldError('end_date') }}
              </small>
            </div>

            <div class="flex flex-col gap-1">
              <label for="period-payment" class="text-slate-900 text-xs font-bold">Fecha programada de pago</label>
              <input
                id="period-payment"
                v-model="form.paymentDate"
                type="date"
                class="min-h-[2.75rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/12"
                :class="{ 'border-rose-700!': firstFieldError('payment_date') }"
              />
              <small v-if="firstFieldError('payment_date')" class="text-rose-600 text-xs font-bold">
                {{ firstFieldError('payment_date') }}
              </small>
            </div>

            <div class="flex flex-col gap-1 col-span-full">
              <label for="period-notes" class="text-slate-900 text-xs font-bold">Observaciones</label>
              <textarea
                id="period-notes"
                v-model="form.notes"
                rows="3"
                maxlength="3000"
                placeholder="Notas u observaciones internas..."
                class="p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/12 resize-y"
                :class="{ 'border-rose-700!': firstFieldError('notes') }"
              ></textarea>
              <small v-if="firstFieldError('notes')" class="text-rose-600 text-xs font-bold">
                {{ firstFieldError('notes') }}
              </small>
            </div>
          </div>

          <footer class="flex justify-end gap-2 mt-2">
            <button
              type="button"
              class="inline-flex items-center justify-center gap-2 min-h-[2.75rem] px-4 text-slate-700 bg-white border border-slate-300 hover:bg-slate-100 rounded-md font-[750] text-sm cursor-pointer transition-colors"
              :disabled="submitting"
              @click="requestClose"
            >
              Cancelar
            </button>

            <button type="submit" class="inline-flex items-center justify-center gap-2 min-h-[2.75rem] px-4 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-[750] text-sm cursor-pointer transition-colors border border-brand-green-700 disabled:opacity-60 disabled:cursor-not-allowed" :disabled="submitting">
              <LoaderCircle v-if="submitting" class="animate-spin" :size="19" aria-hidden="true" />
              <span>Registrar periodo</span>
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>

