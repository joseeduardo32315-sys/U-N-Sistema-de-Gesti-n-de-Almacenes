<script setup lang="ts">
import { computed } from 'vue'
import { Menu } from 'lucide-vue-next'

import { useAuthStore } from '@/modules/auth/stores/auth.store'

defineProps<{
  title: string
}>()

const emit = defineEmits<{
  openNavigation: []
}>()

const authStore = useAuthStore()

const initials = computed<string>(() => {
  const name = authStore.user?.name?.trim() ?? ''

  if (!name) {
    return 'U'
  }

  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((word) => word.charAt(0).toUpperCase())
    .join('')
})
</script>

<template>
  <header class="sticky z-20 top-0 flex min-h-[4rem] items-center justify-between gap-3 px-4 py-2 bg-white/95 border-b border-slate-200 backdrop-blur-md sm:px-6 lg:px-8">
    <div class="flex min-w-0 items-center gap-3">
      <button
        type="button"
        class="inline-flex w-[2.75rem] min-w-[2.75rem] min-h-[2.75rem] items-center justify-center p-0 text-slate-900 bg-white border border-slate-200 rounded-md hover:text-brand-orange-800 hover:bg-brand-orange-50 lg:hidden transition-colors"
        aria-label="Abrir menú principal"
        @click="emit('openNavigation')"
      >
        <Menu
          :size="23"
          aria-hidden="true"
        />
      </button>

      <div class="min-w-0">
        <span class="hidden sm:block sm:mb-1 text-slate-500 text-xs">Sistema de Administración y Control de Procesos (SACOP)</span>
        <h1 class="m-0 overflow-hidden text-lg leading-tight truncate text-slate-900 font-bold">{{ title }}</h1>
      </div>
    </div>

    <div class="flex shrink-0 items-center gap-3">
      <div class="flex w-10 h-10 items-center justify-center text-brand-green-900 bg-brand-green-200 rounded-full text-sm font-extrabold">
        {{ initials }}
      </div>

      <div class="hidden sm:grid">
        <strong class="max-w-[13rem] truncate text-sm text-slate-900 font-bold">{{ authStore.userName }}</strong>
        <span class="text-slate-500 text-xs">{{ authStore.primaryRole }}</span>
      </div>
    </div>
  </header>
</template>