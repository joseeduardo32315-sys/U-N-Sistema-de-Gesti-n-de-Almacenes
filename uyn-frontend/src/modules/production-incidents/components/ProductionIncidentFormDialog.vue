<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  ref,
  watch,
} from 'vue'

import {
  AlertTriangle,
  LoaderCircle,
  MapPin,
  X,
} from 'lucide-vue-next'

import { employeesService } from '@/modules/employees/services/employees.service'
import { productionMovementsService } from '@/modules/production-movements/services/production-movements.service'
import { productionIncidentsService } from '@/modules/production-incidents/services/production-incidents.service'

import {
  getApiErrorMessage,
  getValidationErrors,
} from '@/utils/api-error'

import type { Employee } from '@/modules/employees/types/employee.types'
import type { GarmentCut } from '@/modules/garment-cuts/types/garment-cut.types'
import type { ProductionMovement } from '@/modules/production-movements/types/production-movement.types'

import type {
  CreateProductionIncidentPayload,
  ProductionIncident,
  ProductionIncidentType,
  UpdateProductionIncidentPayload,
} from '@/modules/production-incidents/types/production-incident.types'

const props = defineProps<{
  open: boolean
  incident: ProductionIncident | null
  cuts: GarmentCut[]
}>()

const emit = defineEmits<{
  close: []
  saved: [
    incident: ProductionIncident,
    message: string,
  ]
}>()

const garmentCutId = ref<number | ''>('')
const productionMovementId = ref<number | ''>('')

const incidentType =
  ref<ProductionIncidentType>('quality')

const quantityAffected = ref<number | null>(null)
const description = ref('')
const responsibleEmployeeId = ref<number | ''>('')

const movements = ref<ProductionMovement[]>([])
const responsibleEmployees = ref<Employee[]>([])

const movementsLoading = ref(false)
const employeesLoading = ref(false)
const submitting = ref(false)

const formError = ref('')
const fieldErrors =
  ref<Record<string, string[]>>({})

const isEditing = computed<boolean>(() => {
  return props.incident !== null
})

const title = computed<string>(() => {
  return isEditing.value
    ? 'Editar incidencia'
    : 'Registrar incidencia'
})

const selectedMovement =
  computed<ProductionMovement | undefined>(() => {
    return movements.value.find(
      (movement) =>
        movement.id === productionMovementId.value,
    )
  })

const requiresAffectedQuantity =
  computed<boolean>(() => {
    return ['damage', 'loss', 'quality'].includes(
      incidentType.value,
    )
  })

