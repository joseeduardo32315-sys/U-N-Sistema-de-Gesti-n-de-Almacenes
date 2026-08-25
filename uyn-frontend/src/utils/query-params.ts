import type {
  ApiQueryParams,
  ApiQueryValue,
} from '@/types/api'

function hasValue(value: ApiQueryValue): boolean {
  if (value === null || value === undefined) {
    return false
  }

  if (typeof value === 'string') {
    return value.trim() !== ''
  }

  return true
}

export function cleanQueryParams(
  params: ApiQueryParams,
): ApiQueryParams {
  return Object.fromEntries(
    Object.entries(params).filter(([, value]) =>
      hasValue(value),
    ),
  )
}