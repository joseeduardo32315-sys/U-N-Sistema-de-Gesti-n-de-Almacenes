export type GarmentModelStatus = 'active' | 'inactive'

export interface GarmentModel {
  id: number
  code: string
  name: string
  description: string | null
  size_range: string | null
  image_path: string | null
  image_url: string | null
  status: GarmentModelStatus
  status_label: string
  created_at: string
  updated_at: string
}

export interface GarmentModelsQuery {
  search?: string
  status?: GarmentModelStatus | ''
  page?: number
  per_page?: number
}

export interface SaveGarmentModelPayload {
  code: string
  name: string
  description: string
  size_range: string
  status?: GarmentModelStatus
  image?: File | null
}