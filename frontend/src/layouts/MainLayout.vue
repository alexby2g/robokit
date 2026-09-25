<template>
  <q-layout view="lHh Lpr lFf" class="app-shell">
    <q-header class="topbar">
      <q-toolbar class="q-px-md">
        <q-btn flat round dense icon="menu" color="cyan-4" @click="leftDrawerOpen = !leftDrawerOpen" />
        <q-toolbar-title class="row items-center no-wrap q-ml-sm cursor-pointer" @click="go('/admin/dashboard')">
          <q-avatar square size="34px" class="brand-mark"><q-icon name="precision_manufacturing" /></q-avatar>
          <div class="q-ml-sm"><div class="brand-name">ROBOKIT STORE</div><div class="brand-sub">Panel administrativo</div></div>
        </q-toolbar-title>

        <q-btn flat icon="storefront" label="Ver tienda" class="gt-xs" color="cyan-3" @click="openStore" />

        <q-btn flat round icon="notifications" color="cyan-3" class="q-ml-xs">
          <q-badge v-if="unread" color="red" floating rounded>{{ unread > 99 ? '99+' : unread }}</q-badge>
          <q-menu anchor="bottom right" self="top right">
            <q-card style="width:390px;max-width:92vw" class="panel text-white">
              <q-card-section class="row items-center justify-between q-pb-sm">
                <div><div class="text-subtitle1 text-weight-bold">Notificaciones</div><div class="text-caption text-grey-5">Pedidos, pagos y ventas.</div></div>
                <q-btn v-if="unread" flat dense color="cyan-4" label="Leer todas" @click="readAll" />
              </q-card-section>
              <q-separator dark />
              <q-list v-if="notifications.length" separator dark style="max-height:420px;overflow:auto">
                <q-item v-for="n in notifications" :key="n.id" clickable @click="openNotification(n)">
                  <q-item-section avatar><q-avatar :color="notificationColor(n.tipo)" text-color="white" :icon="notificationIcon(n.tipo)" /></q-item-section>
                  <q-item-section><q-item-label :class="!n.leida_at ? 'text-weight-bold' : ''">{{ n.titulo }}</q-item-label><q-item-label caption>{{ n.mensaje }}</q-item-label><q-item-label caption class="text-grey-6">{{ timeText(n.created_at) }}</q-item-label></q-item-section>
                  <q-item-section side v-if="!n.leida_at"><q-icon name="circle" color="cyan-4" size="10px" /></q-item-section>
                </q-item>
              </q-list>
              <div v-else class="q-pa-lg text-center text-grey-5">No hay notificaciones todavía.</div>
            </q-card>
          </q-menu>
        </q-btn>

        <q-btn flat icon="logout" label="Cerrar sesión" color="red-3" class="q-ml-sm gt-sm" @click="signOut" />
        <q-btn flat round icon="logout" color="red-3" class="q-ml-xs lt-md" @click="signOut"><q-tooltip>Cerrar sesión</q-tooltip></q-btn>

        <q-btn-dropdown flat no-caps class="q-ml-sm gt-xs" color="white" :label="adminName">
          <q-list style="min-width: 210px">
            <q-item><q-item-section><q-item-label>{{ adminName }}</q-item-label><q-item-label caption>{{ roleLabel }}</q-item-label></q-item-section></q-item>
            <q-separator />
            <q-item clickable v-close-popup @click="signOut"><q-item-section avatar><q-icon name="logout" /></q-item-section><q-item-section>Cerrar sesión</q-item-section></q-item>
          </q-list>
        </q-btn-dropdown>
      </q-toolbar>
    </q-header>

    <q-drawer v-model="leftDrawerOpen" show-if-above :width="282" class="sidebar">
      <div class="sidebar-head q-pa-md">
        <div class="row items-center"><q-avatar size="48px" class="brand-mark"><q-icon name="smart_toy" size="28px" /></q-avatar><div class="q-ml-md"><div class="text-weight-bold text-white">Panel administrativo</div><div class="text-caption text-grey-5">{{ roleLabel }}</div></div></div>
      </div>

      <q-scroll-area class="sidebar-scroll">
        <div class="q-pa-sm">
          <div class="menu-label q-px-sm q-pb-xs">GENERAL</div>
          <q-list class="q-mb-md">
            <q-item clickable v-ripple class="menu-item" :active="isActive('/admin/dashboard')" active-class="menu-item-active" @click="go('/admin/dashboard')">
              <q-item-section avatar><q-icon name="dashboard" size="21px" /></q-item-section><q-item-section><q-item-label>Dashboard</q-item-label><q-item-label caption>Resumen del negocio</q-item-label></q-item-section>
            </q-item>
          </q-list>

          <div class="menu-label q-px-sm q-pb-xs">MÓDULOS</div>
          <q-list>
            <q-expansion-item v-for="group in visibleGroups" :key="group.label" :icon="group.icon" :label="group.label" :caption="group.caption" header-class="menu-item" expand-icon-class="text-grey-5" :default-opened="group.items.some(i=>isActive(i.path))">
              <q-item v-for="item in group.items" :key="item.path" clickable v-ripple class="menu-item q-ml-md" :active="isActive(item.path)" active-class="menu-item-active" @click="go(item.path)">
                <q-item-section avatar><q-icon :name="item.icon" size="19px" /></q-item-section><q-item-section><q-item-label>{{ item.title }}</q-item-label><q-item-label caption>{{ item.caption }}</q-item-label></q-item-section><q-item-section side><q-icon name="chevron_right" size="16px" /></q-item-section>
              </q-item>
            </q-expansion-item>
          </q-list>
        </div>
      </q-scroll-area>

      <div class="sidebar-footer q-pa-md"><div class="row items-center text-caption text-grey-5"><q-icon name="circle" color="positive" size="10px" class="q-mr-sm" /> Sesión protegida</div></div>
    </q-drawer>

    <q-page-container class="page-container"><router-view /></q-page-container>
  </q-layout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { authState, logoutAdmin } from '../services/auth'
