<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-lg">
      <div><div class="text-h4 page-title">Pagos</div><div class="page-subtitle">Verifica pagos de pedidos online y ventas registradas.</div></div>
      <q-btn outline color="cyan-4" icon="refresh" label="Actualizar" :loading="loading" @click="load" />
    </div>

    <div class="row q-col-gutter-md q-mb-lg">
      <div v-for="c in cards" :key="c.value" class="col-6 col-md-3">
        <q-card flat class="metric-card q-pa-md cursor-pointer" @click="filter=c.value">
          <div class="metric-label">{{ c.label }}</div><div class="metric-value">{{ count(c.value) }}</div><div class="text-caption text-muted">{{ c.hint }}</div>
        </q-card>
      </div>
    </div>

    <q-card flat class="panel">
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="col-12 col-md-8"><q-input v-model="search" outlined dense dark clearable label="Buscar cliente, pedido o referencia"><template #prepend><q-icon name="search"/></template></q-input></div>
        <div class="col-12 col-md-4"><q-select v-model="filter" :options="filterOptions" emit-value map-options outlined dense dark label="Estado del pago"/></div>
      </q-card-section>
      <q-table :rows="filtered" :columns="columns" row-key="id" dark flat :loading="loading" class="table-dark">
        <template #body-cell-Fecha="p"><q-td :props="p">{{ formatDate(p.row.Fecha) }}</q-td></template>
        <template #body-cell-cliente="p"><q-td :props="p"><div>{{ p.row.Nombre }} {{ p.row.Apellido }}</div><div class="text-caption text-muted">{{ p.row.Telefono || '-' }}</div></q-td></template>
        <template #body-cell-monto="p"><q-td :props="p" class="text-green-3 text-weight-bold">Bs {{ money(p.row.monto) }}</q-td></template>
        <template #body-cell-estado="p"><q-td :props="p"><q-chip dense :color="paymentColor(p.row.estado)" text-color="white">{{ p.row.estado }}</q-chip></q-td></template>
        <template #body-cell-comprobante="p"><q-td :props="p"><q-btn v-if="p.row.comprobante" flat dense color="cyan-4" icon="open_in_new" label="Ver" @click="viewProof(p.row)"/><span v-else class="text-grey-6">Sin archivo</span></q-td></template>
        <template #body-cell-acciones="p"><q-td :props="p"><q-btn flat round dense color="amber-4" icon="edit" @click="openEdit(p.row)"><q-tooltip>Cambiar estado</q-tooltip></q-btn></q-td></template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog" persistent>
      <q-card class="panel text-white" style="width:560px;max-width:95vw">
        <q-card-section><div class="text-h6">Actualizar pago</div><div class="text-caption text-grey-5">Pedido #{{ selected?.pedido_id }} · {{ selected?.Nombre }} {{ selected?.Apellido }}</div></q-card-section>
        <q-separator dark/>
        <q-card-section class="q-gutter-md">
          <div class="row justify-between"><span>Método</span><b>{{ selected?.metodo }}</b></div>
          <div class="row justify-between"><span>Monto</span><b class="text-green-3">Bs {{ money(selected?.monto) }}</b></div>
          <div v-if="selected?.referencia"><div class="text-caption text-grey-5">Referencia</div><div>{{ selected.referencia }}</div></div>
          <q-select v-model="newState" :options="stateOptions" outlined dense dark label="Estado *"/>
          <q-input v-model="note" type="textarea" autogrow outlined dense dark label="Nota interna / motivo (opcional)"/>
          <q-banner rounded class="info-banner"><template #avatar><q-icon name="notifications_active" color="cyan-4"/></template>Al verificar o rechazar, el cliente recibe una notificación en su cuenta.</q-banner>
        </q-card-section>
        <q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup/><q-btn class="action-primary" label="Guardar" :loading="saving" @click="save"/></q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import api from '../services/api'
import { openProtectedFile } from '../utils/download'

const $q=useQuasar(),rows=ref([]),loading=ref(false),saving=ref(false),search=ref(''),filter=ref('Todos'),dialog=ref(false),selected=ref(null),newState=ref('Pendiente'),note=ref('')
const stateOptions=['Pendiente','Reportado','Verificado','Rechazado','Reembolsado']
const filterOptions=['Todos',...stateOptions].map(v=>({label:v,value:v}))
const cards=[{label:'Reportados',value:'Reportado',hint:'Esperan revisión'},{label:'Verificados',value:'Verificado',hint:'Pagos confirmados'},{label:'Pendientes',value:'Pendiente',hint:'Sin confirmación'},{label:'Rechazados',value:'Rechazado',hint:'Requieren corrección'}]
const columns=[{name:'acciones',label:'Acciones',field:'acciones',align:'center'},{name:'pedido',label:'Pedido',field:'pedido_id',align:'left'},{name:'cliente',label:'Cliente',field:'Nombre',align:'left'},{name:'metodo',label:'Método',field:'metodo',align:'center'},{name:'monto',label:'Monto',field:'monto',align:'right'},{name:'estado',label:'Pago',field:'estado',align:'center'},{name:'comprobante',label:'Comprobante',field:'comprobante',align:'center'},{name:'Fecha',label:'Fecha',field:'Fecha',align:'left'}]
const formatDate=v=>{if(!v)return '-';const [y,m,d]=String(v).slice(0,10).split('-');return y&&m&&d?`${d}/${m}/${y}`:v};const money=v=>Number(v||0).toFixed(2),paymentColor=s=>({Pendiente:'blue-grey-7',Reportado:'orange-8',Verificado:'green-8',Rechazado:'red-8',Reembolsado:'purple-8'}[s]||'blue-grey-7')
const count=s=>rows.value.filter(r=>r.estado===s).length
const filtered=computed(()=>{const q=search.value.trim().toLowerCase();return rows.value.filter(r=>(filter.value==='Todos'||r.estado===filter.value)&&(!q||`${r.pedido_id} ${r.Nombre||''} ${r.Apellido||''} ${r.Telefono||''} ${r.referencia||''}`.toLowerCase().includes(q)))})
const load=async()=>{loading.value=true;try{rows.value=(await api.get('/pagos')).data.pagos||[]}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudieron cargar los pagos.'})}finally{loading.value=false}}
const openEdit=r=>{selected.value=r;newState.value=r.estado;note.value=r.nota||'';dialog.value=true}
const viewProof=async r=>{try{await openProtectedFile(api,`/pagos/${r.id}/comprobante`)}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo abrir el comprobante.'})}}
const save=async()=>{saving.value=true;try{await api.put(`/pagos/${selected.value.id}/estado`,{estado:newState.value,nota:note.value||null});dialog.value=false;$q.notify({type:'positive',message:'Pago actualizado y cliente notificado.'});await load()}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo actualizar el pago.'})}finally{saving.value=false}}
onMounted(load)
</script>

<style scoped>.info-banner{background:#0a2232;border:1px solid rgba(34,211,238,.24);color:#c9e9f2}</style>
