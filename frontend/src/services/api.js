import axios from 'axios'

export const API_BASE_URL = (import.meta.env.VITE_API_URL || (import.meta.env.DEV ? 'http://127.0.0.1:8000/api' : '/api')).replace(/\/$/, '')
export const API_ORIGIN = API_BASE_URL.replace(/\/api$/, '')

const FIELD_LABELS = {
  email: 'correo',
  password: 'contraseña',
  password_confirmation: 'confirmación de contraseña',
  current_password: 'contraseña actual',
  name: 'nombre',
  Nombre: 'nombre',
  Apellido: 'apellido',
  Telefono: 'teléfono',
  Direccion_envio: 'dirección',
  Precio: 'precio',
  Stock: 'stock',
  id_categoria: 'categoría',
  tipo_entrega: 'forma de entrega',
  direccion_entrega: 'dirección de entrega',
  items: 'productos del pedido',
  hero_titulo: 'título principal',
  nombre_tienda: 'nombre de la tienda',
  role: 'rol',
  is_active: 'estado del acceso',
  metodo_pago: 'método de pago',
  metodo: 'método de pago',
  referencia: 'referencia del pago',
  comprobante: 'comprobante',
  objetivo: 'objetivo de la guía',
  duracion_minutos: 'duración estimada',
}

const fieldLabel = (field = '') => {
  const root = String(field).split('.')[0]
  return FIELD_LABELS[field] || FIELD_LABELS[root] || root.replaceAll('_', ' ')
}

const looksSpanish = (message = '') => /\b(el|la|los|las|debe|debes|obligatorio|obligatoria|incorrect|válid|cuenta|contraseña|correo|seleccion|ingresa|máximo|mínimo|existe|formato)\b/i.test(message)

const humanizeValidationMessage = (field, raw = '') => {
  const message = String(raw || '').trim()
  if (!message) return `Revisa el campo ${fieldLabel(field)}.`
  if (looksSpanish(message)) return message

  const label = fieldLabel(field)
  const lower = message.toLowerCase()

  if (lower.includes('required')) return `El campo ${label} es obligatorio.`
  if (lower.includes('valid email') || lower.includes('email')) return 'El correo no tiene un formato válido.'
  if (lower.includes('must be a number') || lower.includes('numeric')) return `El campo ${label} debe ser numérico.`
  if (lower.includes('must be an integer') || lower.includes('integer')) return `El campo ${label} debe ser un número entero.`
  if (lower.includes('at least') || lower.includes('minimum')) return `El campo ${label} no cumple el mínimo permitido.`
  if (lower.includes('greater than') || lower.includes('max')) return `El campo ${label} supera el máximo permitido.`
  if (lower.includes('already been taken') || lower.includes('unique')) return `Ese ${label} ya está registrado.`
  if (lower.includes('confirmation') || lower.includes('confirmed')) return `La confirmación de ${label} no coincide.`
  if (lower.includes('selected') || lower.includes('in.')) return `El valor seleccionado para ${label} no es válido.`

  return message
}

const extractValidationErrors = (payload = {}) => {
  const errors = payload?.errors || {}
  const normalized = {}

  Object.entries(errors).forEach(([field, messages]) => {
    const first = Array.isArray(messages) ? messages[0] : messages
    normalized[field] = humanizeValidationMessage(field, first)
  })

  return normalized
}

const firstValidationMessage = (errors = {}) => Object.values(errors)[0] || null

const makeClient = (tokenKey = null) => {
  const instance = axios.create({
    baseURL: API_BASE_URL,
    timeout: 20000,
    headers: { Accept: 'application/json' },
  })

  instance.interceptors.request.use((config) => {
    if (tokenKey) {
      const token = localStorage.getItem(tokenKey)
      if (token) config.headers.Authorization = `Bearer ${token}`
    }
    return config
  })

  instance.interceptors.response.use(
    (response) => response,
    (error) => {
      if (!error.response) {
        error.userMessage = 'No se pudo conectar con el servidor.'
        error.validationErrors = {}
      } else {
        const payload = error.response?.data || {}
        const validationErrors = extractValidationErrors(payload)
        error.validationErrors = validationErrors
        error.userMessage =
          firstValidationMessage(validationErrors) ||
          payload?.message ||
          payload?.mensaje ||
          `Error ${error.response.status}`

        if (error.response.status === 401 && tokenKey && localStorage.getItem(tokenKey)) {
          localStorage.removeItem(tokenKey)
          if (tokenKey.includes('admin')) localStorage.removeItem('robokit_admin_user')
          if (tokenKey.includes('client')) {
            localStorage.removeItem('robokit_client_user')
            localStorage.removeItem('robokit_client_profile')
          }
          if (typeof window !== 'undefined') {
            window.location.hash = '#/login'
          }
        }
      }
      return Promise.reject(error)
    },
  )

  return instance
}

export const publicApi = makeClient()
export const adminApi = makeClient('robokit_admin_token')
export const clientApi = makeClient('robokit_client_token')

// Compatibilidad: los módulos administrativos existentes siguen importando `api` por defecto.
const api = adminApi
export default api
