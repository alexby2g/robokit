import { defineRouter } from '#q-app'
import { createMemoryHistory, createRouter, createWebHashHistory, createWebHistory } from 'vue-router'
import routes from './routes.js'

export default defineRouter(() => {
  const createHistory = import.meta.env.QUASAR_SERVER
    ? createMemoryHistory
    : import.meta.env.QUASAR_VUE_ROUTER_MODE === 'history'
      ? createWebHistory
      : createWebHashHistory

  const Router = createRouter({
    scrollBehavior: () => ({ left: 0, top: 0 }),
    routes,
    history: createHistory(import.meta.env.QUASAR_VUE_ROUTER_BASE),
  })

  Router.beforeEach((to) => {
    if (to.matched.some((record) => record.meta.requiresAdmin)) {
      const token = localStorage.getItem('robokit_admin_token')
      const rawUser = localStorage.getItem('robokit_admin_user')
      if (!token || !rawUser) return { path: '/login', query: { redirect: to.fullPath } }

      let user = null
      try { user = JSON.parse(rawUser) } catch { /* ignore */ }
      const roles = to.matched.flatMap((record) => record.meta.roles || [])
      if (roles.length && (!user || !roles.includes(user.role))) return '/admin/dashboard'
    }

    if (to.matched.some((record) => record.meta.requiresClient)) {
      if (!localStorage.getItem('robokit_client_token')) {
        return { path: '/login', query: { redirect: to.fullPath } }
      }
    }

    return true
  })

  return Router
})
