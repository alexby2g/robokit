import { adminApi, clientApi } from './api'

const activeApi = () => localStorage.getItem('robokit_admin_token') ? adminApi : clientApi

export const fetchNotifications = async () => {
  const { data } = await activeApi().get('/notificaciones')
  return data
}

export const markNotificationRead = async (id) => {
  await activeApi().put(`/notificaciones/${id}/leer`)
}

export const markAllNotificationsRead = async () => {
  await activeApi().put('/notificaciones/leer-todas')
}
