export const PERMISSIONS = {
  dashboard: {
    view: 'dashboard.view',
  },

  users: {
    view: 'users.view',
    create: 'users.create',
    update: 'users.update',
    deactivate: 'users.deactivate',
    activate: 'users.activate',
  },

  roles: {
    view: 'roles.view',
    manage: 'roles.manage',
  },

  operationLogs: {
    view: 'operation-logs.view',
  },

  employees: {
    view: 'employees.view',
    create: 'employees.create',
    update: 'employees.update',
    deactivate: 'employees.deactivate',
    activate: 'employees.activate',
  },

  garmentModels: {
    view: 'garment-models.view',
    create: 'garment-models.create',
    update: 'garment-models.update',
    deactivate: 'garment-models.deactivate',
    activate: 'garment-models.activate',
  },

  cuts: {
    view: 'cuts.view',
    create: 'cuts.create',
    update: 'cuts.update',
    cancel: 'cuts.cancel',
    finish: 'cuts.finish',
  },

  processes: {
    view: 'processes.view',
    assign: 'processes.assign',
    updateStatus: 'processes.update-status',
    classify: 'processes.classify',
  },

  deliveries: {
    create: 'deliveries.create',
  },

  receptions: {
    create: 'receptions.create',
  },

  incidents: {
    view: 'incidents.view',
    create: 'incidents.create',
    update: 'incidents.update',
    close: 'incidents.close',
  },

  payroll: {
    view: 'payroll.view',
    manage: 'payroll.manage',
    generate: 'payroll.generate',
    close: 'payroll.close',
  },

  reports: {
    view: 'reports.view',
    export: 'reports.export',
  },
} as const

type DeepStringValue<T> =
  T extends string
    ? T
    : T extends Record<string, unknown>
      ? DeepStringValue<T[keyof T]>
      : never

export type Permission = DeepStringValue<typeof PERMISSIONS>