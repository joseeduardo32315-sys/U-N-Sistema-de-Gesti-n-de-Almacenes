<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
} from 'vue'

import {
  Boxes,
  Check,
  KeyRound,
  RefreshCw,
  Search,
  Shield,
  ShieldCheck,
  UsersRound,
} from 'lucide-vue-next'

import Swal from 'sweetalert2'

import { permissionsService } from '@/modules/roles/services/permissions.service'
import { rolesService } from '@/modules/roles/services/roles.service'
import { getApiErrorMessage } from '@/utils/api-error'

import type { Permission } from '@/modules/roles/types/permission.types'
import type { Role } from '@/modules/roles/types/role.types'

type ActiveSection = 'roles' | 'permissions'

const roles = ref<Role[]>([])
const permissions = ref<Permission[]>([])

const loading = ref(false)
const activeSection = ref<ActiveSection>('roles')

const search = ref('')
const selectedModule = ref('')

const modules = computed<string[]>(() => {
  return Array.from(
    new Set(
      permissions.value
        .map((permission) => permission.module)
        .filter(Boolean),
    ),
  ).sort((a, b) => a.localeCompare(b, 'es'))
})

const filteredRoles = computed<Role[]>(() => {
  const normalizedSearch = search.value
    .trim()
    .toLocaleLowerCase('es')

  if (!normalizedSearch) {
    return roles.value
  }

  return roles.value.filter((role) => {
    const matchesName = role.name
      .toLocaleLowerCase('es')
      .includes(normalizedSearch)

    const matchesPermission = role.permissions.some(
      (permission) =>
        permission
          .toLocaleLowerCase('es')
          .includes(normalizedSearch),
    )

    return matchesName || matchesPermission
  })
})

const filteredPermissions = computed<Permission[]>(() => {
  const normalizedSearch = search.value
    .trim()
    .toLocaleLowerCase('es')

  return permissions.value.filter((permission) => {
    const matchesSearch =
      !normalizedSearch ||
      permission.name
        .toLocaleLowerCase('es')
        .includes(normalizedSearch) ||
      permission.module
        .toLocaleLowerCase('es')
        .includes(normalizedSearch) ||
      permission.action
        .toLocaleLowerCase('es')
        .includes(normalizedSearch)

    const matchesModule =
      !selectedModule.value ||
      permission.module === selectedModule.value

    return matchesSearch && matchesModule
  })
})

const totalAssignedPermissions = computed<number>(() => {
  return roles.value.reduce(
    (total, role) => total + role.permissions.length,
    0,
  )
})

const usedPermissions = computed<number>(() => {
  return permissions.value.filter(
    (permission) => permission.roles_count > 0,
  ).length
})

function formatModuleName(module: string): string {
  return module
    .split(/[-_.]/)
    .filter(Boolean)
    .map((part) => {
      return (
        part.charAt(0).toLocaleUpperCase('es') +
        part.slice(1)
      )
    })
    .join(' ')
}

function formatAction(action: string): string {
  const actionLabels: Record<string, string> = {
    view: 'Consultar',
    create: 'Crear',
    update: 'Actualizar',
    deactivate: 'Desactivar',
    activate: 'Activar',
    manage: 'Administrar',
    assign: 'Asignar',
    classify: 'Clasificar',
    close: 'Cerrar',
    generate: 'Generar',
    export: 'Exportar',
    cancel: 'Cancelar',
    finish: 'Finalizar',
    'update-status': 'Actualizar estado',
  }

  return actionLabels[action] ?? formatModuleName(action)
}

function permissionsByModule(
  role: Role,
): Array<{
  module: string
  permissions: string[]
}> {
  const grouped = new Map<string, string[]>()

  for (const permissionName of role.permissions) {
    const [module = 'otros'] = permissionName.split('.')

    const currentPermissions =
      grouped.get(module) ?? []

    currentPermissions.push(permissionName)
    grouped.set(module, currentPermissions)
  }

  return Array.from(grouped.entries())
    .map(([module, groupedPermissions]) => ({
      module,
      permissions: groupedPermissions.sort(
        (a, b) => a.localeCompare(b, 'es'),
      ),
    }))
    .sort((a, b) =>
      a.module.localeCompare(b.module, 'es'),
    )
}

function clearFilters(): void {
  search.value = ''
  selectedModule.value = ''
}

function changeSection(section: ActiveSection): void {
  activeSection.value = section
  clearFilters()
}

async function loadData(): Promise<void> {
  loading.value = true

  try {
    const [
      rolesResponse,
      permissionsResponse,
    ] = await Promise.all([
      rolesService.list(),
      permissionsService.list(),
    ])

    roles.value = rolesResponse
    permissions.value = permissionsResponse
  } catch (error) {
    await Swal.fire({
      title: 'No fue posible cargar la información',
      text: getApiErrorMessage(error),
      icon: 'error',
      confirmButtonText: 'Aceptar',
    })
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void loadData()
})
</script>

