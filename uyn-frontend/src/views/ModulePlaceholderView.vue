<script setup lang="ts">
import { computed } from 'vue'
import { Construction } from 'lucide-vue-next'
import { useRoute } from 'vue-router'

const route = useRoute()

const title = computed<string>(() => {
  return route.meta.title ?? 'Módulo'
})

const description = computed<string>(() => {
  return (
    route.meta.description ??
    'Este módulo será integrado próximamente.'
  )
})
</script>

<template>
  <section class="grid gap-5">
    <header class="max-w-[48rem]">
      <p class="m-0 mb-2 text-brand-orange-800 text-xs font-extrabold tracking-wider uppercase">
        Sistema de Administración y Control de Procesos (SACOP)
      </p>

      <h2 class="m-0 text-2xl sm:text-3xl font-bold text-slate-900">{{ title }}</h2>

      <p class="mt-2 mb-0 text-slate-600 leading-relaxed text-sm sm:text-base">{{ description }}</p>
    </header>

    <article class="grid justify-items-center p-10 px-5 text-center bg-white border border-slate-200 rounded-xl shadow-xs">
      <div class="flex w-18 h-18 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-full">
        <Construction
          :size="34"
          aria-hidden="true"
        />
      </div>

      <h3 class="mt-4 mb-2 text-lg font-bold text-slate-900">Módulo preparado</h3>

      <p class="max-w-[36rem] m-0 text-slate-600 leading-relaxed text-sm">
        La ruta, navegación y permisos ya se encuentran
        configurados. La funcionalidad será integrada con
        la API correspondiente.
      </p>
    </article>
  </section>
</template>