<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  ref,
  watch,
} from 'vue'

import {
  AlertTriangle,
  ArrowLeftRight,
  LoaderCircle,
  Route,
  X,
} from 'lucide-vue-next'

import { processesService } from '@/modules/processes/services/processes.service'
import { productionIncidentsService } from '@/modules/production-incidents/services/production-incidents.service'

import {
  getApiErrorMessage,
  getValidationErrors,
} from '@/utils/api-error'

import type {
  OperationProcess,
  ProductionProcess,
} from '@/modules/processes/types/process.types'

import type { ProductionIncident } from '@/modules/production-incidents/types/production-incident.types'
import type { ProductionMovement } from '@/modules/production-movements/types/production-movement.types'

interface ProcessGroup {
  id: number
  name: string
  flowOrder: number
  operations: OperationProcess[]
}

interface SelectedOperation {
  processId: number
  processName: string
  operation: OperationProcess
}

const props = defineProps<{
  open: boolean
  incident: ProductionIncident | null
}>()

const emit = defineEmits<{
  close: []
  created: [
    movement: ProductionMovement,
    message: string,
  ]
}>()

const processes = ref<ProductionProcess[]>([])

const operationProcessId = ref<number | ''>('')
const notes = ref('')

const loading = ref(false)
const submitting = ref(false)

const formError = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const currentProcessFlow = computed<number | null>(() => {
  return (
    props.incident?.production_movement
      ?.process?.flow_order ?? null
  )
})

const currentOperationId = computed<number | null>(() => {
  return (
    props.incident?.production_movement
      ?.operation_process?.id ?? null
  )
})

const currentAreaName = computed<string>(() => {
  return (
    props.incident?.production_movement
      ?.to_area?.name ??
    props.incident?.production_movement
      ?.from_area?.name ??
    'Área actual'
  )
})

const currentOperationName = computed<string>(() => {
  return (
    props.incident?.production_movement
      ?.operation_process?.name ??
    props.incident?.production_movement
      ?.process?.name ??
    'Operación actual'
  )
})

const availableProcessGroups = computed<ProcessGroup[]>(() => {
  const currentFlow = currentProcessFlow.value
  const currentOperation = currentOperationId.value

  return processes.value
    .filter((process) => {
      /*
       * Cuando el movimiento informa el orden de flujo,
       * únicamente se ofrecen procesos anteriores.
       */
      if (currentFlow !== null) {
        return process.flow_order < currentFlow
      }

      return true
    })
    .map((process) => {
      const operations = process.operations
        .filter(
          (operation) =>
            operation.id !== currentOperation,
        )
        .slice()
        .sort(
          (first, second) =>
            second.flow_order - first.flow_order,
        )

      return {
        id: process.id,
        name: process.name,
        flowOrder: process.flow_order,
        operations,
      }
    })
    .filter((process) => process.operations.length > 0)
    .sort(
      (first, second) =>
        second.flowOrder - first.flowOrder,
    )
})

const selectedOperation =
  computed<SelectedOperation | null>(() => {
    if (!operationProcessId.value) {
      return null
    }

    for (const process of availableProcessGroups.value) {
      const operation = process.operations.find(
        (item) =>
          item.id === operationProcessId.value,
      )

      if (operation) {
        return {
          processId: process.id,
          processName: process.name,
          operation,
        }
      }
    }

    return null
  })

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
  processes.value = []
  operationProcessId.value = ''
  notes.value = ''

  formError.value = ''
  fieldErrors.value = {}
}

function selectRecommendedOperation(): void {
  const nearestPreviousProcess =
    availableProcessGroups.value.at(0)

  const recommendedOperation =
    nearestPreviousProcess?.operations.at(0)

  operationProcessId.value =
    recommendedOperation?.id ?? ''
}

async function loadProcesses(): Promise<void> {
  loading.value = true
  formError.value = ''

  try {
    processes.value = await processesService.list()

    selectRecommendedOperation()
  } catch (error) {
    formError.value = getApiErrorMessage(
      error,
      'No fue posible cargar los procesos disponibles.',
    )
  } finally {
    loading.value = false
  }
}

