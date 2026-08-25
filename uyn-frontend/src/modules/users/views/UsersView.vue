<script setup lang="ts">
import {
  onMounted,
  reactive,
  ref,
} from 'vue'

import {
  ChevronLeft,
  ChevronRight,
  Mail,
  Pencil,
  Plus,
  Power,
  PowerOff,
  RotateCcw,
  Search,
  ShieldCheck,
  UserRound,
  Users,
} from 'lucide-vue-next'

import Swal from 'sweetalert2'

import { PERMISSIONS } from '@/config/permissions'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { rolesService } from '@/modules/roles/services/roles.service'
import { usersService } from '@/modules/users/services/users.service'
import UserFormDialog from '@/modules/users/components/UserFormDialog.vue'
import { getApiErrorMessage } from '@/utils/api-error'

import type { Role } from '@/modules/roles/types/role.types'
import type {
  User,
  UserStatus,
} from '@/modules/users/types/user.types'
import type { PaginationMeta } from '@/types/api'

interface Filters {
  search: string
  status: UserStatus | ''
  role: string
  perPage: number
}

const authStore = useAuthStore()

const users = ref<User[]>([])
const roles = ref<Role[]>([])

const loading = ref(false)
const actionUserId = ref<number | null>(null)

const formOpen = ref(false)
const selectedUser = ref<User | null>(null)

const filters = reactive<Filters>({
  search: '',
  status: '',
  role: '',
  perPage: 15,
})

const pagination = ref<PaginationMeta>({
  current_page: 1,
  from: null,
  last_page: 1,
  per_page: 15,
  to: null,
  total: 0,
})

function formatDate(value: string): string {
  return new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
  }).format(new Date(value))
}

function roleName(user: User): string {
  return user.roles.at(0) ?? 'Sin rol'
}

function canEdit(): boolean {
  return (
    authStore.can(PERMISSIONS.users.update) &&
    authStore.can(PERMISSIONS.roles.view)
  )
}

function canCreate(): boolean {
  return (
    authStore.can(PERMISSIONS.users.create) &&
    authStore.can(PERMISSIONS.roles.view)
  )
}

function canActivate(user: User): boolean {
  return (
    user.status === 'inactive' &&
    authStore.can(PERMISSIONS.users.activate)
  )
}

function canDeactivate(user: User): boolean {
  return (
    user.status === 'active' &&
    user.id !== authStore.user?.id &&
    authStore.can(PERMISSIONS.users.deactivate)
  )
}

async function loadUsers(page = 1): Promise<void> {
  loading.value = true

  try {
    const response = await usersService.list({
      search: filters.search.trim(),
      status: filters.status,
      role: filters.role,
      per_page: filters.perPage,
      page,
    })

    users.value = response.data
    pagination.value = response.meta
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible cargar los usuarios',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    loading.value = false
  }
}

