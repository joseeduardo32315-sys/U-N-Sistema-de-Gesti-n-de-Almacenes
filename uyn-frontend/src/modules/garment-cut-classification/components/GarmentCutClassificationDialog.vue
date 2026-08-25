<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  ref,
  watch,
} from 'vue'

import {
  AlertTriangle,
  Layers3,
  LoaderCircle,
  Plus,
  Route,
  Scissors,
  Trash2,
  X,
} from 'lucide-vue-next'

import { PERMISSIONS } from '@/config/permissions'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { garmentCutClassificationService } from '@/modules/garment-cut-classification/services/garment-cut-classification.service'
import { pieceTypesService } from '@/modules/piece-types/services/piece-types.service'
import { processesService } from '@/modules/processes/services/processes.service'
import {
  getApiErrorMessage,
  getValidationErrors,
} from '@/utils/api-error'

import type { GarmentCutClassificationPayload } from '@/modules/garment-cut-classification/types/garment-cut-classification.types'
import type { GarmentCut } from '@/modules/garment-cuts/types/garment-cut.types'
import type { PieceType } from '@/modules/piece-types/types/piece-type.types'
import type { ProductionProcess } from '@/modules/processes/types/process.types'

interface SpecialPieceRow {
  key: number
  pieceTypeId: number | ''
  processId: number | ''
  notes: string
}

const props = defineProps<{
  open: boolean
  cut: GarmentCut | null
}>()

const emit = defineEmits<{
  close: []
  saved: [cut: GarmentCut, message: string]
}>()

const authStore = useAuthStore()

const classification = ref<GarmentCut | null>(null)
const pieceTypes = ref<PieceType[]>([])
const processes = ref<ProductionProcess[]>([])

const complementNotes = ref('')
const specialPieces = ref<SpecialPieceRow[]>([])

const loading = ref(false)
const submitting = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

let rowSequence = 0

const isReadOnly = computed<boolean>(() => {
  return !authStore.can(
    PERMISSIONS.processes.classify,
  )
})

const specialPiecesCount = computed<number>(() => {
  return specialPieces.value.length
})

