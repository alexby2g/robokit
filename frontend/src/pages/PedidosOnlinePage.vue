<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-lg"><div><div class="text-h4 page-title">Pedidos online</div><div class="page-subtitle">Control de solicitudes, pagos, preparación, entrega y evidencia.</div></div><q-btn outline color="cyan-4" icon="refresh" label="Actualizar" :loading="loading" @click="load" /></div>

    <div class="row q-col-gutter-md q-mb-lg">
      <div v-for="c in cards" :key="c.value" class="col-6 col-md-3"><q-card flat class="metric-card q-pa-md cursor-pointer" @click="statusFilter=c.value"><div class="metric-label">{{c.label}}</div><div class="metric-value">{{count(c.value)}}</div><div class="text-caption text-muted">{{c.hint}}</div></q-card></div>
    </div>

    <q-card flat class="panel">
      <q-card-section class="row q-col-gutter-sm items-center"><div class="col-12 col-md-7"><q-input v-model="search" outlined dense dark clearable label="Buscar pedido, cliente, teléfono o código"><template #prepend><q-icon name="search"/></template></q-input></div><div class="col-12 col-md-5"><q-select v-model="statusFilter" :options="statusFilterOptions" emit-value map-options outlined dense dark label="Estado" /></div></q-card-section>
      <q-table :rows="filtered" :columns="columns" row-key="id" dark flat class="table-dark" :loading="loading">
        <template #body-cell-acciones="p"><q-td :props="p"><q-btn flat round dense color="cyan-4" icon="visibility" @click="open(p.row)" /><q-btn flat round dense color="amber-4" icon="sync_alt" @click="openStatus(p.row)" /><q-btn flat round dense color="blue-3" icon="picture_as_pdf" @click="pdf(p.row)" /><q-btn v-if="p.row.evidencia_entrega" flat round dense color="green-4" icon="photo_camera" @click="viewEvidence(p.row)" /></q-td></template>
        <template #body-cell-Fecha="p"><q-td :props="p">{{ formatDate(p.row.Fecha) }}</q-td></template>
        <template #body-cell-entrega="p"><q-td :props="p"><q-chip dense outline color="cyan-4">{{ p.row.tipo_entrega || 'Recojo' }}</q-chip></q-td></template>
        <template #body-cell-Estado="p"><q-td :props="p"><q-chip dense :color="statusColor(p.row.Estado)" text-color="white">{{ p.row.Estado }}</q-chip></q-td></template>
        <template #body-cell-estado_operacion="p"><q-td :props="p"><q-chip dense :color="p.row.estado_operacion==='Finalizado'?'green-8':'blue-8'" text-color="white">{{p.row.estado_operacion||'Activo'}}</q-chip></q-td></template>
        <template #body-cell-EstadoPago="p"><q-td :props="p"><q-chip dense :color="paymentColor(p.row.EstadoPago)" text-color="white">{{ p.row.EstadoPago || 'Pendiente' }}</q-chip><div class="text-caption text-muted">{{ p.row.MetodoPago || p.row.metodo_pago || '-' }}</div></q-td></template>
        <template #body-cell-Total="p"><q-td :props="p" class="text-green-3 text-weight-bold">Bs {{ money(p.row.Total) }}</q-td></template>
      </q-table>
    </q-card>

    <q-dialog v-model="detailDialog">
      <q-card class="panel text-white detail-card">
        <q-card-section class="row items-start justify-between"><div><div class="text-caption text-muted">PEDIDO ONLINE #{{ detail?.id }}</div><div class="text-h5 text-weight-bold">{{ detail?.Nombre }} {{ detail?.Apellido }}</div><div class="text-caption text-cyan-3 q-mt-xs">{{ detail?.codigo_seguimiento }}</div></div><q-chip :color="statusColor(detail?.Estado)" text-color="white">{{ detail?.Estado }}</q-chip></q-card-section>
        <q-separator dark />
        <q-card-section>
          <div class="row q-col-gutter-md q-mb-md"><div class="col-12 col-sm-6"><div class="text-caption text-muted">Fecha</div><div>{{formatDate(detail?.Fecha)}}</div></div><div class="col-12 col-sm-6"><div class="text-caption text-muted">Teléfono</div><div>{{ detail?.Telefono || '-' }}</div></div><div class="col-12 col-sm-6"><div class="text-caption text-muted">Entrega</div><div>{{ detail?.tipo_entrega || '-' }}</div></div><div class="col-12 col-sm-6"><div class="text-caption text-muted">Pago</div><div>{{detail?.EstadoPago || 'Pendiente'}} · {{detail?.MetodoPago || detail?.metodo_pago || '-'}}</div></div><div class="col-12"><div class="text-caption text-muted">Dirección</div><div>{{ detail?.direccion_entrega || detail?.Direccion_envio || 'Recojo en tienda' }}</div></div><div v-if="detail?.notas_cliente" class="col-12"><div class="text-caption text-muted">Nota</div><div>{{ detail.notas_cliente }}</div></div></div>
          <q-card flat class="payment-review q-pa-md q-mb-md">
            <div class="row items-center justify-between q-col-gutter-md">
              <div class="col-12 col-md">
                <div class="text-weight-bold">Pago del pedido</div>
                <div class="text-caption text-grey-5">Estado: {{detail?.EstadoPago||'Pendiente'}} · Método: {{detail?.MetodoPago||detail?.metodo_pago||'QR'}}</div>
              </div>
              <div class="col-12 col-md-auto row q-gutter-sm">
                <q-btn v-if="detail?.ComprobantePago && detail?.pago_id" outline color="cyan-4" icon="open_in_new" label="Ver comprobante" @click="viewPaymentProof(detail)"/>
                <q-btn v-if="detail?.pago_id && detail?.EstadoPago==='Reportado'" color="green-7" icon="verified" label="Confirmar pago" :loading="verifyingPayment" @click="setPaymentStatus(detail,'Verificado')"/>
                <q-btn v-if="detail?.pago_id && detail?.EstadoPago==='Reportado'" outline color="red-4" icon="close" label="Rechazar" :loading="verifyingPayment" @click="setPaymentStatus(detail,'Rechazado')"/>
              </div>
            </div>
            <q-banner v-if="detail?.EstadoPago==='Reportado'" rounded class="q-mt-md bg-orange-10 text-white"><template #avatar><q-icon name="payments"/></template>Al confirmar el pago, el pedido pasará automáticamente a <b>Confirmado</b> y recién se reservará el stock.</q-banner>
          </q-card>
          <q-list bordered separator dark><q-item v-for="item in detail?.items || []" :key="item.id_producto"><q-item-section><q-item-label>{{ item.Nombre }}</q-item-label><q-item-label caption>{{ item.cantidad }} × Bs {{ money(item.precio_unitario || item.Precio) }}</q-item-label></q-item-section><q-item-section side class="text-green-3">Bs {{ money(item.subtotal || item.cantidad * (item.precio_unitario || item.Precio)) }}</q-item-section></q-item></q-list>
          <div class="row items-center justify-end q-gutter-sm q-mt-md"><q-btn v-if="detail?.evidencia_entrega" outline color="green-4" icon="photo_camera" label="Ver evidencia" @click="viewEvidence(detail)"/><div class="text-h6">Total: <span class="text-green-3">Bs {{ money(detail?.Total) }}</span></div></div>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md"><q-btn flat label="Cerrar" v-close-popup /><q-btn class="action-primary" icon="sync_alt" label="Cambiar estado" @click="detailDialog=false;openStatus(detail)" /></q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="statusDialog">
      <q-card class="panel text-white" style="width:520px;max-width:94vw">
        <q-card-section><div class="text-h6">Actualizar pedido #{{ selected?.id }}</div><div class="text-caption text-muted">{{ selected?.cliente || `${selected?.Nombre || ''} ${selected?.Apellido || ''}` }} · Pago: {{selected?.EstadoPago || 'Pendiente'}}</div></q-card-section>
        <q-card-section>
          <q-select v-model="newStatus" :options="statusOptions(selected)" outlined dark label="Nuevo estado" />
          <q-banner v-if="newStatus==='Confirmado' && selected?.EstadoPago!=='Verificado'" class="q-mt-md bg-orange-10 text-white" rounded><template #avatar><q-icon name="payments" /></template>El pedido no puede confirmarse hasta que el pago esté verificado.</q-banner>
          <q-banner v-if="newStatus==='Entregado'" class="q-mt-md bg-green-10 text-white" rounded><template #avatar><q-icon name="inventory_2" /></template>Al entregar se descuenta el stock físico reservado. Se exige pago verificado y evidencia fotográfica.</q-banner>
          <q-banner v-if="newStatus==='Cancelado'" class="q-mt-md bg-red-10 text-white" rounded><template #avatar><q-icon name="undo" /></template>Al cancelar solo se libera la reserva. No se agrega stock físico porque aún no hubo entrega.</q-banner>
          <q-file v-if="newStatus==='Entregado' && !selected?.evidencia_entrega" v-model="deliveryPhoto" accept="image/jpeg,image/png,image/webp" max-file-size="12582912" outlined dark label="Evidencia fotográfica de entrega *" class="q-mt-md"><template #prepend><q-icon name="photo_camera"/></template></q-file>
          <q-banner v-if="selected?.evidencia_entrega" rounded class="q-mt-md bg-blue-grey-10 text-grey-3"><template #avatar><q-icon name="photo_camera" color="green-4"/></template>Este pedido ya cuenta con evidencia fotográfica.</q-banner>
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
import { downloadPdf, openProtectedFile } from '../utils/download'

