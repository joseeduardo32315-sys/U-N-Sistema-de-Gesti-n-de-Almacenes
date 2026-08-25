<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  reactive,
  ref,
  watch,
} from 'vue'

import {
  CalendarDays,
  ClipboardList,
  LoaderCircle,
  X,
} from 'lucide-vue-next'

import { productionOrdersService } from '@/modules/production-orders/services/production-orders.service'
import {
  getApiErrorMessage,
  getValidationErrors,
} from '@/utils/api-error'

import type {
  CreateProductionOrderPayload,
  ProductionOrder,
  ProductionOrderPriority,
  UpdateProductionOrderPayload,
} from '@/modules/production-orders/types/production-order.types'

const props = defineProps<{
  open: boolean
  order: ProductionOrder | null
}>()

const emit = defineEmits<{
  close: []
  saved: [order: ProductionOrder, message: string]
}>()

interface ProductionOrderForm {
  orderCode: string
  location: string
  startDate: string
  endDate: string
  priority: ProductionOrderPriority
  notes: string
}

const form = reactive<ProductionOrderForm>({
  orderCode: '',
  location: '',
  startDate: '',
  endDate: '',
  priority: 'normal',
  notes: '',
})

const submitting = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const isEditing = computed<boolean>(() => {
  return props.order !== null
})

const title = computed<string>(() => {
  return isEditing.value
    ? 'Editar orden de producción'
    : 'Registrar orden de producción'
})

function today(): string {
  const currentDate = new Date()
  const offset = currentDate.getTimezoneOffset()

  const localDate = new Date(
    currentDate.getTime() - offset * 60_000,
  )

  return localDate.toISOString().slice(0, 10)
}

function resetForm(): void {
  form.orderCode = props.order?.order_code ?? ''
  form.location = props.order?.location ?? ''
  form.startDate = props.order?.start_date ?? today()
  form.endDate = props.order?.end_date ?? ''
  form.priority = props.order?.priority ?? 'normal'
  form.notes = props.order?.notes ?? ''

  formError.value = ''
  fieldErrors.value = {}
}