import { fetchNotifications, markAllNotificationsRead, markNotificationRead } from '../services/notifications'

const router=useRouter(),route=useRoute(),leftDrawerOpen=ref(false),notifications=ref([]),unread=ref(0)
let timer=null
const role=computed(()=>authState.adminUser?.role||'admin')
const adminName=computed(()=>authState.adminUser?.name||'Administrador')
const roleLabel=computed(()=>({admin:'Administrador',trabajador:'Trabajador',caja:'Caja / ventas',almacen:'Almacén'}[role.value]||role.value))

const groups=[
  {label:'Catálogo',caption:'Productos, categorías y guías',icon:'storefront',items:[
    {title:'Productos',caption:'Publicación, precios e imágenes',icon:'precision_manufacturing',path:'/admin/productos',roles:['admin','trabajador','almacen']},
    {title:'Categorías',caption:'Clasificación del catálogo',icon:'category',path:'/admin/categorias',roles:['admin','trabajador','almacen']},
    {title:'Capacitación',caption:'Guías de uso para clientes',icon:'school',path:'/admin/capacitacion',roles:['admin']},
    {title:'Editar tienda',caption:'Textos y configuración pública',icon:'web',path:'/admin/catalogo',roles:['admin']},
  ]},
  {label:'Operaciones',caption:'Solicitudes, ventas y pagos',icon:'receipt_long',items:[
    {title:'Pedidos online',caption:'Solicitudes del catálogo',icon:'shopping_bag',path:'/admin/pedidos-online',roles:['admin','trabajador','caja','almacen']},
    {title:'Ventas',caption:'Ventas de mostrador',icon:'point_of_sale',path:'/admin/pedidos',roles:['admin','trabajador','caja']},
    {title:'Clientes',caption:'Datos e historial',icon:'groups',path:'/admin/clientes',roles:['admin','trabajador','caja']},
  ]},
  {label:'Inventario',caption:'Stock y entradas de mercadería',icon:'inventory_2',items:[
    {title:'Stock y movimientos',caption:'Inventario disponible',icon:'inventory',path:'/admin/inventario',roles:['admin','trabajador','almacen']},
    {title:'Compras',caption:'Entradas de mercadería',icon:'local_shipping',path:'/admin/compras',roles:['admin','trabajador','almacen']},
  ]},
  {label:'Administración',caption:'Reportes y accesos',icon:'settings',items:[
    {title:'Reportes',caption:'Estadísticas y documentos',icon:'analytics',path:'/admin/reportes',roles:['admin','trabajador','caja']},
    {title:'Personal y accesos',caption:'Roles y contraseñas',icon:'admin_panel_settings',path:'/admin/personal',roles:['admin']},
  ]},
]
const visibleGroups=computed(()=>groups.map(g=>({...g,items:g.items.filter(i=>!i.roles||i.roles.includes(role.value))})).filter(g=>g.items.length))
const go=path=>{router.push(path);if(window.innerWidth<1024)leftDrawerOpen.value=false}
const openStore=()=>window.open(router.resolve('/tienda').href,'_blank','noopener')
const isActive=path=>route.path.startsWith(path)
const signOut=async()=>{await logoutAdmin();router.replace('/login')}
const loadNotifications=async()=>{try{const d=await fetchNotifications();notifications.value=d.notificaciones||[];unread.value=d.no_leidas||0}catch{/* sesión/interceptor gestiona */}}
const readAll=async()=>{await markAllNotificationsRead();await loadNotifications()}
const openNotification=async n=>{if(!n.leida_at)await markNotificationRead(n.id);await loadNotifications();if(n.ruta)router.push(n.ruta)}
const timeText=v=>v?new Date(v).toLocaleString():'-'
const notificationIcon=t=>({pedido_nuevo:'shopping_bag',pago_reportado:'payments',pago:'payments',venta:'point_of_sale',venta_completada:'check_circle',pedido:'local_shipping'}[t]||'notifications')
const notificationColor=t=>({pedido_nuevo:'blue-8',pago_reportado:'orange-8',pago:'green-8',venta:'indigo-8',venta_completada:'green-8',pedido:'cyan-9'}[t]||'blue-grey-8')
onMounted(()=>{loadNotifications();timer=setInterval(loadNotifications,15000)})
onBeforeUnmount(()=>{if(timer)clearInterval(timer)})
</script>
