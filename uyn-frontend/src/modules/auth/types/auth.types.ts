export type UserStatus = 'active' | 'inactive'

export interface AuthUser {
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

export interface LoginPayload {
  login: string
  password: string
  device_name?: string
}

export interface LoginResponse {
  message: string
  token_type: 'Bearer' | string
  access_token: string
  user: AuthUser
}