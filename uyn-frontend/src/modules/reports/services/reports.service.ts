import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'
import { downloadCsv } from '@/services/download.service'
import { cleanQueryParams } from '@/utils/query-params'

import type {
  ApiResource,
  PaginatedResponse,
} from '@/types/api'

import type {
  PayrollEmployeeReportItem,
  PayrollPeriodReportSummary,
  ProductionCutReportItem,
  ProductionLossReportItem,
  ProductionProcessReportItem,
  ProductionReworkReportItem,
  ReportQueryParams,
} from '@/modules/reports/types/reports.types'

export const reportsService = {
  async getProductionCuts(
    query: ReportQueryParams = {},
  ): Promise<PaginatedResponse<ProductionCutReportItem>> {
    const response = await api.get<
      PaginatedResponse<ProductionCutReportItem>
    >(ENDPOINTS.reports.productionCuts, {
      params: cleanQueryParams(query),
    })
    return response.data
  },

  async getProductionProcesses(
    query: ReportQueryParams = {},
  ): Promise<{ data: ProductionProcessReportItem[] }> {
    const response = await api.get<{
      data: ProductionProcessReportItem[]
    }>(ENDPOINTS.reports.productionProcesses, {
      params: cleanQueryParams(query),
    })
    return response.data
  },

  async getProductionLosses(
    query: ReportQueryParams = {},
  ): Promise<{ data: ProductionLossReportItem[] }> {
    const response = await api.get<{
      data: ProductionLossReportItem[]
    }>(ENDPOINTS.reports.productionLosses, {
      params: cleanQueryParams(query),
    })
    return response.data
  },

  async getProductionReworks(
    query: ReportQueryParams = {},
  ): Promise<{ data: ProductionReworkReportItem[] }> {
    const response = await api.get<{
      data: ProductionReworkReportItem[]
    }>(ENDPOINTS.reports.productionReworks, {
      params: cleanQueryParams(query),
    })
    return response.data
  },

  async getPayrollPeriodReport(
    payrollPeriodId: number,
  ): Promise<PayrollPeriodReportSummary> {
    const response = await api.get<
      ApiResource<PayrollPeriodReportSummary>
    >(ENDPOINTS.reports.payrollPeriod(payrollPeriodId))
    return response.data.data
  },

  async getPayrollEmployees(
    query: ReportQueryParams = {},
  ): Promise<PaginatedResponse<PayrollEmployeeReportItem>> {
    const response = await api.get<
      PaginatedResponse<PayrollEmployeeReportItem>
    >(ENDPOINTS.reports.payrollEmployees, {
      params: cleanQueryParams(query),
    })
    return response.data
  },

  // EXPORTS
  async exportProductionCuts(
    query: ReportQueryParams = {},
  ): Promise<void> {
    await downloadCsv(
      ENDPOINTS.exports.productionCuts,
      'reporte_cortes.csv',
      query,
    )
  },

  async exportProductionProcesses(
    query: ReportQueryParams = {},
  ): Promise<void> {
    await downloadCsv(
      ENDPOINTS.exports.productionProcesses,
      'reporte_procesos.csv',
      query,
    )
  },

  async exportProductionIncidents(
    query: ReportQueryParams = {},
  ): Promise<void> {
    await downloadCsv(
      ENDPOINTS.exports.productionIncidents,
      'reporte_incidencias.csv',
      query,
    )
  },

  async exportProductionLosses(
    query: ReportQueryParams = {},
  ): Promise<void> {
    await downloadCsv(
      ENDPOINTS.exports.productionLosses,
      'reporte_mermas.csv',
      query,
    )
  },

  async exportProductionReworks(
    query: ReportQueryParams = {},
  ): Promise<void> {
    await downloadCsv(
      ENDPOINTS.exports.productionReworks,
      'reporte_reprocesos.csv',
      query,
    )
  },

  async exportPayrollPeriod(
    payrollPeriodId: number,
  ): Promise<void> {
    await downloadCsv(
      ENDPOINTS.exports.payrollPeriod(payrollPeriodId),
      `reporte_nomina_periodo_${payrollPeriodId}.csv`,
    )
  },

  async exportPayrollEmployees(
    query: ReportQueryParams = {},
  ): Promise<void> {
    await downloadCsv(
      ENDPOINTS.exports.payrollEmployees,
      'reporte_pagos_historico.csv',
      query,
    )
  },
}
