<script setup lang="ts">
import {
  onBeforeUnmount,
  watch,
} from 'vue'

import {
  Boxes,
  CalendarDays,
  ClipboardList,
  MapPin,
  Shirt,
  UserRound,
  X,
} from 'lucide-vue-next'

import type { ProductionOrder } from '@/modules/production-orders/types/production-order.types'

const props = defineProps<{
  open: boolean
  order: ProductionOrder | null
}>()

const emit = defineEmits<{
  close: []
}>()

function formatDate(
  value: string | null,
): string {
  if (!value) {
    return 'No especificada'
  }

  const date = new Date(`${value}T00:00:00`)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'long',
  }).format(date)
}

function requestClose(): void {
  emit('close')
}

watch(
  () => props.open,
  (open) => {
    document.body.style.overflow = open ? 'hidden' : ''
  },
)

onBeforeUnmount(() => {
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open && order"
      class="fixed inset-0 z-[110] flex items-stretch sm:items-center justify-center bg-slate-950/60 backdrop-blur-xs sm:p-6"
      @click.self="requestClose"
    >
      <section
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:w-[min(100%,58rem)] overflow-hidden bg-white sm:rounded-xl sm:shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="order-detail-title"
      >
        <header class="flex items-center justify-between gap-4 p-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 border-b border-slate-200">
          <div>
            <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold tracking-wider uppercase">Orden {{ order.order_code }}</p>

            <h2 id="order-detail-title" class="m-0 text-xl font-bold text-slate-900">
              Detalle de producción
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
          <section class="flex items-start gap-4 p-5 bg-gradient-to-br from-brand-green-100/80 to-white border border-brand-green-200 rounded-xl">
            <div class="flex w-14 h-14 shrink-0 items-center justify-center text-brand-green-800 bg-white rounded-lg shadow-xs">
              <ClipboardList
                :size="28"
                aria-hidden="true"
              />
            </div>

            <div class="min-w-0">
              <span class="text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider block">{{ order.order_code }}</span>

              <h3 class="m-0 mt-1 mb-3 text-lg font-bold text-slate-900 break-words">
                {{ order.location || 'Sin ubicación definida' }}
              </h3>

              <div class="flex flex-wrap gap-2">
                <span class="px-3 py-1 text-brand-green-900 bg-brand-green-200/80 rounded-full text-xs font-bold">
                  {{ order.status_label }}
                </span>

                <span class="px-3 py-1 text-brand-orange-900 bg-brand-orange-100 rounded-full text-xs font-bold">
                  {{ order.priority_label }}
                </span>
              </div>
            </div>
          </section>

          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 m-0">
            <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-lg">
              <dt class="flex items-center gap-2 text-slate-500 text-xs font-bold uppercase tracking-wider">
                <CalendarDays
                  :size="18"
                  class="shrink-0 text-slate-400"
                  aria-hidden="true"
                />

                Inicio
              </dt>

              <dd class="m-0 mt-2 text-slate-900 font-bold text-sm leading-snug">{{ formatDate(order.start_date) }}</dd>
            </div>

            <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-lg">
              <dt class="flex items-center gap-2 text-slate-500 text-xs font-bold uppercase tracking-wider">
                <CalendarDays
                  :size="18"
                  class="shrink-0 text-slate-400"
                  aria-hidden="true"
                />

                Finalización
              </dt>

              <dd class="m-0 mt-2 text-slate-900 font-bold text-sm leading-snug">{{ formatDate(order.end_date) }}</dd>
            </div>

            <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-lg">
              <dt class="flex items-center gap-2 text-slate-500 text-xs font-bold uppercase tracking-wider">
                <MapPin
                  :size="18"
                  class="shrink-0 text-slate-400"
                  aria-hidden="true"
                />

                Ubicación
              </dt>

              <dd class="m-0 mt-2 text-slate-900 font-bold text-sm leading-snug">{{ order.location || 'No especificada' }}</dd>
            </div>

            <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-lg">
              <dt class="flex items-center gap-2 text-slate-500 text-xs font-bold uppercase tracking-wider">
                <UserRound
                  :size="18"
                  class="shrink-0 text-slate-400"
                  aria-hidden="true"
                />

                Registrada por
              </dt>

              <dd class="m-0 mt-2 text-slate-900 font-bold text-sm leading-snug">
                {{ order.created_by?.name ?? 'No disponible' }}
              </dd>
            </div>
          </dl>

          <section
            v-if="order.notes"
            class="p-4 bg-brand-orange-50/60 border border-brand-orange-100 rounded-xl"
          >
            <h3 class="m-0 mb-2 text-sm font-bold text-slate-900">Notas</h3>
            <p class="m-0 text-slate-600 text-sm leading-relaxed whitespace-pre-wrap">{{ order.notes }}</p>
          </section>

          <section class="grid gap-4">
            <header class="flex items-center justify-between gap-4">
              <div>
                <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Cortes asociados</p>
                <h3 class="m-0 text-base font-bold text-slate-900">Lotes de producción</h3>
              </div>

              <span class="inline-flex min-w-[2rem] min-h-[2rem] items-center justify-center text-xs font-bold text-brand-orange-900 bg-brand-orange-100 rounded-full">
                {{ order.garment_cuts_count }}
              </span>
            </header>

            <div
              v-if="
                !order.garment_cuts ||
                order.garment_cuts.length === 0
              "
              class="grid place-content-center justify-items-center gap-3 p-8 text-slate-500 text-center bg-slate-50 rounded-lg border border-dashed border-slate-200"
            >
              <Boxes :size="38" class="text-slate-400 mx-auto" aria-hidden="true" />

              <p class="m-0 text-sm">
                Esta orden todavía no tiene cortes registrados.
              </p>
            </div>

            <div
              v-else
              class="grid grid-cols-1 sm:grid-cols-2 gap-3"
            >
              <article
                v-for="cut in order.garment_cuts"
                :key="cut.id"
                class="p-4 bg-white border border-slate-200 rounded-lg"
              >
                <header class="flex items-center gap-3 pb-3 border-b border-slate-100">
                  <div class="flex w-11 h-11 shrink-0 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-md">
                    <Shirt
                      :size="21"
                      aria-hidden="true"
                    />
                  </div>

                  <div class="grid min-w-0">
                    <strong class="text-slate-900 font-mono font-bold text-sm truncate">{{ cut.code }}</strong>
                    <span class="text-slate-500 text-xs truncate">
                      {{
                        cut.garment_model
                          ? `${cut.garment_model.code} · ${cut.garment_model.name}`
                          : 'Sin modelo'
                      }}
                    </span>
                  </div>
                </header>

                <dl class="grid grid-cols-2 gap-3 m-0 mt-3 text-xs">
                  <div>
                    <dt class="text-slate-500">Piezas</dt>
                    <dd class="m-0 mt-0.5 text-slate-900 font-bold text-sm">{{ cut.total_pieces }}</dd>
                  </div>

                  <div>
                    <dt class="text-slate-500">Tallas</dt>
                    <dd class="m-0 mt-0.5 text-slate-900 font-bold text-sm">{{ cut.total_sizes }}</dd>
                  </div>

                  <div>
                    <dt class="text-slate-500">Área actual</dt>
                    <dd class="m-0 mt-0.5 text-slate-900 font-bold text-xs truncate">
                      {{ cut.current_area?.name ?? 'Sin área' }}
                    </dd>
                  </div>

                  <div>
                    <dt class="text-slate-500">Estado</dt>
                    <dd class="m-0 mt-0.5 text-slate-900 font-bold text-xs">
                      {{ cut.status_label ?? cut.status }}
                    </dd>
                  </div>
                </dl>
              </article>
            </div>
          </section>
        </div>

        <footer class="p-4 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))] border-t border-slate-200 sm:flex sm:justify-end">
          <button
            type="button"
            class="w-full sm:w-auto min-w-[9rem] min-h-[3rem] px-5 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-[750] text-sm cursor-pointer transition-colors border-0"
            @click="requestClose"
          >
            Cerrar
          </button>
        </footer>
      </section>
    </div>
  </Teleport>
</template>