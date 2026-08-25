<script setup lang="ts">
import {
  ArrowLeft,
  ShieldX,
} from 'lucide-vue-next'
import { useRouter } from 'vue-router'

const router = useRouter()

function goBack(): void {
  if (window.history.length > 1) {
    router.back()
    return
  }

  void router.push({
    name: 'dashboard',
  })
}
</script>

<template>
  <main class="grid min-h-dvh place-items-center p-5 bg-slate-50">
    <section class="w-[min(100%,31rem)] p-8 sm:py-8 sm:px-5 text-center bg-white border border-slate-200 rounded-xl shadow-md">
      <div class="flex w-18 h-18 items-center justify-center mx-auto text-red-700 bg-red-100 rounded-full">
        <ShieldX
          :size="38"
          aria-hidden="true"
        />
      </div>

      <p class="mt-5 mb-2 text-brand-orange-800 text-sm font-extrabold">
        Error 403
      </p>

      <h1 class="m-0 text-2xl font-bold text-slate-900">Acceso restringido</h1>

      <p class="my-3 mb-6 text-slate-600 leading-relaxed text-sm sm:text-base">
        Tu usuario no cuenta con el permiso necesario para
        consultar esta sección.
      </p>

      <button
        type="button"
        class="inline-flex min-h-[2.75rem] items-center justify-center gap-2 px-5 py-3 text-white bg-brand-green-700 hover:bg-brand-green-800 border-0 rounded-md font-bold transition-colors cursor-pointer"
        @click="goBack"
      >
        <ArrowLeft
          :size="20"
          aria-hidden="true"
        />

        Regresar
      </button>
    </section>
  </main>
</template>