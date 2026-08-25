import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'
import { cleanQueryParams } from '@/utils/query-params'

import type {
  ApiResourceMessage,
  PaginatedResponse,
} from '@/types/api'

import type {
  AssignOperationEmployeePayload,
  ProductionOperationLog,
  ProductionOperationLogsQuery,
  UpdateOperationProgressPayload,
} from '@/modules/production-operation-logs/types/production-operation-log.types'

export const productionOperationLogsService = {
  async list(
    movementId: number,
    query: ProductionOperationLogsQuery = {},
  ): Promise<PaginatedResponse<ProductionOperationLog>> {
    const response = await api.get<
      PaginatedResponse<ProductionOperationLog>
    >(
      ENDPOINTS.productionMovements.operationLogs(
        movementId,
      ),
      {
        params: cleanQueryParams({
          employee_id: query.employee_id,
          status: query.status,
          page: query.page,
          per_page: query.per_page,
        }),
      },
    )

    return response.data
  },

  async assign(
    movementId: number,
    payload: AssignOperationEmployeePayload,
  ): Promise<ApiResourceMessage<ProductionOperationLog>> {
    /*
     * El backend toma operation_process_id directamente
     * del movimiento. No enviar quantity_assigned ni
     * operation_process_id.
     */
    const response = await api.post<
      ApiResourceMessage<ProductionOperationLog>
    >(
      ENDPOINTS.productionMovements.operationLogs(
        movementId,
      ),
      payload,
    )

    return response.data
  },

  async update(
    operationLogId: number,
    payload: UpdateOperationProgressPayload,
  ): Promise<ApiResourceMessage<ProductionOperationLog>> {
    const response = await api.patch<
      ApiResourceMessage<ProductionOperationLog>
    >(
      ENDPOINTS.productionOperationLogs.update(
        operationLogId,
      ),
      payload,
    )

    return response.data
  },
}