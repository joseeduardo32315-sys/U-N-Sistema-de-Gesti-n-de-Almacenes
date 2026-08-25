import type {
  PayrollRuleStatus,
  PayrollUserReference,
} from '@/modules/payroll-settings/types/employee-compensation.types'

export interface EmbroideryProcessReference {
  id: number
  name: string
}

export interface EmbroideryOperationReference {
  id: number
  name: string
  flow_order?: number
  payroll_calculation_type?: string

  process: EmbroideryProcessReference | null
}

export interface EmbroideryPaymentSetting {
  id: number

  stitch_price: string
  application_price: string
  payment_percentage: string

  minimum_payment_per_piece: string
  default_payment_per_piece: string

  effective_from: string
  effective_to: string | null

  status: PayrollRuleStatus
  status_label: string
  is_current: boolean

  notes: string | null

  operation_process: EmbroideryOperationReference
  created_by: PayrollUserReference | null

  created_at: string
  updated_at: string
}

export interface EmbroideryPaymentSettingsQuery {
  operation_process_id?: number | ''
  status?: PayrollRuleStatus | 'all'
  active_on?: string
  page?: number
  per_page?: number
}

export interface CreateEmbroideryPaymentSettingPayload {
  operation_process_id: number

  stitch_price: string
  application_price: string
  payment_percentage: string

  minimum_payment_per_piece: string
  default_payment_per_piece: string

  effective_from: string
  effective_to: string | null

  notes: string | null
}

export interface UpdateEmbroideryPaymentSettingPayload {
  effective_to: string | null
  status: PayrollRuleStatus
  notes: string | null
}