import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'
import { cleanQueryParams } from '@/utils/query-params'

import type {
  ApiResource,
  ApiResourceMessage,
  PaginatedResponse,
} from '@/types/api'

import type {
  CreateEmployeePayload,
  Employee,
  EmployeesQuery,
  UpdateEmployeePayload,
} from '@/modules/employees/types/employee.types'

export const employeesService = {
  async list(
    query: EmployeesQuery = {},
  ): Promise<PaginatedResponse<Employee>> {
    const response = await api.get<
      PaginatedResponse<Employee>
    >(ENDPOINTS.employees.index, {
      params: cleanQueryParams({
        search: query.search,
        area_id: query.area_id,
        worker_type: query.worker_type,
        status: query.status,
        page: query.page,
        per_page: query.per_page,
      }),
    })

    return response.data
  },

  async show(id: number): Promise<Employee> {
    const response = await api.get<ApiResource<Employee>>(
      ENDPOINTS.employees.show(id),
    )

    return response.data.data
  },

  async create(
    payload: CreateEmployeePayload,
  ): Promise<ApiResourceMessage<Employee>> {
    const response = await api.post<
      ApiResourceMessage<Employee>
    >(ENDPOINTS.employees.create, payload)

    return response.data
  },

  async update(
    id: number,
    payload: UpdateEmployeePayload,
  ): Promise<ApiResourceMessage<Employee>> {
    const response = await api.patch<
      ApiResourceMessage<Employee>
    >(ENDPOINTS.employees.update(id), payload)

    return response.data
  },

  async activate(
    id: number,
  ): Promise<ApiResourceMessage<Employee>> {
    const response = await api.post<
      ApiResourceMessage<Employee>
    >(ENDPOINTS.employees.activate(id))

    return response.data
  },

  async deactivate(
    id: number,
  ): Promise<ApiResourceMessage<Employee>> {
    const response = await api.post<
      ApiResourceMessage<Employee>
    >(ENDPOINTS.employees.deactivate(id))

    return response.data
  },
}