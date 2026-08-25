export type SizeStatus = 'active' | 'inactive'

export interface Size {
  id: number
  name: string
  description: string | null
  status: SizeStatus
  status_label: string
}

export interface SizesQuery {
  status?: SizeStatus | 'all'
}