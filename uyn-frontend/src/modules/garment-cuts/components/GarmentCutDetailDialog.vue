<script setup lang="ts">
import {
  onBeforeUnmount,
  watch,
} from 'vue'

import {
  Boxes,
  MapPin,
  PackageCheck,
  Route,
  Scissors,
  Shirt,
  X,
} from 'lucide-vue-next'

import type { GarmentCut } from '@/modules/garment-cuts/types/garment-cut.types'

const props = defineProps<{
  open: boolean
  cut: GarmentCut | null
}>()

const emit = defineEmits<{
  close: []
}>()

function formatDate(value: string): string {
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'long',
    timeStyle: 'short',
  }).format(date)
}

function requestClose(): void {
  emit('close')
}

watch(
  () => props.open,
  (open) => {
    document.body.style.overflow = open
      ? 'hidden'
      : ''
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
      class="fixed inset-0 z-[110] flex items-stretch sm:items-center justify-center bg-slate-950/60 backdrop-blur-xs sm:p-6"
      @click.self="requestClose"
    >
      <section
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:w-[min(100%,62rem)] overflow-hidden bg-white sm:rounded-xl sm:shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cut-detail-title"
      >
        <header class="flex items-center justify-between gap-4 p-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 border-b border-slate-200">
          <div>
            <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase">Corte {{ cut.code }}</p>

            <h2 id="cut-detail-title" class="m-0 text-xl font-bold text-slate-900">
              Detalle del lote
            </h2>
          </div>

          <button
            type="button"
            class="inline-flex w-[2.75rem] min-h-[2.75rem] items-center justify-center p-0 text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-md border-0 cursor-pointer transition-colors"
            aria-label="Cerrar detalle"
            @click="requestClose"
          >
            <X :size="22" aria-hidden="true" />
          </button>
        </header>

        <div class="grid gap-5 overflow-y-auto p-5 px-4 sm:px-6">
          <section class="grid grid-cols-1 sm:grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-4 p-5 bg-brand-green-100/70 rounded-xl border border-brand-green-200">
            <div class="flex w-14 h-14 shrink-0 items-center justify-center text-brand-green-800 bg-white rounded-xl shadow-xs">
              <Scissors
                :size="29"
                aria-hidden="true"
              />
            </div>

            <span class="min-w-0 grid gap-0.5">
              <small class="text-brand-orange-800 font-extrabold text-xs">
                {{
                  cut.production_order?.order_code ??
                  'Sin orden'
                }}
              </small>

              <h3 class="m-0 text-xl font-bold text-slate-900">{{ cut.code }}</h3>

              <p class="m-0 text-slate-600 text-sm">
                {{
                  cut.garment_model
                    ? `${cut.garment_model.code} · ${cut.garment_model.name}`
                    : 'Sin modelo asociado'
                }}
              </p>
            </span>

            <strong class="justify-self-start sm:justify-self-auto px-3 py-1 text-xs font-bold text-brand-green-900 bg-white rounded-full shadow-xs">{{ cut.status_label }}</strong>
          </section>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <article class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200/80 rounded-lg">
              <Boxes
                :size="23"
                class="text-brand-green-800 shrink-0"
                aria-hidden="true"
              />

              <span class="grid">
                <small class="text-slate-500 text-xs">Total de piezas</small>
                <strong class="text-slate-900 text-lg font-bold">{{ cut.total_pieces }}</strong>
              </span>
            </article>

            <article class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200/80 rounded-lg">
              <Shirt
                :size="23"
                class="text-brand-green-800 shrink-0"
                aria-hidden="true"
              />

              <span class="grid">
                <small class="text-slate-500 text-xs">Tallas</small>
                <strong class="text-slate-900 text-lg font-bold">{{ cut.total_sizes }}</strong>
              </span>
            </article>

            <article class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200/80 rounded-lg">
              <MapPin
                :size="23"
                class="text-brand-green-800 shrink-0"
                aria-hidden="true"
              />

              <span class="grid">
                <small class="text-slate-500 text-xs">Área actual</small>
                <strong class="text-slate-900 text-lg font-bold truncate">
                  {{ cut.current_area?.name ?? 'Sin área' }}
                </strong>
              </span>
            </article>

            <article class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200/80 rounded-lg">
              <PackageCheck
                :size="23"
                class="text-brand-green-800 shrink-0"
                aria-hidden="true"
              />

              <span class="grid">
                <small class="text-slate-500 text-xs">Distribución</small>
                <strong class="text-slate-900 text-lg font-bold">
                  {{
                    cut.is_uniform_distribution
                      ? 'Uniforme'
                      : 'Personalizada'
                  }}
                </strong>
              </span>
            </article>
          </div>

          <section
            v-if="cut.description || cut.notes"
            class="grid grid-cols-1 sm:grid-cols-2 gap-4"
          >
            <article v-if="cut.description" class="p-4 bg-white border border-slate-200 rounded-lg">
              <h3 class="m-0 text-sm font-bold text-slate-900">Descripción</h3>
              <p class="mt-2 mb-0 text-slate-600 text-sm leading-relaxed whitespace-pre-wrap">{{ cut.description }}</p>
            </article>

            <article v-if="cut.notes" class="p-4 bg-white border border-slate-200 rounded-lg">
              <h3 class="m-0 text-sm font-bold text-slate-900">Notas</h3>
              <p class="mt-2 mb-0 text-slate-600 text-sm leading-relaxed whitespace-pre-wrap">{{ cut.notes }}</p>
            </article>
          </section>

          <section class="grid gap-3">
            <header class="flex items-center justify-between">
              <div>
                <p class="m-0 mb-0.5 text-brand-orange-800 text-xs font-extrabold uppercase">Distribución registrada</p>
                <h3 class="m-0 text-base font-bold text-slate-900">Piezas por talla</h3>
              </div>

              <span class="inline-flex min-w-[2rem] min-h-[2rem] items-center justify-center text-xs font-bold text-brand-orange-900 bg-brand-orange-100 rounded-full">{{ cut.sizes?.length ?? 0 }}</span>
            </header>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
              <article
                v-for="item in cut.sizes ?? []"
                :key="item.id"
                class="grid justify-items-center p-3.5 bg-slate-50 border border-slate-200/80 rounded-lg text-center"
              >
                <span class="text-slate-400 text-xs">Talla</span>
                <strong class="text-slate-900 text-xl font-extrabold my-0.5">{{ item.size.name }}</strong>
                <small class="text-slate-500 text-xs font-medium">
                  {{ item.total_pieces }} piezas
                </small>
              </article>
            </div>
          </section>

          <section class="grid gap-3">
            <header class="flex items-center justify-between">
              <div>
                <p class="m-0 mb-0.5 text-brand-orange-800 text-xs font-extrabold uppercase">Clasificación productiva</p>
                <h3 class="m-0 text-base font-bold text-slate-900">Complementos y rutas especiales</h3>
              </div>

              <Route
                :size="24"
                class="text-brand-orange-800"
                aria-hidden="true"
              />
            </header>

            <article
              v-if="cut.complement"
              class="p-4 bg-white border border-slate-200 rounded-lg grid gap-2"
            >
              <h4 class="m-0 text-sm font-bold text-slate-900">Complemento</h4>

              <dl class="grid gap-1.5 mt-2 text-xs">
                <div class="flex justify-between gap-4">
                  <dt class="text-slate-500">Estado</dt>
                  <dd class="m-0 font-bold text-slate-800">
                    {{ cut.complement.status_label }}
                  </dd>
                </div>

                <div class="flex justify-between gap-4">
                  <dt class="text-slate-500">Ubicación</dt>
                  <dd class="m-0 font-bold text-slate-800">
                    {{
                      cut.complement.current_area?.name ??
                      'Sin área'
                    }}
                  </dd>
                </div>
              </dl>

              <p v-if="cut.complement.notes" class="mt-2 mb-0 text-slate-600 text-xs leading-relaxed">
                {{ cut.complement.notes }}
              </p>
            </article>

            <div
              v-if="
                cut.special_process_pieces &&
                cut.special_process_pieces.length > 0
              "
              class="grid grid-cols-1 sm:grid-cols-2 gap-3"
            >
              <article
                v-for="
                  piece in cut.special_process_pieces
                "
                :key="piece.id"
                class="p-4 bg-white border border-slate-200 rounded-lg grid gap-2"
              >
                <h4 class="m-0 text-sm font-bold text-slate-900">
                  {{
                    piece.piece_type?.name ??
                    'Pieza especial'
                  }}
                </h4>

                <dl class="grid gap-1.5 mt-2 text-xs">
                  <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Proceso</dt>
                    <dd class="m-0 font-bold text-slate-800">
                      {{
                        piece.process?.name ??
                        'No definido'
                      }}
                    </dd>
                  </div>

                  <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Estado</dt>
                    <dd class="m-0 font-bold text-slate-800">{{ piece.status_label }}</dd>
                  </div>

                  <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Área actual</dt>
                    <dd class="m-0 font-bold text-slate-800">
                      {{
                        piece.current_area?.name ??
                        'Sin área'
                      }}
                    </dd>
                  </div>
                </dl>

                <p v-if="piece.notes" class="mt-2 mb-0 text-slate-600 text-xs leading-relaxed">
                  {{ piece.notes }}
                </p>
              </article>
            </div>

            <div
              v-if="
                !cut.complement &&
                (!cut.special_process_pieces ||
                  cut.special_process_pieces.length === 0)
              "
              class="p-6 text-center text-slate-500 text-sm bg-slate-50 rounded-lg border border-dashed border-slate-200"
            >
              El corte todavía no tiene una clasificación
              productiva configurada.
            </div>
          </section>

          <p class="m-0 text-right text-slate-400 text-xs">
            Registrado el {{ formatDate(cut.created_at) }}
          </p>
        </div>

        <footer class="p-4 border-t border-slate-200 sm:flex sm:justify-end">
          <button
            type="button"
            class="w-full sm:w-auto min-w-[9rem] min-h-[3rem] px-4 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-[750] text-sm cursor-pointer transition-colors border-0"
            @click="requestClose"
          >
            Cerrar
          </button>
        </footer>
      </section>
    </div>
  </Teleport>
</template>