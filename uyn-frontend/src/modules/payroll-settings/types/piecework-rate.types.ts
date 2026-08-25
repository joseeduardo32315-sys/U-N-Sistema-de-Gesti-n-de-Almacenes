import type {
  PayrollEmployeeReference,
  PayrollRuleStatus,
  PayrollUserReference,
} from '@/modules/payroll-settings/types/employee-compensation.types'

export interface PieceworkProcessReference {
  id: number
  name: string
}

export interface PieceworkOperationReference {
  id: number
  name: string
  flow_order: number
  payroll_calculation_type?: string

  process: PieceworkProcessReference | null
}

export interface PieceworkRate {
  id: number

  amount_per_piece: string

  effective_from: string
  effective_to: string | null

  status: PayrollRuleStatus
  status_label: string
  is_current: boolean

  notes: string | null

  employee: PayrollEmployeeReference
  operation_process: PieceworkOperationReference
  created_by: PayrollUserReference | null

  created_at: string
  updated_at: string
}

export interface PieceworkRatesQuery {
  search?: string
  employee_id?: number | ''
  operation_process_id?: number | ''
  status?: PayrollRuleStatus | 'all'
  active_on?: string
  page?: number
  per_page?: number
}

export interface CreatePieceworkRatePayload {
  employee_id: number
  operation_process_id: number
  amount_per_piece: string
  effective_from: string
  effective_to: string | null
  notes: string | null
}

export interface UpdatePieceworkRatePayload {
  effective_to: string | null
  status: PayrollRuleStatus
  notes: string | null
}