async function loadRoles(): Promise<void> {
  if (!authStore.can(PERMISSIONS.roles.view)) {
    return
  }

  try {
    roles.value = await rolesService.list()
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible cargar los roles',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  }
}

function applyFilters(): void {
  void loadUsers(1)
}

function clearFilters(): void {
  filters.search = ''
  filters.status = ''
  filters.role = ''
  filters.perPage = 15

  void loadUsers(1)
}

function openCreateForm(): void {
  selectedUser.value = null
  formOpen.value = true
}

function openEditForm(user: User): void {
  selectedUser.value = user
  formOpen.value = true
}

function closeForm(): void {
  formOpen.value = false
  selectedUser.value = null
}

async function handleSaved(
  user: User,
  message: string,
): Promise<void> {
  closeForm()

  await Swal.fire({
    title: message,
    icon: 'success',
    timer: 1700,
    showConfirmButton: false,
  })

  await loadUsers(pagination.value.current_page)

  if (authStore.user?.id === user.id) {
    await authStore.fetchCurrentUser()
  }
}

async function toggleUserStatus(
  user: User,
): Promise<void> {
  const activating = user.status === 'inactive'

  const confirmation = await Swal.fire({
    title: activating
      ? '¿Activar usuario?'
      : '¿Desactivar usuario?',
    text: activating
      ? `${user.name} podrá ingresar nuevamente al sistema.`
      : `${user.name} dejará de tener acceso al sistema.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: activating
      ? 'Sí, activar'
      : 'Sí, desactivar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: activating
      ? '#3f6b2a'
      : '#b91c1c',
  })

  if (!confirmation.isConfirmed) {
    return
  }

  actionUserId.value = user.id

  try {
    const response = activating
      ? await usersService.activate(user.id)
      : await usersService.deactivate(user.id)

    await Swal.fire({
      title: response.message,
      icon: 'success',
      timer: 1600,
      showConfirmButton: false,
    })

    await loadUsers(pagination.value.current_page)
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible cambiar el estado',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    actionUserId.value = null
  }
}

function goToPreviousPage(): void {
  if (pagination.value.current_page > 1) {
    void loadUsers(
      pagination.value.current_page - 1,
    )
  }
}

function goToNextPage(): void {
  if (
    pagination.value.current_page <
    pagination.value.last_page
  ) {
    void loadUsers(
      pagination.value.current_page + 1,
    )
  }
}

onMounted(async () => {
  await Promise.all([
    loadUsers(),
    loadRoles(),
  ])
})
</script>

<template>
  <section class="grid gap-5">
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <p class="m-0 mb-2 text-brand-orange-800 text-xs font-extrabold tracking-wider uppercase">
          Administración
        </p>

        <h2 class="m-0 text-2xl font-bold text-slate-900">Usuarios</h2>

        <p class="mt-2 mb-0 text-slate-600 leading-relaxed text-sm">
          Gestiona las cuentas, roles y estado de acceso al
          sistema.
        </p>
      </div>

      <button
        v-if="canCreate()"
        type="button"
        class="inline-flex w-full sm:w-auto min-h-[3rem] items-center justify-center gap-2 px-4 py-3 text-white bg-brand-orange-800 hover:bg-brand-orange-900 rounded-md font-[750] text-sm transition-colors cursor-pointer border-0"
        @click="openCreateForm"
      >
        <Plus
          :size="20"
          aria-hidden="true"
        />

        Registrar usuario
      </button>
    </header>

    <form
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1fr_auto] gap-3 p-4 bg-white border border-slate-200 rounded-lg"
      @submit.prevent="applyFilters"
    >
      <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3 min-h-[3rem] px-3 bg-white border border-slate-300 rounded-md focus-within:border-brand-green-700 focus-within:ring-3 focus-within:ring-brand-green-700/13 transition-all">
        <Search
          :size="20"
          class="text-slate-400"
          aria-hidden="true"
        />

        <input
          v-model="filters.search"
          type="search"
          placeholder="Buscar por nombre, usuario o correo"
          maxlength="120"
          class="w-full min-w-0 bg-transparent border-0 outline-hidden text-slate-900 text-sm placeholder:text-slate-400"
        />
      </div>

      <select
        v-model="filters.status"
        aria-label="Filtrar por estado"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">
          Todos los estados
        </option>

        <option value="active">
          Activos
        </option>

        <option value="inactive">
          Inactivos
        </option>
      </select>

      <select
        v-if="roles.length > 0"
        v-model="filters.role"
        aria-label="Filtrar por rol"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">
          Todos los roles
        </option>

        <option
          v-for="role in roles"
          :key="role.id"
          :value="role.name"
        >
          {{ role.name }}
        </option>
      </select>

      <select
        v-model="filters.perPage"
        aria-label="Registros por página"
        class="w-full min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option :value="10">
          10 por página
        </option>

        <option :value="15">
          15 por página
        </option>

        <option :value="25">
          25 por página
        </option>

        <option :value="50">
          50 por página
        </option>
      </select>

      <div class="flex gap-2">
        <button
          type="submit"
          class="inline-flex min-h-[3rem] items-center justify-center gap-2 px-4 text-white bg-brand-green-700 hover:bg-brand-green-800 rounded-md font-bold text-sm transition-colors cursor-pointer border-0 disabled:opacity-50"
          :disabled="loading"
        >
          <Search
            :size="19"
            aria-hidden="true"
          />

          Buscar
        </button>

        <button
          type="button"
          class="inline-flex min-h-[3rem] items-center justify-center gap-2 px-4 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-bold text-sm transition-colors cursor-pointer disabled:opacity-50"
          :disabled="loading"
          @click="clearFilters"
        >
          <RotateCcw
            :size="19"
            aria-hidden="true"
          />

          Limpiar
        </button>
      </div>
    </form>

    <div
      v-if="loading"
      class="grid place-content-center min-h-[16rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <div class="w-9 h-9 border-4 border-brand-green-100 border-t-brand-green-700 rounded-full animate-spin mx-auto" />

      <p class="text-slate-600 text-sm">Cargando usuarios...</p>
    </div>

    <div
      v-else-if="users.length === 0"
      class="grid place-content-center min-h-[16rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <Users
        :size="42"
        class="text-slate-400 mx-auto"
        aria-hidden="true"
      />

      <h3 class="m-0 text-lg font-bold text-slate-900">No se encontraron usuarios</h3>

      <p class="m-0 text-slate-600 text-sm">
        Modifica los filtros o registra una nueva cuenta.
      </p>
    </div>

    <template v-else>
      <div class="grid gap-4 md:hidden">
        <article
          v-for="user in users"
          :key="user.id"
          class="p-4 bg-white border border-slate-200 rounded-xl shadow-xs grid gap-3"
        >
          <header class="flex items-center gap-3">
            <div class="flex w-11 h-11 shrink-0 items-center justify-center text-brand-orange-900 bg-brand-orange-100 rounded-full text-base font-extrabold">
              {{
                user.name
                  .charAt(0)
                  .toUpperCase()
              }}
            </div>

            <div class="grid min-w-0 flex-1">
              <strong class="truncate text-slate-900 font-bold text-sm">{{ user.name }}</strong>
              <span class="truncate text-slate-500 text-xs">@{{ user.username }}</span>
            </div>

            <span
              class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold shrink-0"
              :class="{
                'text-emerald-800 bg-emerald-100':
                  user.status === 'active',
                'text-slate-700 bg-slate-200':
                  user.status === 'inactive',
              }"
            >
              {{
                user.status === 'active'
                  ? 'Activo'
                  : 'Inactivo'
              }}
            </span>
          </header>

          <div class="grid gap-2 py-2 border-y border-slate-100 text-xs text-slate-600">
            <div class="flex items-center gap-2.5">
              <Mail
                :size="18"
                class="text-slate-400"
                aria-hidden="true"
              />

              <span>{{ user.email }}</span>
            </div>

            <div class="flex items-center gap-2.5">
              <ShieldCheck
                :size="18"
                class="text-slate-400"
                aria-hidden="true"
              />

              <span>{{ roleName(user) }}</span>
            </div>

            <div class="flex items-center gap-2.5">
              <UserRound
                :size="18"
                class="text-slate-400"
                aria-hidden="true"
              />

              <span>
                Registrado {{ formatDate(user.created_at) }}
              </span>
            </div>
          </div>

          <footer class="flex items-center gap-2 pt-1">
            <button
              v-if="canEdit()"
              type="button"
              class="flex-1 inline-flex min-h-[2.5rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-md font-bold text-xs transition-colors border-0 cursor-pointer"
              @click="openEditForm(user)"
            >
              <Pencil
                :size="18"
                aria-hidden="true"
              />

              Editar
            </button>

            <button
              v-if="canDeactivate(user)"
              type="button"
              class="flex-1 inline-flex min-h-[2.5rem] items-center justify-center gap-1.5 px-3 text-red-700 bg-red-100 hover:bg-red-200/70 rounded-md font-bold text-xs transition-colors border-0 cursor-pointer disabled:opacity-50"
              :disabled="actionUserId === user.id"
              @click="toggleUserStatus(user)"
            >
              <PowerOff
                :size="18"
                aria-hidden="true"
              />

              Desactivar
            </button>

            <button
              v-if="canActivate(user)"
              type="button"
              class="flex-1 inline-flex min-h-[2.5rem] items-center justify-center gap-1.5 px-3 text-emerald-800 bg-emerald-100 hover:bg-emerald-200/70 rounded-md font-bold text-xs transition-colors border-0 cursor-pointer disabled:opacity-50"
              :disabled="actionUserId === user.id"
              @click="toggleUserStatus(user)"
            >
              <Power
                :size="18"
                aria-hidden="true"
              />

              Activar
            </button>
          </footer>
        </article>
      </div>

      <div class="hidden md:block">
        <div class="overflow-x-auto bg-white border border-slate-200 rounded-xl shadow-xs">
          <table class="w-full border-collapse text-left text-sm">
            <thead>
              <tr>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">Usuario</th>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">Correo</th>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">Rol</th>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">Estado</th>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">Registro</th>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider" aria-label="Acciones" />
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="user in users"
                :key="user.id"
              >
                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">
                  <div class="flex items-center gap-3">
                    <div class="flex w-9 h-9 shrink-0 items-center justify-center text-brand-orange-900 bg-brand-orange-100 rounded-full text-sm font-extrabold">
                      {{
                        user.name
                          .charAt(0)
                          .toUpperCase()
                      }}
                    </div>

                    <span class="grid min-w-0">
                      <strong class="truncate text-slate-900 font-bold">{{ user.name }}</strong>
                      <small class="truncate text-slate-500 text-xs">@{{ user.username }}</small>
                    </span>
                  </div>
                </td>

                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">{{ user.email }}</td>

                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">{{ roleName(user) }}</td>

                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">
                  <span
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold shrink-0"
                    :class="{
                      'text-emerald-800 bg-emerald-100':
                        user.status === 'active',
                      'text-slate-700 bg-slate-200':
                        user.status === 'inactive',
                    }"
                  >
                    {{
                      user.status === 'active'
                        ? 'Activo'
                        : 'Inactivo'
                    }}
                  </span>
                </td>

                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">{{ formatDate(user.created_at) }}</td>

                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">
                  <div class="flex items-center gap-1.5 justify-end">
                    <button
                      v-if="canEdit()"
                      type="button"
                      title="Editar usuario"
                      class="inline-flex w-9 h-9 items-center justify-center p-0 text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 rounded-md transition-colors border-0 cursor-pointer"
                      @click="openEditForm(user)"
                    >
                      <Pencil
                        :size="18"
                        aria-hidden="true"
                      />
                    </button>

                    <button
                      v-if="canDeactivate(user)"
                      type="button"
                      class="inline-flex w-9 h-9 items-center justify-center p-0 text-red-700 bg-red-50 hover:bg-red-100 hover:text-red-800 rounded-md transition-colors border-0 cursor-pointer disabled:opacity-50"
                      title="Desactivar usuario"
                      :disabled="
                        actionUserId === user.id
                      "
                      @click="toggleUserStatus(user)"
                    >
                      <PowerOff
                        :size="18"
                        aria-hidden="true"
                      />
                    </button>

                    <button
                      v-if="canActivate(user)"
                      type="button"
                      class="inline-flex w-9 h-9 items-center justify-center p-0 text-emerald-800 bg-emerald-50 hover:bg-emerald-100 hover:text-emerald-900 rounded-md transition-colors border-0 cursor-pointer disabled:opacity-50"
                      title="Activar usuario"
                      :disabled="
                        actionUserId === user.id
                      "
                      @click="toggleUserStatus(user)"
                    >
                      <Power
                        :size="18"
                        aria-hidden="true"
                      />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <footer class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 bg-white border border-slate-200 rounded-lg text-xs text-slate-600">
        <p class="m-0">
          Mostrando
          <strong class="text-slate-900 font-bold">
            {{ pagination.from ?? 0 }}
          </strong>
          a
          <strong class="text-slate-900 font-bold">
            {{ pagination.to ?? 0 }}
          </strong>
          de
          <strong class="text-slate-900 font-bold">{{ pagination.total }}</strong>
          usuarios
        </p>

        <div class="flex items-center gap-3">
          <button
            type="button"
            class="inline-flex min-h-[2.35rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 rounded-md font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
            :disabled="
              pagination.current_page <= 1 ||
              loading
            "
            @click="goToPreviousPage"
          >
            <ChevronLeft
              :size="19"
              aria-hidden="true"
            />

            Anterior
          </button>

          <span class="font-medium">
            Página {{ pagination.current_page }}
            de {{ pagination.last_page }}
          </span>

          <button
            type="button"
            class="inline-flex min-h-[2.35rem] items-center justify-center gap-1.5 px-3 text-slate-700 bg-white border border-slate-300 rounded-md font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
            :disabled="
              pagination.current_page >=
                pagination.last_page ||
              loading
            "
            @click="goToNextPage"
          >
            Siguiente

            <ChevronRight
              :size="19"
              aria-hidden="true"
            />
          </button>
        </div>
      </footer>
    </template>

    <UserFormDialog
      :open="formOpen"
      :user="selectedUser"
      :roles="roles"
      @close="closeForm"
      @saved="handleSaved"
    />
  </section>
</template>