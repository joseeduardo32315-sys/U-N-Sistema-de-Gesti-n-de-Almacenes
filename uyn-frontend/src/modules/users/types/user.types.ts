export type UserStatus = 'active' | 'inactive'

export interface User {
  id: number
  name: string
  username: string
  email: string
  status: UserStatus
  roles: string[]
  permissions: string[]
  created_at: string
  updated_at: string
}

export interface UsersQuery {
  search?: string
  status?: UserStatus | ''
  role?: string
  page?: number
  per_page?: number
}

export interface CreateUserPayload {
  name: string
  username: string
  email: string
  password: string
  password_confirmation: string
  role: string
  status: UserStatus
}

export interface UpdateUserPayload {
  name: string
  username: string
  email: string
  role: string
  password?: string
  password_confirmation?: string
}