<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  ref,
  watch,
} from 'vue'

import {
  Calculator,
  Check,
  LoaderCircle,
  Scissors,
  X,
} from 'lucide-vue-next'

import { garmentCutsService } from '@/modules/garment-cuts/services/garment-cuts.service'
import {
  getApiErrorMessage,
  getValidationErrors,
} from '@/utils/api-error'

import type { GarmentModel } from '@/modules/garment-models/types/garment-model.types'
import type {
  CreateGarmentCutPayload,
  GarmentCut,
  GarmentCutSizePayload,
  UpdateGarmentCutPayload,
} from '@/modules/garment-cuts/types/garment-cut.types'
import type { ProductionOrder } from '@/modules/production-orders/types/production-order.types'
import type { Size } from '@/modules/sizes/types/size.types'

type DistributionMode = 'uniform' | 'custom'

interface SizeRow {
  sizeId: number
  name: string
  selected: boolean
  totalPieces: number | null
}

const props = defineProps<{
  open: boolean
  cut: GarmentCut | null
  orders: ProductionOrder[]
  models: GarmentModel[]
  sizes: Size[]
}>()

const emit = defineEmits<{
  close: []
  saved: [cut: GarmentCut, message: string]
}>()

const productionOrderId = ref<number | ''>('')
const garmentModelId = ref<number | ''>('')
const code = ref('')
const description = ref('')
const notes = ref('')

const distributionMode =
  ref<DistributionMode>('uniform')

const uniformPieces = ref<number | null>(null)
const sizeRows = ref<SizeRow[]>([])

const submitting = ref(false)
const formError = ref('')
const fieldErrors =
  ref<Record<string, string[]>>({})

const isEditing = computed<boolean>(() => {
  return props.cut !== null
})

const title = computed<string>(() => {
  return isEditing.value
    ? 'Editar corte de producción'
    : 'Registrar corte de producción'
})

const availableOrders = computed<ProductionOrder[]>(() => {
  return props.orders.filter((order) => {
    return ![
      'completed',
      'cancelled',
    ].includes(order.status)
  })
})

const availableModels = computed<GarmentModel[]>(() => {
  return props.models.filter(
    (model) => model.status === 'active',
  )
})

const selectedRows = computed<SizeRow[]>(() => {
  return sizeRows.value.filter((row) => row.selected)
})

const selectedSizesCount = computed<number>(() => {
  return selectedRows.value.length
})

const totalPieces = computed<number>(() => {
  if (distributionMode.value === 'uniform') {
    const pieces = uniformPieces.value ?? 0

    return selectedSizesCount.value * pieces
  }

  return selectedRows.value.reduce(
    (total, row) => total + (row.totalPieces ?? 0),
    0,
  )
})

function initializeSizeRows(): void {
  const existingSizes = new Map(
    (props.cut?.sizes ?? []).map((item) => [
      item.size.id,
      item.total_pieces,
    ]),
  )

  sizeRows.value = props.sizes.map((size) => ({
    sizeId: size.id,
    name: size.name,
    selected: existingSizes.has(size.id),
    totalPieces:
      existingSizes.get(size.id) ?? null,
  }))
}

function detectDistributionMode(): void {
  const quantities = (props.cut?.sizes ?? []).map(
    (item) => item.total_pieces,
  )

  if (
    quantities.length > 0 &&
    quantities.every(
      (quantity) => quantity === quantities[0],
    )
  ) {
    distributionMode.value = 'uniform'
    uniformPieces.value = quantities[0] ?? null
    return
  }

  distributionMode.value = props.cut
    ? 'custom'
    : 'uniform'

  uniformPieces.value = null
}

