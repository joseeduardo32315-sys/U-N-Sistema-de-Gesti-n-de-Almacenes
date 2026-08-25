export type PieceTypeStatus = 'active' | 'inactive'

export interface PieceType {
  id: number
  name: string
  description: string | null
  status: PieceTypeStatus
  status_label: string
}

export interface PieceTypesQuery {
  search?: string
  status?: PieceTypeStatus | 'all'
}