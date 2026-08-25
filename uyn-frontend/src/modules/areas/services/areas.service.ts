import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'

import type { ApiResource } from '@/types/api'
import type { Area } from '@/modules/areas/types/area.types'

export const areasService = {
  async list(): Promise<Area[]> {
    const response = await api.get<ApiResource<Area[]>>(
      ENDPOINTS.areas,
    )

    return response.data.data
  },
}