import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'
import { cleanQueryParams } from '@/utils/query-params'

import type {
  ApiResource,
  ApiResourceMessage,
  PaginatedResponse,
} from '@/types/api'

import type {
  CreatePayrollPeriodPayload,
  PayrollPeriod,
  PayrollPeriodsQuery,
  UpdatePayrollPeriodPayload,
} from '@/modules/payroll-settings/types/payroll-period.types'

export const payrollPeriodsService = {
  async list(
    query: PayrollPeriodsQuery = {},
  ): Promise<PaginatedResponse<PayrollPeriod>> {
    const response = await api.get<
      PaginatedResponse<PayrollPeriod>
    >(ENDPOINTS.payrollPeriods.index, {
      params: cleanQueryParams(query),
    })
    return response.data
  },

  async show(id: number): Promise<PayrollPeriod> {
    const response = await api.get<ApiResource<PayrollPeriod>>(
      ENDPOINTS.payrollPeriods.show(id),
    )
    return response.data.data
  },

  async create(
    payload: CreatePayrollPeriodPayload,
  ): Promise<ApiResourceMessage<PayrollPeriod>> {
    const response = await api.post<
      ApiResourceMessage<PayrollPeriod>
    >(ENDPOINTS.payrollPeriods.create, payload)
    return response.data
  },

  async update(
    id: number,
    payload: UpdatePayrollPeriodPayload,
  ): Promise<ApiResourceMessage<PayrollPeriod>> {
    const response = await api.patch<
      ApiResourceMessage<PayrollPeriod>
    >(ENDPOINTS.payrollPeriods.update(id), payload)
    return response.data
  },

  async generate(
    id: number,
    payload?: { notes?: string },
  ): Promise<ApiResourceMessage<PayrollPeriod>> {
    const response = await api.post<
      ApiResourceMessage<PayrollPeriod>
    >(ENDPOINTS.payrollPeriods.generate(id), payload)
    return response.data
  },

  async close(
    id: number,
    payload?: { notes?: string },
  ): Promise<ApiResourceMessage<PayrollPeriod>> {
    const response = await api.post<
      ApiResourceMessage<PayrollPeriod>
    >(ENDPOINTS.payrollPeriods.close(id), payload)
    return response.data
  },
}