const $q=useQuasar()
const rows=ref([]),loading=ref(false),saving=ref(false),search=ref(''),statusFilter=ref('Todos'),detailDialog=ref(false),statusDialog=ref(false),detail=ref(null),selected=ref(null),newStatus=ref('Nuevo'),deliveryPhoto=ref(null),verifyingPayment=ref(false)
const money=v=>Number(v||0).toFixed(2)
const formatDate=v=>{if(!v)return '-';const [y,m,d]=String(v).slice(0,10).split('-');return y&&m&&d?`${d}/${m}/${y}`:v}
const columns=[
  {name:'acciones',label:'Acciones',field:'acciones',align:'center'},
  {name:'id',label:'#',field:'id',align:'left'},
  {name:'Fecha',label:'Fecha',field:'Fecha',align:'left'},
  {name:'cliente',label:'Cliente',field:'cliente',align:'left'},
  {name:'entrega',label:'Entrega',field:'tipo_entrega',align:'center'},
  {name:'Estado',label:'Pedido',field:'Estado',align:'center'},
  {name:'estado_operacion',label:'Operación',field:'estado_operacion',align:'center'},
  {name:'EstadoPago',label:'Pago',field:'EstadoPago',align:'center'},
  {name:'Total',label:'Total',field:'Total',align:'right'},
]
const cards=[{label:'Nuevos',value:'Nuevo',hint:'Pendientes de pago/confirmación'},{label:'Preparación',value:'Preparando',hint:'En proceso'},{label:'Listos',value:'Listo para entrega',hint:'Esperando recojo'},{label:'En camino',value:'En camino',hint:'Delivery activo'}]
const statusFilterOptions=['Todos','Nuevo','Confirmado','Preparando','Listo para entrega','En camino','Entregado','Cancelado'].map(x=>({label:x,value:x}))
const statusColor=s=>s==='Entregado'?'green-8':s==='Cancelado'?'red-8':s==='Nuevo'?'blue-8':s==='En camino'?'purple-8':'orange-8'
const paymentColor=s=>({Pendiente:'blue-grey-7',Reportado:'orange-8',Verificado:'green-8',Rechazado:'red-8',Reembolsado:'purple-8'}[s]||'blue-grey-7')
const count=status=>status==='Preparando'?rows.value.filter(r=>['Confirmado','Preparando'].includes(r.Estado)).length:rows.value.filter(r=>r.Estado===status).length
const filtered=computed(()=>{const q=search.value.trim().toLowerCase();return rows.value.filter(r=>{const text=`${r.id} ${r.cliente||''} ${r.Nombre||''} ${r.Apellido||''} ${r.Telefono||''} ${r.codigo_seguimiento||''}`.toLowerCase();return(!q||text.includes(q))&&(statusFilter.value==='Todos'||r.Estado===statusFilter.value)})})