function createRow(
  pieceTypeId: number | '' = '',
  processId: number | '' = '',
  notes = '',
): SpecialPieceRow {
  rowSequence += 1

  return {
    key: rowSequence,
    pieceTypeId,
    processId,
    notes,
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

function pieceTypeIsUsed(
  pieceTypeId: number,
  currentRowKey: number,
): boolean {
  return specialPieces.value.some((row) => {
    return (
      row.key !== currentRowKey &&
      row.pieceTypeId === pieceTypeId
    )
  })
}

function addSpecialPiece(): void {
  if (
    isReadOnly.value ||
    specialPieces.value.length >= 20
  ) {
    return
  }

  specialPieces.value.push(createRow())
}

function removeSpecialPiece(rowKey: number): void {
  if (isReadOnly.value) {
    return
  }

  specialPieces.value = specialPieces.value.filter(
    (row) => row.key !== rowKey,
  )

  fieldErrors.value = {}
}

function resetForm(
  currentClassification: GarmentCut,
): void {
  complementNotes.value =
    currentClassification.complement?.notes ?? ''

  specialPieces.value = (
    currentClassification.special_process_pieces ?? []
  ).map((piece) => {
    return createRow(
      piece.piece_type?.id ?? '',
      piece.process?.id ?? '',
      piece.notes ?? '',
    )
  })

  formError.value = ''
  fieldErrors.value = {}
}

async function loadData(): Promise<void> {
  if (!props.cut) {
    return
  }

  loading.value = true
  formError.value = ''
  fieldErrors.value = {}

  try {
    const [
      classificationResponse,
      processesResponse,
      pieceTypesResponse,
    ] = await Promise.all([
      garmentCutClassificationService.show(
        props.cut.id,
      ),

      processesService.list(),

      pieceTypesService.list({
        status: 'active',
      }),
    ])

    classification.value = classificationResponse
    processes.value = processesResponse
    pieceTypes.value = pieceTypesResponse

    resetForm(classificationResponse)
  } catch (error) {
    classification.value = null

    formError.value = getApiErrorMessage(
      error,
      'No fue posible cargar la clasificación del corte.',
    )
  } finally {
    loading.value = false
  }
}

function validateForm(): boolean {
  fieldErrors.value = {}
  formError.value = ''

  if (complementNotes.value.length > 3000) {
    setLocalError(
      'complement_notes',
      'Las notas del complemento no pueden superar 3000 caracteres.',
    )
  }

  if (specialPieces.value.length > 20) {
    setLocalError(
      'special_process_pieces',
      'No puedes registrar más de 20 piezas especiales.',
    )
  }

  const usedPieceTypeIds = new Set<number>()

  specialPieces.value.forEach((row, index) => {
    const pieceTypeField =
      `special_process_pieces.${index}.piece_type_id`

    const processField =
      `special_process_pieces.${index}.process_id`

    const notesField =
      `special_process_pieces.${index}.notes`

    if (!row.pieceTypeId) {
      setLocalError(
        pieceTypeField,
        'Selecciona el tipo de pieza.',
      )
    } else if (
      usedPieceTypeIds.has(row.pieceTypeId)
    ) {
      setLocalError(
        pieceTypeField,
        'Este tipo de pieza ya fue agregado.',
      )
    } else {
      usedPieceTypeIds.add(row.pieceTypeId)
    }

    if (!row.processId) {
      setLocalError(
        processField,
        'Selecciona el proceso especial.',
      )
    }

    if (row.notes.length > 3000) {
      setLocalError(
        notesField,
        'Las instrucciones no pueden superar 3000 caracteres.',
      )
    }
  })

  return Object.keys(fieldErrors.value).length === 0
}

function buildPayload(): GarmentCutClassificationPayload {
  return {
    complement_notes:
      complementNotes.value.trim() || null,

    special_process_pieces:
      specialPieces.value.map((row) => ({
        piece_type_id: Number(row.pieceTypeId),
        process_id: Number(row.processId),
        notes: row.notes.trim() || null,
      })),
  }
}

async function handleSubmit(): Promise<void> {
  if (
    !props.cut ||
    isReadOnly.value ||
    !validateForm()
  ) {
    return
  }

  submitting.value = true
  formError.value = ''

  try {
    const response =
      await garmentCutClassificationService.update(
        props.cut.id,
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
      'No fue posible guardar la clasificación.',
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
      void loadData()
    } else {
      classification.value = null
      specialPieces.value = []
      complementNotes.value = ''
      formError.value = ''
      fieldErrors.value = {}
    }
  },
)

watch(
  () => props.cut?.id,
  () => {
    if (props.open) {
      void loadData()
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
      v-if="open && cut"
      class="fixed inset-0 z-[115] flex items-stretch sm:items-center justify-center bg-slate-950/60 backdrop-blur-xs sm:p-6"
      @click.self="requestClose"
    >
      <section
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:w-[min(100%,62rem)] overflow-hidden bg-white sm:rounded-xl sm:shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="classification-dialog-title"
      >
        <header class="flex items-center justify-between gap-4 p-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 border-b border-slate-200">
          <div class="flex items-center gap-3 min-w-0">
            <div class="flex w-11 h-11 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-md shrink-0">
              <Route
                :size="23"
                aria-hidden="true"
              />
            </div>

            <span class="grid min-w-0">
              <small class="text-brand-orange-800 text-xs font-extrabold uppercase">Flujo productivo</small>

              <h2 id="classification-dialog-title" class="m-0 text-xl font-bold text-slate-900 truncate">
                Clasificación del corte
              </h2>
            </span>
          </div>

          <button
            type="button"
            class="inline-flex w-[2.75rem] min-h-[2.75rem] items-center justify-center p-0 text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-md border-0 cursor-pointer transition-colors"
            aria-label="Cerrar clasificación"
            :disabled="submitting"
            @click="requestClose"
          >
            <X
              :size="22"
              aria-hidden="true"
            />
          </button>
        </header>

        <div
          v-if="loading"
          class="grid place-content-center min-h-[22rem] gap-3 text-center"
        >
          <div class="w-9 h-9 border-4 border-brand-green-100 border-t-brand-green-700 rounded-full animate-spin mx-auto" />

          <p class="text-slate-600 text-sm">Cargando clasificación...</p>
        </div>

        <form
          v-else
          class="grid gap-5 overflow-y-auto p-5 px-4 sm:px-6 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
          novalidate
          @submit.prevent="handleSubmit"
        >
          <div
            v-if="formError"
            class="p-3 px-4 text-red-700 bg-red-100 border border-red-700/20 rounded-md text-sm"
            role="alert"
          >
            {{ formError }}
          </div>

          <section class="grid grid-cols-1 sm:grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 p-4 bg-gradient-to-br from-brand-green-100/70 to-white border border-brand-green-200 rounded-xl">
            <div class="flex w-12 h-12 items-center justify-center text-brand-green-800 bg-white rounded-md shrink-0 shadow-xs">
              <Scissors
                :size="23"
                aria-hidden="true"
              />
            </div>

            <span class="grid min-w-0">
              <small class="text-brand-orange-800 text-xs font-extrabold">
                {{
                  classification?.production_order
                    ?.order_code ??
                  cut.production_order?.order_code ??
                  'Sin orden'
                }}
              </small>

              <strong class="text-slate-900 font-mono font-bold text-base">
                {{ classification?.code ?? cut.code }}
              </strong>

              <p class="m-0 text-slate-500 text-xs truncate">
                {{
                  classification?.garment_model
                    ? `${classification.garment_model.code} · ${classification.garment_model.name}`
                    : cut.garment_model
                      ? `${cut.garment_model.code} · ${cut.garment_model.name}`
                      : 'Sin modelo asociado'
                }}
              </p>
            </span>

            <em class="not-italic justify-self-start sm:justify-self-auto px-3 py-1 text-xs font-bold text-brand-green-900 bg-white rounded-full shadow-xs">
              {{
                classification?.status_label ??
                cut.status_label
              }}
            </em>
          </section>

          <aside
            v-if="isReadOnly"
            class="flex items-start gap-3 p-4 text-amber-800 bg-amber-50 border border-amber-200 rounded-xl text-sm"
          >
            <AlertTriangle
              :size="20"
              class="shrink-0 mt-0.5"
              aria-hidden="true"
            />

            <p class="m-0 leading-relaxed">
              Puedes consultar la clasificación, pero no
              tienes el permiso necesario para modificarla.
            </p>
          </aside>

          <section class="grid gap-4">
            <header class="flex items-center justify-between gap-4">
              <div>
                <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase">Ruta principal</p>
                <h3 class="m-0 text-base font-bold text-slate-900">Complemento del corte</h3>
              </div>

              <Layers3
                :size="24"
                class="text-slate-400"
                aria-hidden="true"
              />
            </header>

            <p class="m-0 text-slate-600 text-sm leading-relaxed">
              El complemento representa las piezas que no
              requieren un proceso especial y permanecen
              esperando la reunión con las piezas derivadas.
            </p>

            <div class="grid gap-2">
              <label for="complement-notes" class="text-slate-900 text-sm font-bold">
                Notas del complemento
              </label>

              <textarea
                id="complement-notes"
                v-model="complementNotes"
                rows="4"
                maxlength="3000"
                placeholder="Ej. Incluye mangas, espalda, cuello y bies"
                :disabled="isReadOnly"
                class="w-full p-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all resize-y disabled:bg-slate-100 disabled:cursor-not-allowed"
                :class="{
                  'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                    firstFieldError(
                      'complement_notes',
                    ),
                }"
              />

              <div class="flex justify-between gap-3 text-xs">
                <small
                  v-if="
                    firstFieldError(
                      'complement_notes',
                    )
                  "
                  class="text-red-700"
                >
                  {{
                    firstFieldError(
                      'complement_notes',
                    )
                  }}
                </small>

                <span class="ml-auto text-slate-500">
                  {{ complementNotes.length }}/3000
                </span>
              </div>
            </div>
          </section>

          <section class="grid gap-4">
            <header class="flex items-center justify-between gap-4">
              <div>
                <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase">Rutas derivadas</p>

                <h3 class="m-0 text-base font-bold text-slate-900">Piezas con proceso especial</h3>

                <span class="text-slate-500 text-xs">
                  {{ specialPiecesCount }}
                  {{
                    specialPiecesCount === 1
                      ? 'pieza configurada'
                      : 'piezas configuradas'
                  }}
                </span>
              </div>

              <button
                v-if="!isReadOnly"
                type="button"
                class="inline-flex min-h-[2.5rem] items-center justify-center gap-2 px-3 text-brand-green-800 bg-brand-green-100 border border-brand-green-200 hover:bg-brand-green-200/80 rounded-md font-bold text-xs cursor-pointer transition-colors disabled:opacity-50"
                :disabled="
                  specialPieces.length >= 20
                "
                @click="addSpecialPiece"
              >
                <Plus
                  :size="19"
                  aria-hidden="true"
                />

                Agregar pieza
              </button>
            </header>

            <div
              v-if="specialPieces.length === 0"
              class="grid place-content-center p-8 text-center gap-2 bg-slate-50 border border-slate-200/80 rounded-xl"
            >
              <Route
                :size="38"
                class="text-slate-400 mx-auto"
                aria-hidden="true"
              />

              <h4 class="m-0 text-sm font-bold text-slate-900">Sin piezas especiales</h4>

              <p class="m-0 text-slate-600 text-xs leading-relaxed max-w-md">
                El corte continuará únicamente mediante su
                complemento. Puedes agregar piezas que deban
                pasar por Bordado, Estampado u otro proceso.
              </p>
            </div>

            <div
              v-else
              class="grid gap-4"
            >
              <article
                v-for="(row, index) in specialPieces"
                :key="row.key"
                class="p-4 bg-white border border-slate-200 rounded-xl"
              >
                <header class="flex items-center justify-between gap-3 pb-3 mb-4 border-b border-slate-200">
                  <div class="grid min-w-0">
                    <span class="text-slate-500 text-xs">
                      Pieza especial {{ index + 1 }}
                    </span>

                    <strong class="text-slate-900 font-bold text-sm truncate">
                      {{
                        pieceTypes.find(
                          (pieceType) =>
                            pieceType.id ===
                            row.pieceTypeId,
                        )?.name ??
                        'Sin tipo seleccionado'
                      }}
                    </strong>
                  </div>

                  <button
                    v-if="!isReadOnly"
                    type="button"
                    class="inline-flex w-9 h-9 items-center justify-center p-0 text-red-700 bg-red-100 hover:bg-red-200 rounded-md border-0 cursor-pointer transition-colors shrink-0"
                    aria-label="Eliminar pieza especial"
                    @click="
                      removeSpecialPiece(row.key)
                    "
                  >
                    <Trash2
                      :size="19"
                      aria-hidden="true"
                    />
                  </button>
                </header>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div class="grid gap-2">
                    <label
                      :for="`piece-type-${row.key}`"
                      class="text-slate-900 text-sm font-bold"
                    >
                      Tipo de pieza
                    </label>

                    <select
                      :id="`piece-type-${row.key}`"
                      v-model="row.pieceTypeId"
                      :disabled="isReadOnly"
                      class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all disabled:bg-slate-100 disabled:cursor-not-allowed"
                      :class="{
                        'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                          firstFieldError(
                            `special_process_pieces.${index}.piece_type_id`,
                          ),
                      }"
                    >
                      <option value="">
                        Selecciona una pieza
                      </option>

                      <option
                        v-for="pieceType in pieceTypes"
                        :key="pieceType.id"
                        :value="pieceType.id"
                        :disabled="
                          pieceTypeIsUsed(
                            pieceType.id,
                            row.key,
                          )
                        "
                      >
                        {{ pieceType.name }}
                      </option>
                    </select>

                    <small
                      v-if="
                        firstFieldError(
                          `special_process_pieces.${index}.piece_type_id`,
                        )
                      "
                      class="text-red-700 text-xs"
                    >
                      {{
                        firstFieldError(
                          `special_process_pieces.${index}.piece_type_id`,
                        )
                      }}
                    </small>
                  </div>

                  <div class="grid gap-2">
                    <label
                      :for="`piece-process-${row.key}`"
                      class="text-slate-900 text-sm font-bold"
                    >
                      Proceso destino
                    </label>

                    <select
                      :id="`piece-process-${row.key}`"
                      v-model="row.processId"
                      :disabled="isReadOnly"
                      class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all disabled:bg-slate-100 disabled:cursor-not-allowed"
                      :class="{
                        'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                          firstFieldError(
                            `special_process_pieces.${index}.process_id`,
                          ),
                      }"
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
                      v-if="
                        firstFieldError(
                          `special_process_pieces.${index}.process_id`,
                        )
                      "
                      class="text-red-700 text-xs"
                    >
                      {{
                        firstFieldError(
                          `special_process_pieces.${index}.process_id`,
                        )
                      }}
                    </small>
                  </div>

                  <div
                    class="grid gap-2 sm:col-span-2"
                  >
                    <label
                      :for="`piece-notes-${row.key}`"
                      class="text-slate-900 text-sm font-bold"
                    >
                      Instrucciones especiales
                    </label>

                    <textarea
                      :id="`piece-notes-${row.key}`"
                      v-model="row.notes"
                      rows="3"
                      maxlength="3000"
                      placeholder="Ej. Bordar logotipo centrado en el delantero"
                      :disabled="isReadOnly"
                      class="w-full p-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all resize-y disabled:bg-slate-100 disabled:cursor-not-allowed"
                      :class="{
                        'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                          firstFieldError(
                            `special_process_pieces.${index}.notes`,
                          ),
                      }"
                    />

                    <div class="flex justify-between gap-3 text-xs">
                      <small
                        v-if="
                          firstFieldError(
                            `special_process_pieces.${index}.notes`,
                          )
                        "
                        class="text-red-700"
                      >
                        {{
                          firstFieldError(
                            `special_process_pieces.${index}.notes`,
                          )
                        }}
                      </small>

                      <span class="ml-auto text-slate-500">
                        {{ row.notes.length }}/3000
                      </span>
                    </div>
                  </div>
                </div>
              </article>
            </div>

            <small
              v-if="
                firstFieldError(
                  'special_process_pieces',
                )
              "
              class="text-red-700 text-xs"
            >
              {{
                firstFieldError(
                  'special_process_pieces',
                )
              }}
            </small>
          </section>

          <footer class="grid grid-cols-1 sm:flex sm:justify-end gap-3 pt-5 border-t border-slate-200">
            <button
              type="button"
              class="inline-flex min-h-[3rem] sm:min-w-[11rem] items-center justify-center gap-2 px-4 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-[750] text-sm cursor-pointer transition-colors"
              :disabled="submitting"
              @click="requestClose"
            >
              {{
                isReadOnly
                  ? 'Cerrar'
                  : 'Cancelar'
              }}
            </button>

            <button
              v-if="!isReadOnly"
              type="submit"
              class="inline-flex min-h-[3rem] sm:min-w-[11rem] items-center justify-center gap-2 px-4 text-white bg-brand-orange-800 border border-brand-orange-800 hover:bg-brand-orange-900 rounded-md font-[750] text-sm cursor-pointer disabled:opacity-70 disabled:cursor-wait transition-colors"
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
                  : 'Guardar clasificación'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>