const prefix = import.meta.env.VITE_STORAGE_PREFIX ?? 'uyn_sacop'

export const STORAGE_KEYS = {
    authToken: `${prefix}_auth_token`,
} as const