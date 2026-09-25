<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-lg">
      <div><div class="text-h4 page-title">Ventas de mostrador</div><div class="page-subtitle">Toda venta nueva queda pendiente. El stock físico se descuenta únicamente al completar la entrega.</div></div>
      <q-btn class="action-primary" icon="point_of_sale" label="Nueva venta" @click="openNew" />
    </div>

    <q-card flat class="panel">
      <q-card-section class="row q-col-gutter-sm"><div class="col-12 col-md-8"><q-input v-model="search" outlined dense dark clearable label="Buscar por número, cliente o estado"><template #prepend><q-icon name="search" /></template></q-input></div><div class="col-12 col-md-4 text-right"><q-btn flat color="cyan-4" icon="refresh" label="Actualizar" :loading="loading" @click="load" /></div></q-card-section>
      <q-table :rows="filtered" :columns="columns" row-key="id" dark flat class="table-dark" :loading="loading">
        <template #body-cell-acciones="p"><q-td :props="p"><q-btn flat round dense color="amber-4" icon="edit" @click="editStatus(p.row)"><q-tooltip>Cambiar estado</q-tooltip></q-btn><q-btn flat round dense color="cyan-4" icon="picture_as_pdf" @click="pdf(p.row)"><q-tooltip>PDF</q-tooltip></q-btn><q-btn v-if="p.row.evidencia_entrega" flat round dense color="green-4" icon="photo_camera" @click="viewEvidence(p.row)"><q-tooltip>Evidencia de entrega</q-tooltip></q-btn></q-td></template>
        <template #body-cell-Fecha="p"><q-td :props="p">{{ formatDate(p.row.Fecha) }}</q-td></template>
        <template #body-cell-cliente="p"><q-td :props="p">{{ p.row.usuario?.Nombre ? `${p.row.usuario.Nombre} ${p.row.usuario.Apellido||''}` : p.row.cliente || `Cliente #${p.row.id_usuario}` }}</q-td></template>
        <template #body-cell-Total="p"><q-td :props="p" class="text-green-3 text-weight-bold">Bs {{ money(p.row.Total) }}</q-td></template>
        <template #body-cell-Estado="p"><q-td :props="p"><q-chip dense :color="statusColor(p.row.Estado)" text-color="white">{{ p.row.Estado }}</q-chip></q-td></template>
        <template #body-cell-EstadoPago="p"><q-td :props="p"><q-chip dense :color="paymentColor(p.row.EstadoPago)" text-color="white">{{ p.row.EstadoPago || 'Pendiente' }}</q-chip></q-td></template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog" persistent>
      <q-card class="panel text-white" style="width:900px;max-width:96vw">
        <q-card-section class="row items-center justify-between"><div><div class="text-h6 text-weight-bold">Nueva venta</div><div class="text-caption text-muted">Se registrará como Pendiente y no afectará stock hasta la entrega.</div></div><q-btn flat round icon="close" v-close-popup /></q-card-section>
        <q-separator dark/>
        <q-card-section>
          <div class="row q-col-gutter-md">
            <div class="col-12 col-md-5"><q-select v-model="form.id_usuario" :options="clients" option-value="id" :option-label="clientLabel" emit-value map-options outlined dense dark label="Cliente *" /></div>
            <div class="col-6 col-md-3"><q-input v-model="form.Fecha" type="date" stack-label outlined dense dark label="Fecha" /></div>
            <div class="col-6 col-md-4"><q-select v-model="form.metodo_pago" :options="['Efectivo','QR','Transferencia']" outlined dense dark label="Método de pago" /></div>
          </div>
          <q-banner rounded class="q-mt-md bg-blue-grey-10 text-grey-3"><template #avatar><q-icon name="schedule" color="amber-4"/></template>Estado inicial: <b>Pendiente</b>. Verifica el pago desde el módulo Pagos antes de completar la entrega.</q-banner>
          <q-separator dark class="q-my-md"/>
          <div class="row items-center justify-between q-mb-sm"><div class="text-subtitle1 text-weight-bold">Detalle de productos</div><q-btn outline color="cyan-4" icon="add" label="Agregar" @click="addItem" /></div>
          <div v-for="(item,idx) in form.items" :key="idx" class="row q-col-gutter-sm items-center q-mb-sm"><div class="col-12 col-md-6"><q-select v-model="item.id_producto" :options="products" option-value="id" option-label="Nombre" emit-value map-options outlined dense dark label="Producto *" @update:model-value="syncPrice(item)" /></div><div class="col-4 col-md-2"><q-input v-model.number="item.cantidad" type="number" min="1" outlined dense dark label="Cantidad" /></div><div class="col-5 col-md-2"><q-input :model-value="money(item.precio_unitario)" readonly outlined dense dark label="Precio" prefix="Bs" /></div><div class="col-3 col-md-2 row justify-end items-center"><span class="text-green-3 q-mr-sm">{{ money(item.cantidad*item.precio_unitario) }}</span><q-btn flat round dense color="red-4" icon="delete" @click="removeItem(idx)" /></div></div>
          <div class="text-right text-h6 q-mt-md">Total: <span class="text-green-3">Bs {{ money(total) }}</span></div>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md"><q-btn flat label="Cancelar" v-close-popup/><q-btn class="action-primary" label="Registrar venta pendiente" :loading="saving" @click="save"/></q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="statusDialog">
      <q-card class="panel text-white" style="width:500px;max-width:94vw">
        <q-card-section><div class="text-h6">Actualizar venta #{{ selected?.id }}</div><div class="text-caption text-muted">Pago: {{ selected?.EstadoPago || 'Pendiente' }}</div></q-card-section>
        <q-card-section>
          <q-select v-model="status" :options="statusOptions(selected)" outlined dark label="Estado" />
          <q-banner v-if="status==='Entregado'" rounded class="q-mt-md bg-green-10 text-white"><template #avatar><q-icon name="verified"/></template>Para entregar, el pago debe estar verificado y debes adjuntar evidencia fotográfica. El stock se descontará una sola vez.</q-banner>
          <q-file v-if="status==='Entregado' && !selected?.evidencia_entrega" v-model="deliveryPhoto" accept="image/jpeg,image/png,image/webp" max-file-size="12582912" outlined dark label="Evidencia fotográfica de entrega *" class="q-mt-md"><template #prepend><q-icon name="photo_camera"/></template></q-file>
          <q-banner v-if="selected?.evidencia_entrega" rounded class="q-mt-md bg-blue-grey-10 text-grey-3"><template #avatar><q-icon name="photo_camera" color="green-4"/></template>Este registro ya cuenta con evidencia fotográfica.</q-banner>
        </q-card-section>
        <q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup/><q-btn class="action-primary" label="Guardar" :loading="savingStatus" @click="saveStatus"/></q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import api from '../services/api'
