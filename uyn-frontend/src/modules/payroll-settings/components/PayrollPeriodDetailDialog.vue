<script setup lang="ts">
import { ref, watch } from 'vue'
import {
  CircleDollarSign,
  Download,
  Lock,
  Play,
  X,
} from 'lucide-vue-next'
import Swal from 'sweetalert2'

import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { PERMISSIONS } from '@/config/permissions'
import { payrollPeriodsService } from '@/modules/payroll-settings/services/payroll-periods.service'
import { reportsService } from '@/modules/reports/services/reports.service'
import { getApiErrorMessage } from '@/utils/api-error'

import type { PayrollPeriod, PayrollEmployeeSummary } from '@/modules/payroll-settings/types/payroll-period.types'

const props = defineProps<{
  open: boolean
  periodId: number | null
}>()

const emit = defineEmits<{
  close: []
  updated: []
}>()

const authStore = useAuthStore()

// State
const period = ref<PayrollPeriod | null>(null)
const loading = ref(false)
const processing = ref(false)
const selectedSummary = ref<PayrollEmployeeSummary | null>(null)

async function loadPeriodDetails(): Promise<void> {
  if (!props.periodId) return
  loading.value = true
  try {
    const data = await payrollPeriodsService.show(props.periodId)
    period.value = data
  } catch (error) {
    void Swal.fire({
      title: 'Error al cargar detalles',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
    emit('close')
  } finally {
    loading.value = false
  }
}

// Generate totals
async function handleGenerate(): Promise<void> {
  if (!period.value || processing.value) return

  const result = await Swal.fire({
    title: '¿Generar totales de nómina?',
    text: 'Se procesarán los avances de producción y compensaciones fijas dentro del rango de fechas del periodo.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Generar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: 'var(--color-brand-green-700)',
  })

  if (!result.isConfirmed) return

  processing.value = true
  try {
    const res = await payrollPeriodsService.generate(period.value.id)
    void Swal.fire({
      title: 'Nómina Generada',
      text: res.message ?? 'Los totales de pago han sido procesados.',
      icon: 'success',
      confirmButtonText: 'Aceptar',
    })
    emit('updated')
    await loadPeriodDetails()
  } catch (error) {
    void Swal.fire({
      title: 'Error al generar',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    processing.value = false
  }
}

// Close payroll period
async function handleClosePeriod(): Promise<void> {
  if (!period.value || processing.value) return

  const result = await Swal.fire({
    title: '¿Cerrar y congelar periodo de nómina?',
    text: 'Esta acción congelará definitivamente todos los montos de pago, registrará las comisiones y bloqueará cualquier modificación futura. ¡No se puede deshacer!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Cerrar periodo',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: 'var(--color-danger)',
  })

  if (!result.isConfirmed) return

  processing.value = true
  try {
    const res = await payrollPeriodsService.close(period.value.id)
    void Swal.fire({
      title: 'Periodo Cerrado',
      text: res.message ?? 'El periodo de nómina ha sido cerrado y pagado.',
      icon: 'success',
      confirmButtonText: 'Aceptar',
    })
    emit('updated')
    await loadPeriodDetails()
  } catch (error) {
    void Swal.fire({
      title: 'Error al cerrar',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    processing.value = false
  }
}

// Export CSV of payroll
async function handleExport(): Promise<void> {
  if (!period.value) return
  try {
    await reportsService.exportPayrollPeriod(period.value.id)
  } catch (error) {
    void Swal.fire({
      title: 'Error de exportación',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  }
}

function selectSummary(summary: PayrollEmployeeSummary): void {
  selectedSummary.value = selectedSummary.value?.id === summary.id ? null : summary
}

function formatDate(value: string | null): string {
  if (!value) return '—'
  const date = new Date(`${value}T00:00:00`)
  return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium' }).format(date)
}

watch(
  () => props.open,
  (open) => {
    if (open) {
      selectedSummary.value = null
      void loadPeriodDetails()
    } else {
      period.value = null
    }
  },
)
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[1000] flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs" @click.self="emit('close')">
      <section
        class="w-full max-w-4xl max-h-[90dvh] bg-white rounded-xl shadow-xl overflow-hidden flex flex-col"
        role="dialog"
        aria-modal="true"
        aria-labelledby="detail-dialog-title"
      >
        <header class="flex items-center gap-3 p-4 sm:px-5 border-b border-slate-200">
          <div class="flex w-11 h-11 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-lg shrink-0">
            <CircleDollarSign :size="23" aria-hidden="true" />
          </div>
          <span class="grid flex-1 min-w-0">
            <small class="text-slate-500 text-xs font-bold uppercase tracking-wider">Periodo de pago</small>
            <h2 id="detail-dialog-title" class="m-0 text-base font-bold text-slate-900 truncate">
              {{ period?.code ?? 'Cargando...' }}
            </h2>
          </span>

          <button
            type="button"
            class="text-slate-500 hover:text-slate-700 bg-transparent border-0 cursor-pointer p-1 transition-colors disabled:opacity-50"
            aria-label="Cerrar detalles"
            :disabled="processing"
            @click="emit('close')"
          >
            <X :size="22" aria-hidden="true" />
          </button>
        </header>

        <!-- Loader -->
        <div v-if="loading" class="grid place-content-center h-96 gap-3 text-center text-slate-500">
          <div class="w-10 h-10 border-4 border-brand-green-100 border-t-brand-green-700 rounded-full animate-spin mx-auto"></div>
          <p class="m-0 text-sm">Consultando sábana de nómina...</p>
        </div>

        <!-- Content -->
        <template v-else-if="period">
          <div class="overflow-y-auto p-5 grid gap-5">
            <!-- Period Metadata -->
            <section class="bg-brand-green-50/70 border border-brand-green-100 rounded-xl p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
              <dl class="grid grid-cols-2 sm:grid-cols-3 gap-3 m-0">
                <div>
                  <dt class="text-[0.75rem] font-bold text-brand-green-950 uppercase">Folio</dt>
                  <dd class="m-0 text-sm font-bold text-slate-900 font-mono">{{ period.code }}</dd>
                </div>
                <div>
                  <dt class="text-[0.75rem] font-bold text-brand-green-950 uppercase">Frecuencia</dt>
                  <dd class="m-0 text-sm text-slate-800">{{ period.frequency_label }}</dd>
                </div>
                <div>
                  <dt class="text-[0.75rem] font-bold text-brand-green-950 uppercase">Periodo</dt>
                  <dd class="m-0 text-sm text-slate-800">{{ formatDate(period.start_date) }} al {{ formatDate(period.end_date) }}</dd>
                </div>
                <div>
                  <dt class="text-[0.75rem] font-bold text-brand-green-950 uppercase">Fecha programada de pago</dt>
                  <dd class="m-0 text-sm text-slate-800">{{ formatDate(period.payment_date) }}</dd>
                </div>
                <div>
                  <dt class="text-[0.75rem] font-bold text-brand-green-950 uppercase">Estado</dt>
                  <dd class="m-0">
                    <span
                      class="inline-block px-2 py-0.5 rounded-full text-xs font-bold"
                      :class="{
                        'bg-slate-200 text-slate-700': period.status === 'draft',
                        'bg-amber-50 text-amber-800 border border-amber-200': period.status === 'generated',
                        'bg-emerald-50 text-emerald-800 border border-emerald-200': period.status === 'closed',
                        'bg-rose-50 text-rose-700 border border-rose-200': period.status === 'cancelled',
                      }"
                    >
                      {{ period.status_label }}
                    </span>
                  </dd>
                </div>
                <div v-if="period.notes" class="col-span-full border-t border-brand-green-200 pt-2">
                  <dt class="text-[0.75rem] font-bold text-brand-green-950 uppercase">Notas</dt>
                  <dd class="m-0 text-sm text-slate-700">{{ period.notes }}</dd>
                </div>
              </dl>

              <!-- Actions inside metadata card -->
              <div class="flex flex-wrap gap-2">
                <button
                  v-if="period.status === 'draft' && authStore.can(PERMISSIONS.payroll.generate)"
                  type="button"
                  class="inline-flex min-h-[2.5rem] items-center justify-center gap-2 px-3 text-white bg-brand-green-700 hover:bg-brand-green-800 border border-brand-green-700 rounded-md font-[750] text-xs cursor-pointer transition-colors disabled:opacity-60"
                  :disabled="processing"
                  @click="handleGenerate"
                >
                  <Play :size="16" aria-hidden="true" />
                  Calcular Totales
                </button>

                <button
                  v-if="period.status === 'generated' && authStore.can(PERMISSIONS.payroll.close)"
                  type="button"
                  class="inline-flex min-h-[2.5rem] items-center justify-center gap-2 px-3 text-white bg-rose-600 hover:bg-rose-700 border border-rose-600 rounded-md font-[750] text-xs cursor-pointer transition-colors disabled:opacity-60"
                  :disabled="processing"
                  @click="handleClosePeriod"
                >
                  <Lock :size="16" aria-hidden="true" />
                  Cerrar y Pagar
                </button>

                <button
                  v-if="['generated', 'closed'].includes(period.status)"
                  type="button"
                  class="inline-flex min-h-[2.5rem] items-center justify-center gap-2 px-3 text-slate-700 bg-white border border-slate-300 hover:bg-slate-100 rounded-md font-[750] text-xs cursor-pointer transition-colors"
                  @click="handleExport"
                >
                  <Download :size="16" aria-hidden="true" />
                  Exportar CSV
                </button>
              </div>
            </section>

            <!-- Employee Summaries Table -->
            <section class="grid gap-3">
              <h3 class="m-0 text-base font-bold text-slate-900">Sábana de Pagos Calculados</h3>

              <div class="border border-slate-200 rounded-xl overflow-x-auto shadow-xs">
                <table class="w-full text-sm text-left border-collapse min-w-[38rem]">
                  <thead>
                    <tr class="bg-brand-green-50/80 text-brand-green-950 border-b border-slate-200 font-bold text-xs uppercase">
                      <th class="p-3 px-4">Trabajador</th>
                      <th class="p-3 px-4">Área</th>
                      <th class="p-3 px-4">Tipo Pago</th>
                      <th class="p-3 px-4">Destajo</th>
                      <th class="p-3 px-4">Fijo</th>
                      <th class="p-3 px-4">Total Neto</th>
                      <th class="p-3 px-4 text-right" aria-label="Ver desglose" />
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-200">
                    <template v-for="summary in period.employee_summaries" :key="summary.id">
                      <!-- Summary Row -->
                      <tr
                        class="hover:bg-brand-green-50/60 cursor-pointer transition-colors"
                        :class="{ 'bg-brand-green-50/80': selectedSummary?.id === summary.id }"
                        @click="selectSummary(summary)"
                      >
                        <td class="p-3 px-4">
                          <div class="grid">
                            <strong class="text-slate-900 font-bold text-sm">{{ summary.employee.name }}</strong>
                            <span class="text-xs text-slate-500">{{ summary.employee.worker_type === 'internal' ? 'Interno' : 'Maquilero Ext.' }}</span>
                          </div>
                        </td>
                        <td class="p-3 px-4 text-slate-700">{{ summary.employee.area ?? 'Sin área' }}</td>
                        <td class="p-3 px-4">
                          <span
                            class="inline-block px-2 py-0.5 rounded-sm text-xs font-bold uppercase"
                            :class="{
                              'bg-brand-orange-50 text-brand-orange-800': summary.payment_type === 'piecework',
                              'bg-brand-green-100 text-brand-green-900': summary.payment_type === 'fixed',
                              'bg-brand-green-200 text-brand-green-950': summary.payment_type === 'mixed',
                            }"
                          >
                            {{ summary.payment_type === 'piecework' ? 'Destajo' : summary.payment_type === 'fixed' ? 'Fijo' : 'Mixto' }}
                          </span>
                        </td>
                        <td class="p-3 px-4 font-mono">${{ summary.piecework_amount }}</td>
                        <td class="p-3 px-4 font-mono">${{ summary.fixed_amount }}</td>
                        <td class="p-3 px-4 font-mono font-bold text-brand-green-800">${{ summary.total_amount }}</td>
                        <td class="p-3 px-4 text-right">
                          <button type="button" class="bg-transparent border-0 text-brand-green-700 font-bold text-xs cursor-pointer hover:underline">
                            {{ selectedSummary?.id === summary.id ? 'Ocultar' : 'Conceptos' }}
                          </button>
                        </td>
                      </tr>

                      <!-- Nested Details Row (shown if expanded) -->
                      <tr v-if="selectedSummary?.id === summary.id" :key="`details-${summary.id}`" class="bg-brand-green-50/20">
                        <td colspan="7" class="p-0">
                          <div class="p-4 bg-white border-y border-slate-200 shadow-inner">
                            <h4 class="m-0 mb-3 text-xs font-bold text-brand-green-950 uppercase tracking-wider">Desglose de Conceptos y Operaciones</h4>
                            <ul v-if="summary.details && summary.details.length > 0" class="list-none p-0 m-0 grid gap-2">
                              <li v-for="detail in summary.details" :key="detail.id">
                                <div class="flex justify-between items-center pb-2 border-b border-dashed border-slate-200 text-xs">
                                  <div class="grid gap-0.5">
                                    <strong class="text-slate-900 font-bold">{{ detail.description }}</strong>
                                    <small v-if="detail.occurred_at" class="text-slate-500">
                                      Fecha: {{ formatDate(detail.occurred_at.slice(0,10)) }}
                                    </small>
                                  </div>
                                  <div class="flex items-center gap-4">
                                    <span class="text-slate-600 font-mono">{{ detail.quantity }} uds × ${{ detail.unit_amount }}</span>
                                    <strong class="text-sm font-mono text-brand-green-800 font-bold">${{ detail.amount }}</strong>
                                  </div>
                                </div>
                              </li>
                            </ul>
                            <p v-else class="m-0 text-xs text-slate-500 text-center py-2">
                              No hay conceptos ni operaciones registradas en el rango de fechas.
                            </p>
                          </div>
                        </td>
                      </tr>
                    </template>

                    <tr v-if="!period.employee_summaries || period.employee_summaries.length === 0">
                      <td colspan="7" class="text-center p-8 text-slate-500 text-sm">
                        No hay pagos calculados. Utiliza "Calcular Totales" para procesar la información.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>
          </div>
        </template>
      </section>
    </div>
  </Teleport>
</template>

