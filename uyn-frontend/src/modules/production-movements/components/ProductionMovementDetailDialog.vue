<script setup lang="ts">
import {
  onBeforeUnmount,
  watch,
} from 'vue'

import {
  ArrowRight,
  Boxes,
  CalendarClock,
  CircleUserRound,
  Factory,
  MapPin,
  PackageCheck,
  Route,
  X,
} from 'lucide-vue-next'

import type { ProductionMovement } from '@/modules/production-movements/types/production-movement.types'

const props = defineProps<{
  open: boolean
  movement: ProductionMovement | null
}>()

const emit = defineEmits<{
  close: []
}>()

function formatDateTime(
  value: string | null,
): string {
  if (!value) {
    return 'No registrada'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

function requestClose(): void {
  emit('close')
}

watch(
  () => props.open,
  (open) => {
    document.body.style.overflow =
      open ? 'hidden' : ''
  },
)

onBeforeUnmount(() => {
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open && movement"
      class="fixed inset-0 z-[115] flex items-stretch sm:items-center justify-center bg-slate-950/60 backdrop-blur-xs sm:p-6"
      @click.self="requestClose"
    >
      <section
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:w-[min(100%,60rem)] overflow-hidden bg-white sm:rounded-xl sm:shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="movement-detail-title"
      >
        <header class="flex items-center justify-between p-4 sm:px-6 border-b border-slate-200">
          <div>
            <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Movimiento #{{ movement.id }}</p>

            <h2 id="movement-detail-title" class="m-0 text-xl font-bold text-slate-900">
              Detalle del movimiento
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
          <section class="grid grid-cols-1 sm:grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 p-5 bg-brand-green-50/70 border border-brand-green-200/80 rounded-xl">
            <div class="flex w-14 h-14 shrink-0 items-center justify-center text-brand-green-800 bg-white rounded-xl shadow-xs">
              <Route :size="28" aria-hidden="true" />
            </div>

            <span class="grid min-w-0">
              <small class="text-brand-orange-800 font-extrabold text-xs">
                {{
                  movement.garment_cut?.code ??
                  'Sin corte'
                }}
              </small>

              <h3 class="m-0 text-lg font-bold text-slate-900 truncate">
                {{ movement.target_type_label }}
              </h3>

              <p class="m-0 text-slate-600 text-sm truncate">
                {{
                  movement.operation_process?.name ??
                  movement.process?.name ??
                  'Sin proceso'
                }}
              </p>
            </span>

            <strong class="px-3 py-1 bg-white text-slate-800 rounded-full text-xs font-bold shadow-xs self-start sm:self-center">
              {{ movement.status_label }}
            </strong>
          </section>

          <section class="grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3">
            <article class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-xl">
              <MapPin :size="21" class="text-brand-green-700 shrink-0" aria-hidden="true" />

              <span class="grid min-w-0">
                <small class="text-slate-400 text-xs font-bold uppercase">Área origen</small>
                <strong class="text-slate-900 text-sm font-bold truncate">
                  {{
                    movement.from_area?.name ??
                    'No disponible'
                  }}
                </strong>
              </span>
            </article>

            <ArrowRight
              :size="24"
              class="rotate-90 sm:rotate-0 text-brand-orange-800 justify-self-center shrink-0"
              aria-hidden="true"
            />

            <article class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-xl">
              <Factory :size="21" class="text-brand-green-700 shrink-0" aria-hidden="true" />

              <span class="grid min-w-0">
                <small class="text-slate-400 text-xs font-bold uppercase">Área destino</small>
                <strong class="text-slate-900 text-sm font-bold truncate">
                  {{
                    movement.to_area?.name ??
                    'No disponible'
                  }}
                </strong>
              </span>
            </article>
          </section>

          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <article class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-lg">
              <Boxes :size="23" class="text-brand-green-700 shrink-0" aria-hidden="true" />

              <span class="grid">
                <small class="text-slate-500 text-xs">Cantidad enviada</small>
                <strong class="text-slate-900 font-mono font-bold text-base">{{ movement.quantity }}</strong>
              </span>
            </article>

            <article class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-lg">
              <PackageCheck
                :size="23"
                class="text-brand-green-700 shrink-0"
                aria-hidden="true"
              />

              <span class="grid">
                <small class="text-slate-500 text-xs">Cantidad efectiva</small>
                <strong class="text-slate-900 font-mono font-bold text-base">
                  {{ movement.effective_quantity }}
                </strong>
              </span>
            </article>

            <article class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-lg">
              <Boxes :size="23" class="text-brand-green-700 shrink-0" aria-hidden="true" />

              <span class="grid">
                <small class="text-slate-500 text-xs">Pérdidas resueltas</small>
                <strong class="text-slate-900 font-mono font-bold text-base">
                  {{
                    movement.resolved_loss_quantity
                  }}
                </strong>
              </span>
            </article>

            <article class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-lg">
              <Route :size="23" class="text-brand-green-700 shrink-0" aria-hidden="true" />

              <span class="grid">
                <small class="text-slate-500 text-xs">Avances registrados</small>
                <strong class="text-slate-900 font-mono font-bold text-base">
                  {{ movement.operation_logs_count }}
                </strong>
              </span>
            </article>
          </div>

          <dl class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 m-0">
            <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg">
              <dt class="flex items-center gap-2 text-slate-500 text-xs font-bold">
                <CalendarClock
                  :size="18"
                  aria-hidden="true"
                />

                Registro
              </dt>

              <dd class="m-0 mt-2 text-slate-900 text-sm font-bold break-all">
                {{
                  formatDateTime(
                    movement.created_at,
                  )
                }}
              </dd>
            </div>

            <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg">
              <dt class="flex items-center gap-2 text-slate-500 text-xs font-bold">
                <CalendarClock
                  :size="18"
                  aria-hidden="true"
                />

                Inicio
              </dt>

              <dd class="m-0 mt-2 text-slate-900 text-sm font-bold break-all">
                {{
                  formatDateTime(
                    movement.start_time,
                  )
                }}
              </dd>
            </div>

            <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg">
              <dt class="flex items-center gap-2 text-slate-500 text-xs font-bold">
                <CalendarClock
                  :size="18"
                  aria-hidden="true"
                />

                Finalización
              </dt>

              <dd class="m-0 mt-2 text-slate-900 text-sm font-bold break-all">
                {{
                  formatDateTime(
                    movement.end_time,
                  )
                }}
              </dd>
            </div>

            <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg">
              <dt class="flex items-center gap-2 text-slate-500 text-xs font-bold">
                <CircleUserRound
                  :size="18"
                  aria-hidden="true"
                />

                Registrado por
              </dt>

              <dd class="m-0 mt-2 text-slate-900 text-sm font-bold truncate">
                {{
                  movement.created_by?.name ??
                  'No disponible'
                }}
              </dd>
            </div>

            <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg">
              <dt class="flex items-center gap-2 text-slate-500 text-xs font-bold">
                <CircleUserRound
                  :size="18"
                  aria-hidden="true"
                />

                Recibido por
              </dt>

              <dd class="m-0 mt-2 text-slate-900 text-sm font-bold truncate">
                {{
                  movement.received_by?.name ??
                  'Pendiente de recepción'
                }}
              </dd>
            </div>

            <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg">
              <dt class="flex items-center gap-2 text-slate-500 text-xs font-bold">Proceso</dt>

              <dd class="m-0 mt-2 text-slate-900 text-sm font-bold truncate">
                {{
                  movement.process?.name ??
                  'No disponible'
                }}
                ·
                {{
                  movement.operation_process?.name ??
                  'Sin operación'
                }}
              </dd>
            </div>
          </dl>

          <section
            v-if="movement.notes"
            class="p-4 bg-brand-orange-50 border border-brand-orange-100 rounded-xl"
          >
            <h3 class="m-0 text-sm font-bold text-slate-900">Notas del despacho</h3>
            <p class="mt-2 mb-0 text-slate-600 text-sm leading-relaxed whitespace-pre-wrap">{{ movement.notes }}</p>
          </section>

          <aside
            v-if="movement.is_return_for_rework"
            class="p-4 text-amber-800 bg-amber-50 border border-amber-200 rounded-xl text-xs leading-relaxed"
          >
            Este movimiento corresponde a una devolución
            para reproceso.
          </aside>
        </div>

        <footer class="p-4 sm:px-6 border-t border-slate-200 flex justify-end">
          <button
            type="button"
            class="w-full sm:w-auto min-h-[3rem] px-6 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-[750] text-sm transition-colors border-0 cursor-pointer"
            @click="requestClose"
          >
            Cerrar
          </button>
        </footer>
      </section>
    </div>
  </Teleport>
</template>