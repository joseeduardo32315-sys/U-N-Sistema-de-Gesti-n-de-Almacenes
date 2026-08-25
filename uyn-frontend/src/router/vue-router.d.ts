import 'vue-router'

export {}

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    description?: string
    requiresAuth?: boolean
    guestOnly?: boolean
    permission?: string
    permissions?: readonly string[]
  }
}