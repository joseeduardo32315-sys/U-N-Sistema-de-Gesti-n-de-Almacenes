export type GarmentCutStatus =
  | 'registered'
  | 'in_progress'
  | 'partially_completed'
  | 'completed'
  | 'cancelled'
  | 'with_incident'
  | 'delayed'

export interface GarmentCutProductionOrder {
  id: number
  order_code: string
  status: string
  priority?: string
  start_date?: string
  end_date?: string | null
}

export interface GarmentCutModel {
  id: number
  code: string
  name: string
  status?: string
}

export interface GarmentCutArea {
  id: number
  name: string
}

export interface GarmentCutSizeReference {
  id: number
  name: string
}

export interface GarmentCutSize {
  id: number
  size: GarmentCutSizeReference
  total_pieces: number
}

export interface GarmentCutComplement {
  id: number
  garment_cut_id: number
  status: string
  status_label: string
  notes: string | null
  current_area: GarmentCutArea | null
}

export interface GarmentCutPieceType {
  id: number
  name: string
}

export interface GarmentCutProcess {
  id: number
  name: string
  flow_order?: number
}

export interface GarmentCutSpecialPiece {
  id: number
  garment_cut_id: number
  status: string
  status_label: string
  notes: string | null
  piece_type: GarmentCutPieceType | null
  process: GarmentCutProcess | null
  current_area: GarmentCutArea | null
}

export interface GarmentCut {
  id: number
  code: string
  description: string | null
  total_sizes: number
  base_pieces_per_size: number | null
  total_pieces: number
  is_uniform_distribution: boolean
  status: GarmentCutStatus
  status_label: string
  notes: string | null
  production_order: GarmentCutProductionOrder | null
  garment_model: GarmentCutModel | null
  current_area: GarmentCutArea | null
  sizes?: GarmentCutSize[]
  complement?: GarmentCutComplement | null
  special_process_pieces?: GarmentCutSpecialPiece[]
  created_at: string
  updated_at: string
}

export interface GarmentCutsQuery {
  search?: string
  production_order_id?: number | ''
  garment_model_id?: number | ''
  current_area_id?: number | ''
  status?: GarmentCutStatus | ''
  page?: number
  per_page?: number
}

export interface GarmentCutSizePayload {
  size_id: number
  total_pieces: number
}

export interface CreateGarmentCutPayload {
  production_order_id: number
  garment_model_id: number
  code: string
  description: string | null
  notes: string | null
  sizes: GarmentCutSizePayload[]
}

export interface UpdateGarmentCutPayload {
  description: string | null
  notes: string | null
  sizes: GarmentCutSizePayload[]
}