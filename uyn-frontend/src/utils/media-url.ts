const backendUrl = (
  import.meta.env.VITE_BACKEND_URL ??
  'http://127.0.0.1:8000'
).replace(/\/+$/, '')

export function resolveMediaUrl(
  value: string | null | undefined,
): string | null {
  if (!value) {
    return null
  }

  const url = value.trim()

  if (!url) {
    return null
  }

  /*
   * Las URLs temporales y embebidas deben conservarse.
   */
  if (
    url.startsWith('blob:') ||
    url.startsWith('data:')
  ) {
    return url
  }

  /*
   * Si Laravel devuelve una URL absoluta con un origen
   * incorrecto, conservamos únicamente su ruta pública.
   *
   * Ejemplo:
   * http://localhost/storage/model.png
   *
   * Resultado:
   * http://127.0.0.1:8000/storage/model.png
   */
  try {
    const parsedUrl = new URL(url)

    if (parsedUrl.pathname.startsWith('/storage/')) {
      return `${backendUrl}${parsedUrl.pathname}${parsedUrl.search}${parsedUrl.hash}`
    }

    return parsedUrl.toString()
  } catch {
    /*
     * También admite rutas relativas:
     *
     * /storage/garment-models/model.png
     * storage/garment-models/model.png
     * garment-models/model.png
     */
    if (url.startsWith('/storage/')) {
      return `${backendUrl}${url}`
    }

    if (url.startsWith('storage/')) {
      return `${backendUrl}/${url}`
    }

    return `${backendUrl}/storage/${url.replace(/^\/+/, '')}`
  }
}