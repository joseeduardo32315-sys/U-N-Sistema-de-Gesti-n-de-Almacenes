<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  reactive,
  ref,
  watch,
} from 'vue'

import {
  BriefcaseBusiness,
  LoaderCircle,
  X,
} from 'lucide-vue-next'

import { employeesService } from '@/modules/employees/services/employees.service'
import {
  getApiErrorMessage,
  getValidationErrors,
} from '@/utils/api-error'

import type { Area } from '@/modules/areas/types/area.types'
import type {
  CreateEmployeePayload,
  Employee,
  EmployeeStatus,
  UpdateEmployeePayload,
  WorkerType,
} from '@/modules/employees/types/employee.types'

const props = defineProps<{
  open: boolean
  employee: Employee | null
  areas: Area[]
}>()

const emit = defineEmits<{
  close: []
  saved: [employee: Employee, message: string]
}>()

interface EmployeeForm {
  name: string
  areaId: number | ''
  workerType: WorkerType
  phone: string
  status: EmployeeStatus
  notes: string
}

const form = reactive<EmployeeForm>({
  name: '',
  areaId: '',
  workerType: 'internal',
  phone: '',
  status: 'active',
  notes: '',
})

const submitting = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const isEditing = computed<boolean>(() => {
  return props.employee !== null
})

const title = computed<string>(() => {
  return isEditing.value
    ? 'Editar empleado'
    : 'Registrar empleado'
})

function resetForm(): void {
  form.name = props.employee?.name ?? ''
  form.areaId = props.employee?.area?.id ?? ''
  form.workerType =
    props.employee?.worker_type ?? 'internal'
  form.phone = props.employee?.phone ?? ''
  form.status = props.employee?.status ?? 'active'
  form.notes = props.employee?.notes ?? ''

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

  if (!form.name.trim()) {
    setLocalError('name', 'Ingresa el nombre del empleado.')
  }

  if (form.name.trim().length > 150) {
    setLocalError(
      'name',
      'El nombre no puede exceder 150 caracteres.',
    )
  }

  if (!form.areaId) {
    setLocalError('area_id', 'Selecciona un área.')
  }

  if (!form.workerType) {
    setLocalError(
      'worker_type',
      'Selecciona el tipo de trabajador.',
    )
  }

  if (!form.phone.trim()) {
    setLocalError(
      'phone',
      'Ingresa un teléfono de contacto.',
    )
  }

  if (form.phone.trim().length > 30) {
    setLocalError(
      'phone',
      'El teléfono no puede exceder 30 caracteres.',
    )
  }

  if (form.notes.length > 2000) {
    setLocalError(
      'notes',
      'Las notas no pueden exceder 2000 caracteres.',
    )
  }

  return Object.keys(fieldErrors.value).length === 0
}

