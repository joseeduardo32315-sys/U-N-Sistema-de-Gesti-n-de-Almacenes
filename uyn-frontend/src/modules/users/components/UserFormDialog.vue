<script setup lang="ts">
import {
  computed,
  onBeforeUnmount,
  reactive,
  ref,
  watch,
} from 'vue'

import {
  Eye,
  EyeOff,
  LoaderCircle,
  X,
} from 'lucide-vue-next'

import { usersService } from '@/modules/users/services/users.service'
import {
  getApiErrorMessage,
  getValidationErrors,
} from '@/utils/api-error'

import type { Role } from '@/modules/roles/types/role.types'
import type {
  CreateUserPayload,
  UpdateUserPayload,
  User,
  UserStatus,
} from '@/modules/users/types/user.types'

const props = defineProps<{
  open: boolean
  user: User | null
  roles: Role[]
}>()

const emit = defineEmits<{
  close: []
  saved: [user: User, message: string]
}>()

interface UserForm {
  name: string
  username: string
  email: string
  role: string
  status: UserStatus
  password: string
  password_confirmation: string
}

const form = reactive<UserForm>({
  name: '',
  username: '',
  email: '',
  role: '',
  status: 'active',
  password: '',
  password_confirmation: '',
})

const submitting = ref(false)
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const isEditing = computed<boolean>(() => {
  return props.user !== null
})

const title = computed<string>(() => {
  return isEditing.value
    ? 'Editar usuario'
    : 'Registrar usuario'
})

function resetForm(): void {
  form.name = props.user?.name ?? ''
  form.username = props.user?.username ?? ''
  form.email = props.user?.email ?? ''
  form.role = props.user?.roles.at(0) ?? ''
  form.status = props.user?.status ?? 'active'
  form.password = ''
  form.password_confirmation = ''

  formError.value = ''
  fieldErrors.value = {}
  showPassword.value = false
  showPasswordConfirmation.value = false
}

function firstFieldError(field: string): string {
  return fieldErrors.value[field]?.at(0) ?? ''
}

function setLocalError(
  field: string,
  message: string,
): void {
  fieldErrors.value[field] = [message]
}

function validateForm(): boolean {
  fieldErrors.value = {}
  formError.value = ''

  if (!form.name.trim()) {
    setLocalError('name', 'Ingresa el nombre completo.')
  }

  if (!form.username.trim()) {
    setLocalError('username', 'Ingresa el nombre de usuario.')
  } else if (
    !/^[a-z0-9._-]{3,50}$/.test(form.username.trim())
  ) {
    setLocalError(
      'username',
      'Usa entre 3 y 50 caracteres: minúsculas, números, puntos, guiones o guiones bajos.',
    )
  }

  if (!form.email.trim()) {
    setLocalError('email', 'Ingresa el correo electrónico.')
  } else if (
    !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())
  ) {
    setLocalError(
      'email',
      'Ingresa un correo electrónico válido.',
    )
  }

  if (!form.role) {
    setLocalError('role', 'Selecciona un rol.')
  }

  const passwordWasEntered =
    form.password.length > 0 ||
    form.password_confirmation.length > 0

  if (!isEditing.value || passwordWasEntered) {
    if (form.password.length < 10) {
      setLocalError(
        'password',
        'La contraseña debe tener al menos 10 caracteres.',
      )
    }

    if (
      form.password !== form.password_confirmation
    ) {
      setLocalError(
        'password_confirmation',
        'Las contraseñas no coinciden.',
      )
    }
  }

  return Object.keys(fieldErrors.value).length === 0
}

async function handleSubmit(): Promise<void> {
  if (!validateForm()) {
    return
  }

  submitting.value = true
  formError.value = ''

  try {
    if (props.user) {
      const payload: UpdateUserPayload = {
        name: form.name.trim(),
        username: form.username.trim(),
        email: form.email.trim(),
        role: form.role,
      }

      if (form.password) {
        payload.password = form.password
        payload.password_confirmation =
          form.password_confirmation
      }

      const response = await usersService.update(
        props.user.id,
        payload,
      )

      emit('saved', response.data, response.message)
      return
    }

    const payload: CreateUserPayload = {
      name: form.name.trim(),
      username: form.username.trim(),
      email: form.email.trim(),
      password: form.password,
      password_confirmation:
        form.password_confirmation,
      role: form.role,
      status: form.status,
    }

    const response = await usersService.create(payload)

    emit('saved', response.data, response.message)
  } catch (error) {
    fieldErrors.value = getValidationErrors(error)

    formError.value = getApiErrorMessage(
      error,
      'No fue posible guardar el usuario.',
    )
  } finally {
    submitting.value = false
  }
}

function requestClose(): void {
  if (!submitting.value) {
    emit('close')
  }
}