function resetForm(): void {
  productionOrderId.value =
    props.cut?.production_order?.id ?? ''

  garmentModelId.value =
    props.cut?.garment_model?.id ?? ''

  code.value = props.cut?.code ?? ''
  description.value = props.cut?.description ?? ''
  notes.value = props.cut?.notes ?? ''

  formError.value = ''
  fieldErrors.value = {}

  initializeSizeRows()
  detectDistributionMode()
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

function normalizeCode(): void {
  code.value = code.value.toUpperCase()
}

function toggleSize(row: SizeRow): void {
  row.selected = !row.selected

  if (!row.selected) {
    row.totalPieces = null
  } else if (
    distributionMode.value === 'custom' &&
    row.totalPieces === null
  ) {
    row.totalPieces = 1
  }
}

function selectAllSizes(): void {
  const shouldSelectAll =
    selectedSizesCount.value !== sizeRows.value.length

  for (const row of sizeRows.value) {
    row.selected = shouldSelectAll

    if (
      shouldSelectAll &&
      distributionMode.value === 'custom' &&
      row.totalPieces === null
    ) {
      row.totalPieces = 1
    }

    if (!shouldSelectAll) {
      row.totalPieces = null
    }
  }
}

function changeDistributionMode(
  mode: DistributionMode,
): void {
  distributionMode.value = mode

  if (mode === 'custom') {
    for (const row of selectedRows.value) {
      row.totalPieces =
        row.totalPieces ??
        uniformPieces.value ??
        1
    }
  }
}

function buildSizesPayload(): GarmentCutSizePayload[] {
  return selectedRows.value.map((row) => ({
    size_id: row.sizeId,

    total_pieces:
      distributionMode.value === 'uniform'
        ? Number(uniformPieces.value)
        : Number(row.totalPieces),
  }))
}

function validateForm(): boolean {
  fieldErrors.value = {}
  formError.value = ''

  if (!isEditing.value) {
    if (!productionOrderId.value) {
      setLocalError(
        'production_order_id',
        'Selecciona una orden de producción.',
      )
    }

    if (!garmentModelId.value) {
      setLocalError(
        'garment_model_id',
        'Selecciona un modelo de prenda.',
      )
    }

    const normalizedCode = code.value.trim()

    if (!normalizedCode) {
      setLocalError(
        'code',
        'Ingresa el folio del corte.',
      )
    } else if (
      !/^[A-Z0-9._-]{3,50}$/.test(normalizedCode)
    ) {
      setLocalError(
        'code',
        'Usa entre 3 y 50 caracteres: mayúsculas, números, puntos, guiones o guiones bajos.',
      )
    }
  }

  if (description.value.length > 3000) {
    setLocalError(
      'description',
      'La descripción no puede superar 3000 caracteres.',
    )
  }

  if (notes.value.length > 3000) {
    setLocalError(
      'notes',
      'Las notas no pueden superar 3000 caracteres.',
    )
  }

  if (selectedSizesCount.value === 0) {
    setLocalError(
      'sizes',
      'Selecciona al menos una talla.',
    )
  }

  if (selectedSizesCount.value > 20) {
    setLocalError(
      'sizes',
      'No puedes seleccionar más de 20 tallas.',
    )
  }

  if (distributionMode.value === 'uniform') {
    const quantity = Number(uniformPieces.value)

    if (
      !Number.isInteger(quantity) ||
      quantity < 1 ||
      quantity > 1_000_000
    ) {
      setLocalError(
        'sizes',
        'Ingresa una cantidad uniforme entre 1 y 1,000,000.',
      )
    }
  } else {
    const invalidRow = selectedRows.value.find(
      (row) => {
        const quantity = Number(row.totalPieces)

        return (
          !Number.isInteger(quantity) ||
          quantity < 1 ||
          quantity > 1_000_000
        )
      },
    )

    if (invalidRow) {
      setLocalError(
        'sizes',
        `Ingresa una cantidad válida para la talla ${invalidRow.name}.`,
      )
    }
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
    if (props.cut) {
      const payload: UpdateGarmentCutPayload = {
        description:
          description.value.trim() || null,

        notes: notes.value.trim() || null,

        sizes: buildSizesPayload(),
      }

      const response =
        await garmentCutsService.update(
          props.cut.id,
          payload,
        )

      emit('saved', response.data, response.message)
      return
    }

    if (
      !productionOrderId.value ||
      !garmentModelId.value
    ) {
      return
    }

    const payload: CreateGarmentCutPayload = {
      production_order_id:
        productionOrderId.value,

      garment_model_id:
        garmentModelId.value,

      code: code.value.trim(),

      description:
        description.value.trim() || null,

      notes: notes.value.trim() || null,

      sizes: buildSizesPayload(),
    }

    const response =
      await garmentCutsService.create(payload)

    emit('saved', response.data, response.message)
  } catch (error) {
    fieldErrors.value = getValidationErrors(error)

    formError.value = getApiErrorMessage(
      error,
      'No fue posible guardar el corte.',
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
    document.body.style.overflow = open
      ? 'hidden'
      : ''

    if (open) {
      resetForm()
    }
  },
)

watch(
  () => props.cut,
  () => {
    if (props.open) {
      resetForm()
    }
  },
)

watch(
  () => props.sizes,
  () => {
    if (props.open) {
      initializeSizeRows()
      detectDistributionMode()
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
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:w-[min(100%,60rem)] overflow-hidden bg-white sm:rounded-xl sm:shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cut-dialog-title"
      >
        <header class="flex items-center justify-between gap-4 p-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 border-b border-slate-200">
          <div class="flex items-center gap-3">
            <div class="flex w-11 h-11 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-md shrink-0">
              <Scissors
                :size="23"
                aria-hidden="true"
              />
            </div>

            <span class="grid">
              <small class="text-brand-orange-800 text-xs font-extrabold uppercase">Producción</small>

              <h2 id="cut-dialog-title" class="m-0 text-xl font-bold text-slate-900">
                {{ title }}
              </h2>
            </span>
          </div>

          <button
            type="button"
            class="inline-flex w-[2.75rem] min-h-[2.75rem] items-center justify-center p-0 text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-md border-0 cursor-pointer transition-colors"
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
            class="mb-4 p-3 px-4 text-red-700 bg-red-100 border border-red-700/20 rounded-md text-sm"
            role="alert"
          >
            {{ formError }}
          </div>

          <section class="grid gap-4">
            <h3 class="m-0 text-base font-bold text-slate-900">Información general</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="grid gap-2">
                <label for="cut-order" class="text-slate-900 text-sm font-bold">
                  Orden de producción
                </label>

                <select
                  id="cut-order"
                  v-model="productionOrderId"
                  :disabled="isEditing"
                  class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all disabled:bg-slate-100 disabled:text-slate-400"
                  :class="{
                    'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                      firstFieldError(
                        'production_order_id',
                      ),
                  }"
                >
                  <option value="">
                    Selecciona una orden
                  </option>

                  <option
                    v-for="order in availableOrders"
                    :key="order.id"
                    :value="order.id"
                  >
                    {{ order.order_code }}
                  </option>
                </select>

                <small
                  v-if="
                    firstFieldError(
                      'production_order_id',
                    )
                  "
                  class="text-red-700 text-xs"
                >
                  {{
                    firstFieldError(
                      'production_order_id',
                    )
                  }}
                </small>
              </div>

              <div class="grid gap-2">
                <label for="cut-model" class="text-slate-900 text-sm font-bold">
                  Modelo de prenda
                </label>

                <select
                  id="cut-model"
                  v-model="garmentModelId"
                  :disabled="isEditing"
                  class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all disabled:bg-slate-100 disabled:text-slate-400"
                  :class="{
                    'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                      firstFieldError(
                        'garment_model_id',
                      ),
                  }"
                >
                  <option value="">
                    Selecciona un modelo
                  </option>

                  <option
                    v-for="model in availableModels"
                    :key="model.id"
                    :value="model.id"
                  >
                    {{ model.code }} · {{ model.name }}
                  </option>
                </select>

                <small
                  v-if="
                    firstFieldError(
                      'garment_model_id',
                    )
                  "
                  class="text-red-700 text-xs"
                >
                  {{
                    firstFieldError(
                      'garment_model_id',
                    )
                  }}
                </small>
              </div>

              <div class="grid gap-2 sm:col-span-2">
                <label for="cut-code" class="text-slate-900 text-sm font-bold">
                  Folio del corte
                </label>

                <input
                  id="cut-code"
                  v-model="code"
                  type="text"
                  maxlength="50"
                  placeholder="Ej. C-PM23-001"
                  autocapitalize="characters"
                  spellcheck="false"
                  :disabled="isEditing"
                  class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all uppercase disabled:bg-slate-100 disabled:text-slate-400"
                  :class="{
                    'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                      firstFieldError('code'),
                  }"
                  @input="normalizeCode"
                />

                <small v-if="firstFieldError('code')" class="text-red-700 text-xs">
                  {{ firstFieldError('code') }}
                </small>
              </div>

              <div class="grid gap-2 sm:col-span-2">
                <label for="cut-description" class="text-slate-900 text-sm font-bold">
                  Descripción
                </label>

                <textarea
                  id="cut-description"
                  v-model="description"
                  rows="3"
                  maxlength="3000"
                  placeholder="Descripción del lote de corte"
                  class="w-full p-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all resize-y"
                />

                <span class="justify-self-end text-slate-500 text-xs">
                  {{ description.length }}/3000
                </span>
              </div>

              <div class="grid gap-2 sm:col-span-2">
                <label for="cut-notes" class="text-slate-900 text-sm font-bold">
                  Notas
                </label>

                <textarea
                  id="cut-notes"
                  v-model="notes"
                  rows="3"
                  maxlength="3000"
                  placeholder="Observaciones adicionales"
                  class="w-full p-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all resize-y"
                />

                <span class="justify-self-end text-slate-500 text-xs">
                  {{ notes.length }}/3000
                </span>
              </div>
            </div>
          </section>

          <section class="grid gap-4 mt-6 pt-5 border-t border-slate-200">
            <header class="flex items-center justify-between gap-3">
              <div>
                <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase">Distribución</p>
                <h3 class="m-0 text-base font-bold text-slate-900">Piezas por talla</h3>
              </div>

              <button
                type="button"
                class="inline-flex min-h-[2.5rem] items-center justify-center px-3 text-brand-green-800 bg-brand-green-100 hover:bg-brand-green-200/80 rounded-md font-bold text-xs cursor-pointer transition-colors border-0"
                @click="selectAllSizes"
              >
                {{
                  selectedSizesCount === sizeRows.length
                    ? 'Deseleccionar todas'
                    : 'Seleccionar todas'
                }}
              </button>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 p-1 bg-slate-100 rounded-lg">
              <button
                type="button"
                class="min-h-[2.75rem] px-3 rounded-md text-sm font-bold transition-all border-0 cursor-pointer"
                :class="
                  distributionMode === 'uniform'
                    ? 'text-brand-green-900 bg-white shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                "
                @click="
                  changeDistributionMode('uniform')
                "
              >
                Distribución uniforme
              </button>

              <button
                type="button"
                class="min-h-[2.75rem] px-3 rounded-md text-sm font-bold transition-all border-0 cursor-pointer"
                :class="
                  distributionMode === 'custom'
                    ? 'text-brand-green-900 bg-white shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                "
                @click="
                  changeDistributionMode('custom')
                "
              >
                Distribución personalizada
              </button>
            </div>

            <div
              v-if="distributionMode === 'uniform'"
              class="grid gap-2"
            >
              <label for="uniform-pieces" class="text-slate-900 text-sm font-bold">
                Piezas por cada talla seleccionada
              </label>

              <input
                id="uniform-pieces"
                v-model.number="uniformPieces"
                type="number"
                min="1"
                max="1000000"
                step="1"
                placeholder="Ej. 50"
                class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
              <article
                v-for="row in sizeRows"
                :key="row.sizeId"
                class="grid gap-2 p-3 border border-slate-200 rounded-lg transition-colors"
                :class="{
                  'bg-brand-green-50/70 border-brand-green-300': row.selected,
                }"
              >
                <button
                  type="button"
                  class="flex min-h-[2.5rem] items-center gap-3 p-0 text-slate-900 bg-transparent border-0 text-left cursor-pointer"
                  :aria-pressed="row.selected"
                  @click="toggleSize(row)"
                >
                  <span
                    class="inline-flex w-5 h-5 items-center justify-center border border-slate-300 rounded-xs transition-colors shrink-0"
                    :class="{
                      'bg-brand-green-700 border-brand-green-700 text-white': row.selected,
                      'bg-white': !row.selected,
                    }"
                  >
                    <Check
                      v-if="row.selected"
                      :size="16"
                      aria-hidden="true"
                    />
                  </span>

                  <strong class="text-sm font-bold">Talla {{ row.name }}</strong>
                </button>

                <input
                  v-if="
                    row.selected &&
                    distributionMode === 'custom'
                  "
                  v-model.number="row.totalPieces"
                  type="number"
                  min="1"
                  max="1000000"
                  step="1"
                  class="w-full min-h-[2.5rem] px-2.5 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700"
                  :aria-label="`Piezas para talla ${row.name}`"
                />

                <span
                  v-else-if="
                    row.selected &&
                    distributionMode === 'uniform'
                  "
                  class="text-slate-600 text-xs font-semibold text-right"
                >
                  {{ uniformPieces ?? 0 }} piezas
                </span>
              </article>
            </div>

            <small
              v-if="firstFieldError('sizes')"
              class="text-red-700 text-xs"
            >
              {{ firstFieldError('sizes') }}
            </small>

            <aside class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-gradient-to-br from-brand-green-100/70 to-brand-orange-50/70 rounded-xl border border-brand-green-200/60">
              <div class="flex items-center gap-3">
                <Calculator
                  :size="23"
                  class="text-brand-green-800 shrink-0"
                  aria-hidden="true"
                />

                <span class="grid">
                  <small class="text-slate-500 text-xs">Tallas seleccionadas</small>
                  <strong class="text-slate-900 text-xl font-bold">{{ selectedSizesCount }}</strong>
                </span>
              </div>

              <div class="flex items-center gap-3">
                <span class="grid">
                  <small class="text-slate-500 text-xs">Total de piezas</small>
                  <strong class="text-slate-900 text-xl font-bold">{{ totalPieces }}</strong>
                </span>
              </div>
            </aside>
          </section>

          <footer class="grid grid-cols-1 sm:flex sm:justify-end gap-3 mt-6 pt-5 border-t border-slate-200">
            <button
              type="button"
              class="inline-flex min-h-[3rem] sm:min-w-[10rem] items-center justify-center gap-2 px-4 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-[750] text-sm cursor-pointer transition-colors"
              :disabled="submitting"
              @click="requestClose"
            >
              Cancelar
            </button>

            <button
              type="submit"
              class="inline-flex min-h-[3rem] sm:min-w-[10rem] items-center justify-center gap-2 px-4 text-white bg-brand-orange-800 border border-brand-orange-800 hover:bg-brand-orange-900 rounded-md font-[750] text-sm cursor-pointer disabled:opacity-70 disabled:cursor-wait transition-colors"
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
                    : 'Registrar corte'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>