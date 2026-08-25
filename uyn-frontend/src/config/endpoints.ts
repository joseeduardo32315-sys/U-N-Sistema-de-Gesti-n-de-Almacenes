type ResourceId = number | string

export const ENDPOINTS = {
  auth: {
    login: '/auth/login',
    me: '/auth/me',
    logout: '/auth/logout',
  },

  operationLogs: '/operation-logs',

  roles: '/roles',
  permissions: '/permissions',

  users: {
    index: '/users',
    show: (id: ResourceId) => `/users/${id}`,
    create: '/users',
    update: (id: ResourceId) => `/users/${id}`,
    activate: (id: ResourceId) => `/users/${id}/activate`,
    deactivate: (id: ResourceId) => `/users/${id}/deactivate`,
  },

  areas: '/areas',

  employees: {
    index: '/employees',
    show: (id: ResourceId) => `/employees/${id}`,
    create: '/employees',
    update: (id: ResourceId) => `/employees/${id}`,
    activate: (id: ResourceId) => `/employees/${id}/activate`,
    deactivate: (id: ResourceId) =>
      `/employees/${id}/deactivate`,
  },

  garmentModels: {
    index: '/garment-models',
    show: (id: ResourceId) => `/garment-models/${id}`,
    create: '/garment-models',
    update: (id: ResourceId) => `/garment-models/${id}`,
    activate: (id: ResourceId) =>
      `/garment-models/${id}/activate`,
    deactivate: (id: ResourceId) =>
      `/garment-models/${id}/deactivate`,
  },

  sizes: '/sizes',

  productionOrders: {
    index: '/production-orders',
    show: (id: ResourceId) => `/production-orders/${id}`,
    create: '/production-orders',
    update: (id: ResourceId) =>
      `/production-orders/${id}`,
  },

  garmentCuts: {
    index: '/garment-cuts',
    show: (id: ResourceId) => `/garment-cuts/${id}`,
    create: '/garment-cuts',
    update: (id: ResourceId) => `/garment-cuts/${id}`,
    classification: (id: ResourceId) =>
      `/garment-cuts/${id}/classification`,
  },

  processes: '/processes',
  pieceTypes: '/piece-types',

  productionMovements: {
    index: '/production-movements',
    show: (id: ResourceId) =>
      `/production-movements/${id}`,
    create: '/production-movements',
    receive: (id: ResourceId) =>
      `/production-movements/${id}/receive`,
    operationLogs: (id: ResourceId) =>
      `/production-movements/${id}/operation-logs`,
  },

  productionOperationLogs: {
    update: (id: ResourceId) =>
      `/production-operation-logs/${id}`,
  },

  productionIncidents: {
    index: '/production-incidents',
    show: (id: ResourceId) =>
      `/production-incidents/${id}`,
    create: '/production-incidents',
    update: (id: ResourceId) =>
      `/production-incidents/${id}`,
    resolve: (id: ResourceId) =>
      `/production-incidents/${id}/resolve`,
    returnForRework: (id: ResourceId) =>
      `/production-incidents/${id}/return-for-rework`,
  },

  employeeCompensations: {
    index: '/employee-compensations',
    show: (id: ResourceId) =>
      `/employee-compensations/${id}`,
    create: '/employee-compensations',
    update: (id: ResourceId) =>
      `/employee-compensations/${id}`,
  },

  pieceworkRates: {
    index: '/piecework-rates',
    show: (id: ResourceId) => `/piecework-rates/${id}`,
    create: '/piecework-rates',
    update: (id: ResourceId) =>
      `/piecework-rates/${id}`,
  },

  embroideryPaymentSettings: {
    index: '/embroidery-payment-settings',
    show: (id: ResourceId) =>
      `/embroidery-payment-settings/${id}`,
    create: '/embroidery-payment-settings',
    update: (id: ResourceId) =>
      `/embroidery-payment-settings/${id}`,
  },

  payrollPeriods: {
    index: '/payroll-periods',
    show: (id: ResourceId) => `/payroll-periods/${id}`,
    create: '/payroll-periods',
    update: (id: ResourceId) =>
      `/payroll-periods/${id}`,
    generate: (id: ResourceId) =>
      `/payroll-periods/${id}/generate`,
    close: (id: ResourceId) =>
      `/payroll-periods/${id}/close`,
  },

  reports: {
    payrollPeriod: (id: ResourceId) =>
      `/reports/payroll-periods/${id}`,
    payrollEmployees: '/reports/payroll-employees',
    productionCuts: '/reports/production-cuts',
    productionProcesses: '/reports/production-processes',
    productionMovements: '/reports/production-movements',
    productionIncidents: '/reports/production-incidents',
    productionLosses: '/reports/production-losses',
    productionReworks: '/reports/production-reworks',
  },

  exports: {
    payrollPeriod: (id: ResourceId) =>
      `/reports/payroll-periods/${id}/export`,
    payrollEmployees:
      '/reports/payroll-employees/export',
    productionCuts:
      '/reports/production-cuts/export',
    productionProcesses:
      '/reports/production-processes/export',
    productionMovements:
      '/reports/production-movements/export',
    productionIncidents:
      '/reports/production-incidents/export',
    productionLosses:
      '/reports/production-losses/export',
    productionReworks:
      '/reports/production-reworks/export',
  },
} as const