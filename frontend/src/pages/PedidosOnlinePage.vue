<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <div class="text-h4 page-title">Pedidos online</div>
        <div class="page-subtitle">Gestiona pedidos del catálogo público hasta su entrega.</div>
      </div>
      <q-btn outline color="cyan-4" icon="refresh" label="Actualizar" :loading="loading" @click="load" />
    </div>

    <div class="row q-col-gutter-md q-mb-lg">
      <div v-for="card in cards" :key="card.label" class="col-6 col-md-3">
        <q-card flat class="metric-card q-pa-md" @click="statusFilter = card.value">
          <div class="metric-label">{{ card.label }}</div>
          <div class="metric-value">{{ count(card.value) }}</div>
          <div class="text-caption text-muted">{{ card.hint }}</div>
        </q-card>
      </div>
    </div>

    <q-card flat class="panel">
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="col-12 col-md-7"><q-input v-model="search" outlined dense dark clearable label="Buscar pedido, cliente, teléfono o código"><template #prepend><q-icon name="search" /></template></q-input></div>
        <div class="col-12 col-md-5"><q-select v-model="statusFilter" :options="statusFilterOptions" emit-value map-options outlined dense dark label="Estado" /></div>
      </q-card-section>
      <q-table :rows="filtered" :columns="columns" row-key="id" dark flat class="table-dark" :loading="loading">
        <template #body-cell-cliente="p"><q-td :props="p"><div>{{ p.row.cliente || `${p.row.Nombre || ''} ${p.row.Apellido || ''}` }}</div><div class="text-caption text-muted">{{ p.row.Telefono || '-' }}</div></q-td></template>
        <template #body-cell-entrega="p"><q-td :props="p"><q-chip dense outline color="cyan-4">{{ p.row.tipo_entrega || 'Recojo' }}</q-chip></q-td></template>
        <template #body-cell-Estado="p"><q-td :props="p"><q-chip dense :color="statusColor(p.row.Estado)" text-color="white">{{ p.row.Estado }}</q-chip></q-td></template>
        <template #body-cell-EstadoPago="p"><q-td :props="p"><q-chip dense :color="paymentColor(p.row.EstadoPago)" text-color="white">{{ p.row.EstadoPago || 'Pendiente' }}</q-chip><div class="text-caption text-muted">{{ p.row.MetodoPago || p.row.metodo_pago || '-' }}</div></q-td></template>
        <template #body-cell-Total="p"><q-td :props="p" class="text-green-3 text-weight-bold">Bs {{ money(p.row.Total) }}</q-td></template>
        <template #body-cell-acciones="p"><q-td :props="p"><q-btn flat round dense color="cyan-4" icon="visibility" @click="open(p.row)" /><q-btn flat round dense color="amber-4" icon="sync_alt" @click="openStatus(p.row)" /><q-btn flat round dense color="blue-3" icon="picture_as_pdf" @click="pdf(p.row)" /></q-td></template>
      </q-table>
    </q-card>

    <q-dialog v-model="detailDialog">
      <q-card class="panel text-white detail-card">
        <q-card-section class="row items-start justify-between">
          <div><div class="text-caption text-muted">PEDIDO ONLINE #{{ detail?.id }}</div><div class="text-h5 text-weight-bold">{{ detail?.Nombre }} {{ detail?.Apellido }}</div><div class="text-caption text-cyan-3 q-mt-xs">{{ detail?.codigo_seguimiento }}</div></div>
          <q-chip :color="statusColor(detail?.Estado)" text-color="white">{{ detail?.Estado }}</q-chip>
        </q-card-section>
        <q-separator dark />
        <q-card-section>
          <div class="row q-col-gutter-md q-mb-md">
            <div class="col-12 col-sm-6"><div class="text-caption text-muted">Teléfono</div><div>{{ detail?.Telefono || '-' }}</div></div>
            <div class="col-12 col-sm-6"><div class="text-caption text-muted">Entrega</div><div>{{ detail?.tipo_entrega || '-' }}</div></div>
            <div class="col-12"><div class="text-caption text-muted">Dirección</div><div>{{ detail?.direccion_entrega || detail?.Direccion_envio || 'Recojo en tienda' }}</div></div>
            <div v-if="detail?.notas_cliente" class="col-12"><div class="text-caption text-muted">Nota</div><div>{{ detail.notas_cliente }}</div></div>
          </div>
          <q-list bordered separator dark>
            <q-item v-for="item in detail?.items || []" :key="item.id_producto">
              <q-item-section><q-item-label>{{ item.Nombre }}</q-item-label><q-item-label caption>{{ item.cantidad }} × Bs {{ money(item.precio_unitario || item.Precio) }}</q-item-label></q-item-section>
              <q-item-section side class="text-green-3">Bs {{ money(item.subtotal || item.cantidad * (item.precio_unitario || item.Precio)) }}</q-item-section>
            </q-item>
          </q-list>
          <div class="text-right text-h6 q-mt-md">Total: <span class="text-green-3">Bs {{ money(detail?.Total) }}</span></div>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md"><q-btn flat label="Cerrar" v-close-popup /><q-btn class="action-primary" icon="sync_alt" label="Cambiar estado" @click="detailDialog = false; openStatus(detail)" /></q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="statusDialog">
      <q-card class="panel text-white" style="width: 480px; max-width: 94vw">
        <q-card-section><div class="text-h6">Actualizar pedido #{{ selected?.id }}</div><div class="text-caption text-muted">{{ selected?.cliente || `${selected?.Nombre || ''} ${selected?.Apellido || ''}` }} · {{ selected?.tipo_entrega }}</div></q-card-section>
        <q-card-section>
          <q-select v-model="newStatus" :options="statusOptions(selected)" outlined dark label="Nuevo estado" />
          <q-banner v-if="newStatus === 'Entregado'" class="q-mt-md bg-green-10 text-white" rounded><template #avatar><q-icon name="inventory_2" /></template>Al marcar Entregado, el sistema descuenta del stock físico las unidades reservadas.</q-banner>
          <q-banner v-if="newStatus === 'Cancelado'" class="q-mt-md bg-red-10 text-white" rounded><template #avatar><q-icon name="undo" /></template>Al cancelar, el sistema libera las unidades reservadas.</q-banner>
        </q-card-section>
        <q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup /><q-btn class="action-primary" label="Guardar estado" :loading="saving" @click="saveStatus" /></q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import api from '../services/api'
