<template>
  <q-page class="account-page q-pa-md q-py-xl">
    <div class="account-wrap">
      <div class="row items-center justify-between q-mb-lg">
        <div><div class="text-h4 text-weight-bold text-white">Mi cuenta</div><div class="text-grey-5">Tus datos, pedidos, pagos y guías relacionadas.</div></div>
        <q-btn outline color="cyan-4" icon="logout" label="Cerrar sesión" @click="signOut" />
      </div>

      <div class="row q-col-gutter-lg">
        <div class="col-12 col-lg-4">
          <q-card flat class="panel text-white q-pa-md q-mb-lg">
            <div class="row items-center q-mb-md"><q-avatar color="blue-grey-9" text-color="cyan-3" icon="person"/><div class="q-ml-sm"><div class="text-h6 text-weight-bold">{{ profile.Nombre || 'Mi perfil' }} {{ profile.Apellido }}</div><div class="text-caption text-grey-5">Se carga automáticamente al comprar.</div></div></div>
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
            <q-card-section class="row items-center justify-between"><div><div class="text-h6 text-weight-bold">Notificaciones</div><div class="text-caption text-grey-5">Cambios de pedido y pago.</div></div><q-badge color="red" v-if="unread">{{unread}}</q-badge></q-card-section>
            <q-separator dark/>
            <q-list v-if="notifications.length" separator dark>
              <q-item v-for="n in notifications.slice(0,6)" :key="n.id"><q-item-section avatar><q-icon :name="n.tipo==='pago'?'payments':'local_shipping'" color="cyan-4"/></q-item-section><q-item-section><q-item-label :class="!n.leida_at?'text-weight-bold':''">{{n.titulo}}</q-item-label><q-item-label caption>{{n.mensaje}}</q-item-label></q-item-section></q-item>
            </q-list>
            <div v-else class="q-pa-md text-grey-5">Sin notificaciones.</div>
          </q-card>
        </div>

        <div class="col-12 col-lg-8">
          <q-card flat class="panel text-white">
            <q-tabs v-model="tab" dense align="left" active-color="cyan-3" indicator-color="cyan-4" class="text-grey-5">
              <q-tab name="pedidos" icon="shopping_bag" label="Mis pedidos"/>
              <q-tab name="pagos" icon="payments" label="Mis pagos"/>
              <q-tab name="guias" icon="school" label="Mis guías"/>
            </q-tabs>
            <q-separator dark/>
            <q-tab-panels v-model="tab" animated class="bg-transparent text-white">
              <q-tab-panel name="pedidos" class="q-pa-none">
                <q-list v-if="orders.length" separator dark>
                  <q-expansion-item v-for="p in orders" :key="p.id" expand-separator>
                    <template #header>
                      <q-item-section avatar><q-avatar color="blue-grey-9" text-color="cyan-3" icon="shopping_bag"/></q-item-section>
                      <q-item-section><q-item-label>Pedido #{{p.id}}</q-item-label><q-item-label caption>{{p.codigo_seguimiento||'Sin código'}} · {{dateText(p.Fecha)}} · {{p.metodo_pago||p.pago?.metodo||'Pago no definido'}}</q-item-label></q-item-section>
                      <q-item-section side><q-chip dense :color="statusColor(p.Estado)" text-color="white">{{p.Estado}}</q-chip><q-chip dense :color="paymentColor(p.pago?.estado)" text-color="white">Pago: {{p.pago?.estado||'Pendiente'}}</q-chip><div class="text-green-3 text-weight-bold">Bs {{money(p.Total)}}</div></q-item-section>
                    </template>
                    <q-card class="bg-transparent text-white q-pa-md">
                      <div v-for="item in p.items||[]" :key="`${p.id}-${item.id_producto}`" class="q-py-sm item-line"><div class="row justify-between"><span>{{item.Nombre}} × {{item.cantidad}}</span><span>Bs {{money(item.subtotal)}}</span></div><div v-if="item.capacitaciones?.length" class="row q-gutter-xs q-mt-xs"><q-btn v-for="g in item.capacitaciones" :key="g.id" flat dense color="cyan-4" icon="school" :label="g.titulo" :to="`/capacitacion/${g.id}`"/></div></div>
                      <div class="row q-gutter-sm q-mt-md"><q-btn outline color="cyan-4" icon="local_shipping" label="Seguimiento" :to="`/seguimiento/${p.codigo_seguimiento}`"/><q-btn v-if="canReportPayment(p)" outline color="amber-4" icon="payments" label="Reportar pago" @click="openPayment(p)"/></div>
                    </q-card>
                  </q-expansion-item>
                </q-list>
                <div v-else-if="!loading" class="q-pa-xl text-center text-grey-5"><q-icon name="shopping_bag" size="48px"/><div class="q-mt-sm">Todavía no tienes pedidos online.</div></div>
              </q-tab-panel>

              <q-tab-panel name="pagos">
                <div v-if="payments.length" class="q-gutter-md">
                  <q-card v-for="pg in payments" :key="pg.id" flat class="payment-card q-pa-md"><div class="row items-center justify-between"><div><div class="text-weight-bold">Pedido #{{pg.pedido_id}} · {{pg.metodo}}</div><div class="text-caption text-grey-5">{{pg.codigo_seguimiento}}</div></div><div class="text-right"><q-chip dense :color="paymentColor(pg.estado)" text-color="white">{{pg.estado}}</q-chip><div class="text-green-3 text-weight-bold">Bs {{money(pg.monto)}}</div></div></div><div v-if="pg.referencia" class="text-caption q-mt-sm">Referencia: {{pg.referencia}}</div><q-btn v-if="pg.comprobante" class="q-mt-sm" flat dense color="cyan-4" icon="open_in_new" label="Ver comprobante" :href="mediaUrl(pg.comprobante)" target="_blank"/></q-card>
                </div>
                <div v-else class="q-pa-xl text-center text-grey-5">Todavía no tienes pagos registrados.</div>
              </q-tab-panel>

              <q-tab-panel name="guias">
                <div v-if="guides.length" class="row q-col-gutter-md"><div v-for="g in guides" :key="g.id" class="col-12 col-md-6"><q-card flat class="guide-card q-pa-md full-height"><div class="row items-start no-wrap"><q-avatar color="cyan-10" text-color="cyan-2" icon="school"/><div class="q-ml-md"><div class="text-subtitle1 text-weight-bold">{{g.titulo}}</div><div class="text-caption text-grey-5 q-mt-xs">{{g.descripcion||'Guía relacionada con uno de tus productos.'}}</div><q-btn flat dense color="cyan-4" icon="menu_book" label="Abrir guía" class="q-mt-sm" :to="`/capacitacion/${g.id}`"/></div></div></q-card></div></div>
                <div v-else class="q-pa-xl text-center text-grey-5"><q-icon name="school" size="48px"/><div class="q-mt-sm">Tus productos todavía no tienen guías relacionadas.</div></div>
              </q-tab-panel>
            </q-tab-panels>
            <q-inner-loading :showing="loading"><q-spinner color="cyan-4" size="48px"/></q-inner-loading>
          </q-card>
        </div>
      </div>
    </div>

    <q-dialog v-model="paymentDialog" persistent>
      <q-card class="panel text-white" style="width:600px;max-width:95vw">
        <q-card-section><div class="text-h6">Reportar pago</div><div class="text-caption text-grey-5">Pedido #{{selectedOrder?.id}} · Bs {{money(selectedOrder?.Total)}}</div></q-card-section>
        <q-separator dark/>
        <q-card-section class="q-gutter-md">
          <q-select v-model="paymentForm.metodo" :options="paymentMethods" outlined dense dark label="Método de pago *"/>
          <q-input v-model="paymentForm.referencia" outlined dense dark label="Referencia / número de operación (opcional)"/>
          <q-file v-model="paymentForm.comprobante" outlined dense dark accept="image/jpeg,image/png,image/webp,application/pdf" max-file-size="8388608" label="Comprobante JPG, PNG, WEBP o PDF"><template #prepend><q-icon name="attach_file"/></template></q-file>
          <q-input v-model="paymentForm.nota" type="textarea" autogrow outlined dense dark label="Nota (opcional)"/>
          <q-banner rounded class="payment-info"><template #avatar><q-icon name="info" color="cyan-4"/></template>Al reportar QR o transferencia, el personal recibirá una notificación para verificar tu pago.</q-banner>
        </q-card-section>
        <q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup/><q-btn class="action-primary" icon="send" label="Reportar pago" :loading="reporting" @click="reportPayment"/></q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useRouter } from 'vue-router'
