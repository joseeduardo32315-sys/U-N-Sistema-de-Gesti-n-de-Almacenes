<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  ref,
  watch,
} from 'vue'
import {
  RouterView,
  useRoute,
} from 'vue-router'

import AppSidebar from '@/components/navigation/AppSidebar.vue'
import AppTopbar from '@/components/navigation/AppTopbar.vue'

const route = useRoute()

const navigationOpen = ref(false)

const pageTitle = computed<string>(() => {
  return route.meta.title ?? 'ERP U&N Moda Infantil'
})

function openNavigation(): void {
  navigationOpen.value = true
}

function closeNavigation(): void {
  navigationOpen.value = false
}

watch(
  () => route.fullPath,
  () => {
    closeNavigation()
  },
)

watch(navigationOpen, (isOpen) => {
  document.body.style.overflow = isOpen
    ? 'hidden'
    : ''
})

onBeforeUnmount(() => {
  document.body.style.overflow = ''
})
</script>

<template>
  <div class="min-h-dvh bg-slate-50">
    <AppSidebar
      :open="navigationOpen"
      @close="closeNavigation"
    />

    <div class="min-w-0 min-h-dvh lg:ml-[17rem]">
      <AppTopbar
        :title="pageTitle"
        @open-navigation="openNavigation"
      />

      <main class="w-full max-w-[90rem] mx-auto pt-5 px-4 pb-[max(2rem,env(safe-area-inset-bottom))] sm:pt-6 sm:px-6 sm:pb-10 lg:pt-8 lg:px-8 lg:pb-12">
        <RouterView />
      </main>
    </div>
  </div>
</template>