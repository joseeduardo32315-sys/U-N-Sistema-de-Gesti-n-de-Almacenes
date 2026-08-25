import axios from 'axios'

import type { LaravelValidationError } from '@/types/api'

export function getApiErrorMessage(
  error: unknown,
  fallback = 'Ocurrió un error inesperado.',
): string {
  if (!axios.isAxiosError<LaravelValidationError>(error)) {
    if (error instanceof Error && error.message.trim()) {
      return error.message
    }

    return fallback
  }

  const response = error.response

  if (!response) {
    return 'No fue posible establecer conexión con el servidor.'
  }

  switch (response.status) {
    case 401:
      return (
        response.data?.message ??
        'Las credenciales o la sesión no son válidas.'
      )

    case 403:
      return (
        response.data?.message ??
        'No tienes permisos para realizar esta acción.'
      )

    case 404:
      return (
        response.data?.message ??
        'El recurso solicitado no fue encontrado.'
      )

    case 422: {
      const firstError = Object.values(
        response.data?.errors ?? {},
      )
        .flat()
        .at(0)

      return (
        firstError ??
        response.data?.message ??
        'Revisa la información capturada.'
      )
    }

    case 429:
      return (
        response.data?.message ??
        'Se realizaron demasiados intentos. Espera un momento.'
      )

    case 500:
      return (
        response.data?.message ??
        'Ocurrió un problema interno en el servidor.'
      )

    default:
      return response.data?.message ?? fallback
  }
}

export function getValidationErrors(
  error: unknown,
): Record<string, string[]> {
  if (!axios.isAxiosError<LaravelValidationError>(error)) {
    return {}
  }

  return error.response?.data?.errors ?? {}
}