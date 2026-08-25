<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  watch,
} from 'vue'

import {
  ArrowRight,
  CalendarClock,
  CircleUserRound,
  FileJson,
  Globe2,
  X,
} from 'lucide-vue-next'

import type { OperationLog } from '@/modules/operation-logs/types/operation-log.types'

const props = defineProps<{
  open: boolean
  log: OperationLog | null
}>()

const emit = defineEmits<{
  close: []
}>()

interface ValueRow {
  key: string
  oldValue: unknown
  newValue: unknown
}

const changedValues = computed<ValueRow[]>(() => {
  if (!props.log) {
    return []
  }

  const oldValues = props.log.old_values ?? {}
  const newValues = props.log.new_values ?? {}

  const keys = new Set([
    ...Object.keys(oldValues),
    ...Object.keys(newValues),
  ])

  return Array.from(keys)
    .sort((a, b) => a.localeCompare(b, 'es'))
    .map((key) => ({
      key,
      oldValue: oldValues[key],
      newValue: newValues[key],
    }))
})

function formatDate(value: string): string {
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'long',
    timeStyle: 'medium',
  }).format(date)
}

function formatValue(value: unknown): string {
  if (value === null || value === undefined) {
    return 'Sin valor'
  }

  if (typeof value === 'boolean') {
    return value ? 'Sí' : 'No'
  }

  if (typeof value === 'object') {
    return JSON.stringify(value, null, 2)
  }

  return String(value)
}

function actionLabel(action: string): string {
  const labels: Record<string, string> = {
    create: 'Creación',
    update: 'Actualización',
    activate: 'Activación',
    deactivate: 'Desactivación',
    delete: 'Eliminación',
    login: 'Inicio de sesión',
    logout: 'Cierre de sesión',
    generate: 'Generación',
    close: 'Cierre',
    assign: 'Asignación',
    receive: 'Recepción',
    resolve: 'Resolución',
  }

  return labels[action] ?? action
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
      v-if="open && log"
      class="fixed inset-0 z-[110] flex items-stretch sm:items-center justify-center bg-slate-950/60 backdrop-blur-xs sm:p-6"
      @click.self="requestClose"
    >
      <section
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:w-[min(100%,56rem)] overflow-hidden bg-white sm:rounded-xl sm:shadow-lg"
        role="dialog"
        aria-modal="true"
        aria-labelledby="log-detail-title"
      >
        <header class="flex items-center justify-between gap-4 p-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 border-b border-slate-200">
          <div>
            <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold tracking-wider uppercase">Registro #{{ log.id }}</p>
            <h2 id="log-detail-title" class="m-0 text-xl font-bold text-slate-900">
              Detalle de operación
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
          <section class="p-4 bg-gradient-to-br from-brand-green-100/70 to-white border border-brand-green-200 rounded-xl">
            <div class="flex items-center gap-3">
              <FileJson :size="22" class="text-brand-green-800 shrink-0" aria-hidden="true" />

              <div class="grid">
                <span class="text-slate-500 text-xs font-medium">{{ log.module }}</span>
                <strong class="text-slate-900 font-bold text-base">{{ actionLabel(log.action) }}</strong>
              </div>
            </div>

            <p class="mt-3 mb-0 text-slate-600 text-sm leading-relaxed">{{ log.description }}</p>
          </section>

          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 m-0">
            <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-lg">
              <dt class="flex items-center gap-2 text-slate-500 text-xs font-bold uppercase tracking-wider">
                <CircleUserRound
                  :size="18"
                  class="shrink-0"
                  aria-hidden="true"
                />
                Usuario
              </dt>

              <dd class="grid gap-0.5 mt-2 m-0 text-slate-900 text-sm">
                <strong class="font-bold">
                  {{ log.user?.name ?? 'Usuario no disponible' }}
                </strong>
                <span v-if="log.user" class="text-slate-500 text-xs">
                  @{{ log.user.username }}
                </span>
              </dd>
            </div>

            <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-lg">
              <dt class="flex items-center gap-2 text-slate-500 text-xs font-bold uppercase tracking-wider">
                <CalendarClock
                  :size="18"
                  class="shrink-0"
                  aria-hidden="true"
                />
                Fecha
              </dt>

              <dd class="mt-2 m-0 text-slate-900 text-sm font-medium">{{ formatDate(log.created_at) }}</dd>
            </div>

            <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-lg">
              <dt class="flex items-center gap-2 text-slate-500 text-xs font-bold uppercase tracking-wider">
                <Globe2 :size="18" class="shrink-0" aria-hidden="true" />
                Dirección IP
              </dt>

              <dd class="mt-2 m-0 font-mono text-slate-900 text-sm font-medium">{{ log.ip_address ?? 'No registrada' }}</dd>
            </div>

            <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-lg">
              <dt class="text-slate-500 text-xs font-bold uppercase tracking-wider">Recurso afectado</dt>

              <dd v-if="log.subject" class="mt-2 m-0 text-slate-900 text-sm font-medium">
                {{ log.subject.type }} #{{ log.subject.id }}
              </dd>

              <dd v-else class="mt-2 m-0 text-slate-400 text-sm">
                Sin recurso asociado
              </dd>
            </div>
          </dl>

          <section class="grid gap-4">
            <header class="flex items-center justify-between gap-4">
              <div>
                <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase tracking-wider">Cambios registrados</p>
                <h3 class="m-0 text-base font-bold text-slate-900">Valores anteriores y nuevos</h3>
              </div>

              <span class="inline-flex min-w-[2rem] min-h-[2rem] items-center justify-center text-xs font-bold text-brand-orange-900 bg-brand-orange-100 rounded-full">{{ changedValues.length }}</span>
            </header>

            <div
              v-if="changedValues.length === 0"
              class="p-6 text-center text-slate-500 text-sm bg-slate-50 rounded-lg border border-dashed border-slate-200"
            >
              Esta operación no contiene valores comparables.
            </div>

            <div
              v-else
              class="grid gap-3"
            >
              <article
                v-for="row in changedValues"
                :key="row.key"
                class="p-4 bg-white border border-slate-200 rounded-lg grid gap-3"
              >
                <strong class="block text-slate-900 text-sm font-mono break-all">{{ row.key }}</strong>

                <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto_1fr] items-center gap-3">
                  <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-md min-w-0">
                    <span class="block mb-1.5 text-slate-400 text-xs font-bold uppercase">Anterior</span>
                    <pre class="m-0 font-mono text-slate-800 text-xs whitespace-pre-wrap break-all">{{ formatValue(row.oldValue) }}</pre>
                  </div>

                  <ArrowRight
                    :size="20"
                    class="hidden sm:block text-brand-orange-800 shrink-0 mx-auto"
                    aria-hidden="true"
                  />

                  <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-md min-w-0">
                    <span class="block mb-1.5 text-slate-400 text-xs font-bold uppercase">Nuevo</span>
                    <pre class="m-0 font-mono text-slate-800 text-xs whitespace-pre-wrap break-all">{{ formatValue(row.newValue) }}</pre>
                  </div>
                </div>
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