import { ENDPOINTS } from '@/config/endpoints'
import { api } from '@/services/api'
import { cleanQueryParams } from '@/utils/query-params'

import type {
  ApiResource,
  ApiResourceMessage,
  PaginatedResponse,
} from '@/types/api'

import type {
  CreateUserPayload,
  UpdateUserPayload,
  User,
  UsersQuery,
} from '@/modules/users/types/user.types'

export const usersService = {
  async list(
    query: UsersQuery = {},
  ): Promise<PaginatedResponse<User>> {
    const response = await api.get<PaginatedResponse<User>>(
      ENDPOINTS.users.index,
      {
        params: cleanQueryParams({
          search: query.search,
          status: query.status,
          role: query.role,
          page: query.page,
          per_page: query.per_page,
        }),
      },
    )

    return response.data
  },

  async show(id: number): Promise<User> {
    const response = await api.get<ApiResource<User>>(
      ENDPOINTS.users.show(id),
    )

    return response.data.data
  },

  async create(
    payload: CreateUserPayload,
  ): Promise<ApiResourceMessage<User>> {
    const response = await api.post<ApiResourceMessage<User>>(
      ENDPOINTS.users.create,
      payload,
    )

    return response.data
  },

  async update(
    id: number,
    payload: UpdateUserPayload,
  ): Promise<ApiResourceMessage<User>> {
    const response = await api.patch<ApiResourceMessage<User>>(
      ENDPOINTS.users.update(id),
      payload,
    )

    return response.data
  },

  async activate(
    id: number,
  ): Promise<ApiResourceMessage<User>> {
    const response = await api.post<ApiResourceMessage<User>>(
      ENDPOINTS.users.activate(id),
    )

    return response.data
  },

  async deactivate(
    id: number,
  ): Promise<ApiResourceMessage<User>> {
    const response = await api.post<ApiResourceMessage<User>>(
      ENDPOINTS.users.deactivate(id),
    )

    return response.data
  },
}