import { downloadPdf, openProtectedFile } from '../utils/download'

const $q=useQuasar()
const rows=ref([]),products=ref([]),clients=ref([]),loading=ref(false),saving=ref(false),savingStatus=ref(false),dialog=ref(false),statusDialog=ref(false),selected=ref(null),status=ref('Pendiente'),search=ref(''),deliveryPhoto=ref(null)
const columns=[
  {name:'acciones',label:'Acciones',field:'acciones',align:'center'},
  {name:'id',label:'#',field:'id',align:'left'},
  {name:'Fecha',label:'Fecha',field:'Fecha',align:'left'},
  {name:'cliente',label:'Cliente',field:'cliente',align:'left'},
  {name:'Estado',label:'Estado',field:'Estado',align:'center'},
  {name:'EstadoPago',label:'Pago',field:'EstadoPago',align:'center'},
  {name:'Total',label:'Total',field:'Total',align:'right'},
]
const today=()=>new Date().toISOString().slice(0,10)
const newForm=()=>({id_usuario:null,Fecha:today(),Estado:'Pendiente',metodo_pago:'Efectivo',items:[{id_producto:null,cantidad:1,precio_unitario:0}]})
const form=ref(newForm())
const money=v=>Number(v||0).toFixed(2)
const formatDate=v=>{if(!v)return '-';const [y,m,d]=String(v).slice(0,10).split('-');return y&&m&&d?`${d}/${m}/${y}`:v}
const clientLabel=c=>`${c.Nombre||''} ${c.Apellido||''}`.trim()||`Cliente #${c.id}`
const statusColor=s=>s==='Entregado'?'green-10':s==='Cancelado'?'red-10':s==='Confirmado'?'blue-9':'amber-10'
const paymentColor=s=>({Pendiente:'blue-grey-7',Reportado:'orange-8',Verificado:'green-8',Rechazado:'red-8',Reembolsado:'purple-8'}[s]||'blue-grey-7')
const total=computed(()=>form.value.items.reduce((sum,i)=>sum+Number(i.cantidad||0)*Number(i.precio_unitario||0),0))
const filtered=computed(()=>{const q=search.value.toLowerCase().trim();return !q?rows.value:rows.value.filter(r=>`${r.id} ${r.Estado||''} ${r.cliente||''} ${r.Nombre||''} ${r.Apellido||''}`.toLowerCase().includes(q))})