const load=async()=>{loading.value=true;try{rows.value=(await api.get('/pedidos?canal=Online')).data||[]}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudieron cargar los pedidos online.'})}finally{loading.value=false}}
const open=async row=>{try{detail.value=(await api.get(`/pedidos/${row.id}`)).data;detailDialog.value=true}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo abrir el pedido.'})}}
const openStatus=row=>{selected.value=row;newStatus.value=row?.Estado||'Nuevo';deliveryPhoto.value=null;statusDialog.value=true}
const statusOptions=row=>{if(row?.Estado==='Entregado')return ['Entregado'];return row?.tipo_entrega==='Delivery'?['Nuevo','Confirmado','Preparando','En camino','Entregado','Cancelado']:['Nuevo','Confirmado','Preparando','Listo para entrega','Entregado','Cancelado']}
const saveStatus=async()=>{if(['Confirmado','Entregado'].includes(newStatus.value)&&selected.value?.EstadoPago!=='Verificado')return $q.notify({type:'warning',message:'Primero debes verificar el pago.'});if(newStatus.value==='Entregado'&&!selected.value?.evidencia_entrega&&!deliveryPhoto.value)return $q.notify({type:'warning',message:'Adjunta una fotografía como evidencia de entrega.'});saving.value=true;try{const fd=new FormData();fd.append('Estado',newStatus.value);fd.append('_method','PUT');if(deliveryPhoto.value)fd.append('evidencia_entrega',deliveryPhoto.value);await api.post(`/pedidos/${selected.value.id}`,fd);statusDialog.value=false;$q.notify({type:'positive',message:'Estado actualizado.'});await load();if(detail.value?.id===selected.value.id)detail.value=(await api.get(`/pedidos/${selected.value.id}`)).data}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo actualizar el pedido.'})}finally{saving.value=false}}
const pdf=async row=>{try{await downloadPdf(`/pedidos/${row.id}/pdf`,`pedido-online-${row.id}.pdf`)}catch{$q.notify({type:'negative',message:'No se pudo generar el PDF.'})}}
const viewEvidence=async row=>{try{await openProtectedFile(api,`/pedidos/${row.id}/evidencia-entrega`)}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo abrir la evidencia.'})}}

const viewPaymentProof=async row=>{try{await openProtectedFile(api,`/pagos/${row.pago_id}/comprobante`)}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo abrir el comprobante.'})}}
const setPaymentStatus=async(row,estado)=>{verifyingPayment.value=true;try{const{data}=await api.put(`/pagos/${row.pago_id}/estado`,{estado});$q.notify({type:'positive',message:data.message||'Pago actualizado.'});await load();detail.value=(await api.get(`/pedidos/${row.id}`)).data}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo actualizar el pago.'})}finally{verifyingPayment.value=false}}

onMounted(load)
</script>

<style scoped>.payment-review{background:#091725;border:1px solid #20364e;border-radius:12px}.detail-card{width:820px;max-width:96vw;border-radius:16px}</style>
