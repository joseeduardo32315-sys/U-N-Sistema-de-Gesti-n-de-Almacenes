<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  reactive,
  ref,
  watch,
} from 'vue'

import {
  ImagePlus,
  LoaderCircle,
  Shirt,
  Trash2,
  Upload,
  X,
} from 'lucide-vue-next'

import { garmentModelsService } from '@/modules/garment-models/services/garment-models.service'
import {
  getApiErrorMessage,
  getValidationErrors,
} from '@/utils/api-error'

import type {
  GarmentModel,
  GarmentModelStatus,
  SaveGarmentModelPayload,
} from '@/modules/garment-models/types/garment-model.types'

const props = defineProps<{
  open: boolean
  model: GarmentModel | null
}>()

const emit = defineEmits<{
  close: []
  saved: [model: GarmentModel, message: string]
}>()

interface GarmentModelForm {
  code: string
  name: string
  description: string
  sizeRange: string
  status: GarmentModelStatus
}

const form = reactive<GarmentModelForm>({
  code: '',
  name: '',
  description: '',
  sizeRange: '',
  status: 'active',
})

const imageInput = ref<HTMLInputElement | null>(null)
const selectedImage = ref<File | null>(null)
const temporaryImageUrl = ref<string | null>(null)
const imageLoadFailed = ref(false)

const submitting = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const isEditing = computed<boolean>(() => {
  return props.model !== null
})

const title = computed<string>(() => {
  return isEditing.value
    ? 'Editar modelo de prenda'
    : 'Registrar modelo de prenda'
})

const previewUrl = computed<string | null>(() => {
  return (
    temporaryImageUrl.value ??
    props.model?.image_url ??
    null
  )
})

function revokeTemporaryImage(): void {
  if (temporaryImageUrl.value) {
    URL.revokeObjectURL(temporaryImageUrl.value)
    temporaryImageUrl.value = null
  }
}

