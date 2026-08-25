export type PayrollRuleStatus =
  | 'active'
  | 'inactive'

export type EmployeePaymentType =
  | 'piecework'
  | 'fixed'

export type PaymentFrequency =
  | 'weekly'
  | 'biweekly'
  | 'monthly'

export interface PayrollEmployeeArea {
  id?: number
  name: string
}

export interface PayrollEmployeeReference {
  id: number
  name: string
  worker_type: 'internal' | 'external'
  status: 'active' | 'inactive'
  area: string | PayrollEmployeeArea | null
}

export interface PayrollUserReference {
  id: number
  name: string
}

export interface EmployeeCompensation {
  id: number

  payment_type: EmployeePaymentType
  payment_type_label: string

  payment_frequency: PaymentFrequency | null
  payment_frequency_label: string | null

  fixed_amount: string | null

  effective_from: string
  effective_to: string | null

  status: PayrollRuleStatus
  status_label: string
  is_current: boolean

  notes: string | null

  employee: PayrollEmployeeReference
  created_by: PayrollUserReference | null

  created_at: string
  updated_at: string
}

export interface EmployeeCompensationsQuery {
  employee_id?: number | ''
  payment_type?: EmployeePaymentType | ''
  status?: PayrollRuleStatus | 'all'
  active_on?: string
  page?: number
  per_page?: number
}

export interface CreateEmployeeCompensationPayload {
  employee_id: number
  payment_type: EmployeePaymentType

  payment_frequency?: PaymentFrequency
  fixed_amount?: string

  effective_from: string
  effective_to: string | null
  notes: string | null
}

export interface UpdateEmployeeCompensationPayload {
  effective_to: string | null
  status: PayrollRuleStatus
  notes: string | null
}