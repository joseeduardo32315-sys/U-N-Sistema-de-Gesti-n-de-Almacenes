<script setup lang="ts">
import {
  onMounted,
  reactive,
  ref,
} from 'vue'

import {
  ChevronLeft,
  ChevronRight,
  ImageOff,
  Pencil,
  Plus,
  Power,
  PowerOff,
  RotateCcw,
  Search,
  Shirt,
} from 'lucide-vue-next'

import Swal from 'sweetalert2'

import { PERMISSIONS } from '@/config/permissions'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import GarmentModelFormDialog from '@/modules/garment-models/components/GarmentModelFormDialog.vue'
import { garmentModelsService } from '@/modules/garment-models/services/garment-models.service'
import { getApiErrorMessage } from '@/utils/api-error'
import { resolveMediaUrl } from '@/utils/media-url'

import type {
  GarmentModel,
  GarmentModelStatus,
} from '@/modules/garment-models/types/garment-model.types'
import type { PaginationMeta } from '@/types/api'

interface Filters {
  search: string
  status: GarmentModelStatus | ''
  perPage: number
}

const authStore = useAuthStore()

const models = ref<GarmentModel[]>([])
const failedImageIds = ref<Set<number>>(new Set<number>())

const loading = ref(false)
const actionModelId = ref<number | null>(null)

const formOpen = ref(false)
const selectedModel = ref<GarmentModel | null>(null)

const filters = reactive<Filters>({
  search: '',
  status: '',
  perPage: 15,
})

const pagination = ref<PaginationMeta>({
  current_page: 1,
  from: null,
  last_page: 1,
  per_page: 15,
  to: null,
  total: 0,
})

function formatDate(value: string): string {
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
  }).format(date)
}

function statusLabel(model: GarmentModel): string {
  if (model.status_label) {
    return model.status_label
  }

  return model.status === 'active'
    ? 'Activo'
    : 'Inactivo'
}

function modelImageUrl(
  model: GarmentModel,
): string | null {
  if (model.image_url) {
    return resolveMediaUrl(model.image_url)
  }

  if (model.image_path) {
    return resolveMediaUrl(model.image_path)
  }

  return null
}

function hasModelImage(model: GarmentModel): boolean {
  const imageUrl = modelImageUrl(model)

  return Boolean(
    imageUrl &&
      !failedImageIds.value.has(model.id),
  )
}

function handleImageError(modelId: number): void {
  failedImageIds.value = new Set<number>([
    ...failedImageIds.value,
    modelId,
  ])
}

function canCreate(): boolean {
  return authStore.can(
    PERMISSIONS.garmentModels.create,
  )
}

function canEdit(): boolean {
  return authStore.can(
    PERMISSIONS.garmentModels.update,
  )
}

function canActivate(model: GarmentModel): boolean {
  return (
    model.status === 'inactive' &&
    authStore.can(
      PERMISSIONS.garmentModels.activate,
    )
  )
}

function canDeactivate(model: GarmentModel): boolean {
  return (
    model.status === 'active' &&
    authStore.can(
      PERMISSIONS.garmentModels.deactivate,
    )
  )
}

async function loadModels(page = 1): Promise<void> {
  loading.value = true

  try {
    const response = await garmentModelsService.list({
      search: filters.search.trim(),
      status: filters.status,
      per_page: filters.perPage,
      page,
    })

    models.value = response.data
    pagination.value = response.meta

    /*
     * Permite reintentar la carga de imágenes cuando
     * se actualice el listado o cambie alguna URL.
     */
    failedImageIds.value = new Set<number>()
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible cargar los modelos',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    loading.value = false
  }
}

function applyFilters(): void {
  void loadModels(1)
}

function clearFilters(): void {
  filters.search = ''
  filters.status = ''
  filters.perPage = 15

  void loadModels(1)
}

function openCreateForm(): void {
  selectedModel.value = null
  formOpen.value = true
}

function openEditForm(model: GarmentModel): void {
  selectedModel.value = model
  formOpen.value = true
}

function closeForm(): void {
  formOpen.value = false
  selectedModel.value = null
}

async function handleSaved(
  _model: GarmentModel,
  message: string,
): Promise<void> {
  closeForm()

  await Swal.fire({
    title: message,
    icon: 'success',
    timer: 1700,
    showConfirmButton: false,
  })

  await loadModels(pagination.value.current_page)
}

