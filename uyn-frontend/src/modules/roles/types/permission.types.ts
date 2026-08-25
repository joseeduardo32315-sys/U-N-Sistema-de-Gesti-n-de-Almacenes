export interface Permission {
  id: number
  name: string
  module: string
  action: string
  guard_name: string
  roles_count: number
  created_at: string
  updated_at: string
}