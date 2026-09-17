import { computed, reactive } from 'vue'
import { adminApi, clientApi } from './api'

const safeJson = (value) => {
  try { return JSON.parse(value || 'null') } catch { return null }
}

export const STAFF_ROLES = ['admin', 'trabajador', 'caja', 'almacen']

export const authState = reactive({
  adminUser: safeJson(localStorage.getItem('robokit_admin_user')),
  clientUser: safeJson(localStorage.getItem('robokit_client_user')),
  clientProfile: safeJson(localStorage.getItem('robokit_client_profile')),
})

export const isAdminLogged = computed(() => Boolean(localStorage.getItem('robokit_admin_token')) && Boolean(authState.adminUser))
export const isClientLogged = computed(() => Boolean(localStorage.getItem('robokit_client_token')) && Boolean(authState.clientUser))

const clearAdminLocal = () => {
  localStorage.removeItem('robokit_admin_token')
  localStorage.removeItem('robokit_admin_user')
  authState.adminUser = null
}

const clearClientLocal = () => {
  localStorage.removeItem('robokit_client_token')
  localStorage.removeItem('robokit_client_user')
  localStorage.removeItem('robokit_client_profile')
  authState.clientUser = null
  authState.clientProfile = null
}

export const setAdminSession = ({ token, user }) => {
  clearClientLocal()
  localStorage.setItem('robokit_admin_token', token)
  localStorage.setItem('robokit_admin_user', JSON.stringify(user))
  authState.adminUser = user
}

export const clearAdminSession = () => clearAdminLocal()

export const setClientSession = ({ token, user, cliente }) => {
  clearAdminLocal()
  localStorage.setItem('robokit_client_token', token)
  localStorage.setItem('robokit_client_user', JSON.stringify(user))
  localStorage.setItem('robokit_client_profile', JSON.stringify(cliente || null))
  authState.clientUser = user
  authState.clientProfile = cliente || null
}

export const clearClientSession = () => clearClientLocal()

/**
 * Recibe la respuesta de /auth/login y guarda la sesión correcta según el rol.
 * No pregunta al usuario si es cliente o administrador.
 */
export const setUnifiedSession = (payload) => {
  const role = payload?.user?.role

  if (STAFF_ROLES.includes(role)) {
    setAdminSession(payload)
    return 'admin'
  }

  if (role === 'cliente') {
    setClientSession(payload)
    return 'cliente'
  }

  throw new Error('El usuario no tiene un rol válido para ingresar.')
}

export const refreshAdminSession = async () => {
  const { data } = await adminApi.get('/auth/me')
  localStorage.setItem('robokit_admin_user', JSON.stringify(data.user))
  authState.adminUser = data.user
  return data
}

export const refreshClientSession = async () => {
  const { data } = await clientApi.get('/auth/me')
  localStorage.setItem('robokit_client_user', JSON.stringify(data.user))
  localStorage.setItem('robokit_client_profile', JSON.stringify(data.cliente || null))
  authState.clientUser = data.user
  authState.clientProfile = data.cliente || null
  return data
}

export const logoutAdmin = async () => {
  try { await adminApi.post('/auth/logout') } catch { /* sesión local se limpia igual */ }
  clearAdminLocal()
}

export const logoutClient = async () => {
  try { await clientApi.post('/auth/logout') } catch { /* sesión local se limpia igual */ }
  clearClientLocal()
}
