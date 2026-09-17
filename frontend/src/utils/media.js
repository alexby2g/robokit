import { API_BASE_URL } from '../services/api'

const encodePath = (value) =>
  String(value)
    .split('/')
    .filter(Boolean)
    .map((segment) => encodeURIComponent(segment))
    .join('/')

export const mediaUrl = (rawValue) => {
  if (!rawValue || typeof rawValue !== 'string') return ''
  let value = rawValue.trim()
  if (!value) return ''

  if (/^(data:|blob:)/i.test(value)) return value

  // Corrige URLs antiguas guardadas con localhost/127.0.0.1 + /storage/.
  if (/^https?:\/\//i.test(value)) {
    try {
      const parsed = new URL(value)
      const storageIndex = parsed.pathname.indexOf('/storage/')
      const isLocalLegacy = ['localhost', '127.0.0.1'].includes(parsed.hostname)
      if (storageIndex >= 0 && isLocalLegacy) {
        value = parsed.pathname.slice(storageIndex + '/storage/'.length)
      } else {
        return value
      }
    } catch {
      return value
    }
  }

  value = value
    .replace(/^\/+/, '')
    .replace(/^public\//i, '')
    .replace(/^storage\//i, '')

  if (!value) return ''
  return `${API_BASE_URL}/media/${encodePath(value)}`
}

export const productImage = (product) => {
  if (!product) return ''
  const first = Array.isArray(product.imagenes) ? product.imagenes[0] : null
  const value =
    first?.url ||
    first?.ruta ||
    first?.path ||
    product.imagen_url ||
    product.imagen ||
    product.Imagen ||
    product.foto ||
    product.image_url ||
    product.imagen_path ||
    ''

  return mediaUrl(value)
}

export const acceptedImageTypes =
  '.jpg,.jpeg,.jfif,.png,.webp,.gif,.bmp,.avif,.heic,.heif,.tif,.tiff,image/*'
