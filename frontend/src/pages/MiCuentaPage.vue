<template>
  <q-page class="account-page q-pa-md q-py-xl">
    <div class="account-wrap">
      <div class="row items-center justify-between q-mb-lg">
        <div>
          <div class="text-h4 text-weight-bold text-white">Mi cuenta</div>
          <div class="text-grey-5">Tus datos, pedidos, pagos y guías relacionadas.</div>
        </div>
        <q-btn outline color="cyan-4" icon="logout" label="Cerrar sesión" @click="signOut" />
      </div>

      <div class="row q-col-gutter-lg">
        <div class="col-12 col-lg-4">
          <q-card flat class="panel text-white q-pa-md q-mb-lg">
            <div class="row items-center q-mb-md">
              <q-avatar color="blue-grey-9" text-color="cyan-3" icon="person" />
              <div class="q-ml-sm">
                <div class="text-h6 text-weight-bold">{{ profile.Nombre || 'Mi perfil' }} {{ profile.Apellido }}</div>
                <div class="text-caption text-grey-5">Se carga automáticamente al comprar.</div>
              </div>
            </div>
            <div class="q-gutter-md">
              <q-input v-model="profile.Nombre" dark outlined dense label="Nombre *" />
              <q-input v-model="profile.Apellido" dark outlined dense label="Apellido *" />
              <q-input v-model="profile.Telefono" dark outlined dense label="Teléfono *" />
              <q-input v-model="profile.Email" dark outlined dense type="email" label="Correo *" />
              <q-input v-model="profile.Direccion_envio" dark outlined dense type="textarea" autogrow label="Dirección habitual" />
              <q-btn class="full-width action-primary" icon="save" label="Guardar datos" :loading="saving" @click="saveProfile" />
            </div>
          </q-card>

          <q-card flat class="panel text-white">
            <q-card-section class="row items-center justify-between">
              <div>
                <div class="text-h6 text-weight-bold">Notificaciones</div>
                <div class="text-caption text-grey-5">Cambios de pedido y pago.</div>
              </div>
              <q-badge v-if="unread" color="red">{{ unread }}</q-badge>
            </q-card-section>
            <q-separator dark />
            <q-list v-if="notifications.length" separator dark>
              <q-item v-for="n in notifications.slice(0, 6)" :key="n.id">
                <q-item-section avatar><q-icon :name="n.tipo === 'pago' ? 'payments' : 'local_shipping'" color="cyan-4" /></q-item-section>
                <q-item-section>
                  <q-item-label :class="!n.leida_at ? 'text-weight-bold' : ''">{{ n.titulo }}</q-item-label>
                  <q-item-label caption>{{ n.mensaje }}</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
            <div v-else class="q-pa-md text-grey-5">Sin notificaciones.</div>
          </q-card>
        </div>

        <div class="col-12 col-lg-8">
          <q-card flat class="panel text-white">
            <q-tabs v-model="tab" dense align="left" active-color="cyan-3" indicator-color="cyan-4" class="text-grey-5">
              <q-tab name="pedidos" icon="shopping_bag" label="Mis pedidos" />
              <q-tab name="guias" icon="school" label="Mis guías" />
            </q-tabs>
            <q-separator dark />
            <q-tab-panels v-model="tab" animated class="bg-transparent text-white">
              <q-tab-panel name="pedidos" class="q-pa-none">
                <q-list v-if="orders.length" separator dark>
                  <q-expansion-item v-for="p in orders" :key="p.id" expand-separator>
                    <template #header>
                      <q-item-section avatar><q-avatar color="blue-grey-9" text-color="cyan-3" icon="shopping_bag" /></q-item-section>
                      <q-item-section>
                        <q-item-label>Pedido #{{ p.id }}</q-item-label>
                        <q-item-label caption>{{ p.codigo_seguimiento || 'Sin código' }} · {{ dateText(p.Fecha) }} · QR</q-item-label>
                      </q-item-section>
                      <q-item-section side>
                        <q-chip dense :color="statusColor(p.Estado)" text-color="white">{{ p.Estado }}</q-chip>
                        <q-chip dense :color="paymentColor(p.pago?.estado)" text-color="white">Pago: {{ p.pago?.estado || 'Pendiente' }}</q-chip>
                        <div class="text-green-3 text-weight-bold">Bs {{ money(p.Total) }}</div>
                      </q-item-section>
                    </template>

                    <q-card class="bg-transparent text-white q-pa-md">
                      <div v-for="item in p.items || []" :key="`${p.id}-${item.id_producto}`" class="q-py-sm item-line">
                        <div class="row justify-between"><span>{{ item.Nombre }} × {{ item.cantidad }}</span><span>Bs {{ money(item.subtotal) }}</span></div>
                        <div v-if="item.capacitaciones?.length" class="row q-gutter-xs q-mt-xs">
                          <q-btn v-for="g in item.capacitaciones" :key="g.id" flat dense color="cyan-4" icon="school" :label="g.titulo" :to="`/capacitacion/${g.id}`" />
                        </div>
                      </div>

                      <div class="payment-inline q-pa-md q-mt-md">
                        <div class="row items-center q-col-gutter-md">
                          <div v-if="p.pago?.estado !== 'Verificado'" class="col-12 col-sm-auto text-center">
                            <img src="/yape-qr.png" alt="QR de pago" class="account-qr cursor-pointer" title="Ampliar QR" @click="qrDialog = true" />
                            <div class="row justify-center q-gutter-xs q-mt-xs">
                              <q-btn flat dense color="cyan-4" icon="zoom_in" label="Ampliar" @click="qrDialog = true" />
                              <q-btn flat dense color="cyan-4" icon="download" label="Descargar" @click="downloadQr" />
                            </div>
                          </div>
                          <div class="col">
                            <div class="text-weight-bold">Pago por QR</div>
                            <div class="text-caption text-grey-5">
                              Estado: {{ p.pago?.estado || 'Pendiente' }}. El pedido se confirma únicamente después de subir el comprobante y verificar el pago.
                            </div>
                            <q-banner v-if="p.pago?.estado === 'Reportado'" dense rounded class="payment-wait q-mt-sm">
                              <template #avatar><q-icon name="hourglass_top" color="orange-4" /></template>
                              Comprobante enviado. El pago está esperando verificación.
                            </q-banner>
                            <q-banner v-if="p.pago?.estado === 'Rechazado'" dense rounded class="payment-rejected q-mt-sm">
                              <template #avatar><q-icon name="error_outline" color="red-4" /></template>
                              El comprobante fue rechazado. Puedes subir uno nuevo.
                            </q-banner>
                            <div class="row q-gutter-sm q-mt-sm">
                              <q-btn outline color="cyan-4" icon="local_shipping" label="Seguimiento" :to="`/seguimiento/${p.codigo_seguimiento}`" />
                              <q-btn v-if="canReportPayment(p)" color="amber-8" text-color="white" icon="qr_code_2" label="Pagar / subir comprobante" @click="openPayment(p)" />
                              <q-btn v-if="p.pago?.comprobante" flat color="cyan-4" icon="open_in_new" label="Ver comprobante" @click="viewProof(p.pago)" />
                            </div>
                          </div>
                        </div>
                      </div>
                    </q-card>
                  </q-expansion-item>
                </q-list>
                <div v-else-if="!loading" class="q-pa-xl text-center text-grey-5"><q-icon name="shopping_bag" size="48px" /><div class="q-mt-sm">Todavía no tienes pedidos online.</div></div>
              </q-tab-panel>

              <q-tab-panel name="guias">
                <div v-if="guides.length" class="row q-col-gutter-md">
                  <div v-for="g in guides" :key="g.id" class="col-12 col-md-6">
                    <q-card flat class="guide-card q-pa-md full-height">
                      <div class="row items-start no-wrap">
                        <q-avatar color="cyan-10" text-color="cyan-2" icon="school" />
                        <div class="q-ml-md">
                          <div class="text-subtitle1 text-weight-bold">{{ g.titulo }}</div>
                          <div class="text-caption text-grey-5 q-mt-xs">{{ g.descripcion || 'Guía relacionada con uno de tus productos.' }}</div>
                          <q-btn flat dense color="cyan-4" icon="menu_book" label="Abrir guía" class="q-mt-sm" :to="`/capacitacion/${g.id}`" />
                        </div>
                      </div>
                    </q-card>
                  </div>
                </div>
                <div v-else class="q-pa-xl text-center text-grey-5"><q-icon name="school" size="48px" /><div class="q-mt-sm">Tus productos todavía no tienen guías relacionadas.</div></div>
              </q-tab-panel>
            </q-tab-panels>
            <q-inner-loading :showing="loading"><q-spinner color="cyan-4" size="48px" /></q-inner-loading>
          </q-card>
        </div>
      </div>
    </div>

    <q-dialog v-model="paymentDialog" persistent>
      <q-card class="panel text-white payment-dialog-card">
        <q-card-section class="row items-start justify-between">
          <div>
            <div class="text-h6">Pagar pedido por QR</div>
            <div class="text-caption text-grey-5">Pedido #{{ selectedOrder?.id }} · Bs {{ money(selectedOrder?.Total) }}</div>
          </div>
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator dark />
        <q-card-section>
          <div class="row q-col-gutter-lg items-center">
            <div class="col-12 col-md-6 text-center">
              <img src="/yape-qr.png" alt="QR de pago" class="dialog-qr cursor-pointer" title="Ampliar QR" @click="qrDialog = true" />
              <div class="row justify-center q-gutter-sm q-mt-sm">
                <q-btn outline color="cyan-4" icon="zoom_in" label="Ampliar QR" @click="qrDialog = true" />
                <q-btn outline color="cyan-4" icon="download" label="Descargar QR" @click="downloadQr" />
              </div>
            </div>
            <div class="col-12 col-md-6 q-gutter-md">
              <div class="payment-step"><b>1.</b> Escanea el QR o descárgalo para pagar desde otro dispositivo.</div>
              <div class="payment-step"><b>2.</b> Realiza el pago por el total de <b>Bs {{ money(selectedOrder?.Total) }}</b>.</div>
              <div class="payment-step"><b>3.</b> Sube la captura o fotografía del comprobante.</div>
              <div class="payment-step"><b>4.</b> El pedido pasará a Confirmado cuando el personal verifique el pago.</div>
              <q-file
                v-model="paymentForm.comprobante"
                outlined
                dark
                accept="image/jpeg,image/png,image/webp,application/pdf"
                max-file-size="8388608"
                label="Subir comprobante de pago *"
                hint="JPG, PNG, WEBP o PDF. Máximo 8 MB."
              >
                <template #prepend><q-icon name="upload_file" /></template>
              </q-file>
              <q-banner rounded class="payment-info">
                <template #avatar><q-icon name="info" color="cyan-4" /></template>
                Enviar el comprobante no confirma el pedido automáticamente. Primero debe ser verificado.
              </q-banner>
            </div>
          </div>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md q-pt-none">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn class="action-primary" icon="send" label="Enviar para verificar" :disable="!paymentForm.comprobante" :loading="reporting" @click="reportPayment" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="qrDialog">
      <q-card class="qr-dialog-card bg-white">
        <q-card-section class="row items-center justify-between text-dark">
          <div>
            <div class="text-h6 text-weight-bold">QR de pago</div>
            <div class="text-caption text-grey-7">Escanea o descarga la imagen.</div>
          </div>
          <q-btn flat round icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-card-section class="text-center q-pa-md">
          <img src="/yape-qr.png" alt="QR de pago ampliado" class="qr-large" />
        </q-card-section>
        <q-card-actions align="center" class="q-pb-lg">
          <q-btn color="purple-8" icon="download" label="Descargar QR" @click="downloadQr" />
          <q-btn outline color="purple-8" label="Cerrar" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import { clientApi } from '../services/api'