function normalizeOrderCode(): void {
  form.orderCode = form.orderCode.toUpperCase()
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

  if (!isEditing.value) {
    const orderCode = form.orderCode.trim()

    if (!orderCode) {
      setLocalError(
        'order_code',
        'Ingresa el folio de la orden.',
      )
    } else if (
      !/^[A-Z0-9._-]{3,50}$/.test(orderCode)
    ) {
      setLocalError(
        'order_code',
        'Usa entre 3 y 50 caracteres: mayúsculas, números, puntos, guiones o guiones bajos.',
      )
    }
  }

  if (form.location.trim().length > 150) {
    setLocalError(
      'location',
      'La ubicación no puede superar 150 caracteres.',
    )
  }

  if (!form.startDate) {
    setLocalError(
      'start_date',
      'Selecciona la fecha de inicio.',
    )
  }

  if (
    form.startDate &&
    form.endDate &&
    form.endDate < form.startDate
  ) {
    setLocalError(
      'end_date',
      'La fecha final debe ser igual o posterior a la fecha de inicio.',
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
    if (props.order) {
      const payload: UpdateProductionOrderPayload = {
        location: form.location.trim() || null,
        start_date: form.startDate,
        end_date: form.endDate || null,
        priority: form.priority,
        notes: form.notes.trim() || null,
      }

      const response =
        await productionOrdersService.update(
          props.order.id,
          payload,
        )

      emit('saved', response.data, response.message)
      return
    }

    const payload: CreateProductionOrderPayload = {
      order_code: form.orderCode.trim(),
      location: form.location.trim() || null,
      start_date: form.startDate,
      end_date: form.endDate || null,
      priority: form.priority,
      notes: form.notes.trim() || null,
    }

    const response =
      await productionOrdersService.create(payload)

    emit('saved', response.data, response.message)
  } catch (error) {
    fieldErrors.value = getValidationErrors(error)

    formError.value = getApiErrorMessage(
      error,
      'No fue posible guardar la orden de producción.',
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
    document.body.style.overflow = open ? 'hidden' : ''

    if (open) {
      resetForm()
    }
  },
)

watch(
  () => props.order,
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
      class="fixed inset-0 z-[110] flex items-stretch sm:items-center justify-center bg-slate-950/60 backdrop-blur-xs sm:p-6"
      @click.self="requestClose"
    >
      <section
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:w-[min(100%,48rem)] overflow-hidden bg-white sm:rounded-xl sm:shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="order-dialog-title"
      >
        <header class="flex items-center justify-between gap-4 p-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 border-b border-slate-200">
          <div class="flex items-center gap-3">
            <div class="flex w-11 h-11 shrink-0 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-md">
              <ClipboardList
                :size="23"
                aria-hidden="true"
              />
            </div>

            <span class="grid">
              <small class="text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Planeación productiva</small>

              <h2 id="order-dialog-title" class="m-0 text-xl font-bold text-slate-900">
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
          class="overflow-y-auto p-5 px-4 sm:px-6 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
          novalidate
          @submit.prevent="handleSubmit"
        >
          <div
            v-if="formError"
            class="mb-4 p-4 text-rose-700 bg-rose-50 border border-rose-200 rounded-md text-sm leading-relaxed"
            role="alert"
          >
            {{ formError }}
          </div>

          <div
            v-if="isEditing"
            class="flex items-center justify-between gap-4 mb-5 p-4 bg-brand-green-100/70 border border-brand-green-200/80 rounded-lg text-sm"
          >
            <span class="text-slate-600 font-medium">Estado actual</span>
            <strong class="font-bold text-brand-green-900">
              {{ order?.status_label ?? order?.status }}
            </strong>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="grid gap-1.5">
              <label for="order-code" class="text-slate-900 text-sm font-bold">
                Folio de orden
              </label>

              <input
                id="order-code"
                v-model="form.orderCode"
                type="text"
                maxlength="50"
                placeholder="Ej. OP-2026-001"
                autocapitalize="characters"
                spellcheck="false"
                :disabled="isEditing"
                class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed uppercase font-mono"
                :class="{
                  'border-rose-700!': firstFieldError('order_code'),
                }"
                @input="normalizeOrderCode"
              />

              <small v-if="firstFieldError('order_code')" class="text-rose-600 text-xs">
                {{ firstFieldError('order_code') }}
              </small>

              <small
                v-else-if="isEditing"
                class="text-slate-500 text-xs"
              >
                El folio no puede modificarse después del
                registro.
              </small>
            </div>

            <div class="grid gap-1.5">
              <label for="order-priority" class="text-slate-900 text-sm font-bold">
                Prioridad
              </label>

              <select
                id="order-priority"
                v-model="form.priority"
                class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
                :class="{
                  'border-rose-700!': firstFieldError('priority'),
                }"
              >
                <option value="low">Baja</option>
                <option value="normal">Normal</option>
                <option value="high">Alta</option>
                <option value="urgent">Urgente</option>
              </select>

              <small v-if="firstFieldError('priority')" class="text-rose-600 text-xs">
                {{ firstFieldError('priority') }}
              </small>
            </div>

            <div class="grid gap-1.5 sm:col-span-2">
              <label for="order-location" class="text-slate-900 text-sm font-bold">
                Ubicación o destino
              </label>

              <input
                id="order-location"
                v-model="form.location"
                type="text"
                maxlength="150"
                placeholder="Ej. Almacén principal"
                class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
                :class="{
                  'border-rose-700!': firstFieldError('location'),
                }"
              />

              <small v-if="firstFieldError('location')" class="text-rose-600 text-xs">
                {{ firstFieldError('location') }}
              </small>
            </div>

            <div class="grid gap-1.5">
              <label for="order-start-date" class="text-slate-900 text-sm font-bold">
                Fecha de inicio
              </label>

              <div class="flex items-center gap-2 pl-3 bg-white border border-slate-300 rounded-md focus-within:border-brand-green-700 focus-within:ring-3 focus-within:ring-brand-green-700/13">
                <CalendarDays
                  :size="19"
                  class="text-slate-400 shrink-0"
                  aria-hidden="true"
                />

                <input
                  id="order-start-date"
                  v-model="form.startDate"
                  type="date"
                  class="w-full min-h-[3rem] pr-3 text-slate-900 bg-transparent border-0 outline-hidden text-sm"
                  :class="{
                    'text-rose-600!': firstFieldError('start_date'),
                  }"
                />
              </div>

              <small v-if="firstFieldError('start_date')" class="text-rose-600 text-xs">
                {{ firstFieldError('start_date') }}
              </small>
            </div>

            <div class="grid gap-1.5">
              <label for="order-end-date" class="text-slate-900 text-sm font-bold">
                Fecha estimada de finalización
              </label>

              <div class="flex items-center gap-2 pl-3 bg-white border border-slate-300 rounded-md focus-within:border-brand-green-700 focus-within:ring-3 focus-within:ring-brand-green-700/13">
                <CalendarDays
                  :size="19"
                  class="text-slate-400 shrink-0"
                  aria-hidden="true"
                />

                <input
                  id="order-end-date"
                  v-model="form.endDate"
                  type="date"
                  :min="form.startDate || undefined"
                  class="w-full min-h-[3rem] pr-3 text-slate-900 bg-transparent border-0 outline-hidden text-sm"
                  :class="{
                    'text-rose-600!': firstFieldError('end_date'),
                  }"
                />
              </div>

              <small v-if="firstFieldError('end_date')" class="text-rose-600 text-xs">
                {{ firstFieldError('end_date') }}
              </small>
            </div>

            <div class="grid gap-1.5 sm:col-span-2">
              <label for="order-notes" class="text-slate-900 text-sm font-bold">
                Notas
              </label>

              <textarea
                id="order-notes"
                v-model="form.notes"
                rows="5"
                maxlength="3000"
                placeholder="Indicaciones, cliente, condiciones o información adicional"
                class="w-full min-h-[8rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 leading-relaxed resize-y"
                :class="{
                  'border-rose-700!': firstFieldError('notes'),
                }"
              />

              <div class="flex justify-between items-center gap-3">
                <small v-if="firstFieldError('notes')" class="text-rose-600 text-xs">
                  {{ firstFieldError('notes') }}
                </small>

                <span class="ml-auto text-slate-400 text-xs">
                  {{ form.notes.length }}/3000
                </span>
              </div>
            </div>
          </div>

          <footer class="grid grid-cols-1 sm:flex sm:justify-end gap-3 mt-6 pt-5 border-t border-slate-200">
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
              class="inline-flex min-h-[3rem] sm:w-44 items-center justify-center gap-2 px-5 text-white bg-brand-orange-800 hover:bg-brand-orange-900 rounded-md font-[750] text-sm cursor-pointer transition-colors border-0 disabled:opacity-50"
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
                    : 'Registrar orden'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>