function validateForm(): boolean {
  fieldErrors.value = {}
  formError.value = ''

  if (!operationProcessId.value) {
    setLocalError(
      'operation_process_id',
      'Selecciona la operación a la que regresarán las piezas.',
    )
  }

  const normalizedNotes = notes.value.trim()

  if (!normalizedNotes) {
    setLocalError(
      'notes',
      'Describe las instrucciones del reproceso.',
    )
  } else if (normalizedNotes.length < 5) {
    setLocalError(
      'notes',
      'Las instrucciones deben contener al menos 5 caracteres.',
    )
  } else if (normalizedNotes.length > 3000) {
    setLocalError(
      'notes',
      'Las instrucciones no pueden superar 3000 caracteres.',
    )
  }

  return Object.keys(fieldErrors.value).length === 0
}

async function handleSubmit(): Promise<void> {
  if (
    !props.incident ||
    !operationProcessId.value ||
    !validateForm()
  ) {
    return
  }

  submitting.value = true
  formError.value = ''

  try {
    const response =
      await productionIncidentsService.returnForRework(
        props.incident.id,
        {
          operation_process_id:
            operationProcessId.value,

          notes: notes.value.trim(),
        },
      )

    emit(
      'created',
      response.data,
      response.message,
    )
  } catch (error) {
    fieldErrors.value = getValidationErrors(error)

    formError.value = getApiErrorMessage(
      error,
      'No fue posible generar el movimiento de reproceso.',
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
      void loadProcesses()
    } else {
      resetForm()
    }
  },
)