import { authState, logoutClient, refreshClientSession } from '../services/auth'
import { openProtectedFile } from '../utils/download'

const $q = useQuasar()
const router = useRouter()
const route = useRoute()
const tab = ref('pedidos')
const orders = ref([])
const notifications = ref([])
const unread = ref(0)
const loading = ref(false)
const saving = ref(false)
const paymentDialog = ref(false)
const qrDialog = ref(false)
const selectedOrder = ref(null)
const reporting = ref(false)
const profile = reactive({ Nombre: '', Apellido: '', Telefono: '', Direccion_envio: '', Email: '' })
const paymentForm = reactive({ comprobante: null })

const money = v => Number(v || 0).toFixed(2)
const dateText = v => {
  if (!v) return '-'
  const raw = String(v).slice(0, 10)
  const [y, m, d] = raw.split('-')
  return y && m && d ? `${d}/${m}/${y}` : raw
}
const statusColor = s => ({ Nuevo: 'blue-8', Confirmado: 'indigo-7', Preparando: 'orange-8', 'Listo para entrega': 'purple-7', 'En camino': 'deep-purple-7', Entregado: 'green-8', Cancelado: 'red-8' }[s] || 'blue-grey-7')
const paymentColor = s => ({ Pendiente: 'blue-grey-7', Reportado: 'orange-8', Verificado: 'green-8', Rechazado: 'red-8', Reembolsado: 'purple-8' }[s] || 'blue-grey-7')
const guides = computed(() => {
  const map = new Map()
  for (const p of orders.value) for (const item of p.items || []) for (const g of item.capacitaciones || []) map.set(g.id, g)
  return [...map.values()]
})