<template>
  <section class="grid gap-5">
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <p class="m-0 mb-2 text-brand-orange-800 text-xs font-extrabold tracking-wider uppercase">
          Seguridad y acceso
        </p>

        <h2 class="m-0 text-2xl font-bold text-slate-900">Roles y permisos</h2>

        <p class="mt-2 mb-0 text-slate-600 leading-relaxed text-sm max-w-2xl">
          Consulta los niveles de acceso configurados y las
          funciones asignadas a cada rol.
        </p>
      </div>

      <button
        type="button"
        class="inline-flex w-full sm:w-auto min-h-[3rem] items-center justify-center gap-2 px-4 text-brand-green-800 bg-brand-green-100 border border-brand-green-200 hover:bg-brand-green-200/80 rounded-md font-[750] text-sm cursor-pointer transition-colors"
        :disabled="loading"
        @click="loadData"
      >
        <RefreshCw
          :size="19"
          :class="{
            'animate-spin': loading,
          }"
          aria-hidden="true"
        />

        Actualizar
      </button>
    </header>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
      <article class="flex items-center gap-4 p-4 bg-white border border-slate-200 rounded-lg shadow-xs min-w-0">
        <div class="flex w-13 h-13 shrink-0 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-lg">
          <ShieldCheck
            :size="24"
            aria-hidden="true"
          />
        </div>

        <div class="grid min-w-0">
          <span class="text-slate-500 text-xs">Roles registrados</span>
          <strong class="my-0.5 text-xl font-bold text-slate-900">{{ roles.length }}</strong>
          <small class="text-slate-500 text-xs">Niveles de acceso</small>
        </div>
      </article>

      <article class="flex items-center gap-4 p-4 bg-white border border-slate-200 rounded-lg shadow-xs min-w-0">
        <div class="flex w-13 h-13 shrink-0 items-center justify-center text-brand-orange-900 bg-brand-orange-100 rounded-lg">
          <KeyRound
            :size="24"
            aria-hidden="true"
          />
        </div>

        <div class="grid min-w-0">
          <span class="text-slate-500 text-xs">Permisos disponibles</span>
          <strong class="my-0.5 text-xl font-bold text-slate-900">{{ permissions.length }}</strong>
          <small class="text-slate-500 text-xs">Funciones del sistema</small>
        </div>
      </article>

      <article class="flex items-center gap-4 p-4 bg-white border border-slate-200 rounded-lg shadow-xs min-w-0">
        <div class="flex w-13 h-13 shrink-0 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-lg">
          <Boxes
            :size="24"
            aria-hidden="true"
          />
        </div>

        <div class="grid min-w-0">
          <span class="text-slate-500 text-xs">Módulos protegidos</span>
          <strong class="my-0.5 text-xl font-bold text-slate-900">{{ modules.length }}</strong>
          <small class="text-slate-500 text-xs">Grupos funcionales</small>
        </div>
      </article>

      <article class="flex items-center gap-4 p-4 bg-white border border-slate-200 rounded-lg shadow-xs min-w-0">
        <div class="flex w-13 h-13 shrink-0 items-center justify-center text-brand-orange-900 bg-brand-orange-100 rounded-lg">
          <UsersRound
            :size="24"
            aria-hidden="true"
          />
        </div>

        <div class="grid min-w-0">
          <span class="text-slate-500 text-xs">Permisos utilizados</span>
          <strong class="my-0.5 text-xl font-bold text-slate-900">{{ usedPermissions }}</strong>
          <small class="text-slate-500 text-xs">
            {{ totalAssignedPermissions }} asignaciones
          </small>
        </div>
      </article>
    </div>

    <nav
      class="grid grid-cols-2 gap-2 p-1 bg-slate-100 border border-slate-200 rounded-lg"
      aria-label="Secciones de roles y permisos"
    >
      <button
        type="button"
        class="inline-flex min-h-[3rem] items-center justify-center gap-2 px-3 rounded-md font-[750] text-sm cursor-pointer transition-colors border-0"
        :class="
          activeSection === 'roles'
            ? 'text-slate-900 bg-white shadow-xs'
            : 'text-slate-600 hover:text-slate-900'
        "
        @click="changeSection('roles')"
      >
        <Shield
          :size="19"
          aria-hidden="true"
        />

        Roles

        <span class="inline-flex min-w-[1.6rem] min-h-[1.6rem] items-center justify-center px-1 text-slate-600 bg-slate-100 rounded-full text-xs font-bold">{{ roles.length }}</span>
      </button>

      <button
        type="button"
        class="inline-flex min-h-[3rem] items-center justify-center gap-2 px-3 rounded-md font-[750] text-sm cursor-pointer transition-colors border-0"
        :class="
          activeSection === 'permissions'
            ? 'text-slate-900 bg-white shadow-xs'
            : 'text-slate-600 hover:text-slate-900'
        "
        @click="changeSection('permissions')"
      >
        <KeyRound
          :size="19"
          aria-hidden="true"
        />

        Permisos

        <span class="inline-flex min-w-[1.6rem] min-h-[1.6rem] items-center justify-center px-1 text-slate-600 bg-slate-100 rounded-full text-xs font-bold">{{ permissions.length }}</span>
      </button>
    </nav>

    <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto_auto] gap-3 p-4 bg-white border border-slate-200 rounded-lg">
      <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3 min-h-[3rem] px-3 bg-white border border-slate-300 rounded-md focus-within:border-brand-green-700 focus-within:ring-3 focus-within:ring-brand-green-700/13 transition-all">
        <Search
          :size="19"
          class="text-slate-400"
          aria-hidden="true"
        />

        <input
          v-model="search"
          type="search"
          :placeholder="
            activeSection === 'roles'
              ? 'Buscar rol o permiso asignado'
              : 'Buscar permiso, módulo o acción'
          "
          class="w-full min-w-0 bg-transparent border-0 outline-hidden text-slate-900 text-sm placeholder:text-slate-400"
        />
      </div>

      <select
        v-if="activeSection === 'permissions'"
        v-model="selectedModule"
        aria-label="Filtrar permisos por módulo"
        class="w-full sm:w-auto min-h-[3rem] px-3 text-slate-900 bg-white border border-slate-300 rounded-md text-sm outline-hidden focus:border-brand-green-700 focus:ring-3 focus:ring-brand-green-700/13"
      >
        <option value="">
          Todos los módulos
        </option>

        <option
          v-for="module in modules"
          :key="module"
          :value="module"
        >
          {{ formatModuleName(module) }}
        </option>
      </select>

      <button
        type="button"
        class="inline-flex min-h-[3rem] items-center justify-center px-4 text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md font-bold text-sm transition-colors cursor-pointer disabled:opacity-50"
        :disabled="
          !search &&
          !selectedModule
        "
        @click="clearFilters"
      >
        Limpiar filtros
      </button>
    </div>

    <div
      v-if="loading"
      class="grid place-content-center min-h-[16rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
    >
      <div class="w-9 h-9 border-4 border-brand-green-100 border-t-brand-green-700 rounded-full animate-spin mx-auto" />

      <p class="text-slate-600 text-sm">Cargando roles y permisos...</p>
    </div>

    <template v-else-if="activeSection === 'roles'">
      <div
        v-if="filteredRoles.length > 0"
        class="grid grid-cols-1 lg:grid-cols-2 gap-4"
      >
        <article
          v-for="role in filteredRoles"
          :key="role.id"
          class="flex flex-col p-5 bg-white border border-slate-200 rounded-xl shadow-xs gap-4"
        >
          <header class="flex items-center gap-3 pb-3 border-b border-slate-100">
            <div class="flex w-11 h-11 shrink-0 items-center justify-center text-brand-green-800 bg-brand-green-100 rounded-lg">
              <ShieldCheck
                :size="24"
                aria-hidden="true"
              />
            </div>

            <div>
              <h3 class="m-0 text-lg font-bold text-slate-900">{{ role.name }}</h3>

              <span class="text-slate-500 text-xs">
                {{ role.permissions.length }}
                {{
                  role.permissions.length === 1
                    ? 'permiso'
                    : 'permisos'
                }}
              </span>
            </div>
          </header>

          <div
            v-if="role.permissions.length > 0"
            class="grid gap-3 flex-1"
          >
            <section
              v-for="group in permissionsByModule(role)"
              :key="group.module"
              class="p-3 bg-slate-50 rounded-lg border border-slate-200/60 grid gap-2"
            >
              <header class="flex items-center justify-between gap-2">
                <strong class="text-slate-900 font-bold text-xs">
                  {{ formatModuleName(group.module) }}
                </strong>

                <span class="text-slate-500 text-[11px] font-semibold">
                  {{ group.permissions.length }}
                </span>
              </header>

              <div class="flex flex-wrap gap-1.5">
                <span
                  v-for="permissionName in group.permissions"
                  :key="permissionName"
                  class="inline-flex items-center gap-1 px-2 py-0.5 text-xs text-brand-green-900 bg-brand-green-100/70 border border-brand-green-200 rounded-md font-mono text-[11px]"
                >
                  <Check
                    :size="14"
                    aria-hidden="true"
                  />

                  {{ permissionName }}
                </span>
              </div>
            </section>
          </div>

          <div
            v-else
            class="p-6 text-center text-slate-500 text-sm bg-slate-50 rounded-lg border border-dashed border-slate-200"
          >
            Este rol no tiene permisos asignados.
          </div>

          <footer class="flex items-center justify-between text-xs text-slate-500 pt-3 border-t border-slate-100">
            <span>Guard</span>
            <strong class="font-mono text-slate-700">{{ role.guard_name }}</strong>
          </footer>
        </article>
      </div>

      <div
        v-else
        class="grid place-content-center min-h-[16rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
      >
        <Shield
          :size="42"
          class="text-slate-400 mx-auto"
          aria-hidden="true"
        />

        <h3 class="m-0 text-lg font-bold text-slate-900">No se encontraron roles</h3>

        <p class="m-0 text-slate-600 text-sm">
          Modifica el criterio de búsqueda.
        </p>
      </div>
    </template>

    <template v-else>
      <div
        v-if="filteredPermissions.length > 0"
        class="grid gap-3 md:hidden"
      >
        <article
          v-for="permission in filteredPermissions"
          :key="permission.id"
          class="p-4 bg-white border border-slate-200 rounded-xl shadow-xs grid gap-3"
        >
          <header class="flex items-center gap-3">
            <div class="flex w-9 h-9 shrink-0 items-center justify-center text-brand-orange-800 bg-brand-orange-100 rounded-md">
              <KeyRound
                :size="20"
                aria-hidden="true"
              />
            </div>

            <div class="grid min-w-0">
              <strong class="truncate text-slate-900 font-mono text-xs font-bold">{{ permission.name }}</strong>
              <span class="text-slate-500 text-xs">
                {{ formatModuleName(permission.module) }}
              </span>
            </div>
          </header>

          <dl class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 text-xs">
            <div>
              <dt class="text-slate-400">Acción</dt>
              <dd class="m-0 font-medium text-slate-800">
                {{ formatAction(permission.action) }}
              </dd>
            </div>

            <div>
              <dt class="text-slate-400">Roles</dt>
              <dd class="m-0 font-medium text-slate-800">{{ permission.roles_count }}</dd>
            </div>

            <div>
              <dt class="text-slate-400">Guard</dt>
              <dd class="m-0 font-mono text-slate-800">{{ permission.guard_name }}</dd>
            </div>
          </dl>
        </article>
      </div>

      <div
        v-if="filteredPermissions.length > 0"
        class="hidden md:block"
      >
        <div class="overflow-x-auto bg-white border border-slate-200 rounded-xl shadow-xs">
          <table class="w-full border-collapse text-left text-sm">
            <thead>
              <tr>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">Permiso</th>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">Módulo</th>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">Acción</th>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">Roles asignados</th>
                <th class="bg-slate-50 p-3.5 px-4 font-bold text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wider">Guard</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="permission in filteredPermissions"
                :key="permission.id"
              >
                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">
                  <div class="flex items-center gap-2.5">
                    <KeyRound
                      :size="18"
                      class="text-brand-orange-800 shrink-0"
                      aria-hidden="true"
                    />

                    <strong class="font-mono text-slate-900 font-bold text-xs">
                      {{ permission.name }}
                    </strong>
                  </div>
                </td>

                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                    {{
                      formatModuleName(
                        permission.module,
                      )
                    }}
                  </span>
                </td>

                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">
                  {{
                    formatAction(permission.action)
                  }}
                </td>

                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold"
                    :class="
                      permission.roles_count === 0
                        ? 'text-slate-500 bg-slate-100'
                        : 'text-brand-green-900 bg-brand-green-100'
                    "
                  >
                    {{ permission.roles_count }}
                  </span>
                </td>

                <td class="p-3.5 px-4 border-b border-slate-100 text-slate-700 align-middle font-mono text-xs">{{ permission.guard_name }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div
        v-if="filteredPermissions.length === 0"
        class="grid place-content-center min-h-[16rem] p-8 text-center gap-3 bg-white border border-slate-200 rounded-xl"
      >
        <KeyRound
          :size="42"
          class="text-slate-400 mx-auto"
          aria-hidden="true"
        />

        <h3 class="m-0 text-lg font-bold text-slate-900">No se encontraron permisos</h3>

        <p class="m-0 text-slate-600 text-sm">
          Modifica la búsqueda o el módulo seleccionado.
        </p>
      </div>
    </template>

    <aside class="flex items-center gap-3 p-4 bg-amber-50 border border-amber-200 text-amber-900 rounded-lg text-xs leading-relaxed">
      <ShieldCheck
        :size="21"
        class="shrink-0 text-amber-700"
        aria-hidden="true"
      />

      <p class="m-0">
        La API actual permite consultar roles y permisos.
        Las operaciones de creación o modificación no están
        disponibles en la especificación vigente.
      </p>
    </aside>
  </section>
</template>