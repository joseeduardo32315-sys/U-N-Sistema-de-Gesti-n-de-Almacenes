export type ProductionIncidentType =
  | 'damage'
  | 'loss'
  | 'quality'
  | 'delay'
  | 'other'

export type ProductionIncidentStatus =
  | 'open'
  | 'resolved'
  | 'cancelled'

export interface ProductionIncidentArea {
  id: number
  name: string
}

export interface ProductionIncidentEmployee {
  id: number
  name: string
  worker_type?: 'internal' | 'external'
  status?: 'active' | 'inactive'
  area?: ProductionIncidentArea | null
}

export interface ProductionIncidentUser {
  id: number
  name: string
  username?: string
}

export interface ProductionIncidentProcess {
  id: number
  name: string
  flow_order?: number
}

export interface ProductionIncidentGarmentCut {
  id: number
  code: string
  total_pieces: number
  status: string
  status_label?: string
}

export interface ProductionIncidentMovement {
  id: number

  target_type?: string
  target_type_label?: string

  quantity: number
  effective_quantity?: number

  status: string
  status_label?: string

  from_area: ProductionIncidentArea | null
  to_area: ProductionIncidentArea | null

  process?: ProductionIncidentProcess | null
  operation_process?: ProductionIncidentProcess | null
}

export interface ProductionIncident {
  id: number

  incident_type: ProductionIncidentType
  incident_type_label: string

  quantity_affected: number
  description: string

  status: ProductionIncidentStatus
  status_label: string

  resolution_notes: string | null
  resolved_at: string | null

  garment_cut: ProductionIncidentGarmentCut | null
  production_movement: ProductionIncidentMovement | null
  responsible_employee: ProductionIncidentEmployee | null

  reported_by?: ProductionIncidentUser | null
  resolved_by?: ProductionIncidentUser | null

  return_movement_id?: number | null

  created_at: string
  updated_at: string
}

export interface ProductionIncidentsQuery {
  incident_type?: ProductionIncidentType | ''
  status?: ProductionIncidentStatus | ''
  garment_cut_id?: number | ''
  production_movement_id?: number | ''
  responsible_employee_id?: number | ''
  from?: string
  to?: string
  page?: number
  per_page?: number
}

export interface CreateProductionIncidentPayload {
  production_movement_id: number
  incident_type: ProductionIncidentType
  quantity_affected: number
  description: string
  responsible_employee_id: number
}

export interface UpdateProductionIncidentPayload {
  quantity_affected: number
  description: string
  responsible_employee_id: number
}

export interface ResolveProductionIncidentPayload {
  notes: string
}

export interface ReturnIncidentForReworkPayload {
  operation_process_id: number
  notes: string | null
}