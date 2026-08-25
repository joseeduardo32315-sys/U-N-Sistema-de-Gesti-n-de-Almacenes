import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'
import { cleanQueryParams } from '@/utils/query-params'

import type {
  ApiResource,
  ApiResourceMessage,
  PaginatedResponse,
} from '@/types/api'

import type {
  CreateGarmentCutPayload,
  GarmentCut,
  GarmentCutsQuery,
  UpdateGarmentCutPayload,
} from '@/modules/garment-cuts/types/garment-cut.types'

export const garmentCutsService = {
  async list(
    query: GarmentCutsQuery = {},
  ): Promise<PaginatedResponse<GarmentCut>> {
    const response = await api.get<
      PaginatedResponse<GarmentCut>
    >(ENDPOINTS.garmentCuts.index, {
      params: cleanQueryParams({
        search: query.search,
        production_order_id:
          query.production_order_id,
        garment_model_id:
          query.garment_model_id,
        current_area_id:
          query.current_area_id,
        status: query.status,
        page: query.page,
        per_page: query.per_page,
      }),
    })

    return response.data
  },

  async show(id: number): Promise<GarmentCut> {
    const response = await api.get<
      ApiResource<GarmentCut>
    >(ENDPOINTS.garmentCuts.show(id))

    return response.data.data
  },

  async create(
    payload: CreateGarmentCutPayload,
  ): Promise<ApiResourceMessage<GarmentCut>> {
    const response = await api.post<
      ApiResourceMessage<GarmentCut>
    >(ENDPOINTS.garmentCuts.create, payload)

    return response.data
  },

  async update(
    id: number,
    payload: UpdateGarmentCutPayload,
  ): Promise<ApiResourceMessage<GarmentCut>> {
    const response = await api.patch<
      ApiResourceMessage<GarmentCut>
    >(ENDPOINTS.garmentCuts.update(id), payload)

    return response.data
  },
}