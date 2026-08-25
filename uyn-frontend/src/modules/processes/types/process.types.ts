export type PayrollCalculationType =
  | 'standard'
  | 'stitches'
  | 'per_piece'
  | 'embroidery_formula'
  | 'none'
  | string

export interface OperationProcess {
  id: number
  name: string
  flow_order: number

  /*
   * El catálogo GET /processes puede no incluir este campo.
   * Otros recursos y reportes sí pueden devolverlo.
   */
  payroll_calculation_type?: PayrollCalculationType
}

export interface ProductionProcess {
  id: number
  name: string
  flow_order: number
  operations: OperationProcess[]
}