async function handleSubmit(): Promise<void> {
  if (!validateForm() || !form.areaId) {
    return
  }

  submitting.value = true
  formError.value = ''

  try {
    if (props.employee) {
      const payload: UpdateEmployeePayload = {
        name: form.name.trim(),
        area_id: form.areaId,
        worker_type: form.workerType,
        phone: form.phone.trim(),
        notes: form.notes.trim() || undefined,
      }

      const response = await employeesService.update(
        props.employee.id,
        payload,
      )

      emit('saved', response.data, response.message)
      return
    }

    const payload: CreateEmployeePayload = {
      name: form.name.trim(),
      area_id: form.areaId,
      worker_type: form.workerType,
      phone: form.phone.trim(),
      status: form.status,
      notes: form.notes.trim() || undefined,
    }

    const response =
      await employeesService.create(payload)

    emit('saved', response.data, response.message)
  } catch (error) {
    fieldErrors.value = getValidationErrors(error)

    formError.value = getApiErrorMessage(
      error,
      'No fue posible guardar el empleado.',
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
  () => props.employee,
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
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:w-[min(100%,46rem)] overflow-hidden bg-white sm:rounded-xl sm:shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="employee-dialog-title"
      >
        <header class="flex items-center justify-between gap-4 p-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 border-b border-slate-200">
          <div class="flex items-center gap-3">
            <div class="flex w-11 h-11 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-md shrink-0">
              <BriefcaseBusiness
                :size="23"
                aria-hidden="true"
              />
            </div>

            <span class="grid">
              <small class="text-brand-orange-800 text-xs font-extrabold uppercase">Catálogo de empleados</small>

              <h2 id="employee-dialog-title" class="m-0 text-xl font-bold text-slate-900">
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

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="grid gap-2 sm:col-span-2">
              <label for="employee-name" class="text-slate-900 text-sm font-bold">
                Nombre completo
              </label>

              <input
                id="employee-name"
                v-model="form.name"
                type="text"
                maxlength="150"
                autocomplete="name"
                placeholder="Ej. María López"
                class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
                :class="{
                  'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                    firstFieldError('name'),
                }"
              />

              <small v-if="firstFieldError('name')" class="text-red-700 text-xs">
                {{ firstFieldError('name') }}
              </small>
            </div>

            <div class="grid gap-2">
              <label for="employee-worker-type" class="text-slate-900 text-sm font-bold">
                Tipo de trabajador
              </label>

              <select
                id="employee-worker-type"
                v-model="form.workerType"
                class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
                :class="{
                  'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                    firstFieldError('worker_type'),
                }"
              >
                <option value="internal">
                  Empleado interno
                </option>

                <option value="external">
                  Maquilero externo
                </option>
              </select>

              <small
                v-if="firstFieldError('worker_type')"
                class="text-red-700 text-xs"
              >
                {{ firstFieldError('worker_type') }}
              </small>
            </div>

            <div class="grid gap-2">
              <label for="employee-area" class="text-slate-900 text-sm font-bold">
                Área asignada
              </label>

              <select
                id="employee-area"
                v-model="form.areaId"
                class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
                :class="{
                  'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                    firstFieldError('area_id'),
                }"
              >
                <option value="">
                  Selecciona un área
                </option>

                <option
                  v-for="area in areas"
                  :key="area.id"
                  :value="area.id"
                >
                  {{ area.name }}
                </option>
              </select>

              <small v-if="firstFieldError('area_id')" class="text-red-700 text-xs">
                {{ firstFieldError('area_id') }}
              </small>
            </div>

            <div class="grid gap-2">
              <label for="employee-phone" class="text-slate-900 text-sm font-bold">
                Teléfono
              </label>

              <input
                id="employee-phone"
                v-model="form.phone"
                type="tel"
                maxlength="30"
                autocomplete="tel"
                placeholder="Ej. 2221234567"
                class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
                :class="{
                  'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                    firstFieldError('phone'),
                }"
              />

              <small v-if="firstFieldError('phone')" class="text-red-700 text-xs">
                {{ firstFieldError('phone') }}
              </small>
            </div>

            <div
              v-if="!isEditing"
              class="grid gap-2"
            >
              <label for="employee-status" class="text-slate-900 text-sm font-bold">
                Estado inicial
              </label>

              <select
                id="employee-status"
                v-model="form.status"
                class="w-full min-h-[3rem] p-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
              >
                <option value="active">
                  Activo
                </option>

                <option value="inactive">
                  Inactivo
                </option>
              </select>
            </div>

            <div class="grid gap-2 sm:col-span-2">
              <label for="employee-notes" class="text-slate-900 text-sm font-bold">
                Notas
              </label>

              <textarea
                id="employee-notes"
                v-model="form.notes"
                rows="4"
                maxlength="2000"
                placeholder="Especialidad, observaciones o información adicional"
                class="w-full min-h-[7rem] resize-y p-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
                :class="{
                  'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                    firstFieldError('notes'),
                }"
              />

              <div class="flex justify-between gap-3 text-xs">
                <small v-if="firstFieldError('notes')" class="text-red-700">
                  {{ firstFieldError('notes') }}
                </small>

                <span class="ml-auto text-slate-500">
                  {{ form.notes.length }}/2000
                </span>
              </div>
            </div>
          </div>

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
                    : 'Registrar empleado'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>