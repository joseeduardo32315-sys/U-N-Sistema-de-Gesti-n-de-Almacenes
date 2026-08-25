<script setup lang="ts">
import {
  onBeforeUnmount,
  ref,
  watch,
} from 'vue'

import {
  CheckCircle2,
  LoaderCircle,
  X,
} from 'lucide-vue-next'

import { productionIncidentsService } from '@/modules/production-incidents/services/production-incidents.service'
import {
  getApiErrorMessage,
  getValidationErrors,
} from '@/utils/api-error'

import type { ProductionIncident } from '@/modules/production-incidents/types/production-incident.types'

const props = defineProps<{
  open: boolean
  incident: ProductionIncident | null
}>()

const emit = defineEmits<{
  close: []
  resolved: [
    incident: ProductionIncident,
    message: string,
  ]
}>()

const resolutionNotes = ref('')
const submitting = ref(false)

const formError = ref('')
const fieldErrors =
  ref<Record<string, string[]>>({})

function firstFieldError(field: string): string {
  return fieldErrors.value[field]?.[0] ?? ''
}

function resetForm(): void {
  resolutionNotes.value = ''
  formError.value = ''
  fieldErrors.value = {}
}

function validateForm(): boolean {
  fieldErrors.value = {}
  formError.value = ''

  if (!resolutionNotes.value.trim()) {
    fieldErrors.value.resolution_notes = [
      'Describe cómo fue resuelta la incidencia.',
    ]
  } else if (resolutionNotes.value.length > 3000) {
    fieldErrors.value.resolution_notes = [
      'Las notas no pueden superar 3000 caracteres.',
    ]
  }

  return Object.keys(fieldErrors.value).length === 0
}

async function handleSubmit(): Promise<void> {
  if (!props.incident || !validateForm()) {
    return
  }

  submitting.value = true
  formError.value = ''

  try {
    const response =
      await productionIncidentsService.resolve(
        props.incident.id,
        {
          notes:
            resolutionNotes.value.trim(),
        },
      )

    emit(
      'resolved',
      response.data,
      response.message,
    )
  } catch (error) {
    fieldErrors.value = getValidationErrors(error)

    formError.value = getApiErrorMessage(
      error,
      'No fue posible resolver la incidencia.',
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
      v-if="open && incident"
      class="fixed inset-0 z-[130] flex items-stretch sm:items-center justify-center bg-slate-950/70 backdrop-blur-xs sm:p-6"
      @click.self="requestClose"
    >
      <section
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:w-[min(100%,40rem)] overflow-hidden bg-white sm:rounded-xl sm:shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="resolve-dialog-title"
      >
        <header class="flex items-center justify-between gap-3 p-4 sm:px-6 border-b border-slate-200">
          <div class="flex items-center gap-3">
            <div class="flex w-11 h-11 shrink-0 items-center justify-center text-emerald-800 bg-emerald-100 rounded-md">
              <CheckCircle2
                :size="23"
                aria-hidden="true"
              />
            </div>

            <span class="grid">
              <small class="text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">
                {{ incident.incident_type_label }}
              </small>

              <h2 id="resolve-dialog-title" class="m-0 text-xl font-bold text-slate-900">
                Resolver incidencia
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

        <form class="grid gap-5 overflow-y-auto p-5 px-4 sm:px-6" @submit.prevent="handleSubmit">
          <div
            v-if="formError"
            class="p-4 text-rose-700 bg-rose-50 border border-rose-200 rounded-md text-sm leading-relaxed"
            role="alert"
          >
            {{ formError }}
          </div>

          <p class="m-0 text-slate-600 text-sm leading-relaxed">
            Describe la acción tomada y el resultado final.
            En una pérdida confirmada, esta resolución puede
            reducir la cantidad efectiva del movimiento.
          </p>

          <div class="grid gap-1.5">
            <label for="resolution-notes" class="text-slate-900 text-sm font-bold">
              Notas de resolución
            </label>

            <textarea
              id="resolution-notes"
              v-model="resolutionNotes"
              rows="6"
              maxlength="3000"
              placeholder="Ej. Se confirma la pérdida de cinco piezas y se actualiza la cantidad disponible."
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
                {{ resolutionNotes.length }}/3000
              </span>
            </div>
          </div>

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
              class="inline-flex min-h-[3rem] sm:w-48 items-center justify-center gap-2 px-5 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-[750] text-sm cursor-pointer transition-colors border-0 disabled:opacity-50"
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
                  ? 'Resolviendo...'
                  : 'Confirmar resolución'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>