import { clientApi } from '../services/api'
import { authState, logoutClient, refreshClientSession } from '../services/auth'
import { mediaUrl } from '../utils/media'

const $q=useQuasar(),router=useRouter(),tab=ref('pedidos'),orders=ref([]),payments=ref([]),notifications=ref([]),unread=ref(0),loading=ref(false),saving=ref(false),paymentDialog=ref(false),selectedOrder=ref(null),reporting=ref(false)
const profile=reactive({Nombre:'',Apellido:'',Telefono:'',Direccion_envio:'',Email:''})
const paymentForm=reactive({metodo:'QR',referencia:'',comprobante:null,nota:''}),paymentMethods=['QR','Transferencia','Efectivo']
const money=v=>Number(v||0).toFixed(2),dateText=v=>v?new Date(v).toLocaleDateString():'-'
const statusColor=s=>({Nuevo:'blue-8',Confirmado:'indigo-7',Preparando:'orange-8','Listo para entrega':'purple-7','En camino':'deep-purple-7',Entregado:'green-8',Cancelado:'red-8'}[s]||'blue-grey-7')
const paymentColor=s=>({Pendiente:'blue-grey-7',Reportado:'orange-8',Verificado:'green-8',Rechazado:'red-8',Reembolsado:'purple-8'}[s]||'blue-grey-7')
const guides=computed(()=>{const map=new Map();for(const p of orders.value)for(const item of p.items||[])for(const g of item.capacitaciones||[])map.set(g.id,g);return [...map.values()]})
const fill=()=>{const c=authState.clientProfile||{};profile.Nombre=c.Nombre||'';profile.Apellido=c.Apellido||'';profile.Telefono=c.Telefono||'';profile.Direccion_envio=c.Direccion_envio||'';profile.Email=c.Email||authState.clientUser?.email||''}
const load=async()=>{loading.value=true;try{await refreshClientSession();fill();const[o,p,n]=await Promise.all([clientApi.get('/auth/cliente/pedidos'),clientApi.get('/auth/cliente/pagos'),clientApi.get('/notificaciones')]);orders.value=o.data.pedidos||[];payments.value=p.data.pagos||[];notifications.value=n.data.notificaciones||[];unread.value=n.data.no_leidas||0}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo cargar tu cuenta.'})}finally{loading.value=false}}
const saveProfile=async()=>{saving.value=true;try{const{data}=await clientApi.put('/auth/cliente/perfil',{...profile,email:profile.Email});authState.clientProfile=data.cliente;authState.clientUser=data.user;localStorage.setItem('robokit_client_profile',JSON.stringify(data.cliente));localStorage.setItem('robokit_client_user',JSON.stringify(data.user));$q.notify({type:'positive',message:'Tus datos quedaron actualizados.'})}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudieron guardar tus datos.'})}finally{saving.value=false}}
const canReportPayment=p=>p?.Estado!=='Cancelado'&&p?.pago?.estado!=='Verificado'&&p?.pago?.estado!=='Reembolsado'
const openPayment=p=>{selectedOrder.value=p;paymentForm.metodo=p?.pago?.metodo||p?.metodo_pago||'QR';paymentForm.referencia=p?.pago?.referencia||'';paymentForm.comprobante=null;paymentForm.nota=p?.pago?.nota||'';paymentDialog.value=true}
const reportPayment=async()=>{if(!selectedOrder.value)return;reporting.value=true;try{const fd=new FormData();fd.append('metodo',paymentForm.metodo);if(paymentForm.referencia)fd.append('referencia',paymentForm.referencia);if(paymentForm.nota)fd.append('nota',paymentForm.nota);if(paymentForm.comprobante)fd.append('comprobante',paymentForm.comprobante);const{data}=await clientApi.post(`/auth/cliente/pedidos/${selectedOrder.value.id}/pago`,fd);paymentDialog.value=false;$q.notify({type:'positive',message:data.message||'Pago reportado.'});await load();tab.value='pagos'}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo reportar el pago.'})}finally{reporting.value=false}}
const signOut=async()=>{await logoutClient();router.replace('/tienda')}
onMounted(load)
</script>

<style scoped>
.account-page{background:#07101d;min-height:80vh}.account-wrap{max-width:1260px;margin:0 auto}.panel{background:#0d1828;border:1px solid #1d3148;border-radius:16px}.payment-card,.guide-card{background:#0a1524;border:1px solid #1d3148;border-radius:14px}.payment-info{background:#0a2232;border:1px solid rgba(34,211,238,.24);color:#c9e9f2}.item-line{border-bottom:1px solid rgba(148,163,184,.12)}
</style>
