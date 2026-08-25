import type { PayrollUserReference, PayrollEmployeeReference } from '@/modules/payroll-settings/types/employee-compensation.types'
import type { ApiQueryParams } from '@/types/api'

export type PayrollPeriodStatus =
  | 'draft'
  | 'generated'
  | 'closed'
  | 'cancelled'

export type PayrollPeriodFrequency =
  | 'weekly'
  | 'biweekly'
  | 'monthly'

export interface PayrollPeriodDetail {
  id: number
  source_type: string
  production_operation_log_id: number | null
  employee_compensation_id: number | null
  description: string
  quantity: number
  unit_amount: string
  amount: string
  occurred_at: string
  calculation_snapshot: Record<string, unknown> | null
}

export interface PayrollEmployeeSummary {
  id: number
  payment_type: 'piecework' | 'fixed' | 'mixed'
  piecework_amount: string
  fixed_amount: string
  total_amount: string
  status: string
  employee: PayrollEmployeeReference
  details: PayrollPeriodDetail[]
}

export interface PayrollPeriod {
  id: number
  code: string
  frequency: PayrollPeriodFrequency
  frequency_label: string
  start_date: string
  end_date: string
  payment_date: string | null
  status: PayrollPeriodStatus
  status_label: string
  notes: string | null
  generated_at: string | null
  closed_at: string | null
  created_by: PayrollUserReference | null
  generated_by: PayrollUserReference | null
  closed_by: PayrollUserReference | null
  employee_summaries_count: number
  employee_summaries?: PayrollEmployeeSummary[]
  created_at: string
}

export interface PayrollPeriodsQuery extends ApiQueryParams {
  frequency?: PayrollPeriodFrequency | ''
  status?: PayrollPeriodStatus | 'all'
  from?: string
  to?: string
  search?: string
  page?: number
  per_page?: number
}

export interface CreatePayrollPeriodPayload {
  code: string
  frequency: PayrollPeriodFrequency
  start_date: string
  end_date: string
  payment_date?: string
  notes?: string
}

export interface UpdatePayrollPeriodPayload {
  payment_date?: string
  notes?: string
}
