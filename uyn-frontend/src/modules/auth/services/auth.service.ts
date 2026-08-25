import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'

import type { ApiResource } from '@/types/api'
import type {
  AuthUser,
  LoginPayload,
  LoginResponse,
} from '@/modules/auth/types/auth.types'

export const authService = {
  async login(
    payload: LoginPayload,
  ): Promise<LoginResponse> {
    const response = await api.post<LoginResponse>(
      ENDPOINTS.auth.login,
      {
        ...payload,
        device_name: payload.device_name ?? 'uyn-web',
      },
    )

    return response.data
  },

  async me(): Promise<AuthUser> {
    const response = await api.get<ApiResource<AuthUser>>(
      ENDPOINTS.auth.me,
    )

    return response.data.data
  },

  async logout(): Promise<void> {
    await api.post(ENDPOINTS.auth.logout)
  },
}