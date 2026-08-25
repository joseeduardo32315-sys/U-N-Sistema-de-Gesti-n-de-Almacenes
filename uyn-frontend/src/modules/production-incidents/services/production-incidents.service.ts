import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'
import { cleanQueryParams } from '@/utils/query-params'
import type { ProductionMovement } from '@/modules/production-movements/types/production-movement.types'

import type {
  ApiResource,
  ApiResourceMessage,
  PaginatedResponse,
} from '@/types/api'

import type {
  CreateProductionIncidentPayload,
  ProductionIncident,
  ProductionIncidentsQuery,
  ResolveProductionIncidentPayload,
  ReturnIncidentForReworkPayload,
  UpdateProductionIncidentPayload,
} from '@/modules/production-incidents/types/production-incident.types'

export const productionIncidentsService = {
  async list(
    query: ProductionIncidentsQuery = {},
  ): Promise<PaginatedResponse<ProductionIncident>> {
    const response = await api.get<
      PaginatedResponse<ProductionIncident>
    >(ENDPOINTS.productionIncidents.index, {
      params: cleanQueryParams({
        incident_type: query.incident_type,
        status: query.status,
        garment_cut_id: query.garment_cut_id,
        production_movement_id:
          query.production_movement_id,
        responsible_employee_id:
          query.responsible_employee_id,
        from: query.from,
        to: query.to,
        page: query.page,
        per_page: query.per_page,
      }),
    })

    return response.data
  },

  async show(
    id: number,
  ): Promise<ProductionIncident> {
    const response = await api.get<
      ApiResource<ProductionIncident>
    >(ENDPOINTS.productionIncidents.show(id))

    return response.data.data
  },

  async create(
    payload: CreateProductionIncidentPayload,
  ): Promise<ApiResourceMessage<ProductionIncident>> {
    const response = await api.post<
      ApiResourceMessage<ProductionIncident>
    >(
      ENDPOINTS.productionIncidents.create,
      payload,
    )

    return response.data
  },

  async update(
    id: number,
    payload: UpdateProductionIncidentPayload,
  ): Promise<ApiResourceMessage<ProductionIncident>> {
    const response = await api.patch<
      ApiResourceMessage<ProductionIncident>
    >(
      ENDPOINTS.productionIncidents.update(id),
      payload,
    )

    return response.data
  },

  async resolve(
    id: number,
    payload: ResolveProductionIncidentPayload,
  ): Promise<ApiResourceMessage<ProductionIncident>> {
    const response = await api.post<
      ApiResourceMessage<ProductionIncident>
    >(
      ENDPOINTS.productionIncidents.resolve(id),
      payload,
    )

    return response.data
  },

  async returnForRework(
    id: number,
    payload: ReturnIncidentForReworkPayload,
    ): Promise<ApiResourceMessage<ProductionMovement>> {
    const response = await api.post<
        ApiResourceMessage<ProductionMovement>
    >(
        ENDPOINTS.productionIncidents.returnForRework(id),
        payload,
    )

    return response.data
    },
}