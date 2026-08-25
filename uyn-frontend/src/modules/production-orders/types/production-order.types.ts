export type ProductionOrderStatus =
  | 'registered'
  | 'in_progress'
  | 'completed'
  | 'cancelled'

export type ProductionOrderPriority =
  | 'low'
  | 'normal'
  | 'high'
  | 'urgent'

export interface ProductionOrderCreator {
  id: number
  name: string
  username?: string
}

export interface ProductionOrderGarmentModel {
  id: number
  code: string
  name: string
}

export interface ProductionOrderCurrentArea {
  id: number
  name: string
}

export interface ProductionOrderCut {
  id: number
  code: string
  description: string | null
  total_sizes: number
  total_pieces: number
  status: string
  status_label?: string
  garment_model: ProductionOrderGarmentModel | null
  current_area: ProductionOrderCurrentArea | null
}

export interface ProductionOrder {
  id: number
  order_code: string
  location: string | null
  status: ProductionOrderStatus
  status_label: string
  priority: ProductionOrderPriority
  priority_label: string
  start_date: string
  end_date: string | null
  notes: string | null
  created_by: ProductionOrderCreator | null
  garment_cuts_count: number
  garment_cuts?: ProductionOrderCut[]
  created_at: string
  updated_at: string
}

export interface ProductionOrdersQuery {
  search?: string
  status?: ProductionOrderStatus | ''
  priority?: ProductionOrderPriority | ''
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}

export interface CreateProductionOrderPayload {
  order_code: string
  location: string | null
  start_date: string
  end_date: string | null
  priority: ProductionOrderPriority
  notes: string | null
}

export interface UpdateProductionOrderPayload {
  location: string | null
  start_date: string
  end_date: string | null
  priority: ProductionOrderPriority
  notes: string | null
}