import { downloadPdf } from '../utils/download'

const $q = useQuasar()
const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const search = ref('')
const statusFilter = ref('Todos')
const detailDialog = ref(false)
const statusDialog = ref(false)
const detail = ref(null)
const selected = ref(null)
const newStatus = ref('Nuevo')
const money = (v) => Number(v || 0).toFixed(2)

const columns = [
  { name: 'id', label: '#', field: 'id', align: 'left' },
  { name: 'Fecha', label: 'Fecha', field: 'Fecha', align: 'left' },
  { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left' },
  { name: 'entrega', label: 'Entrega', field: 'tipo_entrega', align: 'center' },
  { name: 'Estado', label: 'Pedido', field: 'Estado', align: 'center' },
  { name: 'EstadoPago', label: 'Pago', field: 'EstadoPago', align: 'center' },
  { name: 'Total', label: 'Total', field: 'Total', align: 'right' },
  { name: 'acciones', label: 'Acciones', field: 'acciones', align: 'center' },
]
const cards = [
  { label: 'Nuevos', value: 'Nuevo', hint: 'Pendientes de confirmar' },
  { label: 'Preparación', value: 'Preparando', hint: 'En proceso' },
  { label: 'Listos', value: 'Listo para entrega', hint: 'Esperando recojo' },
  { label: 'En camino', value: 'En camino', hint: 'Delivery activo' },
]
const statusFilterOptions = ['Todos', 'Nuevo', 'Confirmado', 'Preparando', 'Listo para entrega', 'En camino', 'Entregado', 'Cancelado'].map((x) => ({ label: x, value: x }))
const statusColor = (s) => s === 'Entregado' ? 'green-8' : s === 'Cancelado' ? 'red-8' : s === 'Nuevo' ? 'blue-8' : s === 'En camino' ? 'purple-8' : 'orange-8'
const paymentColor = (s) => ({ Pendiente:'blue-grey-7', Reportado:'orange-8', Verificado:'green-8', Rechazado:'red-8', Reembolsado:'purple-8' }[s] || 'blue-grey-7')
const count = (status) => status === 'Preparando'
  ? rows.value.filter((r) => ['Confirmado', 'Preparando'].includes(r.Estado)).length
  : rows.value.filter((r) => r.Estado === status).length
const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return rows.value.filter((r) => {
    const text = `${r.id} ${r.cliente || ''} ${r.Nombre || ''} ${r.Apellido || ''} ${r.Telefono || ''} ${r.codigo_seguimiento || ''}`.toLowerCase()
    return (!q || text.includes(q)) && (statusFilter.value === 'Todos' || r.Estado === statusFilter.value)
  })
})

const load = async () => {
  loading.value = true
  try { rows.value = (await api.get('/pedidos?canal=Online')).data || [] }
  catch (e) { $q.notify({ type: 'negative', message: e.userMessage || 'No se pudieron cargar los pedidos online.' }) }
  finally { loading.value = false }
}
const open = async (row) => {
  try { detail.value = (await api.get(`/pedidos/${row.id}`)).data; detailDialog.value = true }
  catch (e) { $q.notify({ type: 'negative', message: e.userMessage || 'No se pudo abrir el pedido.' }) }
}
const openStatus = (row) => { selected.value = row; newStatus.value = row?.Estado || 'Nuevo'; statusDialog.value = true }
const statusOptions = (row) => {
  if (row?.Estado === 'Entregado') return ['Entregado']
  return row?.tipo_entrega === 'Delivery'
    ? ['Nuevo', 'Confirmado', 'Preparando', 'En camino', 'Entregado', 'Cancelado']
    : ['Nuevo', 'Confirmado', 'Preparando', 'Listo para entrega', 'Entregado', 'Cancelado']
}
const saveStatus = async () => {
  saving.value = true
  try {
    await api.put(`/pedidos/${selected.value.id}`, { Estado: newStatus.value })
    statusDialog.value = false
    $q.notify({ type: 'positive', message: 'Estado actualizado.' })
    await load()
    if (detail.value?.id === selected.value.id) detail.value = (await api.get(`/pedidos/${selected.value.id}`)).data
  } catch (e) { $q.notify({ type: 'negative', message: e.userMessage || 'No se pudo actualizar el pedido.' }) }
  finally { saving.value = false }
}
const pdf = async (row) => {
  try { await downloadPdf(`/pedidos/${row.id}/pdf`, `pedido-online-${row.id}.pdf`) }
  catch { $q.notify({ type: 'negative', message: 'No se pudo generar el PDF.' }) }
}

onMounted(load)
</script>

<style scoped>
.detail-card { width: 820px; max-width: 96vw; border-radius: 16px; }
</style>
