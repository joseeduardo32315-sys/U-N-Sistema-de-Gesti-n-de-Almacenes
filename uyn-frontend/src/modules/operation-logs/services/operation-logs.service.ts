import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'
import { cleanQueryParams } from '@/utils/query-params'

import type { PaginatedResponse } from '@/types/api'
import type {
  OperationLog,
  OperationLogsQuery,
} from '@/modules/operation-logs/types/operation-log.types'

export const operationLogsService = {
  async list(
    query: OperationLogsQuery = {},
  ): Promise<PaginatedResponse<OperationLog>> {
    const response = await api.get<
      PaginatedResponse<OperationLog>
    >(ENDPOINTS.operationLogs, {
      params: cleanQueryParams({
        user_id: query.user_id,
        module: query.module,
        action: query.action,
        date_from: query.date_from,
        date_to: query.date_to,
        page: query.page,
        per_page: query.per_page,
      }),
    })

    return response.data
  },
}