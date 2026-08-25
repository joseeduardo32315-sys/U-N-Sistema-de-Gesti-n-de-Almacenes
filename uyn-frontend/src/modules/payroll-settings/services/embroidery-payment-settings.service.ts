import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'
import { cleanQueryParams } from '@/utils/query-params'

import type {
  ApiResource,
  ApiResourceMessage,
  PaginatedResponse,
} from '@/types/api'

import type {
  CreateEmbroideryPaymentSettingPayload,
  EmbroideryPaymentSetting,
  EmbroideryPaymentSettingsQuery,
  UpdateEmbroideryPaymentSettingPayload,
} from '@/modules/payroll-settings/types/embroidery-payment-setting.types'

export const embroideryPaymentSettingsService = {
  async list(
    query: EmbroideryPaymentSettingsQuery = {},
  ): Promise<
    PaginatedResponse<EmbroideryPaymentSetting>
  > {
    const response = await api.get<
      PaginatedResponse<EmbroideryPaymentSetting>
    >(ENDPOINTS.embroideryPaymentSettings.index, {
      params: cleanQueryParams({
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

  async show(
    id: number,
  ): Promise<EmbroideryPaymentSetting> {
    const response = await api.get<
      ApiResource<EmbroideryPaymentSetting>
    >(
      ENDPOINTS.embroideryPaymentSettings.show(id),
    )

    return response.data.data
  },

  async create(
    payload: CreateEmbroideryPaymentSettingPayload,
  ): Promise<
    ApiResourceMessage<EmbroideryPaymentSetting>
  > {
    const response = await api.post<
      ApiResourceMessage<EmbroideryPaymentSetting>
    >(
      ENDPOINTS.embroideryPaymentSettings.create,
      payload,
    )

    return response.data
  },

  async update(
    id: number,
    payload: UpdateEmbroideryPaymentSettingPayload,
  ): Promise<
    ApiResourceMessage<EmbroideryPaymentSetting>
  > {
    const response = await api.patch<
      ApiResourceMessage<EmbroideryPaymentSetting>
    >(
      ENDPOINTS.embroideryPaymentSettings.update(id),
      payload,
    )

    return response.data
  },
}