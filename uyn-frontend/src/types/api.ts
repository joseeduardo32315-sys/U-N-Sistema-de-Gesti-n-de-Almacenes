export interface ApiResource<T> {
  data: T
}

export interface ApiResourceMessage<T> {
  data: T
  message: string
}

export interface ApiMessage {
  message: string
}

export interface PaginationLinks {
  first: string
  last: string
  prev: string | null
  next: string | null
}

export interface PaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  path?: string
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedResponse<T> {
  data: T[]
  links?: PaginationLinks
  meta: PaginationMeta
}

export interface LaravelValidationError {
  message: string
  errors?: Record<string, string[]>
}

export interface SelectOption {
  id: number
  name: string
}

export type ApiQueryValue =
  | string
  | number
  | boolean
  | null
  | undefined

export type ApiQueryParams = Record<string, ApiQueryValue>