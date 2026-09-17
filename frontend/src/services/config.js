export const API_BASE_URL = (import.meta.env.VITE_API_URL || (import.meta.env.DEV ? 'http://127.0.0.1:8000/api' : '/api')).replace(/\/$/, '')
export const SERVER_BASE_URL = API_BASE_URL.replace(/\/api$/, '')

export const storageUrl = (ruta) => {
  if (!ruta) return ''
  if (/^https?:\/\//i.test(ruta)) return ruta

  let limpia = String(ruta).replace(/^\/+/, '')
  limpia = limpia.replace(/^storage\//, '')

  // Las imágenes de producto se sirven por la API para evitar problemas
  // con public/storage en Windows/Laragon.
  if (limpia.startsWith('productos/')) {
    const archivo = limpia.split('/').pop()
    return `${API_BASE_URL}/media/productos/${encodeURIComponent(archivo)}`
  }

  return `${SERVER_BASE_URL}/storage/${limpia}`
}
