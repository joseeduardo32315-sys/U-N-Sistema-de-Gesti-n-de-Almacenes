<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  ref,
  watch,
} from 'vue'

import {
  ArrowRight,
  LoaderCircle,
  PackageOpen,
  Route,
  Scissors,
  X,
} from 'lucide-vue-next'

import { garmentCutsService } from '@/modules/garment-cuts/services/garment-cuts.service'
import { productionMovementsService } from '@/modules/production-movements/services/production-movements.service'
import {
  getApiErrorMessage,
  getValidationErrors,
} from '@/utils/api-error'

import type {
  GarmentCut,
  GarmentCutComplement,
  GarmentCutSpecialPiece,
} from '@/modules/garment-cuts/types/garment-cut.types'

import type { ProductionProcess } from '@/modules/processes/types/process.types'

import type {
  CreateProductionMovementPayload,
  ProductionMovement,
  ProductionMovementTargetType,
} from '@/modules/production-movements/types/production-movement.types'

interface TargetOption {
  key: string
  type: ProductionMovementTargetType
  id: number | null
  label: string
  description: string
  currentArea: string
  configuredProcessId: number | null
}

const props = defineProps<{
  open: boolean
  cuts: GarmentCut[]
  processes: ProductionProcess[]
  initialCutId?: number | null
  initialTargetKey?: string | null
}>()

const emit = defineEmits<{
  close: []
  saved: [
    movement: ProductionMovement,
    message: string,
  ]
}>()

const garmentCutId = ref<number | ''>('')
const selectedCut = ref<GarmentCut | null>(null)
const targetKey = ref('')

const processId = ref<number | ''>('')
const operationProcessId = ref<number | ''>('')

const quantity = ref<number | null>(null)
const notes = ref('')

const cutLoading = ref(false)
const submitting = ref(false)

const formError = ref('')
const fieldErrors =
  ref<Record<string, string[]>>({})

function normalizeName(value: string): string {
  return value
    .trim()
    .toLocaleLowerCase('es')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
}

const targetOptions = computed<TargetOption[]>(() => {
  const cut = selectedCut.value

  if (!cut) {
    return []
  }

  const options: TargetOption[] = []

  const currentArea =
    cut.current_area?.name ?? 'Sin área'

  const normalizedArea = normalizeName(currentArea)

  /*
   * Antes de clasificarse, el corte completo debe viajar
   * desde Corte hacia Diseño.
   */
  if (
    normalizedArea === 'corte' ||
    cut.status === 'registered'
  ) {
    options.push({
      key: 'cut',
      type: 'cut',
      id: null,
      label: 'Corte completo',
      description:
        'Movimiento inicial del lote completo.',
      currentArea,
      configuredProcessId:
        findProcessByName('Diseño')?.id ?? null,
    })
  }

  if (cut.complement) {
    options.push(
      complementToTargetOption(
        cut.complement,
        cut,
      ),
    )
  }

  for (
    const specialPiece of
    cut.special_process_pieces ?? []
  ) {
    options.push(
      specialPieceToTargetOption(specialPiece),
    )
  }

  /*
   * Protección para cortes antiguos o respuestas parciales.
   */
  if (options.length === 0) {
    options.push({
      key: 'cut',
      type: 'cut',
      id: null,
      label: 'Corte completo',
      description: 'Lote completo de producción.',
      currentArea,
      configuredProcessId: null,
    })
  }

  return options
})

const selectedTarget = computed<
  TargetOption | undefined
>(() => {
  return targetOptions.value.find(
    (target) => target.key === targetKey.value,
  )
})

const selectedProcess = computed<
  ProductionProcess | undefined
>(() => {
  return props.processes.find(
    (process) => process.id === processId.value,
  )
})

const availableOperations = computed(() => {
  return selectedProcess.value?.operations ?? []
})

function findProcessByName(
  name: string,
): ProductionProcess | undefined {
  const normalized = normalizeName(name)

  return props.processes.find(
    (process) =>
      normalizeName(process.name) === normalized,
  )
}

function complementToTargetOption(
  complement: GarmentCutComplement,
  cut: GarmentCut,
): TargetOption {
  return {
    key: `complement:${complement.id}`,
    type: 'complement',
    id: complement.id,
    label: 'Complemento',
    description:
      complement.notes ||
      'Piezas sin proceso especial.',
    currentArea:
      complement.current_area?.name ??
      cut.current_area?.name ??
      'Sin área',
    configuredProcessId:
      findProcessByName('Maquila')?.id ?? null,
  }
}

