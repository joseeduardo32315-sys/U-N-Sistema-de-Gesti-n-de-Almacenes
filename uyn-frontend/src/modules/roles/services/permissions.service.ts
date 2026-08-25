import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'

import type { ApiResource } from '@/types/api'
import type { Permission } from '@/modules/roles/types/permission.types'

export const permissionsService = {
  async list(): Promise<Permission[]> {
    const response = await api.get<ApiResource<Permission[]>>(
      ENDPOINTS.permissions,
    )

    return response.data.data
  },
}