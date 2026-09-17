<template>
  <q-page class="tracking-page q-pa-md">
    <div class="tracking-wrap">
      <div class="text-center q-mb-xl">
        <q-icon name="local_shipping" size="58px" color="cyan-4" />
        <div class="text-h4 text-weight-bold q-mt-md">Seguimiento de pedido</div>
        <div class="text-muted q-mt-xs">Ingresa el código que recibiste al confirmar tu compra.</div>
      </div>

      <q-card flat class="panel search-card q-pa-md q-mb-lg">
        <div class="row q-col-gutter-sm items-center">
          <div class="col-12 col-md"><q-input v-model="code" outlined dark clearable label="Código de seguimiento" @keyup.enter="searchOrder" /></div>
          <div class="col-12 col-md-auto"><q-btn class="action-primary full-width" icon="search" label="Consultar" :loading="loading" @click="searchOrder" /></div>
        </div>
      </q-card>

      <q-card v-if="order" flat class="panel order-card">
        <q-card-section class="row items-start justify-between q-col-gutter-md">
          <div>
            <div class="text-caption text-muted">PEDIDO #{{ order.id }}</div>
            <div class="text-h5 text-weight-bold">{{ order.Nombre }} {{ order.Apellido }}</div>
            <div class="text-caption text-muted q-mt-xs">{{ order.codigo_seguimiento }}</div>
          </div>
          <q-chip :color="statusColor(order.Estado)" text-color="white" icon="schedule">{{ order.Estado }}</q-chip>
        </q-card-section>
        <q-separator dark />
        <q-card-section>
          <div class="row q-col-gutter-lg">
            <div class="col-12 col-md-7">
              <div class="text-subtitle1 text-weight-bold q-mb-md">Progreso</div>
              <q-timeline color="cyan-4" layout="comfortable">
                <q-timeline-entry v-for="step in steps" :key="step" :title="step" :icon="stepIcons[step]" :color="stepColor(step)" :subtitle="stepSubtitle(step)" />
              </q-timeline>
            </div>
            <div class="col-12 col-md-5">
              <q-card flat class="summary-box q-pa-md">
                <div class="text-subtitle1 text-weight-bold">Resumen</div>
                <div class="row justify-between q-mt-md"><span class="text-muted">Entrega</span><strong>{{ order.tipo_entrega || '-' }}</strong></div>
                <div class="row justify-between q-mt-sm"><span class="text-muted">Fecha</span><strong>{{ order.Fecha || '-' }}</strong></div>
                <div class="row justify-between q-mt-sm"><span class="text-muted">Pago</span><q-chip dense :color="paymentColor(order.pago?.estado)" text-color="white">{{ order.pago?.estado || 'Pendiente' }}</q-chip></div>
                <div class="row justify-between q-mt-sm"><span class="text-muted">Método</span><strong>{{ order.pago?.metodo || order.metodo_pago || '-' }}</strong></div>
                <q-separator dark class="q-my-md" />
                <div v-for="item in order.items || []" :key="item.id_producto" class="row justify-between q-mb-sm">
                  <span>{{ item.cantidad }} × {{ item.Nombre }}</span><span>Bs {{ money(item.subtotal || item.cantidad * item.precio_unitario) }}</span>
                </div>
                <q-separator dark class="q-my-md" />
                <div class="row justify-between text-h6"><span>Total</span><span class="text-green-3">Bs {{ money(order.Total) }}</span></div>
              </q-card>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </div>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'

const $q = useQuasar()
const route = useRoute()
const router = useRouter()
const code = ref(route.params.codigo || '')
const loading = ref(false)
const order = ref(null)
const money = (v) => Number(v || 0).toFixed(2)
const allSteps = ['Nuevo', 'Confirmado', 'Preparando', 'Listo para entrega', 'En camino', 'Entregado']
const steps = computed(() => {
  if (order.value?.tipo_entrega === 'Delivery') return allSteps.filter((x) => x !== 'Listo para entrega')
  return allSteps.filter((x) => x !== 'En camino')
})
const stepIcons = { Nuevo: 'receipt_long', Confirmado: 'check_circle', Preparando: 'inventory_2', 'Listo para entrega': 'store', 'En camino': 'local_shipping', Entregado: 'done_all' }
const statusColor = (s) => s === 'Entregado' ? 'green-8' : s === 'Cancelado' ? 'red-8' : s === 'Nuevo' ? 'blue-8' : 'orange-8'
const paymentColor = (s) => ({ Pendiente:'blue-grey-7', Reportado:'orange-8', Verificado:'green-8', Rechazado:'red-8', Reembolsado:'purple-8' }[s] || 'blue-grey-7')
const stepIndex = computed(() => steps.value.indexOf(order.value?.Estado))
const stepColor = (step) => order.value?.Estado === 'Cancelado' ? 'grey-7' : (steps.value.indexOf(step) <= stepIndex.value ? 'cyan-5' : 'blue-grey-8')
const stepSubtitle = (step) => order.value?.Estado === 'Cancelado' ? (step === 'Nuevo' ? 'Pedido cancelado' : '') : (steps.value.indexOf(step) <= stepIndex.value ? 'Completado' : 'Pendiente')

const searchOrder = async () => {
  if (!code.value.trim()) return $q.notify({ type: 'warning', message: 'Ingresa tu código de seguimiento.' })
  loading.value = true
  order.value = null
  try {
    const clean = code.value.trim().toUpperCase()
    const response = await api.get(`/tienda/seguimiento/${encodeURIComponent(clean)}`)
    order.value = response.data.pedido
    if (route.params.codigo !== clean) router.replace(`/seguimiento/${encodeURIComponent(clean)}`)
  } catch (e) {
    $q.notify({ type: 'negative', message: e.userMessage || 'No encontramos ese pedido.' })
  } finally { loading.value = false }
}

onMounted(() => { if (code.value) searchOrder() })
</script>

<style scoped>
.tracking-page { min-height: 100vh; background: #07101d; color: white; }
.tracking-wrap { max-width: 1040px; margin: 36px auto 80px; }
.search-card, .order-card { border-radius: 16px; }
.summary-box { background: #091421; border: 1px solid #1e344c; border-radius: 14px; }
</style>
