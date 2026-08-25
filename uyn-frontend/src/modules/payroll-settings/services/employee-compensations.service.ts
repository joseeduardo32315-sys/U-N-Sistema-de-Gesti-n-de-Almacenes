import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'
import { cleanQueryParams } from '@/utils/query-params'

import type {
  ApiResource,
  ApiResourceMessage,
  PaginatedResponse,
} from '@/types/api'

import type {
  CreateEmployeeCompensationPayload,
  EmployeeCompensation,
  EmployeeCompensationsQuery,
  UpdateEmployeeCompensationPayload,
} from '@/modules/payroll-settings/types/employee-compensation.types'

export const employeeCompensationsService = {
  async list(
    query: EmployeeCompensationsQuery = {},
  ): Promise<PaginatedResponse<EmployeeCompensation>> {
    const response = await api.get<
      PaginatedResponse<EmployeeCompensation>
    >(ENDPOINTS.employeeCompensations.index, {
      params: cleanQueryParams({
        employee_id: query.employee_id,
        payment_type: query.payment_type,
        status: query.status,
        active_on: query.active_on,
        page: query.page,
        per_page: query.per_page,
      }),
    })

    return response.data
  },

  async show(
    id: number,
  ): Promise<EmployeeCompensation> {
    const response = await api.get<
      ApiResource<EmployeeCompensation>
    >(ENDPOINTS.employeeCompensations.show(id))

    return response.data.data
  },

  async create(
    payload: CreateEmployeeCompensationPayload,
  ): Promise<ApiResourceMessage<EmployeeCompensation>> {
    const response = await api.post<
      ApiResourceMessage<EmployeeCompensation>
    >(
      ENDPOINTS.employeeCompensations.create,
      payload,
    )

    return response.data
  },

  async update(
    id: number,
    payload: UpdateEmployeeCompensationPayload,
  ): Promise<ApiResourceMessage<EmployeeCompensation>> {
    const response = await api.patch<
      ApiResourceMessage<EmployeeCompensation>
    >(
      ENDPOINTS.employeeCompensations.update(id),
      payload,
    )

    return response.data
  },
}