function resetForm(): void {
  revokeTemporaryImage()

  form.code = props.model?.code ?? ''
  form.name = props.model?.name ?? ''
  form.description = props.model?.description ?? ''
  form.sizeRange = props.model?.size_range ?? ''
  form.status = props.model?.status ?? 'active'

  selectedImage.value = null
  imageLoadFailed.value = false
  formError.value = ''
  fieldErrors.value = {}

  if (imageInput.value) {
    imageInput.value.value = ''
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

function normalizeCode(): void {
  form.code = form.code.toUpperCase()
}

function validateForm(): boolean {
  fieldErrors.value = {}
  formError.value = ''

  const code = form.code.trim()

  if (!code) {
    setLocalError('code', 'Ingresa el código del modelo.')
  } else if (!/^[A-Z0-9._-]{2,50}$/.test(code)) {
    setLocalError(
      'code',
      'Usa entre 2 y 50 caracteres: mayúsculas, números, puntos, guiones o guiones bajos.',
    )
  }

  if (!form.name.trim()) {
    setLocalError('name', 'Ingresa el nombre del modelo.')
  } else if (form.name.trim().length > 150) {
    setLocalError(
      'name',
      'El nombre no puede exceder 150 caracteres.',
    )
  }

  if (form.description.length > 3000) {
    setLocalError(
      'description',
      'La descripción no puede exceder 3000 caracteres.',
    )
  }

  if (form.sizeRange.length > 100) {
    setLocalError(
      'size_range',
      'El rango de tallas no puede exceder 100 caracteres.',
    )
  }

  return Object.keys(fieldErrors.value).length === 0
}

function selectImage(): void {
  imageInput.value?.click()
}

function handleImageSelection(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.item(0) ?? null

  if (!file) {
    return
  }

  const allowedMimeTypes = [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/webp',
  ]

  const hasAllowedMimeType =
    file.type === '' ||
    allowedMimeTypes.includes(file.type.toLowerCase())

  const hasAllowedExtension =
    /\.(jpe?g|png|webp)$/i.test(file.name)

  if (!hasAllowedMimeType || !hasAllowedExtension) {
    setLocalError(
      'image',
      'Selecciona una imagen JPG, JPEG, PNG o WEBP.',
    )

    selectedImage.value = null
    input.value = ''

    return
  }

  const maximumSizeInBytes = 5 * 1024 * 1024

  if (file.size > maximumSizeInBytes) {
    setLocalError(
      'image',
      'La imagen no puede superar los 5 MB.',
    )

    selectedImage.value = null
    input.value = ''

    return
  }

  revokeTemporaryImage()

  selectedImage.value = file
  temporaryImageUrl.value = URL.createObjectURL(file)
  imageLoadFailed.value = false

  const updatedErrors = {
    ...fieldErrors.value,
  }

  delete updatedErrors.image

  fieldErrors.value = updatedErrors
}

function discardSelectedImage(): void {
  revokeTemporaryImage()

  selectedImage.value = null
  imageLoadFailed.value = false

  if (imageInput.value) {
    imageInput.value.value = ''
  }
}

function handleImageError(): void {
  imageLoadFailed.value = true
}

async function handleSubmit(): Promise<void> {
  if (!validateForm()) {
    return
  }

  submitting.value = true
  formError.value = ''

  const payload: SaveGarmentModelPayload = {
    code: form.code.trim(),
    name: form.name.trim(),
    description: form.description.trim(),
    size_range: form.sizeRange.trim(),
    image: selectedImage.value,
  }

  if (!props.model) {
    payload.status = form.status
  }

  try {
    const response = props.model
      ? await garmentModelsService.update(
          props.model.id,
          payload,
        )
      : await garmentModelsService.create(payload)

    emit('saved', response.data, response.message)
  } catch (error) {
    fieldErrors.value = getValidationErrors(error)

    formError.value = getApiErrorMessage(
      error,
      'No fue posible guardar el modelo de prenda.',
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
    } else {
      revokeTemporaryImage()
    }
  },
)

watch(
  () => props.model,
  () => {
    if (props.open) {
      resetForm()
    }
  },
)

onBeforeUnmount(() => {
  document.body.style.overflow = ''
  revokeTemporaryImage()
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
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:w-[min(100%,56rem)] overflow-hidden bg-white sm:rounded-xl sm:shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="model-dialog-title"
      >
        <header class="flex items-center justify-between gap-4 p-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 border-b border-slate-200">
          <div class="flex items-center gap-3">
            <div class="flex w-11 h-11 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-md shrink-0">
              <Shirt
                :size="23"
                aria-hidden="true"
              />
            </div>

            <span class="grid">
              <small class="text-brand-orange-800 text-xs font-extrabold uppercase">Catálogo de producción</small>

              <h2 id="model-dialog-title" class="m-0 text-xl font-bold text-slate-900">
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
            <X
              :size="22"
              aria-hidden="true"
            />
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

          <div class="grid grid-cols-1 md:grid-cols-[16rem_1fr] gap-6">
            <section class="grid content-start gap-3">
              <label class="text-slate-900 text-sm font-bold">Imagen del modelo</label>

              <div class="relative w-full aspect-4/3 bg-slate-100 border border-dashed border-slate-300 rounded-lg overflow-hidden">
                <div class="absolute inset-0 flex flex-col items-center justify-center text-slate-400 gap-1.5">
                  <ImagePlus
                    :size="42"
                    aria-hidden="true"
                  />

                  <span class="text-xs">Sin imagen</span>
                </div>

                <img
                  v-if="previewUrl && !imageLoadFailed"
                  :src="previewUrl"
                  :alt="`Vista previa de ${form.name || 'modelo de prenda'}`"
                  class="relative z-10 w-full h-full object-cover bg-white"
                  @error="handleImageError"
                />
              </div>

              <input
                ref="imageInput"
                type="file"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                hidden
                @change="handleImageSelection"
              />

              <div class="grid gap-2">
                <button
                  type="button"
                  class="inline-flex min-h-[2.5rem] items-center justify-center gap-2 px-3 text-brand-green-800 bg-brand-green-100 border border-brand-green-200 hover:bg-brand-green-200/80 rounded-md font-bold text-xs cursor-pointer transition-colors"
                  @click="selectImage"
                >
                  <Upload
                    :size="18"
                    aria-hidden="true"
                  />

                  {{
                    previewUrl
                      ? 'Cambiar imagen'
                      : 'Seleccionar imagen'
                  }}
                </button>

                <button
                  v-if="selectedImage"
                  type="button"
                  class="inline-flex min-h-[2.5rem] items-center justify-center gap-2 px-3 text-red-700 bg-red-100 border border-red-200 hover:bg-red-200/80 rounded-md font-bold text-xs cursor-pointer transition-colors"
                  @click="discardSelectedImage"
                >
                  <Trash2
                    :size="18"
                    aria-hidden="true"
                  />

                  Descartar selección
                </button>
              </div>

              <p class="m-0 text-slate-500 text-xs leading-relaxed">
                JPG, JPEG, PNG o WEBP. Tamaño máximo de 5 MB.
              </p>

              <small v-if="firstFieldError('image')" class="text-red-700 text-xs">
                {{ firstFieldError('image') }}
              </small>
            </section>

            <div class="grid gap-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="grid gap-2">
                  <label for="model-code" class="text-slate-900 text-sm font-bold">
                    Código
                  </label>

                  <input
                    id="model-code"
                    v-model="form.code"
                    type="text"
                    maxlength="50"
                    placeholder="Ej. PM-23"
                    autocapitalize="characters"
                    spellcheck="false"
                    class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all uppercase"
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

                <div class="grid gap-2">
                  <label for="model-size-range" class="text-slate-900 text-sm font-bold">
                    Rango de tallas
                  </label>

                  <input
                    id="model-size-range"
                    v-model="form.sizeRange"
                    type="text"
                    maxlength="100"
                    placeholder="Ej. 2 - 16"
                    class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
                    :class="{
                      'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                        firstFieldError('size_range'),
                    }"
                  />

                  <small
                    v-if="firstFieldError('size_range')"
                    class="text-red-700 text-xs"
                  >
                    {{ firstFieldError('size_range') }}
                  </small>
                </div>

                <div class="grid gap-2 sm:col-span-2">
                  <label for="model-name" class="text-slate-900 text-sm font-bold">
                    Nombre del modelo
                  </label>

                  <input
                    id="model-name"
                    v-model="form.name"
                    type="text"
                    maxlength="150"
                    placeholder="Ej. Conjunto infantil bordado"
                    class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
                    :class="{
                      'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                        firstFieldError('name'),
                    }"
                  />

                  <small v-if="firstFieldError('name')" class="text-red-700 text-xs">
                    {{ firstFieldError('name') }}
                  </small>
                </div>

                <div
                  v-if="!isEditing"
                  class="grid gap-2 sm:col-span-2"
                >
                  <label for="model-status" class="text-slate-900 text-sm font-bold">
                    Estado inicial
                  </label>

                  <select
                    id="model-status"
                    v-model="form.status"
                    class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
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
                  <label for="model-description" class="text-slate-900 text-sm font-bold">
                    Descripción
                  </label>

                  <textarea
                    id="model-description"
                    v-model="form.description"
                    rows="5"
                    maxlength="3000"
                    placeholder="Detalles técnicos, características o notas del modelo"
                    class="w-full min-h-[7rem] resize-y p-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
                    :class="{
                      'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                        firstFieldError('description'),
                    }"
                  />

                  <div class="flex justify-between gap-3 text-xs">
                    <small
                      v-if="firstFieldError('description')"
                      class="text-red-700"
                    >
                      {{ firstFieldError('description') }}
                    </small>

                    <span class="ml-auto text-slate-500">
                      {{ form.description.length }}/3000
                    </span>
                  </div>
                </div>
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
                    : 'Registrar modelo'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>