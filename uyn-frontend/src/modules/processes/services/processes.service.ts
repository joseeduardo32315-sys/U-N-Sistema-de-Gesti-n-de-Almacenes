import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'

import type { ApiResource } from '@/types/api'
import type { ProductionProcess } from '@/modules/processes/types/process.types'

export const processesService = {
  async list(): Promise<ProductionProcess[]> {
    const response = await api.get<
      ApiResource<ProductionProcess[]>
    >(ENDPOINTS.processes)

    return response.data.data
  },
}