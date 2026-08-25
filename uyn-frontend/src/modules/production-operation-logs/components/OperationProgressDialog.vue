<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  reactive,
  ref,
  watch,
} from 'vue'

import {
  CheckCircle2,
  LoaderCircle,
  Play,
  Save,
  X,
} from 'lucide-vue-next'

import { productionOperationLogsService } from '@/modules/production-operation-logs/services/production-operation-logs.service'
import {
  getApiErrorMessage,
  getValidationErrors,
} from '@/utils/api-error'

import type {
  OperationProgressMode,
  ProductionOperationLog,
  UpdateOperationProgressPayload,
} from '@/modules/production-operation-logs/types/production-operation-log.types'

const props = defineProps<{
  open: boolean
  log: ProductionOperationLog | null
  mode: OperationProgressMode
  maximumQuantity: number
}>()

const emit = defineEmits<{
  close: []
  saved: [
    log: ProductionOperationLog,
    message: string,
  ]
}>()

interface ProgressForm {
  quantityProcessed: number | null
  stitchesCount: number | null
  applicationsCount: number | null
  notes: string
}

const form = reactive<ProgressForm>({
  quantityProcessed: null,
  stitchesCount: null,
  applicationsCount: null,
  notes: '',
})

const submitting = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const title = computed<string>(() => {
  const titles: Record<OperationProgressMode, string> = {
    start: 'Iniciar operación',
    update: 'Registrar avance',
    complete: 'Completar operación',
  }

  return titles[props.mode]
})

const submitLabel = computed<string>(() => {
  const labels: Record<OperationProgressMode, string> = {
    start: 'Iniciar operación',
    update: 'Guardar avance',
    complete: 'Completar operación',
  }

  return labels[props.mode]
})

const currentQuantity = computed<number>(() => {
  return props.log?.quantity_processed ?? 0
})

const isEmbroidery = computed<boolean>(() => {
  const operation = props.log?.operation_process

  if (
    operation?.payroll_calculation_type ===
    'embroidery_formula'
  ) {
    return true
  }

  const operationName =
    operation?.name
      ?.toLocaleLowerCase('es')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '') ?? ''

  return operationName.includes('bordado')
})

function firstFieldError(field: string): string {
  return fieldErrors.value[field]?.[0] ?? ''
}

function setLocalError(
  field: string,
  message: string,
): void {
  fieldErrors.value[field] = [message]
}

function resetForm(): void {
  form.quantityProcessed =
    props.log?.quantity_processed ?? 0

  form.stitchesCount =
    props.log?.stitches_count ?? 0

  form.applicationsCount =
    props.log?.applications_count ?? 0

  form.notes = props.log?.notes ?? ''

  formError.value = ''
  fieldErrors.value = {}
}

