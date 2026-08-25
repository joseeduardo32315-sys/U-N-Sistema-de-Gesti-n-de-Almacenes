import type { Component } from 'vue'

import {
  BarChart3,
  ClipboardList,
  LayoutDashboard,
  ScrollText,
  ShieldCheck,
  Shirt,
  TriangleAlert,
  UserRoundCog,
  Users,
  WalletCards,
  Workflow,
} from 'lucide-vue-next'

import { PERMISSIONS } from '@/config/permissions'

export interface NavigationItem {
  label: string
  to: string
  icon: Component
  permission?: string
  permissions?: readonly string[]
}

export interface NavigationGroup {
  label: string
  items: NavigationItem[]
}

export const navigationGroups: NavigationGroup[] = [
  {
    label: 'Principal',
    items: [
      {
        label: 'Dashboard',
        to: '/',
        icon: LayoutDashboard,
        permission: PERMISSIONS.dashboard.view,
      },
    ],
  },
  {
    label: 'Administración',
    items: [
      {
        label: 'Usuarios',
        to: '/usuarios',
        icon: Users,
        permission: PERMISSIONS.users.view,
      },
      {
        label: 'Roles y permisos',
        to: '/roles',
        icon: ShieldCheck,
        permission: PERMISSIONS.roles.view,
      },
      {
        label: 'Bitácora',
        to: '/bitacora',
        icon: ScrollText,
        permission: PERMISSIONS.operationLogs.view,
      },
    ],
  },
  {
    label: 'Catálogos',
    items: [
      {
        label: 'Empleados',
        to: '/empleados',
        icon: UserRoundCog,
        permission: PERMISSIONS.employees.view,
      },
      {
        label: 'Modelos de prenda',
        to: '/modelos',
        icon: Shirt,
        permission: PERMISSIONS.garmentModels.view,
      },
    ],
  },
  {
    label: 'Producción',
    items: [
      {
        label: 'Órdenes',
        to: '/produccion/ordenes',
        icon: ClipboardList,
        permission: PERMISSIONS.cuts.view,
      },
      {
        label: 'Cortes',
        to: '/produccion/cortes',
        icon: ClipboardList,
        permission: PERMISSIONS.cuts.view,
      },
      {
        label: 'Movimientos y avances',
        to: '/produccion/movimientos',
        icon: Workflow,
        permission: PERMISSIONS.processes.view,
      },
      {
        label: 'Incidencias',
        to: '/incidencias',
        icon: TriangleAlert,
        permission: PERMISSIONS.incidents.view,
      },
    ],
  },
  {
    label: 'Control administrativo',
    items: [
      {
        label: 'Nómina',
        to: '/nomina',
        icon: WalletCards,
        permission: PERMISSIONS.payroll.view,
      },
      {
        label: 'Reportes',
        to: '/reportes',
        icon: BarChart3,
        permission: PERMISSIONS.reports.view,
      },
    ],
  },
]