watch(
  () => props.incident?.id,
  () => {
    if (props.open) {
      resetForm()
      void loadProcesses()
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
      class="fixed inset-0 z-[130] flex items-stretch sm:items-center justify-center bg-slate-950/68 backdrop-blur-xs sm:p-6"
      @click.self="requestClose"
    >
      <section
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:w-[min(100%,50rem)] overflow-hidden bg-white sm:rounded-xl sm:shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="rework-dialog-title"
      >
        <header class="flex items-center justify-between gap-3 p-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 border-b border-slate-200">
          <div class="flex items-center gap-3 min-w-0">
            <div class="flex w-11 h-11 shrink-0 items-center justify-center text-brand-orange-900 bg-brand-orange-100 rounded-md">
              <ArrowLeftRight
                :size="23"
                aria-hidden="true"
              />
            </div>

            <span class="grid min-w-0">
              <small class="text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Control de calidad</small>

              <h2 id="rework-dialog-title" class="m-0 text-xl font-bold text-slate-900 truncate">
                Generar reproceso
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

          <section class="grid grid-cols-1 sm:grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 p-4 bg-rose-50/70 border border-rose-100 rounded-xl">
            <div class="flex w-11 h-11 shrink-0 items-center justify-center text-rose-700 bg-white rounded-md shadow-xs">
              <AlertTriangle
                :size="22"
                aria-hidden="true"
              />
            </div>

            <span class="grid min-w-0">
              <small class="text-brand-orange-800 font-extrabold text-xs">
                {{
                  incident.garment_cut?.code ??
                  `Incidencia #${incident.id}`
                }}
              </small>

              <strong class="text-slate-900 text-sm font-bold">
                {{ incident.incident_type_label }}
              </strong>

              <p class="m-0 mt-1 text-slate-600 text-xs leading-relaxed line-clamp-2">{{ incident.description }}</p>
            </span>

            <em class="inline-flex items-center px-3 py-1 text-rose-700 bg-white rounded-full text-xs font-bold not-italic self-start sm:self-center">
              {{ incident.quantity_affected }}
              piezas
            </em>
          </section>

          <aside class="flex items-start gap-3 p-4 text-amber-800 bg-amber-50 border border-amber-200/80 rounded-xl text-xs leading-relaxed">
            <AlertTriangle
              :size="20"
              class="shrink-0 text-amber-600"
              aria-hidden="true"
            />

            <p class="m-0">
              Al confirmar, el movimiento donde ocurrió la
              incidencia será cancelado y se creará un nuevo
              movimiento pendiente de recepción para el
              reproceso.
            </p>
          </aside>

          <section class="grid gap-4">
            <header>
              <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Ruta de retorno</p>
              <h3 class="m-0 text-base font-bold text-slate-900">Operación de corrección</h3>
            </header>

            <div
              v-if="loading"
              class="flex items-center gap-2 p-4 text-slate-600 bg-slate-50 border border-slate-200 rounded-md text-sm"
            >
              <LoaderCircle
                :size="21"
                class="animate-spin text-brand-green-700"
                aria-hidden="true"
              />

              Consultando operaciones anteriores...
            </div>

            <template v-else>
              <div class="grid gap-1.5">
                <label for="rework-operation" class="text-slate-900 text-sm font-bold">
                  Operación destino
                </label>

                <select
                  id="rework-operation"
                  v-model="operationProcessId"
                  class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
                  :class="{
                    'border-rose-700!': firstFieldError('operation_process_id'),
                  }"
                >
                  <option value="">
                    Selecciona una operación
                  </option>

                  <optgroup
                    v-for="
                      process in availableProcessGroups
                    "
                    :key="process.id"
                    :label="process.name"
                  >
                    <option
                      v-for="
                        operation in process.operations
                      "
                      :key="operation.id"
                      :value="operation.id"
                    >
                      {{ operation.name }}
                    </option>
                  </optgroup>
                </select>

                <small v-if="firstFieldError('operation_process_id')" class="text-rose-600 text-xs">
                  {{ firstFieldError('operation_process_id') }}
                </small>

                <small
                  v-else
                  class="text-slate-500 text-xs"
                >
                  Selecciona una suboperación perteneciente a
                  un proceso anterior del flujo productivo.
                </small>
              </div>

              <div
                v-if="
                  availableProcessGroups.length === 0
                "
                class="p-5 text-center text-slate-500 text-sm bg-slate-50 border border-slate-200 rounded-lg"
              >
                No se encontraron operaciones anteriores
                disponibles para este movimiento.
              </div>

              <div
                v-if="selectedOperation"
                class="grid grid-cols-1 sm:grid-cols-[1fr_auto_1fr] items-center gap-3 p-4 bg-gradient-to-r from-brand-orange-50/60 to-brand-green-50/60 border border-slate-200 rounded-xl"
              >
                <span class="grid p-3 bg-white border border-slate-200 rounded-md">
                  <small class="text-slate-400 text-xs uppercase font-bold">Origen</small>
                  <strong class="text-slate-900 text-sm font-bold">{{ currentAreaName }}</strong>
                  <em class="text-slate-500 text-xs not-italic">{{ currentOperationName }}</em>
                </span>

                <Route
                  :size="24"
                  class="rotate-90 sm:rotate-0 text-brand-orange-800 justify-self-center shrink-0"
                  aria-hidden="true"
                />

                <span class="grid p-3 bg-white border border-slate-200 rounded-md">
                  <small class="text-slate-400 text-xs uppercase font-bold">Destino del reproceso</small>
                  <strong class="text-slate-900 text-sm font-bold">
                    {{ selectedOperation.processName }}
                  </strong>
                  <em class="text-slate-500 text-xs not-italic">
                    {{
                      selectedOperation.operation.name
                    }}
                  </em>
                </span>
              </div>
            </template>
          </section>

          <section class="grid gap-4 pt-5 border-t border-slate-200">
            <header>
              <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Indicaciones</p>
              <h3 class="m-0 text-base font-bold text-slate-900">Instrucciones de corrección</h3>
            </header>

            <div class="grid gap-1.5">
              <label for="rework-notes" class="text-slate-900 text-sm font-bold">
                Notas del reproceso
              </label>

              <textarea
                id="rework-notes"
                v-model="notes"
                rows="6"
                minlength="5"
                maxlength="3000"
                placeholder="Ej. Regresar las piezas a Bordado para corregir la posición del logotipo y verificar la calidad antes de reenviarlas."
                class="w-full p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 leading-relaxed resize-y"
                :class="{
                  'border-rose-700!': firstFieldError('notes'),
                }"
              />

              <div class="flex justify-between items-center gap-3">
                <small v-if="firstFieldError('notes')" class="text-rose-600 text-xs">
                  {{ firstFieldError('notes') }}
                </small>

                <span class="ml-auto text-slate-400 text-xs">
                  {{ notes.length }}/3000
                </span>
              </div>
            </div>
          </section>

          <footer class="grid grid-cols-1 sm:flex sm:justify-end gap-3 pt-5 border-t border-slate-200">
            <button
              type="button"
              class="inline-flex min-h-[3rem] sm:w-44 items-center justify-center px-4 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-[750] text-sm cursor-pointer transition-colors disabled:opacity-50"
              :disabled="submitting"
              @click="requestClose"
            >
              Cancelar
            </button>

            <button
              type="submit"
              class="inline-flex min-h-[3rem] sm:w-48 items-center justify-center gap-2 px-5 text-white bg-brand-orange-800 hover:bg-brand-orange-900 rounded-md font-[750] text-sm cursor-pointer transition-colors border-0 disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="
                submitting ||
                loading ||
                availableProcessGroups.length === 0
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
                  ? 'Generando...'
                  : 'Generar reproceso'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>