const fill = () => {
  const c = authState.clientProfile || {}
  profile.Nombre = c.Nombre || ''
  profile.Apellido = c.Apellido || ''
  profile.Telefono = c.Telefono || ''
  profile.Direccion_envio = c.Direccion_envio || ''
  profile.Email = c.Email || authState.clientUser?.email || ''
}

const canReportPayment = p => {
  if (!p || p.Estado === 'Cancelado') return false
  const estado = p.pago?.estado || 'Pendiente'
  return ['Pendiente', 'Rechazado'].includes(estado)
}

const openPayment = p => {
  selectedOrder.value = p
  paymentForm.comprobante = null
  paymentDialog.value = true
}

const load = async () => {
  loading.value = true
  try {
    await refreshClientSession()
    fill()
    const [o, n] = await Promise.all([clientApi.get('/auth/cliente/pedidos'), clientApi.get('/notificaciones')])
    orders.value = o.data.pedidos || []
    notifications.value = n.data.notificaciones || []
    unread.value = n.data.no_leidas || 0

    const paymentOrderId = Number(route.query.pagar || 0)
    if (paymentOrderId && !paymentDialog.value) {
      const pendingOrder = orders.value.find(p => Number(p.id) === paymentOrderId)
      if (pendingOrder && canReportPayment(pendingOrder)) openPayment(pendingOrder)
    }
  } catch (e) {
    $q.notify({ type: 'negative', message: e.userMessage || 'No se pudo cargar tu cuenta.' })
  } finally {
    loading.value = false
  }
}

