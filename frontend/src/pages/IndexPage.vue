<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <div class="text-h4 page-title">Dashboard</div>
        <div class="page-subtitle">Resumen real de inventario, compras, ventas y clientes.</div>
      </div>
      <div class="row q-gutter-sm">
        <q-btn outline color="cyan-4" icon="sync" label="Actualizar" :loading="loading" @click="load" />
        <q-btn class="action-primary" icon="add_shopping_cart" label="Nueva venta" @click="$router.push('/admin/pedidos')" />
      </div>
    </div>

    <div class="row q-col-gutter-md q-mb-lg">
      <div v-for="item in metrics" :key="item.label" class="col-12 col-sm-6 col-lg-3">
        <q-card flat class="metric-card q-pa-md" @click="$router.push(item.path)">
          <div class="row items-center justify-between">
            <div class="metric-label">{{ item.label }}</div>
            <q-avatar size="34px" :color="item.color" text-color="white"><q-icon :name="item.icon" /></q-avatar>
          </div>
          <div class="metric-value q-mt-sm">{{ item.value }}</div>
          <div class="text-caption text-muted">{{ item.hint }}</div>
        </q-card>
      </div>
    </div>

    <div class="row q-col-gutter-md q-mb-lg">
      <div v-for="item in onlineMetrics" :key="item.label" class="col-6 col-md-3">
        <q-card flat class="metric-card q-pa-md" @click="$router.push('/admin/pedidos-online')">
          <div class="row items-center justify-between">
            <div class="metric-label">{{ item.label }}</div>
            <q-icon :name="item.icon" :color="item.color" size="24px" />
          </div>
          <div class="metric-value q-mt-sm">{{ item.value }}</div>
          <div class="text-caption text-muted">{{ item.hint }}</div>
        </q-card>
      </div>
    </div>

    <div class="row q-col-gutter-md">
      <div class="col-12 col-lg-7">
        <q-card flat class="panel">
          <q-card-section class="row items-center justify-between">
            <div>
              <div class="text-h6 text-weight-bold">Movimiento de los últimos 6 meses</div>
              <div class="text-caption text-muted">Comparación de ventas y compras.</div>
            </div>
            <q-btn flat color="cyan-4" icon-right="arrow_forward" label="Reportes" @click="$router.push('/admin/reportes')" />
          </q-card-section>
          <q-separator dark />
          <q-card-section>
            <div v-if="monthly.length" class="column q-gutter-md">
              <div v-for="row in monthly" :key="row.mes">
                <div class="row items-center justify-between text-caption q-mb-xs">
                  <span>{{ row.mes }}</span><span class="text-muted">Ventas Bs {{ money(row.ventas) }} · Compras Bs {{ money(row.compras) }}</span>
                </div>
                <div class="row q-gutter-xs no-wrap">
                  <div class="bar sale" :style="{ width: barWidth(row.ventas) }"></div>
                  <div class="bar purchase" :style="{ width: barWidth(row.compras) }"></div>
                </div>
              </div>
            </div>
            <div v-else class="text-center text-muted q-pa-xl">Todavía no hay datos históricos.</div>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-lg-5">
        <q-card flat class="panel full-height">
          <q-card-section>
            <div class="text-h6 text-weight-bold">Stock bajo</div>
            <div class="text-caption text-muted">Productos con 5 unidades o menos.</div>
          </q-card-section>
          <q-separator dark />
          <q-list separator dark>
            <q-item v-for="p in lowStock" :key="p.id" clickable @click="$router.push('/admin/inventario')">
              <q-item-section avatar><q-avatar color="red-10" text-color="red-2" icon="warning" /></q-item-section>
              <q-item-section><q-item-label>{{ p.Nombre }}</q-item-label><q-item-label caption>Reponer inventario</q-item-label></q-item-section>
              <q-item-section side><q-chip dense color="red-10" text-color="red-2">{{ p.Disponible ?? p.Stock }} u.</q-chip></q-item-section>
            </q-item>
          </q-list>
          <div v-if="!lowStock.length" class="text-center text-muted q-pa-xl">Sin alertas de stock.</div>
        </q-card>
      </div>
    </div>

    <div class="row q-col-gutter-md q-mt-xs">
      <div class="col-12 col-md-6">
        <q-card flat class="panel">
          <q-card-section class="row items-center justify-between"><div class="text-h6 text-weight-bold">Ventas recientes</div><q-btn flat color="cyan-4" label="Ver todas" @click="$router.push('/admin/pedidos')" /></q-card-section>
          <q-list separator dark>
            <q-item v-for="v in recentSales" :key="v.id"><q-item-section><q-item-label>Venta #{{ v.id }}</q-item-label><q-item-label caption>{{ v.Fecha || v.created_at || 'Sin fecha' }}</q-item-label></q-item-section><q-item-section side class="text-green-3">Bs {{ money(v.Total) }}</q-item-section></q-item>
          </q-list>
        </q-card>
      </div>
      <div class="col-12 col-md-6">
        <q-card flat class="panel">
          <q-card-section class="row items-center justify-between"><div class="text-h6 text-weight-bold">Compras recientes</div><q-btn flat color="cyan-4" label="Ver todas" @click="$router.push('/admin/compras')" /></q-card-section>
          <q-list separator dark>
            <q-item v-for="c in recentPurchases" :key="c.id"><q-item-section><q-item-label>{{ c.proveedor || `Compra #${c.id}` }}</q-item-label><q-item-label caption>{{ c.fecha || c.created_at || 'Sin fecha' }}</q-item-label></q-item-section><q-item-section side class="text-amber-3">Bs {{ money(c.total) }}</q-item-section></q-item>
          </q-list>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../services/api'

