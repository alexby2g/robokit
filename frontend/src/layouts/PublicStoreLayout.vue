<template>
  <q-layout view="hHh lpR fFf" class="public-shell">
    <q-header class="public-header">
      <q-toolbar class="public-toolbar">
        <div class="brand-block cursor-pointer" @click="$router.push('/tienda')">
          <q-avatar size="42px" class="public-logo"><q-icon name="smart_toy" /></q-avatar>
          <div><div class="public-brand">{{ config.nombre_tienda || 'ROBOKIT STORE' }}</div><div class="public-tagline">{{ config.subtitulo || 'Robótica, kits y componentes' }}</div></div>
        </div>
        <q-space />
        <div class="gt-xs row q-gutter-sm items-center">
          <q-btn flat no-caps icon="storefront" label="Catálogo" to="/tienda" />
          <q-btn flat no-caps icon="school" label="Capacitación" to="/capacitacion" />
          <q-btn flat no-caps icon="local_shipping" label="Seguir pedido" to="/seguimiento" />

          <q-btn v-if="clientLogged" flat round icon="notifications" color="cyan-3">
            <q-badge v-if="unread" color="red" floating rounded>{{ unread > 99 ? '99+' : unread }}</q-badge>
            <q-menu anchor="bottom right" self="top right">
              <q-card class="panel text-white" style="width:360px;max-width:92vw">
                <q-card-section class="row items-center justify-between q-pb-sm"><div><div class="text-subtitle1 text-weight-bold">Mis notificaciones</div><div class="text-caption text-grey-5">Pedidos y pagos.</div></div><q-btn v-if="unread" flat dense color="cyan-4" label="Leer todas" @click="readAll"/></q-card-section>
                <q-separator dark/>
                <q-list v-if="notifications.length" separator dark style="max-height:420px;overflow:auto">
                  <q-item v-for="n in notifications" :key="n.id" clickable @click="openNotification(n)"><q-item-section avatar><q-avatar color="blue-grey-9" text-color="cyan-3" :icon="notificationIcon(n.tipo)"/></q-item-section><q-item-section><q-item-label :class="!n.leida_at?'text-weight-bold':''">{{n.titulo}}</q-item-label><q-item-label caption>{{n.mensaje}}</q-item-label><q-item-label caption class="text-grey-6">{{timeText(n.created_at)}}</q-item-label></q-item-section><q-item-section side v-if="!n.leida_at"><q-icon name="circle" color="cyan-4" size="9px"/></q-item-section></q-item>
                </q-list>
                <div v-else class="q-pa-lg text-center text-grey-5">Aún no tienes notificaciones.</div>
              </q-card>
            </q-menu>
          </q-btn>

          <q-btn v-if="clientLogged" outline no-caps icon="person" label="Mi cuenta" to="/mi-cuenta" color="cyan-4" />
          <q-btn v-else-if="adminLogged" outline no-caps icon="dashboard" label="Ir al panel" to="/admin/dashboard" color="cyan-4" />
          <q-btn v-else outline no-caps icon="login" label="Iniciar sesión" to="/login" color="cyan-4" />
        </div>
        <q-btn class="lt-sm" flat round icon="menu" @click="menu = true" />
      </q-toolbar>
    </q-header>

    <q-drawer v-model="menu" side="right" overlay bordered class="public-drawer">
      <q-list padding>
        <q-item clickable v-ripple to="/tienda" @click="menu = false"><q-item-section avatar><q-icon name="storefront" /></q-item-section><q-item-section>Catálogo</q-item-section></q-item>
        <q-item clickable v-ripple to="/capacitacion" @click="menu = false"><q-item-section avatar><q-icon name="school" /></q-item-section><q-item-section>Capacitación</q-item-section></q-item>
        <q-item clickable v-ripple to="/seguimiento" @click="menu = false"><q-item-section avatar><q-icon name="local_shipping" /></q-item-section><q-item-section>Seguir pedido</q-item-section></q-item>
        <q-item v-if="clientLogged" clickable v-ripple to="/mi-cuenta" @click="menu = false"><q-item-section avatar><q-icon name="person" /></q-item-section><q-item-section>Mi cuenta <q-badge v-if="unread" color="red" class="q-ml-xs">{{unread}}</q-badge></q-item-section></q-item>
        <q-item v-else-if="adminLogged" clickable v-ripple to="/admin/dashboard" @click="menu = false"><q-item-section avatar><q-icon name="dashboard" /></q-item-section><q-item-section>Ir al panel</q-item-section></q-item>
        <q-item v-else clickable v-ripple to="/login" @click="menu = false"><q-item-section avatar><q-icon name="login" /></q-item-section><q-item-section>Iniciar sesión</q-item-section></q-item>
      </q-list>
    </q-drawer>

    <q-page-container><router-view /></q-page-container>
    <q-footer class="public-footer"><div class="row items-center justify-between q-px-lg q-py-sm"><div class="text-caption">{{ config.nombre_tienda || 'ROBOKIT STORE' }} · Catálogo público</div><div class="text-caption text-grey-5">Stock sincronizado con administración</div></div></q-footer>
  </q-layout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { clientApi } from '../services/api'
import { authState } from '../services/auth'
import { fetchNotifications, markAllNotificationsRead, markNotificationRead } from '../services/notifications'

const router=useRouter(),menu=ref(false),config=reactive({}),notifications=ref([]),unread=ref(0)
let timer=null
const clientLogged=computed(()=>Boolean(authState.clientUser&&localStorage.getItem('robokit_client_token')))
const adminLogged=computed(()=>Boolean(authState.adminUser&&localStorage.getItem('robokit_admin_token')))
const loadNotifications=async()=>{if(!clientLogged.value){notifications.value=[];unread.value=0;return}try{const d=await fetchNotifications();notifications.value=d.notificaciones||[];unread.value=d.no_leidas||0}catch{/* nada */}}
const readAll=async()=>{await markAllNotificationsRead();await loadNotifications()}
const openNotification=async n=>{if(!n.leida_at)await markNotificationRead(n.id);await loadNotifications();if(n.ruta)router.push(n.ruta)}
const notificationIcon=t=>({pedido:'local_shipping',pago:'payments'}[t]||'notifications')
const timeText=v=>v?new Date(v).toLocaleString():'-'
onMounted(async()=>{try{const{data}=await clientApi.get('/tienda/config');Object.assign(config,data.config||{})}catch{/* defaults */}await loadNotifications();timer=setInterval(loadNotifications,15000)})
onBeforeUnmount(()=>{if(timer)clearInterval(timer)})
</script>

<style scoped>
.public-shell{background:#07101d;min-height:100vh}.public-header{background:rgba(7,16,29,.96);border-bottom:1px solid rgba(34,211,238,.2);backdrop-filter:blur(16px)}.public-toolbar{min-height:70px;max-width:1320px;margin:0 auto;width:100%}.brand-block{display:flex;align-items:center;gap:12px}.public-logo{background:linear-gradient(135deg,#0891b2,#2563eb);color:white;box-shadow:0 0 24px rgba(34,211,238,.24)}.public-brand{color:white;font-size:1rem;font-weight:900;letter-spacing:.07em}.public-tagline{color:#7890a9;font-size:.68rem}.public-drawer{background:#0b1422;color:#dbeafe}.public-footer{background:#08111f;border-top:1px solid #1b2d43;color:#8ca0b8}
</style>
