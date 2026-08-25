import { api } from '@/services/api'
import { cleanQueryParams } from '@/utils/query-params'

import type { ApiQueryParams } from '@/types/api'

function getFilename(
  contentDisposition: string | undefined,
  fallback: string,
): string {
  if (!contentDisposition) {
    return fallback
  }

  const utf8Match = contentDisposition.match(
    /filename\*=UTF-8''([^;]+)/i,
  )

  if (utf8Match?.[1]) {
    return decodeURIComponent(utf8Match[1])
  }

  const filenameMatch = contentDisposition.match(
    /filename="?([^";]+)"?/i,
  )

  return filenameMatch?.[1] ?? fallback
}

export async function downloadCsv(
  endpoint: string,
  fallbackFilename: string,
  params: ApiQueryParams = {},
): Promise<void> {
  const response = await api.get<Blob>(endpoint, {
    params: cleanQueryParams(params),
    responseType: 'blob',
    headers: {
      Accept: 'text/csv, application/json',
    },
  })

  const filename = getFilename(
    response.headers['content-disposition'],
    fallbackFilename,
  )

  const blobUrl = window.URL.createObjectURL(
    response.data,
  )

  const anchor = document.createElement('a')

  anchor.href = blobUrl
  anchor.download = filename

  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()

  window.URL.revokeObjectURL(blobUrl)
}