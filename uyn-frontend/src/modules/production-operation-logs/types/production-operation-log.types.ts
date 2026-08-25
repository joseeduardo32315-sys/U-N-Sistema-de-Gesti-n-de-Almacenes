export type ProductionOperationLogStatus =
  | 'pending'
  | 'in_progress'
  | 'completed'
  | 'cancelled'
  | 'with_incident'

export interface ProductionOperationLogArea {
  id: number
  name: string
}

export interface ProductionOperationLogEmployee {
  id: number
  name: string
  worker_type: 'internal' | 'external'
  status: 'active' | 'inactive'
  area?: ProductionOperationLogArea | string | null
}

export interface ProductionOperationLogProcess {
  id: number
  name: string
  flow_order?: number
  payroll_calculation_type?: string
}

export interface ProductionOperationLogMovement {
  id: number
  status: string
  status_label?: string
  quantity?: number
  effective_quantity?: number
}

export interface ProductionOperationLog {
  id: number
  quantity_processed: number
  stitches_count: number
  applications_count: number

  status: ProductionOperationLogStatus
  status_label: string

  start_time: string | null
  end_time: string | null
  notes: string | null

  payout_amount?: string | null
  payout_status?: string | null
  payout_snapshot?: Record<string, unknown> | null

  employee: ProductionOperationLogEmployee | null
  operation_process: ProductionOperationLogProcess | null
  production_movement?: ProductionOperationLogMovement | null

  created_at: string
  updated_at: string
}

export interface ProductionOperationLogsQuery {
  employee_id?: number | ''
  status?: ProductionOperationLogStatus | ''
  page?: number
  per_page?: number
}

export interface AssignOperationEmployeePayload {
  employee_id: number
  notes: string | null
}

export interface UpdateOperationProgressPayload {
  start?: boolean
  complete?: boolean
  quantity_processed?: number
  stitches_count?: number
  applications_count?: number
  notes?: string | null
}

export type OperationProgressMode =
  | 'start'
  | 'update'
  | 'complete'