watch(
  () => props.open,
  (open) => {
    document.body.style.overflow = open ? 'hidden' : ''

    if (open) {
      resetForm()
    }
  },
)

watch(
  () => props.user,
  () => {
    if (props.open) {
      resetForm()
    }
  },
)

onBeforeUnmount(() => {
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[100] flex items-stretch sm:items-center justify-center bg-slate-950/60 backdrop-blur-xs sm:p-6"
      role="presentation"
      @click.self="requestClose"
    >
      <section
        class="flex flex-col w-full max-h-dvh sm:max-h-[calc(100dvh-3rem)] sm:w-[min(100%,46rem)] overflow-hidden bg-white sm:rounded-xl sm:shadow-lg"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="'user-dialog-title'"
      >
        <header class="flex items-center justify-between gap-4 p-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6 border-b border-slate-200">
          <div>
            <p class="m-0 mb-1 text-brand-orange-800 text-xs font-extrabold uppercase">Administración de usuarios</p>

            <h2 id="user-dialog-title" class="m-0 text-xl font-bold text-slate-900">
              {{ title }}
            </h2>
          </div>

          <button
            type="button"
            class="inline-flex w-[2.75rem] min-h-[2.75rem] items-center justify-center p-0 text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-md border-0 cursor-pointer transition-colors"
            aria-label="Cerrar formulario"
            :disabled="submitting"
            @click="requestClose"
          >
            <X
              :size="22"
              aria-hidden="true"
            />
          </button>
        </header>

        <form
          class="overflow-y-auto p-5 px-4 sm:px-6 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
          novalidate
          @submit.prevent="handleSubmit"
        >
          <div
            v-if="formError"
            class="mb-4 p-3 px-4 text-red-700 bg-red-100 border border-red-700/20 rounded-md text-sm"
            role="alert"
          >
            {{ formError }}
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="grid gap-2 sm:col-span-2">
              <label for="user-name" class="text-slate-900 text-sm font-bold">
                Nombre completo
              </label>

              <input
                id="user-name"
                v-model="form.name"
                type="text"
                autocomplete="name"
                maxlength="150"
                class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
                :class="{
                  'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                    firstFieldError('name'),
                }"
              />

              <small v-if="firstFieldError('name')" class="text-red-700 text-xs leading-tight">
                {{ firstFieldError('name') }}
              </small>
            </div>

            <div class="grid gap-2">
              <label for="user-username" class="text-slate-900 text-sm font-bold">
                Nombre de usuario
              </label>

              <input
                id="user-username"
                v-model="form.username"
                type="text"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                maxlength="50"
                placeholder="ej. coordinador.taller"
                class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
                :class="{
                  'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                    firstFieldError('username'),
                }"
              />

              <small v-if="firstFieldError('username')" class="text-red-700 text-xs leading-tight">
                {{ firstFieldError('username') }}
              </small>
            </div>

            <div class="grid gap-2">
              <label for="user-email" class="text-slate-900 text-sm font-bold">
                Correo electrónico
              </label>

              <input
                id="user-email"
                v-model="form.email"
                type="email"
                autocomplete="email"
                maxlength="150"
                placeholder="usuario@empresa.com"
                class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
                :class="{
                  'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                    firstFieldError('email'),
                }"
              />

              <small v-if="firstFieldError('email')" class="text-red-700 text-xs leading-tight">
                {{ firstFieldError('email') }}
              </small>
            </div>

            <div class="grid gap-2">
              <label for="user-role" class="text-slate-900 text-sm font-bold">
                Rol
              </label>

              <select
                id="user-role"
                v-model="form.role"
                class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
                :class="{
                  'border-red-700! focus:border-red-700! focus:ring-red-700/12!':
                    firstFieldError('role'),
                }"
              >
                <option value="">
                  Selecciona un rol
                </option>

                <option
                  v-for="role in roles"
                  :key="role.id"
                  :value="role.name"
                >
                  {{ role.name }}
                </option>
              </select>

              <small v-if="firstFieldError('role')" class="text-red-700 text-xs leading-tight">
                {{ firstFieldError('role') }}
              </small>
            </div>

            <div
              v-if="!isEditing"
              class="grid gap-2"
            >
              <label for="user-status" class="text-slate-900 text-sm font-bold">
                Estado inicial
              </label>

              <select
                id="user-status"
                v-model="form.status"
                class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13 text-sm transition-all"
              >
                <option value="active">
                  Activo
                </option>

                <option value="inactive">
                  Inactivo
                </option>
              </select>
            </div>

            <div class="grid gap-2">
              <label for="user-password" class="text-slate-900 text-sm font-bold">
                {{
                  isEditing
                    ? 'Nueva contraseña'
                    : 'Contraseña'
                }}
              </label>

              <div
                class="grid grid-cols-[minmax(0,1fr)_auto] items-center border border-slate-300 rounded-md focus-within:border-brand-green-700 focus-within:ring-3 focus-within:ring-brand-green-700/13 transition-all"
                :class="{
                  'border-red-700! focus-within:border-red-700! focus-within:ring-red-700/12!':
                    firstFieldError('password'),
                }"
              >
                <input
                  id="user-password"
                  v-model="form.password"
                  :type="
                    showPassword ? 'text' : 'password'
                  "
                  :autocomplete="
                    isEditing
                      ? 'new-password'
                      : 'new-password'
                  "
                  :placeholder="
                    isEditing
                      ? 'Dejar vacío para conservarla'
                      : 'Mínimo 10 caracteres'
                  "
                  class="w-full min-h-[2.875rem] px-3 text-slate-900 bg-transparent border-0 outline-hidden text-sm"
                />

                <button
                  type="button"
                  class="inline-flex w-[2.75rem] min-h-[2.75rem] items-center justify-center mr-1 text-slate-500 bg-transparent border-0 rounded-sm hover:text-slate-900 cursor-pointer transition-colors"
                  :aria-label="
                    showPassword
                      ? 'Ocultar contraseña'
                      : 'Mostrar contraseña'
                  "
                  @click="
                    showPassword = !showPassword
                  "
                >
                  <EyeOff
                    v-if="showPassword"
                    :size="19"
                    aria-hidden="true"
                  />

                  <Eye
                    v-else
                    :size="19"
                    aria-hidden="true"
                  />
                </button>
              </div>

              <small v-if="firstFieldError('password')" class="text-red-700 text-xs leading-tight">
                {{ firstFieldError('password') }}
              </small>
            </div>

            <div class="grid gap-2">
              <label for="user-password-confirmation" class="text-slate-900 text-sm font-bold">
                Confirmar contraseña
              </label>

              <div
                class="grid grid-cols-[minmax(0,1fr)_auto] items-center border border-slate-300 rounded-md focus-within:border-brand-green-700 focus-within:ring-3 focus-within:ring-brand-green-700/13 transition-all"
                :class="{
                  'border-red-700! focus-within:border-red-700! focus-within:ring-red-700/12!':
                    firstFieldError(
                      'password_confirmation',
                    ),
                }"
              >
                <input
                  id="user-password-confirmation"
                  v-model="form.password_confirmation"
                  :type="
                    showPasswordConfirmation
                      ? 'text'
                      : 'password'
                  "
                  autocomplete="new-password"
                  class="w-full min-h-[2.875rem] px-3 text-slate-900 bg-transparent border-0 outline-hidden text-sm"
                />

                <button
                  type="button"
                  class="inline-flex w-[2.75rem] min-h-[2.75rem] items-center justify-center mr-1 text-slate-500 bg-transparent border-0 rounded-sm hover:text-slate-900 cursor-pointer transition-colors"
                  :aria-label="
                    showPasswordConfirmation
                      ? 'Ocultar confirmación'
                      : 'Mostrar confirmación'
                  "
                  @click="
                    showPasswordConfirmation =
                      !showPasswordConfirmation
                  "
                >
                  <EyeOff
                    v-if="showPasswordConfirmation"
                    :size="19"
                    aria-hidden="true"
                  />

                  <Eye
                    v-else
                    :size="19"
                    aria-hidden="true"
                  />
                </button>
              </div>

              <small
                v-if="
                  firstFieldError(
                    'password_confirmation',
                  )
                "
                class="text-red-700 text-xs leading-tight"
              >
                {{
                  firstFieldError(
                    'password_confirmation',
                  )
                }}
              </small>
            </div>
          </div>

          <p class="mt-4 mb-0 text-slate-500 text-xs leading-relaxed">
            La contraseña debe incluir mayúsculas,
            minúsculas, números y símbolos.
          </p>

          <footer class="grid grid-cols-1 sm:flex sm:justify-end gap-3 mt-6 pt-5 border-t border-slate-200">
            <button
              type="button"
              class="inline-flex min-h-[3rem] sm:min-w-[10rem] items-center justify-center gap-2 px-4 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-[750] text-sm cursor-pointer transition-colors"
              :disabled="submitting"
              @click="requestClose"
            >
              Cancelar
            </button>

            <button
              type="submit"
              class="inline-flex min-h-[3rem] sm:min-w-[10rem] items-center justify-center gap-2 px-4 text-white bg-brand-orange-800 border border-brand-orange-800 hover:bg-brand-orange-900 rounded-md font-[750] text-sm cursor-pointer disabled:opacity-70 disabled:cursor-wait transition-colors"
              :disabled="submitting"
            >
              <LoaderCircle
                v-if="submitting"
                :size="20"
                class="animate-spin"
                aria-hidden="true"
              />

              {{
                submitting
                  ? 'Guardando...'
                  : isEditing
                    ? 'Guardar cambios'
                    : 'Registrar usuario'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>