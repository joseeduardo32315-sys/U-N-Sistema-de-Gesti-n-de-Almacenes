import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'
import { cleanQueryParams } from '@/utils/query-params'

import type {
  ApiResource,
  ApiResourceMessage,
  PaginatedResponse,
} from '@/types/api'

import type {
  CreatePieceworkRatePayload,
  PieceworkRate,
  PieceworkRatesQuery,
  UpdatePieceworkRatePayload,
} from '@/modules/payroll-settings/types/piecework-rate.types'

export const pieceworkRatesService = {
  async list(
    query: PieceworkRatesQuery = {},
  ): Promise<PaginatedResponse<PieceworkRate>> {
    const response = await api.get<
      PaginatedResponse<PieceworkRate>
    >(ENDPOINTS.pieceworkRates.index, {
      params: cleanQueryParams({
        search: query.search,
        employee_id: query.employee_id,
        operation_process_id:
          query.operation_process_id,
        status: query.status,
        active_on: query.active_on,
        page: query.page,
        per_page: query.per_page,
      }),
    })

    return response.data
  },

  async show(id: number): Promise<PieceworkRate> {
    const response = await api.get<
      ApiResource<PieceworkRate>
    >(ENDPOINTS.pieceworkRates.show(id))

    return response.data.data
  },

  async create(
    payload: CreatePieceworkRatePayload,
  ): Promise<ApiResourceMessage<PieceworkRate>> {
    const response = await api.post<
      ApiResourceMessage<PieceworkRate>
    >(
      ENDPOINTS.pieceworkRates.create,
      payload,
    )

    return response.data
  },

  async update(
    id: number,
    payload: UpdatePieceworkRatePayload,
  ): Promise<ApiResourceMessage<PieceworkRate>> {
    const response = await api.patch<
      ApiResourceMessage<PieceworkRate>
    >(
      ENDPOINTS.pieceworkRates.update(id),
      payload,
    )

    return response.data
  },
}