async function toggleModelStatus(
  model: GarmentModel,
): Promise<void> {
  const activating = model.status === 'inactive'

  const confirmation = await Swal.fire({
    title: activating
      ? '¿Activar modelo?'
      : '¿Desactivar modelo?',
    text: activating
      ? `${model.code} estará disponible nuevamente para producción.`
      : `${model.code} dejará de estar disponible para nuevos cortes.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: activating
      ? 'Sí, activar'
      : 'Sí, desactivar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: activating
      ? '#3f6b2a'
      : '#b91c1c',
  })

  if (!confirmation.isConfirmed) {
    return
  }

  actionModelId.value = model.id

  try {
    const response = activating
      ? await garmentModelsService.activate(model.id)
      : await garmentModelsService.deactivate(model.id)

    await Swal.fire({
      title: response.message,
      icon: 'success',
      timer: 1600,
      showConfirmButton: false,
    })

    await loadModels(pagination.value.current_page)
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible cambiar el estado',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    actionModelId.value = null
  }
}

function previousPage(): void {
  if (pagination.value.current_page <= 1) {
    return
  }

  void loadModels(
    pagination.value.current_page - 1,
  )
}

function nextPage(): void {
  if (
    pagination.value.current_page >=
    pagination.value.last_page
  ) {
    return
  }

  void loadModels(
    pagination.value.current_page + 1,
  )
}

onMounted(() => {
  void loadModels()
})
</script>

<template>
  <section class="grid gap-5">
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <p class="m-0 mb-2 text-brand-orange-800 text-xs font-extrabold tracking-wider uppercase">
          Catálogo de producción
        </p>

        <h2 class="m-0 text-2xl font-bold text-slate-900">Modelos de prenda</h2>

        <p class="mt-2 mb-0 text-slate-600 leading-relaxed text-sm max-w-2xl">
          Administra los diseños, códigos, imágenes y rangos
          de tallas utilizados en producción.
        </p>
      </div>

      <button
        v-if="canCreate()"
        type="button"
        class="inline-flex w-full sm:w-auto min-h-[3rem] items-center justify-center gap-2 px-4 text-white bg-brand-orange-800 hover:bg-brand-orange-900 rounded-md font-[750] text-sm cursor-pointer transition-colors border-0"
        @click="openCreateForm"
      >
        <Plus
          :size="20"
          aria-hidden="true"
        />

        Registrar modelo
      </button>
    </header>

    <form
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1.8fr_1fr_1fr_auto] gap-3 p-4 bg-white border border-slate-200 rounded-lg"
      @submit.prevent="applyFilters"
    >
      <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3 min-h-[3rem] px-3 bg-white border border-slate-300 rounded-md focus-within:border-brand-green-700 focus-within:ring-3 focus-within:ring-brand-green-700/13 transition-all">
        <Search
          :size="20"
          class="text-slate-400"
          aria-hidden="true"
        />

        <input
          v-model="filters.search"
          type="search"
          maxlength="150"
          placeholder="Buscar por código, nombre o descripción"
          class="w-full min-w-0 bg-transparent border-0 outline-hidden text-slate-900 text-sm placeholder:text-slate-400"
        />
      </div>

      <select
        v-model="filters.status"
        aria-label="Filtrar por estado"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">
          Todos los estados
        </option>

        <option value="active">
          Activos
        </option>

        <option value="inactive">
          Inactivos
        </option>
      </select>

      <select
        v-model="filters.perPage"
        aria-label="Registros por página"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option :value="10">
          10 por página
        </option>

        <option :value="15">
          15 por página
        </option>

        <option :value="25">
          25 por página
        </option>

        <option :value="50">
          50 por página
        </option>
      </select>

      <div class="flex gap-2">
        <button
          type="submit"
          class="inline-flex min-h-[3rem] items-center justify-center gap-2 px-4 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-bold text-sm transition-colors cursor-pointer border-0 disabled:opacity-60"
          :disabled="loading"
        >
          <Search
            :size="19"
            aria-hidden="true"
          />

          Buscar
        </button>

        <button
          type="button"
          class="inline-flex min-h-[3rem] items-center justify-center gap-2 px-4 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-bold text-sm transition-colors cursor-pointer disabled:opacity-60"
          :disabled="loading"
          @click="clearFilters"
        >
          <RotateCcw
            :size="19"
            aria-hidden="true"
          />

          Limpiar
        </button>
      </div>
    </form>

    <div
      v-if="loading"
      class="grid place-content-center min-h-[16rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <div class="w-9 h-9 border-4 border-brand-green-100 border-t-brand-green-700 rounded-full animate-spin mx-auto" />

      <p class="text-slate-600 text-sm">Cargando modelos de prenda...</p>
    </div>

    <div
      v-else-if="models.length === 0"
      class="grid place-content-center min-h-[16rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <Shirt
        :size="44"
        class="text-slate-400 mx-auto"
        aria-hidden="true"
      />

      <h3 class="m-0 text-lg font-bold text-slate-900">No se encontraron modelos</h3>

      <p class="m-0 text-slate-600 text-sm">
        Modifica los filtros o registra un nuevo modelo.
      </p>
    </div>

    <template v-else>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <article
          v-for="model in models"
          :key="model.id"
          class="flex flex-col overflow-hidden bg-white border border-slate-200 rounded-xl shadow-xs"
        >
          <div class="relative w-full aspect-4/3 bg-slate-100 overflow-hidden">
            <div class="absolute inset-0 flex flex-col items-center justify-center text-slate-400 gap-1.5">
              <ImageOff
                :size="36"
                aria-hidden="true"
              />

              <span class="text-xs">Sin imagen</span>
            </div>

            <img
              v-if="hasModelImage(model)"
              :src="modelImageUrl(model) ?? undefined"
              :alt="`Modelo ${model.code} - ${model.name}`"
              loading="lazy"
              class="relative z-10 w-full h-full object-cover"
              @error="handleImageError(model.id)"
            />

            <span
              class="absolute top-3 right-3 z-20 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold shadow-xs"
              :class="{
                'text-emerald-800 bg-emerald-100/90 backdrop-blur-xs':
                  model.status === 'active',
                'text-slate-700 bg-slate-200/90 backdrop-blur-xs':
                  model.status === 'inactive',
              }"
            >
              {{ statusLabel(model) }}
            </span>
          </div>

          <div class="flex flex-col flex-1 p-4 gap-2">
            <header>
              <span class="inline-block px-2 py-0.5 text-xs font-mono font-bold text-brand-orange-800 bg-brand-orange-50 border border-brand-orange-200 rounded-md mb-1">{{ model.code }}</span>

              <h3 class="m-0 text-base font-bold text-slate-900 leading-snug">{{ model.name }}</h3>
            </header>

            <p class="m-0 text-xs text-slate-600 line-clamp-2 leading-relaxed flex-1">
              {{
                model.description ||
                'Sin descripción registrada.'
              }}
            </p>

            <dl class="grid grid-cols-2 gap-2 pt-3 border-t border-slate-100 text-xs">
              <div>
                <dt class="text-slate-400">Rango de tallas</dt>

                <dd class="m-0 font-medium text-slate-800">
                  {{
                    model.size_range ||
                    'No especificado'
                  }}
                </dd>
              </div>

              <div>
                <dt class="text-slate-400">Fecha de registro</dt>

                <dd class="m-0 font-medium text-slate-800">
                  {{ formatDate(model.created_at) }}
                </dd>
              </div>
            </dl>
          </div>

          <footer class="flex items-center gap-2 p-3 bg-slate-50 border-t border-slate-200">
            <button
              v-if="canEdit()"
              type="button"
              class="flex-1 inline-flex min-h-[2.35rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-bold text-xs transition-colors cursor-pointer"
              @click="openEditForm(model)"
            >
              <Pencil
                :size="18"
                aria-hidden="true"
              />

              Editar
            </button>

            <button
              v-if="canDeactivate(model)"
              type="button"
              class="flex-1 inline-flex min-h-[2.35rem] items-center justify-center gap-1.5 px-3 text-red-700 bg-red-50 hover:bg-red-100 rounded-md font-bold text-xs transition-colors border-0 cursor-pointer disabled:opacity-50"
              :disabled="actionModelId === model.id"
              @click="toggleModelStatus(model)"
            >
              <PowerOff
                :size="18"
                aria-hidden="true"
              />

              Desactivar
            </button>

            <button
              v-if="canActivate(model)"
              type="button"
              class="flex-1 inline-flex min-h-[2.35rem] items-center justify-center gap-1.5 px-3 text-emerald-800 bg-emerald-50 hover:bg-emerald-100 rounded-md font-bold text-xs transition-colors border-0 cursor-pointer disabled:opacity-50"
              :disabled="actionModelId === model.id"
              @click="toggleModelStatus(model)"
            >
              <Power
                :size="18"
                aria-hidden="true"
              />

              Activar
            </button>
          </footer>
        </article>
      </div>

      <footer class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 bg-white border border-slate-200 rounded-lg text-xs text-slate-600">
        <p class="m-0">
          Mostrando
          <strong class="text-slate-900 font-bold">{{ pagination.from ?? 0 }}</strong>
          a
          <strong class="text-slate-900 font-bold">{{ pagination.to ?? 0 }}</strong>
          de
          <strong class="text-slate-900 font-bold">{{ pagination.total }}</strong>
          modelos
        </p>

        <div class="flex items-center gap-3">
          <button
            type="button"
            class="inline-flex min-h-[2.35rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 rounded-md font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
            :disabled="
              pagination.current_page <= 1 ||
              loading
            "
            @click="previousPage"
          >
            <ChevronLeft
              :size="19"
              aria-hidden="true"
            />

            Anterior
          </button>

          <span class="font-medium">
            Página {{ pagination.current_page }}
            de {{ pagination.last_page }}
          </span>

          <button
            type="button"
            class="inline-flex min-h-[2.35rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 rounded-md font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
            :disabled="
              pagination.current_page >=
                pagination.last_page ||
              loading
            "
            @click="nextPage"
          >
            Siguiente

            <ChevronRight
              :size="19"
              aria-hidden="true"
            />
          </button>
        </div>
      </footer>
    </template>

    <GarmentModelFormDialog
      :open="formOpen"
      :model="selectedModel"
      @close="closeForm"
      @saved="handleSaved"
    />
  </section>
</template>