function specialPieceToTargetOption(
  piece: GarmentCutSpecialPiece,
): TargetOption {
  const pieceName =
    piece.piece_type?.name ?? 'Pieza especial'

  return {
    key: `special_piece:${piece.id}`,
    type: 'special_piece',
    id: piece.id,
    label: pieceName,
    description:
      piece.notes ||
      `Pieza dirigida a ${
        piece.process?.name ?? 'proceso especial'
      }.`,
    currentArea:
      piece.current_area?.name ?? 'Sin área',
    configuredProcessId:
      piece.process?.id ?? null,
  }
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
  garmentCutId.value = ''
  selectedCut.value = null
  targetKey.value = ''

  processId.value = ''
  operationProcessId.value = ''

  quantity.value = null
  notes.value = ''

  formError.value = ''
  fieldErrors.value = {}
}

async function loadSelectedCut(): Promise<void> {
  selectedCut.value = null
  targetKey.value = ''
  processId.value = ''
  operationProcessId.value = ''
  quantity.value = null

  if (!garmentCutId.value) {
    return
  }

  cutLoading.value = true
  formError.value = ''

  try {
    const cut = await garmentCutsService.show(
      garmentCutId.value,
    )

    selectedCut.value = cut
    quantity.value = cut.total_pieces

    const targetToUse = props.initialTargetKey
      ? targetOptions.value.find((t) => t.key === props.initialTargetKey)
      : targetOptions.value.at(0)

    if (targetToUse) {
      targetKey.value = targetToUse.key
      applyTargetDefaults(targetToUse)
    }
  } catch (error) {
    formError.value = getApiErrorMessage(
      error,
      'No fue posible consultar el corte seleccionado.',
    )
  } finally {
    cutLoading.value = false
  }
}

function applyTargetDefaults(
  target: TargetOption,
): void {
  const defaultProcessId =
    target.configuredProcessId

  processId.value =
    defaultProcessId &&
    props.processes.some(
      (process) => process.id === defaultProcessId,
    )
      ? defaultProcessId
      : ''

  selectFirstOperation()
}

function handleTargetChange(): void {
  const target = selectedTarget.value

  if (!target) {
    processId.value = ''
    operationProcessId.value = ''
    return
  }

  applyTargetDefaults(target)
}

function selectFirstOperation(): void {
  const firstOperation =
    selectedProcess.value?.operations.at(0)

  operationProcessId.value =
    firstOperation?.id ?? ''
}

function validateForm(): boolean {
  fieldErrors.value = {}
  formError.value = ''

  if (!garmentCutId.value) {
    setLocalError(
      'garment_cut_id',
      'Selecciona un corte.',
    )
  }

  if (!selectedTarget.value) {
    setLocalError(
      'target_type',
      'Selecciona el lote que deseas mover.',
    )
  }

  if (!processId.value) {
    setLocalError(
      'process_id',
      'Selecciona el proceso de destino.',
    )
  }

  if (!operationProcessId.value) {
    setLocalError(
      'operation_process_id',
      'Selecciona la operación de destino.',
    )
  }

  const parsedQuantity = Number(quantity.value)

  if (
    !Number.isInteger(parsedQuantity) ||
    parsedQuantity < 1 ||
    parsedQuantity > 1_000_000
  ) {
    setLocalError(
      'quantity',
      'Ingresa una cantidad entre 1 y 1,000,000.',
    )
  }

  if (notes.value.length > 3000) {
    setLocalError(
      'notes',
      'Las notas no pueden superar 3000 caracteres.',
    )
  }

  return Object.keys(fieldErrors.value).length === 0
}

function buildPayload():
  CreateProductionMovementPayload | null {
  const target = selectedTarget.value

  if (
    !target ||
    !garmentCutId.value ||
    !processId.value ||
    !operationProcessId.value ||
    !quantity.value
  ) {
    return null
  }

  const payload: CreateProductionMovementPayload = {
    garment_cut_id: garmentCutId.value,
    target_type: target.type,
    process_id: processId.value,
    operation_process_id:
      operationProcessId.value,
    quantity: Number(quantity.value),
    notes: notes.value.trim() || null,
  }

  if (
    target.type === 'special_piece' &&
    target.id
  ) {
    payload.special_process_piece_id = target.id
  }

  if (
    target.type === 'complement' &&
    target.id
  ) {
    payload.complement_id = target.id
  }

  return payload
}

async function handleSubmit(): Promise<void> {
  if (!validateForm()) {
    return
  }

  const payload = buildPayload()

  if (!payload) {
    return
  }

  submitting.value = true
  formError.value = ''

  try {
    const response =
      await productionMovementsService.create(
        payload,
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
      'No fue posible registrar el movimiento.',
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
      if (props.initialCutId) {
        garmentCutId.value = props.initialCutId
      }
    }
  },
)

