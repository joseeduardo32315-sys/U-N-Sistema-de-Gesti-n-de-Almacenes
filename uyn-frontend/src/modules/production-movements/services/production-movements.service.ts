import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'
import { cleanQueryParams } from '@/utils/query-params'

import type {
  ApiResource,
  ApiResourceMessage,
  PaginatedResponse,
} from '@/types/api'

import type {
  CreateProductionMovementPayload,
  ProductionMovement,
  ProductionMovementsQuery,
} from '@/modules/production-movements/types/production-movement.types'

export const productionMovementsService = {
  async list(
    query: ProductionMovementsQuery = {},
  ): Promise<PaginatedResponse<ProductionMovement>> {
    const response = await api.get<
      PaginatedResponse<ProductionMovement>
    >(ENDPOINTS.productionMovements.index, {
      params: cleanQueryParams({
        search: query.search,
        garment_cut_id: query.garment_cut_id,
        target_type: query.target_type,
        process_id: query.process_id,
        from_area_id: query.from_area_id,
        to_area_id: query.to_area_id,
        status: query.status,
        page: query.page,
        per_page: query.per_page,
      }),
    })

    return response.data
  },

  async show(
    id: number,
  ): Promise<ProductionMovement> {
    const response = await api.get<
      ApiResource<ProductionMovement>
    >(ENDPOINTS.productionMovements.show(id))

    return response.data.data
  },

  async create(
    payload: CreateProductionMovementPayload,
  ): Promise<
    ApiResourceMessage<ProductionMovement>
  > {
    const response = await api.post<
      ApiResourceMessage<ProductionMovement>
    >(
      ENDPOINTS.productionMovements.create,
      payload,
    )

    return response.data
  },

  async receive(
    id: number,
  ): Promise<
    ApiResourceMessage<ProductionMovement>
  > {
    /*
     * El endpoint de recepción no acepta parámetros.
     * No enviar notas ni un objeto vacío explícito.
     */
    const response = await api.post<
      ApiResourceMessage<ProductionMovement>
    >(
      ENDPOINTS.productionMovements.receive(id),
    )

    return response.data
  },
}