import type { OperationProcess } from '@/modules/processes/types/process.types'
import type { ProductionOperationLog } from '@/modules/production-operation-logs/types/production-operation-log.types'



export type ProductionMovementTargetType =
  | 'cut'
  | 'complement'
  | 'special_piece'

export type ProductionMovementStatus =
  | 'pending'
  | 'received'
  | 'in_progress'
  | 'partially_completed'
  | 'completed'
  | 'cancelled'
  | 'with_incident'
  | 'delayed'

export interface ProductionMovementArea {
  id: number
  name: string
}

export interface ProductionMovementUser {
  id: number
  name: string
  username?: string
}

export interface ProductionMovementProcess {
  id: number
  name: string
  flow_order?: number
}

export interface ProductionMovementCut {
  id: number
  code: string
  total_pieces: number
  status: string
  status_label?: string
}

export interface ProductionMovementPieceType {
  id: number
  name: string
}

export interface ProductionMovementTarget {
  id: number
  status?: string
  status_label?: string
  notes?: string | null
  piece_type?: ProductionMovementPieceType | null
  special_process?: ProductionMovementProcess | null
  process?: ProductionMovementProcess | null
  current_area?: ProductionMovementArea | null
}

export interface ProductionMovementReturnIncident {
  id: number
  incident_type?: string
  incident_type_label?: string
  description?: string
}

export interface ProductionMovement {
  id: number

  target_type: ProductionMovementTargetType
  target_type_label: string

  return_incident_id: number | null
  is_return_for_rework: boolean
  return_incident: ProductionMovementReturnIncident | null

  quantity: number
  resolved_loss_quantity: number
  effective_quantity: number

  status: ProductionMovementStatus
  status_label: string

  start_time: string | null
  end_time: string | null
  notes: string | null

  garment_cut: ProductionMovementCut | null
  target: ProductionMovementTarget | null

  process: ProductionMovementProcess | null
  operation_process: OperationProcess | null

  from_area: ProductionMovementArea | null
  to_area: ProductionMovementArea | null

  created_by: ProductionMovementUser | null
  received_by: ProductionMovementUser | null

  operation_logs_count: number
  operation_logs?: ProductionOperationLog[]

  created_at: string
  updated_at: string
}

export interface ProductionMovementsQuery {
  search?: string
  garment_cut_id?: number | ''
  target_type?: ProductionMovementTargetType | ''
  process_id?: number | ''
  from_area_id?: number | ''
  to_area_id?: number | ''
  status?: ProductionMovementStatus | ''
  page?: number
  per_page?: number
}

export interface CreateProductionMovementPayload {
  garment_cut_id: number
  target_type: ProductionMovementTargetType
  special_process_piece_id?: number
  complement_id?: number
  process_id: number
  operation_process_id: number
  quantity: number
  notes: string | null
}