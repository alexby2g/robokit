<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-lg">
      <div><div class="text-h4 page-title">Compras</div><div class="page-subtitle">Cada compra aumenta el stock y deja un movimiento de inventario.</div></div>
      <q-btn class="action-primary" icon="add" label="Nueva compra" @click="openNew" />
    </div>

    <q-card flat class="panel">
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="col-12 col-md-8"><q-input v-model="search" outlined dense dark clearable label="Buscar proveedor o documento"><template #prepend><q-icon name="search" /></template></q-input></div>
        <div class="col-12 col-md-4 text-right"><q-btn flat color="cyan-4" icon="refresh" label="Actualizar" :loading="loading" @click="load" /></div>
      </q-card-section>
      <q-table :rows="filtered" :columns="columns" row-key="id" dark flat class="table-dark" :loading="loading">
        <template #body-cell-total="p"><q-td :props="p" class="text-amber-3 text-weight-bold">Bs {{ money(p.row.total) }}</q-td></template>
        <template #body-cell-acciones="p"><q-td :props="p"><q-btn flat round dense color="cyan-4" icon="picture_as_pdf" @click="pdf(p.row)" /><q-btn flat round dense color="grey-4" icon="visibility" @click="detail(p.row)" /></q-td></template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog" persistent>
      <q-card class="panel text-white" style="width:900px;max-width:96vw">
        <q-card-section class="row items-center justify-between"><div><div class="text-h6 text-weight-bold">Registrar compra</div><div class="text-caption text-muted">El stock se actualizará automáticamente.</div></div><q-btn flat round icon="close" v-close-popup /></q-card-section>
        <q-separator dark />
        <q-card-section>
          <div class="row q-col-gutter-md">
            <div class="col-12 col-md-5"><q-input v-model="form.proveedor" outlined dense dark label="Proveedor *" /></div>
            <div class="col-12 col-md-3"><q-input v-model="form.nro_documento" outlined dense dark label="N.º documento" /></div>
            <div class="col-12 col-md-4"><q-input v-model="form.fecha" outlined dense dark type="date" stack-label label="Fecha *" /></div>
          </div>
          <q-separator dark class="q-my-md" />
          <div class="row items-center justify-between q-mb-sm"><div class="text-subtitle1 text-weight-bold">Productos</div><q-btn outline color="cyan-4" icon="add" label="Agregar fila" @click="addItem" /></div>
          <div v-for="(item, idx) in form.items" :key="idx" class="row q-col-gutter-sm items-center q-mb-sm">
            <div class="col-12 col-md-5"><q-select v-model="item.id_producto" :options="products" option-value="id" option-label="Nombre" emit-value map-options outlined dense dark label="Producto *" /></div>
            <div class="col-4 col-md-2"><q-input v-model.number="item.cantidad" type="number" min="1" outlined dense dark label="Cantidad" /></div>
            <div class="col-5 col-md-3"><q-input v-model.number="item.costo_unitario" type="number" min="0" step="0.01" outlined dense dark label="Costo unitario" prefix="Bs" /></div>
            <div class="col-3 col-md-2 row items-center justify-end"><span class="q-mr-sm text-amber-3">{{ money(item.cantidad * item.costo_unitario) }}</span><q-btn flat round dense color="red-4" icon="delete" @click="removeItem(idx)" /></div>
          </div>
          <q-input v-model="form.observacion" outlined dense dark type="textarea" rows="2" label="Observación" class="q-mt-md" />
          <div class="text-right text-h6 q-mt-md">Total: <span class="text-amber-3">Bs {{ money(total) }}</span></div>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md"><q-btn flat label="Cancelar" v-close-popup /><q-btn class="action-primary" label="Guardar compra" :loading="saving" @click="save" /></q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="detailDialog"><q-card class="panel text-white" style="width:700px;max-width:94vw"><q-card-section class="row items-center justify-between"><div class="text-h6">Compra #{{ selected?.id }}</div><q-btn flat round icon="close" v-close-popup /></q-card-section><q-separator dark/><q-card-section><div><b>Proveedor:</b> {{ selected?.proveedor }}</div><div><b>Documento:</b> {{ selected?.nro_documento || '-' }}</div><div><b>Fecha:</b> {{ selected?.fecha }}</div><div class="q-mt-md"><q-list separator dark><q-item v-for="i in selected?.items || selected?.productos || []" :key="i.id"><q-item-section><q-item-label>{{ i.Nombre || i.producto?.Nombre || `Producto #${i.id_producto}` }}</q-item-label><q-item-label caption>{{ i.cantidad }} × Bs {{ money(i.costo_unitario) }}</q-item-label></q-item-section><q-item-section side>Bs {{ money(i.subtotal || i.cantidad * i.costo_unitario) }}</q-item-section></q-item></q-list></div></q-card-section></q-card></q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import api from '../services/api'
import { downloadPdf } from '../utils/download'
const $q = useQuasar()
const rows=ref([]), products=ref([]), loading=ref(false), saving=ref(false), dialog=ref(false), detailDialog=ref(false), selected=ref(null), search=ref('')
const columns=[{name:'id',label:'#',field:'id',align:'left'},{name:'fecha',label:'Fecha',field:'fecha',align:'left'},{name:'proveedor',label:'Proveedor',field:'proveedor',align:'left'},{name:'nro_documento',label:'Documento',field:'nro_documento',align:'left'},{name:'total',label:'Total',field:'total',align:'right'},{name:'acciones',label:'Acciones',field:'acciones',align:'center'}]
const today=()=>new Date().toISOString().slice(0,10)
const newForm=()=>({proveedor:'',nro_documento:'',fecha:today(),observacion:'',items:[{id_producto:null,cantidad:1,costo_unitario:0}]})
const form=ref(newForm())
const money=(v)=>Number(v||0).toFixed(2)
const total=computed(()=>form.value.items.reduce((s,i)=>s+Number(i.cantidad||0)*Number(i.costo_unitario||0),0))
const filtered=computed(()=>{const q=search.value.toLowerCase().trim();return !q?rows.value:rows.value.filter(x=>`${x.proveedor||''} ${x.nro_documento||''}`.toLowerCase().includes(q))})
const load=async()=>{loading.value=true;try{const [a,b]=await Promise.all([api.get('/compras'),api.get('/productos')]);rows.value=a.data.compras||a.data.data||a.data||[];products.value=b.data.productos||[]}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudieron cargar las compras'})}finally{loading.value=false}}
const openNew=()=>{form.value=newForm();dialog.value=true}; const addItem=()=>form.value.items.push({id_producto:null,cantidad:1,costo_unitario:0}); const removeItem=(i)=>{if(form.value.items.length>1)form.value.items.splice(i,1)}
const save=async()=>{if(!form.value.proveedor||!form.value.fecha||form.value.items.some(i=>!i.id_producto||Number(i.cantidad)<1)){return $q.notify({type:'warning',message:'Completa proveedor, fecha y productos.'})}saving.value=true;try{await api.post('/compras',form.value);dialog.value=false;$q.notify({type:'positive',message:'Compra registrada y stock actualizado.'});await load()}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo registrar la compra'})}finally{saving.value=false}}
const pdf=async(row)=>{try{await downloadPdf(`/compras/${row.id}/pdf`,`compra-${row.id}.pdf`)}catch{$q.notify({type:'negative',message:'No se pudo generar el PDF.'})}}
const detail=async(row)=>{selected.value=row;try{const r=await api.get(`/compras/${row.id}`);selected.value=r.data.compra||r.data.data||r.data}catch{selected.value=row}detailDialog.value=true}
onMounted(load)
</script>
