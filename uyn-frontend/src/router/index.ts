import {
  createRouter,
  createWebHistory,
  type RouteRecordRaw,
} from 'vue-router'

import { PERMISSIONS } from '@/config/permissions'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { pinia } from '@/plugins/pinia'

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () =>
      import('@/modules/auth/views/LoginView.vue'),
    meta: {
      title: 'Iniciar sesión',
      guestOnly: true,
    },
  },

  {
    path: '/',
    component: () =>
      import('@/layouts/AdminLayout.vue'),
    meta: {
      requiresAuth: true,
    },
    children: [
      {
        path: '',
        name: 'dashboard',
        component: () =>
          import('@/views/DashboardView.vue'),
        meta: {
          title: 'Dashboard',
          permission: PERMISSIONS.dashboard.view,
        },
      },

      {
        path: 'usuarios',
        name: 'users',
        component: () => 
          import('@/modules/users/views/UsersView.vue'),
        meta: {
          title: 'Usuarios',
          description: 'Administra los usuarios que tienen acceso al sistema.',
          permission: PERMISSIONS.users.view
        },
      },

      {
        path: 'roles',
        name: 'roles',
        component: () => 
          import('@/modules/roles/views/RolesView.vue'),
        meta: {
          title: 'Roles y permisos',
          description:
            'Consulta los roles y permisos disponibles.',
          permission: PERMISSIONS.roles.view,
        },
      },

      {
        path: 'bitacora',
        name: 'operation-logs',
        component: () =>
          import('@/modules/operation-logs/views/OperationLogsView.vue'),
        meta: {
          title: 'Bitácora',
          description:
            'Consulta las acciones realizadas dentro del sistema.',
          permission: PERMISSIONS.operationLogs.view,
        },
      },

      {
        path: 'empleados',
        name: 'employees',
        component: () => 
          import('@/modules/employees/views/EmployeesView.vue'),
        meta: {
          title: 'Empleados',
          description:
            'Administra empleados internos y maquileros externos.',
          permission: PERMISSIONS.employees.view,
        },
      },

      {
        path: 'modelos',
        name: 'garment-models',
        component: () => 
          import('@/modules/garment-models/views/GarmentModelsView.vue'),
        meta: {
          title: 'Modelos de prenda',
          description:
            'Gestiona los modelos producidos por la empresa.',
          permission: PERMISSIONS.garmentModels.view,
        },
      },

      {
        path: 'produccion/ordenes',
        name: 'production-orders',
        component: () => 
          import('@/modules/production-orders/views/ProductionOrdersView.vue'),
        meta: {
          title: 'Órdenes de producción',
          description:
            'Consulta y registra pedidos maestros de producción.',
          permission: PERMISSIONS.cuts.view,
        },
      },

      {
        path: 'produccion/cortes',
        name: 'garment-cuts',
        component: () => 
          import('@/modules/garment-cuts/views/GarmentCutsView.vue'),
        meta: {
          title: 'Cortes de producción',
          description:
            'Consulta lotes, tallas y distribución de piezas.',
          permission: PERMISSIONS.cuts.view,
        },
      },

      {
        path: 'produccion/movimientos',
        name: 'production-movements',
        component: () => 
          import('@/modules/production-movements/views/ProductionMovementsView.vue'),
        meta: {
          title: 'Movimientos y avances',
          description:
            'Administra transferencias y avances del taller.',
          permission: PERMISSIONS.processes.view,
        },
      },

      {
        path: 'incidencias',
        name: 'production-incidents',
        component: () =>
          import(
            '@/modules/production-incidents/views/ProductionIncidentsView.vue'
          ),
        meta: {
          title: 'Incidencias',
          description:
            'Consulta pérdidas, daños, retrasos y reprocesos.',
          permission: PERMISSIONS.incidents.view,
        },
      },

      {
        path: 'nomina',
        name: 'payroll-settings',
        component: () =>
          import('@/modules/payroll-settings/views/PayrollSettingsView.vue'),
        meta: {
          title: 'Nómina',
          description:
            'Gestiona compensaciones, tarifas y periodos de pago.',
          permission: PERMISSIONS.payroll.view,
        },
      },

      {
        path: 'reportes',
        name: 'reports',
        component: () =>
          import('@/modules/reports/views/ReportsView.vue'),
        meta: {
          title: 'Reportes',
          description:
            'Consulta y exporta información productiva y administrativa.',
          permission: PERMISSIONS.reports.view,
        },
      },
    ],
  },

  {
    path: '/sin-permiso',
    name: 'forbidden',
    component: () =>
      import('@/views/ForbiddenView.vue'),
    meta: {
      title: 'Acceso restringido',
      requiresAuth: true,
    },
  },

  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () =>
      import('@/views/NotFoundView.vue'),
    meta: {
      title: 'Página no encontrada',
    },
  },
]

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,

  scrollBehavior() {
    return {
      top: 0,
      behavior: 'smooth',
    }
  },
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore(pinia)

  await authStore.initialize()

  if (
    to.meta.guestOnly &&
    authStore.isAuthenticated
  ) {
    return {
      name: 'dashboard',
    }
  }

  if (
    to.meta.requiresAuth &&
    !authStore.isAuthenticated
  ) {
    return {
      name: 'login',
      query: {
        redirect: to.fullPath,
      },
    }
  }

  if (
    to.meta.permission &&
    !authStore.can(to.meta.permission)
  ) {
    return {
      name: 'forbidden',
    }
  }

  if (
    to.meta.permissions &&
    !authStore.canAny(to.meta.permissions)
  ) {
    return {
      name: 'forbidden',
    }
  }

  return true
})

router.afterEach((to) => {
  const appName =
    import.meta.env.VITE_APP_NAME ??
    'Sistema de Administración y Control de Procesos (SACOP)'

  document.title = to.meta.title
    ? `${to.meta.title} | ${appName}`
    : appName
})

export default router