const maximumQuantity = computed<number>(() => {
  return (
    selectedMovement.value?.effective_quantity ??
    selectedMovement.value?.quantity ??
    1_000_000
  )
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
  garmentCutId.value =
    props.incident?.garment_cut?.id ?? ''

  productionMovementId.value =
    props.incident?.production_movement?.id ?? ''

  incidentType.value =
    props.incident?.incident_type ?? 'quality'

  quantityAffected.value =
    props.incident?.quantity_affected ?? null

  description.value =
    props.incident?.description ?? ''

  responsibleEmployeeId.value =
    props.incident?.responsible_employee?.id ?? ''

  movements.value = []
  responsibleEmployees.value = []

  formError.value = ''
  fieldErrors.value = {}
}

function uniqueEmployees(
  values: Employee[],
): Employee[] {
  const employeeMap = new Map<number, Employee>()

  for (const employee of values) {
    employeeMap.set(employee.id, employee)
  }

  return Array.from(employeeMap.values()).sort(
    (first, second) =>
      first.name.localeCompare(second.name, 'es'),
  )
}

async function loadMovements(
  preserveSelection = false,
): Promise<void> {
    fieldErrors.value = {
        ...fieldErrors.value,
    }

    fieldErrors.value = {
        ...fieldErrors.value,
    }

    delete fieldErrors.value.garment_cut_id
    delete fieldErrors.value.production_movement_id
  movements.value = []
  responsibleEmployees.value = []

  if (!preserveSelection) {
    productionMovementId.value = ''
    responsibleEmployeeId.value = ''
  }

  if (!garmentCutId.value) {
    return
  }

  movementsLoading.value = true
  formError.value = ''

  try {
    const response =
      await productionMovementsService.list({
        garment_cut_id: garmentCutId.value,
        per_page: 100,
      })

    movements.value = response.data

    if (
      productionMovementId.value &&
      movements.value.some(
        (movement) =>
          movement.id ===
          productionMovementId.value,
      )
    ) {
      await loadResponsibleEmployees(true)
    }
  } catch (error) {
    formError.value = getApiErrorMessage(
      error,
      'No fue posible cargar los movimientos del corte.',
    )
  } finally {
    movementsLoading.value = false
  }
}

async function loadResponsibleEmployees(
  preserveSelection = false,
): Promise<void> {
  responsibleEmployees.value = []

  if (!preserveSelection) {
    responsibleEmployeeId.value = ''
  }

  const movement = selectedMovement.value

  if (!movement) {
    return
  }

  const areaIds = Array.from(
    new Set(
      [
        movement.from_area?.id,
        movement.to_area?.id,
      ].filter(
        (id): id is number =>
          typeof id === 'number',
      ),
    ),
  )

  if (areaIds.length === 0) {
    return
  }

  employeesLoading.value = true
  formError.value = ''

  try {
    const responses = await Promise.all(
      areaIds.map((areaId) =>
        employeesService.list({
          area_id: areaId,
          status: 'active',
          per_page: 100,
          page: 1,
        }),
      ),
    )

    responsibleEmployees.value = uniqueEmployees(
      responses.flatMap(
        (response) => response.data,
      ),
    )
  } catch (error) {
    formError.value = getApiErrorMessage(
      error,
      'No fue posible cargar los responsables disponibles.',
    )
  } finally {
    employeesLoading.value = false
  }
}

async function initializeForm(): Promise<void> {
  resetForm()

  if (props.incident) {
    await loadMovements(true)

    /*
     * Conserva al responsable si la API no lo devuelve en
     * el catálogo por encontrarse inactivo actualmente.
     */
    const responsible =
      props.incident.responsible_employee

    if (
      responsible &&
      !responsibleEmployees.value.some(
        (employee) => employee.id === responsible.id,
      )
    ) {
      responsibleEmployees.value.push({
        id: responsible.id,
        name: responsible.name,
        worker_type:
          responsible.worker_type ?? 'internal',
        status: responsible.status ?? 'active',
      } as Employee)
    }
  }
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

    if (!productionMovementId.value) {
    setLocalError(
        'production_movement_id',
        'Selecciona el movimiento donde ocurrió la incidencia.',
    )
    }

    if (
    productionMovementId.value &&
    garmentCutId.value &&
    selectedMovement.value?.garment_cut?.id !==
        garmentCutId.value
    ) {
    setLocalError(
        'production_movement_id',
        'El movimiento seleccionado no pertenece al corte indicado.',
    )
    }

  const quantity = Number(quantityAffected.value)

  if (
    requiresAffectedQuantity.value &&
    (!Number.isInteger(quantity) || quantity < 1)
  ) {
    setLocalError(
      'quantity_affected',
      'Esta incidencia requiere al menos una pieza afectada.',
    )
  }

  if (
    !requiresAffectedQuantity.value &&
    (!Number.isInteger(quantity) || quantity < 0)
  ) {
    setLocalError(
      'quantity_affected',
      'La cantidad afectada debe ser cero o un número entero positivo.',
    )
  }

  if (
    Number.isInteger(quantity) &&
    quantity > maximumQuantity.value
  ) {
    setLocalError(
      'quantity_affected',
      `La cantidad no puede superar ${maximumQuantity.value} piezas.`,
    )
  }

  if (!description.value.trim()) {
    setLocalError(
      'description',
      'Describe lo ocurrido.',
    )
  } else if (description.value.length > 3000) {
    setLocalError(
      'description',
      'La descripción no puede superar 3000 caracteres.',
    )
  }

  if (!responsibleEmployeeId.value) {
    setLocalError(
      'responsible_employee_id',
      'Selecciona al responsable relacionado con la incidencia.',
    )
  }

  return Object.keys(fieldErrors.value).length === 0
}

async function handleSubmit(): Promise<void> {
  if (!validateForm()) {
    return
  }

  if (
    !garmentCutId.value ||
    !productionMovementId.value ||
    !responsibleEmployeeId.value
  ) {
    return
  }

  submitting.value = true
  formError.value = ''

  try {
    if (props.incident) {
      const payload: UpdateProductionIncidentPayload = {
        quantity_affected: Number(
          quantityAffected.value,
        ),

        description: description.value.trim(),

        responsible_employee_id:
          responsibleEmployeeId.value,
      }

      const response =
        await productionIncidentsService.update(
          props.incident.id,
          payload,
        )

      emit(
        'saved',
        response.data,
        response.message,
      )

      return
    }

    const payload: CreateProductionIncidentPayload = {
        production_movement_id:
            productionMovementId.value,

        incident_type: incidentType.value,

        quantity_affected: Number(
            quantityAffected.value,
        ),

        description: description.value.trim(),

        responsible_employee_id:
            responsibleEmployeeId.value,
    }

    const response =
      await productionIncidentsService.create(
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
      'No fue posible guardar la incidencia.',
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
      void initializeForm()
    }
  },
)

watch(
  () => props.incident?.id,
  () => {
    if (props.open) {
      void initializeForm()
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
      class="fixed inset-0 z-[120] flex items-stretch sm:items-center justify-center bg-slate-950/65 backdrop-blur-xs sm:p-6"
      @click.self="requestClose"
    >
      <section
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:w-[min(100%,55rem)] overflow-hidden bg-white sm:rounded-xl sm:shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="incident-dialog-title"
      >
        <header class="flex items-center justify-between gap-3 p-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 border-b border-slate-200">
          <div class="flex items-center gap-3">
            <div class="flex w-11 h-11 shrink-0 items-center justify-center text-rose-800 bg-rose-100 rounded-md">
              <AlertTriangle
                :size="23"
                aria-hidden="true"
              />
            </div>

            <span class="grid">
              <small class="text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Control de producción</small>

              <h2 id="incident-dialog-title" class="m-0 text-xl font-bold text-slate-900">
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
              <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Ubicación del problema</p>
              <h3 class="m-0 text-base font-bold text-slate-900">Corte y movimiento</h3>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="grid gap-1.5">
                <label for="incident-cut" class="text-slate-900 text-sm font-bold">
                  Corte de producción
                </label>

                <select
                  id="incident-cut"
                  v-model="garmentCutId"
                  :disabled="isEditing || submitting"
                  class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-500"
                  :class="{
                    'border-rose-700!': firstFieldError('garment_cut_id'),
                  }"
                  @change="loadMovements(false)"
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
                  </option>
                </select>

                <small v-if="firstFieldError('garment_cut_id')" class="text-rose-600 text-xs">
                  {{ firstFieldError('garment_cut_id') }}
                </small>
              </div>

              <div class="grid gap-1.5">
                <label for="incident-movement" class="text-slate-900 text-sm font-bold">
                  Movimiento
                </label>

                <select
                  id="incident-movement"
                  v-model="productionMovementId"
                  :disabled="
                    isEditing ||
                    movementsLoading ||
                    !garmentCutId
                  "
                  class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-500"
                  :class="{
                    'border-rose-700!': firstFieldError('production_movement_id'),
                  }"
                  @change="
                    loadResponsibleEmployees(false)
                  "
                >
                  <option value="">
                    Selecciona un movimiento
                  </option>

                  <option
                    v-for="movement in movements"
                    :key="movement.id"
                    :value="movement.id"
                  >
                    #{{ movement.id }}
                    ·
                    {{
                      movement.from_area?.name ??
                      'Origen'
                    }}
                    →
                    {{
                      movement.to_area?.name ??
                      'Destino'
                    }}
                  </option>
                </select>

                <small v-if="firstFieldError('production_movement_id')" class="text-rose-600 text-xs">
                  {{ firstFieldError('production_movement_id') }}
                </small>
              </div>
            </div>

            <div
              v-if="selectedMovement"
              class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-lg text-sm"
            >
              <div class="flex items-center gap-2 min-w-0">
                <MapPin
                  :size="20"
                  class="text-brand-green-800 shrink-0"
                  aria-hidden="true"
                />

                <span class="font-medium text-slate-800 truncate">
                  {{
                    selectedMovement.from_area?.name ??
                    'Origen'
                  }}
                </span>
              </div>

              <span class="text-brand-orange-800 font-extrabold font-mono">→</span>

              <div class="flex items-center justify-end gap-2 min-w-0">
                <MapPin
                  :size="20"
                  class="text-brand-green-800 shrink-0"
                  aria-hidden="true"
                />

                <span class="font-medium text-slate-800 truncate">
                  {{
                    selectedMovement.to_area?.name ??
                    'Destino'
                  }}
                </span>
              </div>
            </div>
          </section>

          <section class="grid gap-4 pt-5 border-t border-slate-200">
            <header>
              <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Clasificación</p>
              <h3 class="m-0 text-base font-bold text-slate-900">Información de la incidencia</h3>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="grid gap-1.5">
                <label for="incident-type" class="text-slate-900 text-sm font-bold">
                  Tipo de incidencia
                </label>

                <select
                  id="incident-type"
                  v-model="incidentType"
                  :disabled="isEditing || submitting"
                  class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-500"
                >
                  <option value="damage">
                    Daño
                  </option>

                  <option value="loss">
                    Pérdida
                  </option>

                  <option value="quality">
                    Problema de calidad
                  </option>

                  <option value="delay">
                    Retraso
                  </option>

                  <option value="other">
                    Otro
                  </option>
                </select>
              </div>

              <div class="grid gap-1.5">
                <label for="incident-quantity" class="text-slate-900 text-sm font-bold">
                  Cantidad afectada
                </label>

                <input
                  id="incident-quantity"
                  v-model.number="quantityAffected"
                  type="number"
                  :min="
                    requiresAffectedQuantity ? 1 : 0
                  "
                  :max="maximumQuantity"
                  step="1"
                  class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
                  :class="{
                    'border-rose-700!': firstFieldError('quantity_affected'),
                  }"
                />

                <small v-if="firstFieldError('quantity_affected')" class="text-rose-600 text-xs">
                  {{ firstFieldError('quantity_affected') }}
                </small>

                <small
                  v-else
                  class="text-slate-500 text-xs"
                >
                  Máximo disponible:
                  {{ maximumQuantity }} piezas.
                </small>
              </div>

              <div class="grid gap-1.5 sm:col-span-2">
                <label for="incident-responsible" class="text-slate-900 text-sm font-bold">
                  Responsable relacionado
                </label>

                <select
                  id="incident-responsible"
                  v-model="responsibleEmployeeId"
                  :disabled="
                    employeesLoading ||
                    !selectedMovement
                  "
                  class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 disabled:bg-slate-100 disabled:text-slate-500"
                  :class="{
                    'border-rose-700!': firstFieldError('responsible_employee_id'),
                  }"
                >
                  <option value="">
                    Selecciona un responsable
                  </option>

                  <option
                    v-for="
                      employee in responsibleEmployees
                    "
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

                <small v-if="firstFieldError('responsible_employee_id')" class="text-rose-600 text-xs">
                  {{ firstFieldError('responsible_employee_id') }}
                </small>

                <small
                  v-else
                  class="text-slate-500 text-xs"
                >
                  Solo se muestran trabajadores del área
                  origen o destino.
                </small>
              </div>

              <div class="grid gap-1.5 sm:col-span-2">
                <label for="incident-description" class="text-slate-900 text-sm font-bold">
                  Descripción
                </label>

                <textarea
                  id="incident-description"
                  v-model="description"
                  rows="5"
                  maxlength="3000"
                  placeholder="Describe lo ocurrido, sus causas y las piezas afectadas"
                  class="w-full min-h-[8rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 leading-relaxed resize-y"
                  :class="{
                    'border-rose-700!': firstFieldError('description'),
                  }"
                />

                <div class="flex justify-between items-center gap-3">
                  <small v-if="firstFieldError('description')" class="text-rose-600 text-xs">
                    {{ firstFieldError('description') }}
                  </small>

                  <span class="ml-auto text-slate-400 text-xs">
                    {{ description.length }}/3000
                  </span>
                </div>
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
              class="inline-flex min-h-[3rem] sm:w-48 items-center justify-center gap-2 px-5 text-white bg-brand-orange-800 hover:bg-brand-orange-900 rounded-md font-[750] text-sm cursor-pointer transition-colors border-0 disabled:opacity-50"
              :disabled="
                submitting ||
                movementsLoading ||
                employeesLoading
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
                  ? 'Guardando...'
                  : isEditing
                    ? 'Guardar cambios'
                    : 'Registrar incidencia'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>