const load=async()=>{loading.value=true;try{const [a,b,c]=await Promise.all([api.get('/pedidos?canal=Mostrador'),api.get('/productos'),api.get('/usuarios')]);rows.value=a.data.pedidos||a.data.data||a.data||[];products.value=b.data.productos||[];clients.value=c.data.usuarios||c.data.data||c.data||[]}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo cargar ventas'})}finally{loading.value=false}}
const openNew=()=>{form.value=newForm();dialog.value=true}
const addItem=()=>form.value.items.push({id_producto:null,cantidad:1,precio_unitario:0})
const removeItem=i=>{if(form.value.items.length>1)form.value.items.splice(i,1)}
const syncPrice=item=>{const p=products.value.find(x=>x.id===item.id_producto);item.precio_unitario=Number(p?.Precio||0)}
const save=async()=>{for(const i of form.value.items){const p=products.value.find(x=>x.id===i.id_producto);if(!p||Number(i.cantidad)<1)return $q.notify({type:'warning',message:'Completa todos los productos.'});if(Number(i.cantidad)>Number(p.Disponible??p.Stock))return $q.notify({type:'warning',message:`Stock insuficiente para ${p.Nombre}. Disponible: ${p.Disponible??p.Stock}`})}if(!form.value.id_usuario)return $q.notify({type:'warning',message:'Selecciona un cliente.'});saving.value=true;try{await api.post('/pedidos',form.value);dialog.value=false;$q.notify({type:'positive',message:'Venta registrada como pendiente. El stock todavía no fue descontado.'});await load()}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo registrar la venta'})}finally{saving.value=false}}
const pdf=async r=>{try{await downloadPdf(`/pedidos/${r.id}/pdf`,`venta-${r.id}.pdf`)}catch{$q.notify({type:'negative',message:'No se pudo generar el PDF.'})}}
const viewEvidence=async r=>{try{await openProtectedFile(api,`/pedidos/${r.id}/evidencia-entrega`)}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo abrir la evidencia.'})}}
const editStatus=r=>{selected.value=r;status.value=r.Estado;deliveryPhoto.value=null;statusDialog.value=true}
const statusOptions=r=>r?.Estado==='Entregado'?['Entregado']:['Pendiente','Confirmado','Entregado','Cancelado']
const saveStatus=async()=>{if(status.value==='Entregado'&&selected.value?.EstadoPago!=='Verificado')return $q.notify({type:'warning',message:'Primero debes verificar el pago.'});if(status.value==='Entregado'&&!selected.value?.evidencia_entrega&&!deliveryPhoto.value)return $q.notify({type:'warning',message:'Adjunta una fotografía como evidencia de entrega.'});savingStatus.value=true;try{const fd=new FormData();fd.append('Estado',status.value);fd.append('_method','PUT');if(deliveryPhoto.value)fd.append('evidencia_entrega',deliveryPhoto.value);await api.post(`/pedidos/${selected.value.id}`,fd);statusDialog.value=false;$q.notify({type:'positive',message:'Estado actualizado.'});await load()}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo actualizar'})}finally{savingStatus.value=false}}

onMounted(load)
</script>
