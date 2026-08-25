import type { ApiQueryParams } from '@/types/api'

export interface ProductionCutReportItem {
  id: number
  status: string
  status_label: string
  total_sizes: number
  total_pieces: number
  effective_pieces: number
  current_area: { id: number; name: string } | null
  garment_model: { id: number; code: string; name: string } | null
  production_order: { id: number; code: string; status: string } | null
  movement_summary: {
    movements_count: number
    dispatched_quantity: number
    received_quantity: number
    completed_quantity: number
    processed_quantity: number
    resolved_loss_quantity: number
    open_incidents_count: number
  }
  progress: {
    completed_percentage: number
    processed_percentage: number
  }
}

export interface ProductionProcessReportItem {
  process: { id: number; name: string; flow_order: number }
  operation_process: { id: number; name: string; flow_order: number; payroll_calculation_type: string }
  stats: {
    movements_count: number
    dispatched_quantity: number
    received_quantity: number
    in_progress_quantity: number
    completed_quantity: number
    processed_quantity: number
    resolved_loss_quantity: number
    open_incidents_count: number
  }
}

export interface ProductionLossReportItem {
  group: {
    type: 'garment_cut' | 'process' | 'responsible_employee'
    id: number
    name: string
    code?: string
  }
  stats: {
    incidents_count: number
    open_incidents_count: number
    resolved_incidents_count: number
    cancelled_incidents_count: number
    affected_quantity: number
    resolved_loss_quantity: number
  }
}

export interface ProductionReworkReportItem {
  process: { id: number; name: string }
  operation_process: { id: number; name: string }
  stats: {
    incidents_count: number
    reworks_count: number
    rework_quantity: number
    rework_percentage: number
  }
}

export interface PayrollPeriodReportSummary {
  payroll_period: {
    id: number
    name: string
    start_date: string
    end_date: string
    status: string
  }
  stats: {
    total_piecework: number
    total_fixed: number
    total_payroll: number
    employees_count: number
    processed_pieces: number
  }
}

export interface PayrollEmployeeReportItem {
  employee: {
    id: number
    name: string
    worker_type: string
    area: { id: number; name: string }
  }
  stats: {
    total_payout: string
    periods_count: number
    details_count: number
  }
}

export interface ReportQueryParams extends ApiQueryParams {
  from?: string
  to?: string
  status?: string
  current_area_id?: number | ''
  garment_model_id?: number | ''
  process_id?: number | ''
  operation_process_id?: number | ''
  target_type?: string
  incident_type?: string
  responsible_employee_id?: number | ''
  group_by?: string
  search?: string
  page?: number
  per_page?: number
}
