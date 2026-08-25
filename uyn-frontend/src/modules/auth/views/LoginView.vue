<script setup lang="ts">
import {
  computed,
  reactive,
  ref,
} from 'vue'
import {
  useRoute,
  useRouter,
} from 'vue-router'
import {
  AlertCircle,
  Eye,
  EyeOff,
  LoaderCircle,
  LockKeyhole,
  UserRound,
} from 'lucide-vue-next'

import logoUyn from '@/assets/images/logo-uyn.png'

import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { getApiErrorMessage } from '@/utils/api-error'

import type { LoginPayload } from '@/modules/auth/types/auth.types'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const form = reactive<LoginPayload>({
  login: '',
  password: '',
})

const showPassword = ref(false)
const errorMessage = ref('')
const submitted = ref(false)

const loginError = computed<string>(() => {
  if (!submitted.value) {
    return ''
  }

  if (!form.login.trim()) {
    return 'Ingresa tu usuario o correo electrónico.'
  }

  return ''
})

const passwordError = computed<string>(() => {
  if (!submitted.value) {
    return ''
  }

  if (!form.password) {
    return 'Ingresa tu contraseña.'
  }

  return ''
})

const formIsValid = computed<boolean>(() => {
  return Boolean(
    form.login.trim() &&
      form.password,
  )
})

const sessionExpiredMessage = computed<boolean>(() => {
  return route.query.reason === 'session-expired'
})

function togglePasswordVisibility(): void {
  showPassword.value = !showPassword.value
}

async function handleSubmit(): Promise<void> {
  submitted.value = true
  errorMessage.value = ''

  if (!formIsValid.value) {
    return
  }

  try {
    await authStore.login({
      login: form.login.trim(),
      password: form.password,
    })

    const redirect =
      typeof route.query.redirect === 'string'
        ? route.query.redirect
        : '/'

    await router.replace(redirect)
  } catch (error) {
    errorMessage.value = getApiErrorMessage(
      error,
      'No fue posible iniciar sesión.',
    )
  }
}
</script>

