import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'
import { cleanQueryParams } from '@/utils/query-params'

import type { ApiResource } from '@/types/api'
import type {
  PieceType,
  PieceTypesQuery,
} from '@/modules/piece-types/types/piece-type.types'

export const pieceTypesService = {
  async list(
    query: PieceTypesQuery = {},
  ): Promise<PieceType[]> {
    const response = await api.get<
      ApiResource<PieceType[]>
    >(ENDPOINTS.pieceTypes, {
      params: cleanQueryParams({
        search: query.search,
        status: query.status ?? 'active',
      }),
    })

    return response.data.data
  },
}