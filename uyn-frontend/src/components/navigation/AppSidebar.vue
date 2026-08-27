<script setup lang="ts">
import { computed } from 'vue'
import { LogOut, X } from 'lucide-vue-next'
import { RouterLink, useRouter } from 'vue-router'

import logoUyn from '@/assets/images/logo-uyn.png'
import { navigationGroups } from '@/config/navigation'
import { useAuthStore } from '@/modules/auth/stores/auth.store'

defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  close: []
}>()

const router = useRouter()
const authStore = useAuthStore()

const visibleGroups = computed(() => {
  return navigationGroups
    .map((group) => ({
      ...group,

      items: group.items.filter((item) => {
        if (item.permission) {
          return authStore.can(item.permission)
        }

        if (item.permissions) {
          return authStore.canAny(item.permissions)
        }

        return true
      }),
    }))
    .filter((group) => group.items.length > 0)
})

function handleNavClick(): void {
  emit('close')
}

async function handleLogout(): Promise<void> {
  emit('close')

  try {
    await authStore.logout()
  } finally {
    await router.replace({
      name: 'login',
    })
  }
}
</script>

<template>
  <div>
    <!-- Mobile Backdrop Overlay -->
    <div
      v-if="open"
      class="fixed inset-0 z-40 bg-slate-950/55 backdrop-blur-xs lg:hidden cursor-pointer"
      aria-label="Cerrar menú principal"
      @click="emit('close')"
    />

    <!-- Navigation Sidebar Drawer -->
    <aside
      class="fixed top-0 bottom-0 left-0 z-50 flex flex-col w-[min(88vw,17rem)] lg:w-[17rem] bg-white border-r border-slate-200 shadow-2xl lg:shadow-none transition-transform duration-250 ease-in-out"
      :class="[
        open
          ? 'translate-x-0 pointer-events-auto'
          : '-translate-x-full pointer-events-none lg:translate-x-0 lg:pointer-events-auto'
      ]"
      aria-label="Navegación principal"
    >
      <header class="flex min-h-[4.75rem] items-center justify-between gap-3 px-4 py-3 border-b border-slate-200 shrink-0">
        <RouterLink
          to="/"
          class="flex min-w-0 items-center gap-3 text-inherit no-underline cursor-pointer"
          @click="handleNavClick"
        >
          <img
            :src="logoUyn"
            alt="U&N Moda Infantil"
            class="w-14 h-13 shrink-0 object-contain"
          />

          <div class="grid min-w-0">
            <strong class="text-slate-900 text-base font-bold">SACOP</strong>
            <span class="text-slate-500 text-xs">Control de procesos</span>
          </div>
        </RouterLink>

        <button
          type="button"
          class="inline-flex w-11 h-11 shrink-0 items-center justify-center p-0 text-slate-600 bg-transparent border-0 rounded-md hover:bg-slate-100 lg:hidden cursor-pointer"
          aria-label="Cerrar menú"
          @click="emit('close')"
        >
          <X
            :size="22"
            aria-hidden="true"
          />
        </button>
      </header>

      <nav class="flex-1 overflow-y-auto p-4">
        <section
          v-for="(group, idx) in visibleGroups"
          :key="group.label"
          :class="{ 'mt-6': idx > 0 }"
        >
          <h2 class="m-0 mb-2 px-3 text-slate-500 text-[0.6875rem] font-extrabold tracking-wider uppercase">
            {{ group.label }}
          </h2>

          <div class="grid gap-1">
            <RouterLink
              v-for="item in group.items"
              :key="item.to"
              :to="item.to"
              class="relative flex min-h-[2.75rem] items-center gap-3 p-3 text-slate-600 rounded-md text-sm font-[650] hover:text-brand-orange-900 hover:bg-brand-orange-50 transition-colors cursor-pointer"
              active-class="text-brand-green-900! bg-brand-green-100! before:absolute before:top-1/2 before:left-0 before:w-1 before:h-6 before:bg-brand-green-700 before:rounded-r-full before:-translate-y-1/2"
              @click="handleNavClick"
            >
              <component
                :is="item.icon"
                :size="20"
                aria-hidden="true"
              />

              <span>{{ item.label }}</span>
            </RouterLink>
          </div>
        </section>
      </nav>

      <footer class="p-4 bg-slate-100 border-t border-slate-200 shrink-0">
        <div class="flex min-w-0 items-center gap-3 mb-3">
          <div class="flex w-11 h-11 shrink-0 items-center justify-center text-brand-orange-900 bg-brand-orange-100 rounded-full font-extrabold">
            {{
              authStore.user?.name
                ?.charAt(0)
                .toUpperCase() ?? 'U'
            }}
          </div>

          <div class="grid min-w-0">
            <strong class="truncate text-sm text-slate-900 font-bold">{{ authStore.userName }}</strong>
            <span class="truncate text-slate-500 text-xs">{{ authStore.primaryRole }}</span>
          </div>
        </div>

        <button
          type="button"
          class="flex w-full min-h-[2.75rem] items-center justify-center gap-2 px-4 text-red-700 bg-white border border-red-700/18 rounded-md font-bold hover:enabled:bg-red-100/50 disabled:opacity-55 disabled:cursor-not-allowed transition-colors cursor-pointer"
          :disabled="authStore.loading"
          @click="handleLogout"
        >
          <LogOut
            :size="20"
            aria-hidden="true"
          />

          <span>Cerrar sesión</span>
        </button>
      </footer>
    </aside>
  </div>
</template>