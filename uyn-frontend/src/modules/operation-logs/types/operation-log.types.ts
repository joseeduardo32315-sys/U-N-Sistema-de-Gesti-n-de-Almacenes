export interface OperationLogUser {
  id: number
  name: string
  username: string
  email: string
}

export interface OperationLogSubject {
  type: string
  id: number
}

export type OperationLogValues = Record<string, unknown>

export interface OperationLog {
  id: number
  module: string
  action: string
  description: string
  subject: OperationLogSubject | null
  old_values: OperationLogValues | null
  new_values: OperationLogValues | null
  ip_address: string | null
  user: OperationLogUser | null
  created_at: string
}

export interface OperationLogsQuery {
  user_id?: number | ''
  module?: string
  action?: string
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}