import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'

import type { ApiResource } from '@/types/api'
import type { Role } from '@/modules/roles/types/role.types'

export const rolesService = {
  async list(): Promise<Role[]> {
    const response = await api.get<ApiResource<Role[]>>(
      ENDPOINTS.roles,
    )

    return response.data.data
  },
}