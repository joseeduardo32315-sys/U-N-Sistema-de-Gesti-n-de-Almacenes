import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'
import { cleanQueryParams } from '@/utils/query-params'

import type {
  ApiResource,
  ApiResourceMessage,
  PaginatedResponse,
} from '@/types/api'

import type {
  GarmentModel,
  GarmentModelsQuery,
  SaveGarmentModelPayload,
} from '@/modules/garment-models/types/garment-model.types'

function buildFormData(
  payload: SaveGarmentModelPayload,
  method?: 'PUT',
): FormData {
  const formData = new FormData()

  if (method) {
    formData.append('_method', method)
  }

  formData.append('code', payload.code)
  formData.append('name', payload.name)
  formData.append('description', payload.description)
  formData.append('size_range', payload.size_range)

  if (payload.status) {
    formData.append('status', payload.status)
  }

  if (payload.image) {
    formData.append('image', payload.image)
  }

  return formData
}

export const garmentModelsService = {
  async list(
    query: GarmentModelsQuery = {},
  ): Promise<PaginatedResponse<GarmentModel>> {
    const response = await api.get<
      PaginatedResponse<GarmentModel>
    >(ENDPOINTS.garmentModels.index, {
      params: cleanQueryParams({
        search: query.search,
        status: query.status,
        page: query.page,
        per_page: query.per_page,
      }),
    })

    return response.data
  },

  async show(id: number): Promise<GarmentModel> {
    const response = await api.get<
      ApiResource<GarmentModel>
    >(ENDPOINTS.garmentModels.show(id))

    return response.data.data
  },

  async create(
    payload: SaveGarmentModelPayload,
  ): Promise<ApiResourceMessage<GarmentModel>> {
    const formData = buildFormData(payload)

    const response = await api.post<
      ApiResourceMessage<GarmentModel>
    >(
      ENDPOINTS.garmentModels.create,
      formData,
    )

    return response.data
  },

  async update(
    id: number,
    payload: SaveGarmentModelPayload,
  ): Promise<ApiResourceMessage<GarmentModel>> {
    /*
     * Se utiliza POST con _method=PUT para que Laravel
     * procese correctamente archivos multipart.
     */
    const formData = buildFormData(payload, 'PUT')

    const response = await api.post<
      ApiResourceMessage<GarmentModel>
    >(
      ENDPOINTS.garmentModels.update(id),
      formData,
    )

    return response.data
  },

  async activate(
    id: number,
  ): Promise<ApiResourceMessage<GarmentModel>> {
    const response = await api.post<
      ApiResourceMessage<GarmentModel>
    >(ENDPOINTS.garmentModels.activate(id))

    return response.data
  },

  async deactivate(
    id: number,
  ): Promise<ApiResourceMessage<GarmentModel>> {
    const response = await api.post<
      ApiResourceMessage<GarmentModel>
    >(ENDPOINTS.garmentModels.deactivate(id))

    return response.data
  },
}