<template>
  <main class="min-h-dvh bg-[radial-gradient(circle_at_top_right,rgba(255,157,34,0.12),transparent_30rem)] bg-slate-50 lg:grid lg:grid-cols-[minmax(28rem,1.05fr)_minmax(30rem,0.95fr)]">
    <section class="hidden lg:relative lg:flex lg:min-h-dvh lg:items-center lg:overflow-hidden lg:p-[clamp(3rem,7vw,7rem)] lg:bg-[linear-gradient(145deg,#e5f2db,#f7fbf4_62%,#fff7ed)]" aria-hidden="true">
      <div class="relative z-2 w-[min(100%,38rem)]">
        <img
          :src="logoUyn"
          alt=""
          class="w-[clamp(11rem,17vw,16rem)] mb-10 object-contain"
        />

        <div>
          <p class="m-0 mb-2 text-brand-orange-800 text-xs font-extrabold tracking-wider uppercase">
            Sistema de administración
          </p>

          <h1 class="max-w-[35rem] m-0 text-slate-900 text-[clamp(2.2rem,4vw,4rem)] leading-[1.05] tracking-tight font-bold">
            Control de producción claro y accesible
          </h1>

          <p class="max-w-[34rem] mt-5 mb-0 text-slate-600 text-lg leading-relaxed">
            Consulta cortes, movimientos, incidencias y
            avances desde una plataforma centralizada.
          </p>
        </div>
      </div>

      <div class="absolute -right-28 -bottom-32 w-92 h-92 bg-amber-500/18 rounded-full blur-xs" />
      <div class="absolute -top-28 -left-32 w-88 h-88 bg-brand-green-300/45 rounded-full blur-xs" />
    </section>

    <section class="flex min-h-dvh items-center justify-center p-[max(1.25rem,env(safe-area-inset-top))_1rem_max(1.25rem,env(safe-area-inset-bottom))] lg:p-[clamp(2rem,5vw,5rem)] lg:bg-slate-50">
      <div class="w-full max-w-[29rem] p-5 sm:p-8 bg-white/96 border border-slate-200 rounded-xl shadow-lg">
        <header class="flex flex-col sm:flex-row sm:items-center lg:block gap-4 mb-6">
          <img
            :src="logoUyn"
            alt="U&N Moda Infantil"
            class="w-28 h-22 sm:w-26 sm:shrink-0 lg:hidden object-contain object-left"
          />

          <div>
            <p class="m-0 mb-2 text-brand-orange-800 text-xs font-extrabold tracking-wider uppercase">
              ERP U&amp;N Moda Infantil
            </p>

            <h2 class="m-0 text-slate-900 text-[clamp(1.65rem,6vw,2rem)] leading-tight font-bold">Iniciar sesión</h2>

            <p class="mt-2 mb-0 text-slate-600 text-sm leading-normal">
              Ingresa con tu usuario o correo electrónico.
            </p>
          </div>
        </header>

        <div
          v-if="sessionExpiredMessage"
          class="flex items-start gap-3 mb-5 p-3 px-4 text-amber-800 bg-amber-100/80 border border-amber-700/20 rounded-md text-sm leading-normal"
          role="status"
        >
          <AlertCircle
            :size="20"
            class="shrink-0 mt-0.5"
            aria-hidden="true"
          />

          <span>
            Tu sesión terminó. Ingresa nuevamente para
            continuar.
          </span>
        </div>

        <div
          v-if="errorMessage"
          class="flex items-start gap-3 mb-5 p-3 px-4 text-red-700 bg-red-100 border border-red-700/20 rounded-md text-sm leading-normal"
          role="alert"
        >
          <AlertCircle
            :size="20"
            class="shrink-0 mt-0.5"
            aria-hidden="true"
          />

          <span>{{ errorMessage }}</span>
        </div>

        <form
          class="grid gap-5"
          novalidate
          @submit.prevent="handleSubmit"
        >
          <div class="grid gap-2">
            <label for="login" class="text-slate-900 text-sm font-bold">
              Usuario o correo
            </label>

            <div
              class="grid min-h-[3rem] grid-cols-[auto_minmax(0,1fr)] items-center gap-3 px-4 bg-white border border-slate-300 rounded-md focus-within:border-brand-green-700 focus-within:ring-3 focus-within:ring-brand-green-700/14 transition-all"
              :class="{
                'border-red-700! focus-within:border-red-700! focus-within:ring-red-700/12!': loginError,
              }"
            >
              <UserRound
                :size="20"
                class="text-slate-500"
                aria-hidden="true"
              />

              <input
                id="login"
                v-model="form.login"
                type="text"
                name="login"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                placeholder="Ej. admin"
                class="w-full min-w-0 min-h-[2.875rem] p-0 text-slate-900 bg-transparent border-0 outline-hidden placeholder:text-slate-400"
                :aria-invalid="Boolean(loginError)"
                :aria-describedby="
                  loginError ? 'login-error' : undefined
                "
              />
            </div>

            <p
              v-if="loginError"
              id="login-error"
              class="m-0 text-red-700 text-xs leading-tight"
            >
              {{ loginError }}
            </p>
          </div>

          <div class="grid gap-2">
            <label for="password" class="text-slate-900 text-sm font-bold">
              Contraseña
            </label>

            <div
              class="grid min-h-[3rem] grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 pl-4 pr-2 bg-white border border-slate-300 rounded-md focus-within:border-brand-green-700 focus-within:ring-3 focus-within:ring-brand-green-700/14 transition-all"
              :class="{
                'border-red-700! focus-within:border-red-700! focus-within:ring-red-700/12!': passwordError,
              }"
            >
              <LockKeyhole
                :size="20"
                class="text-slate-500"
                aria-hidden="true"
              />

              <input
                id="password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                name="password"
                autocomplete="current-password"
                placeholder="Ingresa tu contraseña"
                class="w-full min-w-0 min-h-[2.875rem] p-0 text-slate-900 bg-transparent border-0 outline-hidden placeholder:text-slate-400"
                :aria-invalid="Boolean(passwordError)"
                :aria-describedby="
                  passwordError
                    ? 'password-error'
                    : undefined
                "
              />

              <button
                type="button"
                class="inline-flex w-[2.75rem] min-h-[2.75rem] items-center justify-center p-0 text-slate-600 bg-transparent border-0 rounded-sm hover:text-brand-orange-800 hover:bg-brand-orange-50 transition-colors"
                :aria-label="
                  showPassword
                    ? 'Ocultar contraseña'
                    : 'Mostrar contraseña'
                "
                @click="togglePasswordVisibility"
              >
                <EyeOff
                  v-if="showPassword"
                  :size="20"
                  aria-hidden="true"
                />

                <Eye
                  v-else
                  :size="20"
                  aria-hidden="true"
                />
              </button>
            </div>

            <p
              v-if="passwordError"
              id="password-error"
              class="m-0 text-red-700 text-xs leading-tight"
            >
              {{ passwordError }}
            </p>
          </div>

          <button
            type="submit"
            class="inline-flex w-full min-h-[3rem] items-center justify-center gap-2 px-4 py-3 text-white bg-brand-orange-800 border border-brand-orange-800 rounded-md font-extrabold hover:enabled:bg-brand-orange-900 hover:enabled:border-brand-orange-900 hover:enabled:shadow-md active:enabled:scale-[0.99] disabled:cursor-wait disabled:opacity-70 transition-all cursor-pointer"
            :disabled="authStore.loading"
          >
            <LoaderCircle
              v-if="authStore.loading"
              :size="20"
              class="animate-spin"
              aria-hidden="true"
            />

            <span>
              {{
                authStore.loading
                  ? 'Iniciando sesión...'
                  : 'Ingresar al sistema'
              }}
            </span>
          </button>
        </form>

        <footer class="mt-6 pt-4 border-t border-slate-200">
          <p class="m-0 text-slate-500 text-xs leading-normal text-center">
            El acceso está limitado al personal autorizado.
          </p>
        </footer>
      </div>
    </section>
  </main>
</template>