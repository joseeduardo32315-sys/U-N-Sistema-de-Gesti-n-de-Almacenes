import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'
import { cleanQueryParams } from '@/utils/query-params'

import type { ApiResource } from '@/types/api'
import type {
  Size,
  SizesQuery,
} from '@/modules/sizes/types/size.types'

export const sizesService = {
  async list(
    query: SizesQuery = {},
  ): Promise<Size[]> {
    const response = await api.get<ApiResource<Size[]>>(
      ENDPOINTS.sizes,
      {
        params: cleanQueryParams({
          status: query.status ?? 'active',
        }),
      },
    )

    return response.data.data
  },
}