const saveProfile = async () => {
  saving.value = true
  try {
    const { data } = await clientApi.put('/auth/cliente/perfil', { ...profile, email: profile.Email })
    authState.clientProfile = data.cliente
    authState.clientUser = data.user
    localStorage.setItem('robokit_client_profile', JSON.stringify(data.cliente))
    localStorage.setItem('robokit_client_user', JSON.stringify(data.user))
    $q.notify({ type: 'positive', message: 'Tus datos quedaron actualizados.' })
  } catch (e) {
    $q.notify({ type: 'negative', message: e.userMessage || 'No se pudieron guardar tus datos.' })
  } finally {
    saving.value = false
  }
}

const downloadQr = () => {
  const link = document.createElement('a')
  link.href = '/yape-qr.png'
  link.download = 'QR-pago-ROBOKIT.png'
  document.body.appendChild(link)
  link.click()
  link.remove()
}

const reportPayment = async () => {
  if (!selectedOrder.value) return
  if (!paymentForm.comprobante) return $q.notify({ type: 'warning', message: 'Sube el comprobante de pago.' })

  reporting.value = true
  try {
    const fd = new FormData()
    fd.append('comprobante', paymentForm.comprobante)
    const { data } = await clientApi.post(`/auth/cliente/pedidos/${selectedOrder.value.id}/pago`, fd)
    paymentDialog.value = false
    paymentForm.comprobante = null
    await router.replace({ path: '/mi-cuenta', query: {} })
    $q.notify({ type: 'positive', message: data.message || 'Comprobante enviado para verificación.' })
    await load()
    tab.value = 'pedidos'
  } catch (e) {
    $q.notify({ type: 'negative', message: e.userMessage || 'No se pudo enviar el comprobante.' })
  } finally {
    reporting.value = false
  }
}

const viewProof = async pg => {
  try {
    await openProtectedFile(clientApi, `/pagos/${pg.id}/comprobante`)
  } catch (e) {
    $q.notify({ type: 'negative', message: e.userMessage || 'No se pudo abrir el comprobante.' })
  }
}

const signOut = async () => {
  await logoutClient()
  router.replace('/tienda')
}

onMounted(load)
</script>

<style scoped>
.account-page{background:#07101d;min-height:80vh}.account-wrap{max-width:1260px;margin:0 auto}.panel{background:#0d1828;border:1px solid #1d3148;border-radius:16px}.payment-inline{background:#091725;border:1px solid #20364e;border-radius:12px}.account-qr{width:120px;height:140px;object-fit:cover;border-radius:10px;border:1px solid rgba(34,211,238,.28)}.payment-dialog-card{width:820px;max-width:96vw;border-radius:16px}.dialog-qr{width:300px;max-width:100%;border-radius:14px;border:2px solid rgba(34,211,238,.35)}.qr-dialog-card{width:720px;max-width:96vw;border-radius:18px}.qr-large{width:min(640px,86vw);max-height:76vh;object-fit:contain;border-radius:14px}.payment-step{padding:9px 11px;background:#091725;border:1px solid #20364e;border-radius:10px;color:#d9e8f4}.payment-card,.guide-card{background:#0a1524;border:1px solid #1d3148;border-radius:14px}.payment-info{background:#0a2232;border:1px solid rgba(34,211,238,.24);color:#c9e9f2}.payment-wait{background:#2b1a06;color:#ffd7a0;border:1px solid rgba(251,146,60,.3)}.payment-rejected{background:#2a0e14;color:#fecaca;border:1px solid rgba(248,113,113,.28)}.item-line{border-bottom:1px solid rgba(148,163,184,.12)}
</style>