function validateForm(): boolean {
  fieldErrors.value = {}
  formError.value = ''

  const quantity = Number(form.quantityProcessed)

  if (!Number.isInteger(quantity)) {
    setLocalError(
      'quantity_processed',
      'La cantidad procesada debe ser un número entero.',
    )
  } else if (quantity < currentQuantity.value) {
    setLocalError(
      'quantity_processed',
      `La cantidad no puede disminuir de ${currentQuantity.value} piezas.`,
    )
  } else if (quantity > props.maximumQuantity) {
    setLocalError(
      'quantity_processed',
      `Este trabajador puede registrar como máximo ${props.maximumQuantity} piezas.`,
    )
  }

  if (props.mode === 'complete' && quantity < 1) {
    setLocalError(
      'quantity_processed',
      'No es posible completar una operación sin piezas procesadas.',
    )
  }

  if (isEmbroidery.value) {
    const stitches = Number(form.stitchesCount)
    const applications = Number(
      form.applicationsCount,
    )

    if (
      !Number.isInteger(stitches) ||
      stitches < 0 ||
      stitches > 100_000_000
    ) {
      setLocalError(
        'stitches_count',
        'Las puntadas deben ser un entero entre 0 y 100,000,000.',
      )
    }

    if (
      !Number.isInteger(applications) ||
      applications < 0
    ) {
      setLocalError(
        'applications_count',
        'Las aplicaciones deben ser un entero mayor o igual a cero.',
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

function buildPayload(): UpdateOperationProgressPayload {
  const payload: UpdateOperationProgressPayload = {
    quantity_processed: Number(
      form.quantityProcessed,
    ),

    notes: form.notes.trim() || null,
  }

  if (props.mode === 'start') {
    payload.start = true
  }

  if (props.mode === 'complete') {
    payload.complete = true
  }

  if (isEmbroidery.value) {
    payload.stitches_count = Number(
      form.stitchesCount,
    )

    payload.applications_count = Number(
      form.applicationsCount,
    )
  }

  return payload
}

async function handleSubmit(): Promise<void> {
  if (!props.log || !validateForm()) {
    return
  }

  submitting.value = true
  formError.value = ''

  try {
    const response =
      await productionOperationLogsService.update(
        props.log.id,
        buildPayload(),
      )

    emit(
      'saved',
      response.data,
      response.message,
    )
  } catch (error) {
    fieldErrors.value = getValidationErrors(error)

    formError.value = getApiErrorMessage(
      error,
      'No fue posible actualizar el avance.',
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
    <div
      v-if="open && log"
      class="fixed inset-0 z-[125] flex items-stretch sm:items-center justify-center bg-slate-950/68 backdrop-blur-xs sm:p-6"
      @click.self="requestClose"
    >
      <section
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:w-[min(100%,42rem)] overflow-hidden bg-white sm:rounded-xl sm:shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="progress-dialog-title"
      >
        <header class="flex items-center justify-between gap-3 p-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 border-b border-slate-200">
          <div class="flex items-center gap-3">
            <div class="flex w-11 h-11 shrink-0 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-md">
              <Play
                v-if="mode === 'start'"
                :size="23"
                aria-hidden="true"
              />

              <Save
                v-else-if="mode === 'update'"
                :size="23"
                aria-hidden="true"
              />

              <CheckCircle2
                v-else
                :size="23"
                aria-hidden="true"
              />
            </div>

            <span class="grid">
              <small class="text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">
                {{ log.employee?.name ?? 'Trabajador' }}
              </small>

              <h2 id="progress-dialog-title" class="m-0 text-xl font-bold text-slate-900">
                {{ title }}
              </h2>
            </span>
          </div>

          <button
            type="button"
            class="inline-flex w-[2.75rem] min-h-[2.75rem] items-center justify-center p-0 text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-md border-0 cursor-pointer transition-colors disabled:opacity-50"
            aria-label="Cerrar formulario"
            :disabled="submitting"
            @click="requestClose"
          >
            <X :size="22" aria-hidden="true" />
          </button>
        </header>

        <form
          class="grid gap-5 overflow-y-auto p-5 px-4 sm:px-6 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
          novalidate
          @submit.prevent="handleSubmit"
        >
          <div
            v-if="formError"
            class="p-4 text-rose-700 bg-rose-50 border border-rose-200 rounded-md text-sm leading-relaxed"
            role="alert"
          >
            {{ formError }}
          </div>

          <section class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-4 bg-brand-green-50/70 border border-brand-green-200/80 rounded-xl text-xs">
            <div class="grid">
              <span class="text-slate-500 font-medium">Operación</span>

              <strong class="text-slate-900 font-bold text-sm truncate">
                {{
                  log.operation_process?.name ??
                  'No disponible'
                }}
              </strong>
            </div>

            <div class="grid">
              <span class="text-slate-500 font-medium">Avance actual</span>
              <strong class="text-slate-900 font-bold text-sm font-mono">
                {{ currentQuantity }} piezas
              </strong>
            </div>

            <div class="grid">
              <span class="text-slate-500 font-medium">Máximo permitido</span>
              <strong class="text-slate-900 font-bold text-sm font-mono">
                {{ maximumQuantity }} piezas
              </strong>
            </div>
          </section>

          <div class="grid gap-1.5">
            <label for="progress-quantity" class="text-slate-900 text-sm font-bold">
              Cantidad procesada acumulada
            </label>

            <input
              id="progress-quantity"
              v-model.number="form.quantityProcessed"
              type="number"
              :min="currentQuantity"
              :max="maximumQuantity"
              step="1"
              class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
              :class="{
                'border-rose-700!': firstFieldError('quantity_processed'),
              }"
            />

            <small
              v-if="firstFieldError('quantity_processed')"
              class="text-rose-600 text-xs"
            >
              {{ firstFieldError('quantity_processed') }}
            </small>

            <small
              v-else
              class="text-slate-500 text-xs"
            >
              Registra el total acumulado del trabajador,
              no únicamente las piezas de este momento.
            </small>
          </div>

          <section
            v-if="isEmbroidery"
            class="grid gap-4 p-4 bg-brand-orange-50/70 border border-brand-orange-100 rounded-xl"
          >
            <header>
              <p class="m-0 text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Cálculo de Bordado</p>
              <h3 class="m-0 mt-0.5 text-base font-bold text-slate-900">Datos por pieza</h3>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="grid gap-1.5">
                <label for="progress-stitches" class="text-slate-900 text-sm font-bold">
                  Puntadas por pieza
                </label>

                <input
                  id="progress-stitches"
                  v-model.number="form.stitchesCount"
                  type="number"
                  min="0"
                  max="100000000"
                  step="1"
                  class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
                  :class="{
                    'border-rose-700!': firstFieldError('stitches_count'),
                  }"
                />

                <small
                  v-if="firstFieldError('stitches_count')"
                  class="text-rose-600 text-xs"
                >
                  {{ firstFieldError('stitches_count') }}
                </small>
              </div>

              <div class="grid gap-1.5">
                <label for="progress-applications" class="text-slate-900 text-sm font-bold">
                  Aplicaciones por pieza
                </label>

                <input
                  id="progress-applications"
                  v-model.number="
                    form.applicationsCount
                  "
                  type="number"
                  min="0"
                  step="1"
                  class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
                  :class="{
                    'border-rose-700!': firstFieldError('applications_count'),
                  }"
                />

                <small
                  v-if="firstFieldError('applications_count')"
                  class="text-rose-600 text-xs"
                >
                  {{ firstFieldError('applications_count') }}
                </small>
              </div>
            </div>
          </section>

          <div class="grid gap-1.5">
            <label for="progress-notes" class="text-slate-900 text-sm font-bold">
              Observaciones
            </label>

            <textarea
              id="progress-notes"
              v-model="form.notes"
              rows="4"
              maxlength="3000"
              placeholder="Describe el avance, condiciones o incidencias observadas"
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
                {{ form.notes.length }}/3000
              </span>
            </div>
          </div>

          <aside
            v-if="mode === 'complete'"
            class="p-4 text-amber-800 bg-amber-50 border border-amber-200 rounded-xl text-xs leading-relaxed"
          >
            Al completar la operación se congelará el pago
            calculado asociado a este avance.
          </aside>

          <footer class="grid grid-cols-1 sm:flex sm:justify-end gap-3 pt-5 border-t border-slate-200">
            <button
              type="button"
              class="inline-flex min-h-[3rem] sm:w-40 items-center justify-center px-4 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-[750] text-sm cursor-pointer transition-colors disabled:opacity-50"
              :disabled="submitting"
              @click="requestClose"
            >
              Cancelar
            </button>

            <button
              type="submit"
              class="inline-flex min-h-[3rem] sm:w-48 items-center justify-center gap-2 px-5 text-white bg-brand-orange-800 hover:bg-brand-orange-900 rounded-md font-[750] text-sm cursor-pointer transition-colors border-0 disabled:opacity-50"
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
                  : submitLabel
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>