import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import { STORAGE_KEYS } from '@/config/storage'
import { authService } from '@/modules/auth/services/auth.service'

import type {
  AuthUser,
  LoginPayload,
} from '@/modules/auth/types/auth.types'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(
    localStorage.getItem(STORAGE_KEYS.authToken),
  )

  const user = ref<AuthUser | null>(null)
  const loading = ref(false)
  const initialized = ref(false)

  const isAuthenticated = computed<boolean>(() => {
    return Boolean(token.value && user.value)
  })

  const userName = computed<string>(() => {
    return user.value?.name ?? ''
  })

  const primaryRole = computed<string>(() => {
    return user.value?.roles.at(0) ?? 'Sin rol'
  })

  function persistToken(accessToken: string): void {
    token.value = accessToken

    localStorage.setItem(
      STORAGE_KEYS.authToken,
      accessToken,
    )
  }

  function clearSession(): void {
    token.value = null
    user.value = null

    localStorage.removeItem(STORAGE_KEYS.authToken)
  }

  async function login(
    payload: LoginPayload,
  ): Promise<void> {
    loading.value = true

    try {
      const response = await authService.login(payload)

      persistToken(response.access_token)
      user.value = response.user
      initialized.value = true
    } catch (error) {
      clearSession()
      throw error
    } finally {
      loading.value = false
    }
  }

  async function fetchCurrentUser(): Promise<AuthUser> {
    const currentUser = await authService.me()

    user.value = currentUser

    return currentUser
  }

  async function initialize(): Promise<void> {
    if (initialized.value) {
      return
    }

    if (!token.value) {
      initialized.value = true
      return
    }

    loading.value = true

    try {
      await fetchCurrentUser()
    } catch {
      clearSession()
    } finally {
      initialized.value = true
      loading.value = false
    }
  }

  async function logout(): Promise<void> {
    loading.value = true

    try {
      if (token.value) {
        await authService.logout()
      }
    } catch {
      /*
       * La sesión local debe eliminarse incluso cuando
       * el token ya expiró o el backend no está disponible.
       */
    } finally {
      clearSession()
      initialized.value = true
      loading.value = false
    }
  }

  function can(permission: string): boolean {
    return Boolean(
      user.value?.permissions.includes(permission),
    )
  }

  function canAny(permissions: readonly string[]): boolean {
    return permissions.some((permission) =>
      can(permission),
    )
  }

  function canAll(permissions: readonly string[]): boolean {
    return permissions.every((permission) =>
      can(permission),
    )
  }

  function hasRole(role: string): boolean {
    return Boolean(user.value?.roles.includes(role))
  }

  return {
    token,
    user,
    loading,
    initialized,
    isAuthenticated,
    userName,
    primaryRole,
    login,
    logout,
    initialize,
    fetchCurrentUser,
    clearSession,
    can,
    canAny,
    canAll,
    hasRole,
  }
})