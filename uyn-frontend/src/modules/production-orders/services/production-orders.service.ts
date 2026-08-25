import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'
import { cleanQueryParams } from '@/utils/query-params'

import type {
  ApiResource,
  ApiResourceMessage,
  PaginatedResponse,
} from '@/types/api'

import type {
  CreateProductionOrderPayload,
  ProductionOrder,
  ProductionOrdersQuery,
  UpdateProductionOrderPayload,
} from '@/modules/production-orders/types/production-order.types'

export const productionOrdersService = {
  async list(
    query: ProductionOrdersQuery = {},
  ): Promise<PaginatedResponse<ProductionOrder>> {
    const response = await api.get<
      PaginatedResponse<ProductionOrder>
    >(ENDPOINTS.productionOrders.index, {
      params: cleanQueryParams({
        search: query.search,
        status: query.status,
        priority: query.priority,
        date_from: query.date_from,
        date_to: query.date_to,
        page: query.page,
        per_page: query.per_page,
      }),
    })

    return response.data
  },

  async show(id: number): Promise<ProductionOrder> {
    const response = await api.get<
      ApiResource<ProductionOrder>
    >(ENDPOINTS.productionOrders.show(id))

    return response.data.data
  },

  async create(
    payload: CreateProductionOrderPayload,
  ): Promise<ApiResourceMessage<ProductionOrder>> {
    const response = await api.post<
      ApiResourceMessage<ProductionOrder>
    >(ENDPOINTS.productionOrders.create, payload)

    return response.data
  },

  async update(
    id: number,
    payload: UpdateProductionOrderPayload,
  ): Promise<ApiResourceMessage<ProductionOrder>> {
    const response = await api.patch<
      ApiResourceMessage<ProductionOrder>
    >(ENDPOINTS.productionOrders.update(id), payload)

    return response.data
  },
}