watch(
  garmentCutId,
  () => {
    if (props.open) {
      void loadSelectedCut()
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
      class="fixed inset-0 z-[115] flex items-stretch sm:items-center justify-center bg-slate-950/62 backdrop-blur-xs sm:p-6"
      @click.self="requestClose"
    >
      <section
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:w-[min(100%,58rem)] overflow-hidden bg-white sm:rounded-xl sm:shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="movement-dialog-title"
      >
        <header class="flex items-center justify-between gap-4 p-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 border-b border-slate-200">
          <div class="flex items-center gap-3 min-w-0">
            <div class="flex w-11 h-11 shrink-0 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-md">
              <Route :size="23" aria-hidden="true" />
            </div>

            <span class="grid min-w-0">
              <small class="text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Flujo productivo</small>

              <h2 id="movement-dialog-title" class="m-0 text-xl font-bold text-slate-900 truncate">
                Registrar despacho
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
          class="grid gap-6 overflow-y-auto p-5 px-4 sm:px-6 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
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

          <section class="grid gap-4">
            <header>
              <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Origen del movimiento</p>
              <h3 class="m-0 text-base font-bold text-slate-900">Seleccionar corte y lote</h3>
            </header>

            <div class="grid gap-1.5">
              <label for="movement-cut" class="text-slate-900 text-sm font-bold">
                Corte de producción
              </label>

              <select
                id="movement-cut"
                v-model="garmentCutId"
                :disabled="cutLoading"
                class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-500"
                :class="{
                  'border-rose-700!': firstFieldError('garment_cut_id'),
                }"
              >
                <option value="">
                  Selecciona un corte
                </option>

                <option
                  v-for="cut in cuts"
                  :key="cut.id"
                  :value="cut.id"
                >
                  {{ cut.code }}
                  ·
                  {{
                    cut.garment_model?.code ??
                    'Sin modelo'
                  }}
                  ·
                  {{
                    cut.current_area?.name ??
                    'Sin área'
                  }}
                </option>
              </select>

              <small
                v-if="firstFieldError('garment_cut_id')"
                class="text-rose-600 text-xs"
              >
                {{ firstFieldError('garment_cut_id') }}
              </small>
            </div>

            <div
              v-if="cutLoading"
              class="flex items-center gap-2 text-slate-600 text-sm"
            >
              <LoaderCircle
                :size="20"
                class="animate-spin text-brand-green-700"
                aria-hidden="true"
              />

              Consultando corte...
            </div>

            <div
              v-else-if="selectedCut"
              class="grid grid-cols-[auto_minmax(0,1fr)] gap-3 p-4 bg-brand-green-50/70 border border-brand-green-200/80 rounded-xl"
            >
              <div class="flex w-11 h-11 shrink-0 items-center justify-center text-brand-green-800 bg-white rounded-md shadow-xs">
                <Scissors
                  :size="22"
                  aria-hidden="true"
                />
              </div>

              <span class="grid min-w-0">
                <strong class="text-slate-900 font-mono font-bold text-sm truncate">{{ selectedCut.code }}</strong>

                <small class="text-slate-500 text-xs truncate">
                  {{
                    selectedCut.garment_model
                      ? `${selectedCut.garment_model.code} · ${selectedCut.garment_model.name}`
                      : 'Sin modelo'
                  }}
                </small>
              </span>

              <dl class="grid col-span-full grid-cols-2 gap-3 m-0 pt-2 border-t border-brand-green-200/50 text-xs">
                <div>
                  <dt class="text-slate-500">Área actual</dt>
                  <dd class="m-0 mt-0.5 font-bold text-slate-800 truncate">
                    {{
                      selectedCut.current_area?.name ??
                      'Sin área'
                    }}
                  </dd>
                </div>

                <div>
                  <dt class="text-slate-500">Piezas planeadas</dt>
                  <dd class="m-0 mt-0.5 font-bold font-mono text-slate-900">{{ selectedCut.total_pieces }}</dd>
                </div>
              </dl>
            </div>

            <div
              v-if="selectedCut"
              class="grid grid-cols-1 sm:grid-cols-2 gap-3"
            >
              <label
                v-for="target in targetOptions"
                :key="target.key"
                class="relative grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3 p-4 bg-white border rounded-xl cursor-pointer transition-colors"
                :class="targetKey === target.key ? 'bg-brand-green-50/60 border-brand-green-700' : 'border-slate-200 hover:border-slate-300'"
              >
                <input
                  v-model="targetKey"
                  type="radio"
                  name="movement-target"
                  :value="target.key"
                  class="sr-only"
                  @change="handleTargetChange"
                />

                <div
                  class="flex w-11 h-11 shrink-0 items-center justify-center rounded-md transition-colors"
                  :class="targetKey === target.key ? 'text-brand-green-800 bg-brand-green-200' : 'text-slate-400 bg-slate-100'"
                >
                  <PackageOpen
                    :size="20"
                    aria-hidden="true"
                  />
                </div>

                <span class="grid min-w-0">
                  <strong class="text-slate-900 text-sm font-bold truncate">{{ target.label }}</strong>
                  <small class="text-slate-500 text-xs truncate">
                    {{ target.description }}
                  </small>
                  <em class="text-slate-400 text-xs not-italic mt-0.5 truncate">
                    Ubicación:
                    {{ target.currentArea }}
                  </em>
                </span>
              </label>

              <small
                v-if="firstFieldError('target_type')"
                class="text-rose-600 text-xs col-span-full"
              >
                {{ firstFieldError('target_type') }}
              </small>
            </div>
          </section>

          <section class="grid gap-4 pt-5 border-t border-slate-200">
            <header>
              <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Destino</p>
              <h3 class="m-0 text-base font-bold text-slate-900">Proceso y operación</h3>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="grid gap-1.5">
                <label for="movement-process" class="text-slate-900 text-sm font-bold">
                  Proceso destino
                </label>

                <select
                  id="movement-process"
                  v-model="processId"
                  :disabled="!selectedTarget"
                  class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-500"
                  :class="{
                    'border-rose-700!': firstFieldError('process_id'),
                  }"
                  @change="selectFirstOperation"
                >
                  <option value="">
                    Selecciona un proceso
                  </option>

                  <option
                    v-for="process in processes"
                    :key="process.id"
                    :value="process.id"
                  >
                    {{ process.name }}
                  </option>
                </select>

                <small
                  v-if="firstFieldError('process_id')"
                  class="text-rose-600 text-xs"
                >
                  {{ firstFieldError('process_id') }}
                </small>
              </div>

              <div class="grid gap-1.5">
                <label for="movement-operation" class="text-slate-900 text-sm font-bold">
                  Operación destino
                </label>

                <select
                  id="movement-operation"
                  v-model="operationProcessId"
                  :disabled="!processId"
                  class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-500"
                  :class="{
                    'border-rose-700!': firstFieldError('operation_process_id'),
                  }"
                >
                  <option value="">
                    Selecciona una operación
                  </option>

                  <option
                    v-for="
                      operation in availableOperations
                    "
                    :key="operation.id"
                    :value="operation.id"
                  >
                    {{ operation.name }}
                  </option>
                </select>

                <small
                  v-if="firstFieldError('operation_process_id')"
                  class="text-rose-600 text-xs"
                >
                  {{ firstFieldError('operation_process_id') }}
                </small>
              </div>
            </div>

            <div
              v-if="
                selectedTarget &&
                selectedProcess &&
                operationProcessId
              "
              class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-800"
            >
              <span class="truncate text-center">
                {{ selectedTarget.currentArea }}
              </span>

              <ArrowRight
                :size="21"
                class="text-brand-orange-800 shrink-0"
                aria-hidden="true"
              />

              <span class="truncate text-center">
                {{ selectedProcess.name }}
                ·
                {{
                  availableOperations.find(
                    (operation) =>
                      operation.id ===
                      operationProcessId,
                  )?.name
                }}
              </span>
            </div>
          </section>

          <section class="grid gap-4 pt-5 border-t border-slate-200">
            <header>
              <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Cantidad</p>
              <h3 class="m-0 text-base font-bold text-slate-900">Piezas a despachar</h3>
            </header>

            <div class="grid gap-1.5">
              <label for="movement-quantity" class="text-slate-900 text-sm font-bold">
                Cantidad enviada
              </label>

              <input
                id="movement-quantity"
                v-model.number="quantity"
                type="number"
                min="1"
                max="1000000"
                step="1"
                class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
                :class="{
                  'border-rose-700!': firstFieldError('quantity'),
                }"
              />

              <small
                v-if="firstFieldError('quantity')"
                class="text-rose-600 text-xs"
              >
                {{ firstFieldError('quantity') }}
              </small>

              <small
                v-else
                class="text-slate-500 text-xs"
              >
                El backend validará la cantidad efectiva
                disponible considerando pérdidas resueltas.
              </small>
            </div>

            <div class="grid gap-1.5">
              <label for="movement-notes" class="text-slate-900 text-sm font-bold">
                Notas del despacho
              </label>

              <textarea
                id="movement-notes"
                v-model="notes"
                rows="4"
                maxlength="3000"
                placeholder="Instrucciones, condiciones de entrega u observaciones"
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

                <span class="ml-auto text-slate-400 text-xs">{{ notes.length }}/3000</span>
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
                cutLoading ||
                !selectedCut
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
                  ? 'Registrando...'
                  : 'Registrar despacho'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>