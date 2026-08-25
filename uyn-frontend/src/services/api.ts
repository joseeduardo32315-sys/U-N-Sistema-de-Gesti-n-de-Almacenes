import axios, {
  AxiosError,
  type InternalAxiosRequestConfig,
} from 'axios'

import { STORAGE_KEYS } from '@/config/storage'

import type { LaravelValidationError } from '@/types/api'

type UnauthorizedHandler = () => void

let unauthorizedHandler: UnauthorizedHandler | null = null

const ENDPOINTS_AUTH_LOGIN = '/auth/login'

export function setUnauthorizedHandler(
  handler: UnauthorizedHandler,
): void {
  unauthorizedHandler = handler
}

export const api = axios.create({
  baseURL:
    import.meta.env.VITE_API_BASE_URL ??
    'http://127.0.0.1:8000/api/v1',

  timeout: 20_000,

  headers: {
    Accept: 'application/json',
  },
})

api.interceptors.request.use(
  (
    config: InternalAxiosRequestConfig,
  ): InternalAxiosRequestConfig => {
    const token = localStorage.getItem(
      STORAGE_KEYS.authToken,
    )

    if (token) {
      config.headers.set(
        'Authorization',
        `Bearer ${token}`,
      )
    }

    return config
  },
)

api.interceptors.response.use(
  (response) => response,

  (
    error: AxiosError<LaravelValidationError>,
  ): Promise<never> => {
    const status = error.response?.status
    const requestUrl = error.config?.url ?? ''

    const isLoginRequest = requestUrl.includes(
      ENDPOINTS_AUTH_LOGIN,
    )

    if (
      status === 401 &&
      !isLoginRequest
    ) {
      localStorage.removeItem(
        STORAGE_KEYS.authToken,
      )

      unauthorizedHandler?.()
    }

    return Promise.reject(error)
  },
)