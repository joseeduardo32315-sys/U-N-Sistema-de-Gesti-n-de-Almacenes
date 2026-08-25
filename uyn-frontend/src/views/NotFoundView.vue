<script setup lang="ts">
import {
  Home,
  SearchX,
} from 'lucide-vue-next'
import { RouterLink } from 'vue-router'
</script>

<template>
  <main class="grid min-h-dvh place-items-center p-5 bg-[radial-gradient(circle_at_top,#e5f2db,transparent_26rem)] bg-slate-50">
    <section class="w-[min(100%,31rem)] text-center">
      <SearchX
        :size="52"
        aria-hidden="true"
        class="text-brand-orange-800 mx-auto"
      />

      <p class="mt-5 mb-2 text-brand-green-700 text-lg font-black">404</p>

      <h1 class="m-0 text-2xl font-bold text-slate-900">Página no encontrada</h1>

      <span class="block my-3 mb-6 text-slate-600 leading-relaxed text-sm sm:text-base">
        La dirección solicitada no existe o fue modificada.
      </span>

      <RouterLink
        :to="{ name: 'dashboard' }"
        class="inline-flex min-h-[2.75rem] items-center justify-center gap-2 px-5 py-3 text-white bg-brand-orange-800 hover:bg-brand-orange-900 rounded-md font-bold no-underline transition-colors"
      >
        <Home
          :size="20"
          aria-hidden="true"
        />

        Ir al inicio
      </RouterLink>
    </section>
  </main>
</template>