const loading = ref(false)
const data = ref({})
const products = ref([])
const money = (v) => Number(v || 0).toFixed(2)

const load = async () => {
  loading.value = true
  try {
    const [dash, prod] = await Promise.allSettled([api.get('/dashboard/totales'), api.get('/productos')])
    if (dash.status === 'fulfilled') data.value = dash.value.data?.data || dash.value.data || {}
    if (prod.status === 'fulfilled') products.value = prod.value.data?.productos || []
  } finally { loading.value = false }
}

const totals = computed(() => data.value.totales || data.value)
const metrics = computed(() => [
  { label: 'Productos', value: totals.value.productos ?? products.value.length, hint: 'Equipos y repuestos registrados', icon: 'precision_manufacturing', color: 'cyan-8', path: '/admin/productos' },
  { label: 'Clientes', value: totals.value.clientes ?? 0, hint: 'Clientes registrados', icon: 'groups', color: 'blue-8', path: '/admin/clientes' },
  { label: 'Ventas del mes', value: `Bs ${money(totals.value.ventas_mes)}`, hint: `${totals.value.pedidos_mes ?? 0} operaciones`, icon: 'point_of_sale', color: 'green-8', path: '/admin/pedidos' },
  { label: 'Compras del mes', value: `Bs ${money(totals.value.compras_mes)}`, hint: `${totals.value.compras_cantidad_mes ?? 0} ingresos`, icon: 'local_shipping', color: 'orange-8', path: '/admin/compras' },
])
const onlineMetrics = computed(() => {
  const o = data.value.pedidos_online || {}
  return [
    { label: 'Pedidos nuevos', value: o.nuevos ?? 0, hint: 'Esperando confirmación', icon: 'fiber_new', color: 'blue-4' },
    { label: 'En preparación', value: o.preparando ?? 0, hint: 'Confirmados o preparando', icon: 'inventory_2', color: 'orange-4' },
    { label: 'Listos', value: o.listos ?? 0, hint: 'Esperando recojo', icon: 'store', color: 'cyan-4' },
    { label: 'En camino', value: o.en_camino ?? 0, hint: 'Delivery activo', icon: 'local_shipping', color: 'purple-4' },
  ]
})
const lowStock = computed(() => data.value.stock_bajo || products.value.filter((p) => Number(p.Stock) <= 5).slice(0, 6))
const recentSales = computed(() => data.value.ventas_recientes || [])
const recentPurchases = computed(() => data.value.compras_recientes || [])
const monthly = computed(() => data.value.meses || data.value.estadisticas_mensuales || [])
const maxMonthly = computed(() => Math.max(1, ...monthly.value.flatMap((x) => [Number(x.ventas || 0), Number(x.compras || 0)])))
const barWidth = (value) => `${Math.max(4, (Number(value || 0) / maxMonthly.value) * 48)}%`

onMounted(load)
</script>

<style scoped>
.bar { height: 9px; border-radius: 999px; min-width: 4px; }
.bar.sale { background: #22c55e; }
.bar.purchase { background: #f59e0b; }
</style>
