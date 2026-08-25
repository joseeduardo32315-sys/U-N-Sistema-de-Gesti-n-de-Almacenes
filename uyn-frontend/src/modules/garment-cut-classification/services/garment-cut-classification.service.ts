import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'

import type {
  ApiResource,
  ApiResourceMessage,
} from '@/types/api'

import type { GarmentCut } from '@/modules/garment-cuts/types/garment-cut.types'
import type { GarmentCutClassificationPayload } from '@/modules/garment-cut-classification/types/garment-cut-classification.types'

export const garmentCutClassificationService = {
  async show(id: number): Promise<GarmentCut> {
    const response = await api.get<
      ApiResource<GarmentCut>
    >(
      ENDPOINTS.garmentCuts.classification(id),
    )

    return response.data.data
  },

  async update(
    id: number,
    payload: GarmentCutClassificationPayload,
  ): Promise<ApiResourceMessage<GarmentCut>> {
    const response = await api.patch<
      ApiResourceMessage<GarmentCut>
    >(
      ENDPOINTS.garmentCuts.classification(id),
      payload,
    )

    return response.data
  },
}