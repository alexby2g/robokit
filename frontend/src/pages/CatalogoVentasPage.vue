<template>
  <q-page class="store-page q-pa-md q-pb-xl">
    <div class="store-wrap">
      <section class="hero q-pa-lg q-mb-lg">
        <div class="row items-center q-col-gutter-lg">
          <div class="col-12 col-md-8">
            <q-chip dense outline color="cyan-4" text-color="cyan-3" icon="bolt">CATÁLOGO EN LÍNEA</q-chip>
            <div class="hero-title q-mt-sm">{{ config.hero_titulo || 'Robótica lista para tu próximo proyecto.' }}</div>
            <div class="hero-copy q-mt-sm">{{ config.hero_texto || 'Explora kits, placas, sensores y componentes.' }}</div>
          </div>
          <div class="col-12 col-md-4 row justify-md-end q-gutter-sm">
            <q-btn v-if="clientLogged" outline color="cyan-4" icon="person" label="Mi cuenta" to="/mi-cuenta" />
            <q-btn v-else-if="adminLogged" outline color="cyan-4" icon="dashboard" label="Ir al panel" to="/admin/dashboard" />
            <q-btn v-else outline color="cyan-4" icon="login" label="Iniciar sesión" to="/login" />
            <q-btn class="action-primary" icon="shopping_cart" :label="`Carrito (${cartCount})`" @click="cartDialog = true" />
          </div>
        </div>
      </section>

      <div v-if="featuredProducts.length" class="q-mb-xl">
        <div class="row items-center q-mb-md"><q-icon name="star" color="purple-4" size="28px"/><div class="text-h5 text-weight-bold q-ml-sm">Destacados</div></div>
        <div class="row q-col-gutter-md">
          <div v-for="p in featuredProducts" :key="`featured-${p.id}`" class="col-12 col-sm-6 col-lg-3"><product-card :product="p" @view="openDetail" @add="add" /></div>
        </div>
      </div>

      <div class="row q-col-gutter-md q-mb-lg">
        <div class="col-12 col-md-8"><q-input v-model="search" outlined dark clearable debounce="150" label="Buscar productos, kits o componentes"><template #prepend><q-icon name="search" /></template></q-input></div>
        <div class="col-12 col-md-4"><q-select v-model="category" :options="categoryOptions" emit-value map-options outlined dark label="Categoría" /></div>
      </div>

      <div v-if="loading" class="flex flex-center q-pa-xl"><q-spinner-orbit color="cyan-4" size="58px" /></div>
      <div v-else-if="filtered.length" class="row q-col-gutter-md">
        <div v-for="p in filtered" :key="p.id" class="col-12 col-sm-6 col-lg-3"><product-card :product="p" @view="openDetail" @add="add" /></div>
      </div>
      <q-card v-else flat class="empty-state q-pa-xl text-center"><q-icon name="inventory_2" size="54px" color="blue-grey-5" /><div class="text-h6 q-mt-md">No encontramos productos con esos filtros.</div></q-card>
    </div>

    <q-page-sticky position="bottom-right" :offset="[20,20]"><q-btn round size="lg" class="action-primary shadow-8" icon="shopping_cart" @click="cartDialog=true"><q-badge v-if="cartCount" floating color="red">{{cartCount}}</q-badge></q-btn></q-page-sticky>

    <q-dialog v-model="detailDialog">
      <q-card class="panel text-white detail-card">
        <q-carousel v-if="selectedImages.length" v-model="slide" animated arrows navigation infinite height="310px" class="bg-black">
          <q-carousel-slide v-for="img in selectedImages" :key="img.id" :name="img.id" :img-src="mediaUrl(img.ruta)" class="contain-slide" />
        </q-carousel>
        <div v-else class="bg-black flex flex-center" style="height:280px"><q-icon name="smart_toy" size="72px" color="cyan-4"/></div>
        <q-card-section>
          <div class="row items-start justify-between"><div><div class="text-h5 text-weight-bold">{{selected?.Nombre}}</div><div class="text-caption text-cyan-3">{{selected?.categoria_nombre}}</div></div><q-chip v-if="selected?.destacado" color="purple-8" text-color="white" icon="star">Destacado</q-chip></div>
          <div class="row items-center q-gutter-sm q-mt-sm"><div class="text-h5 text-green-3">Bs {{money(selected?.Precio)}}</div><div v-if="selected?.precio_anterior" class="old-price text-grey-5">Bs {{money(selected.precio_anterior)}}</div></div>
          <div class="text-body2 text-grey-4 q-mt-md pre-line">{{selected?.Descripcion||'Equipo de robótica disponible para pedido.'}}</div>
          <q-chip v-if="config.mostrar_stock!==false" class="q-mt-md" :color="availableStock(selected)>0?'green-10':'red-9'" text-color="white">{{availableStock(selected)}} unidades disponibles</q-chip>
          <q-banner v-if="selected?.capacitaciones?.length" rounded class="guide-banner q-mt-md">
            <template #avatar><q-icon name="school" color="cyan-4" /></template>
            <div class="text-weight-bold">¿Necesitas ayuda para usar este producto?</div>
            <div class="text-caption text-grey-4 q-mb-sm">Tiene {{ selected.capacitaciones.length }} guía{{ selected.capacitaciones.length === 1 ? '' : 's' }} de capacitación disponible{{ selected.capacitaciones.length === 1 ? '' : 's' }}.</div>
            <div class="row q-gutter-xs"><q-btn v-for="g in selected.capacitaciones" :key="g.id" dense outline color="cyan-4" icon="menu_book" :label="g.titulo" @click="detailDialog=false;router.push(`/capacitacion/${g.id}`)" /></div>
          </q-banner>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md"><q-btn flat label="Cerrar" v-close-popup/><q-btn class="action-primary" icon="add_shopping_cart" label="Agregar al carrito" :disable="availableStock(selected)<=0" @click="add(selected);detailDialog=false"/></q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="cartDialog" persistent>
      <q-card class="panel text-white cart-card">
        <q-card-section class="row items-center justify-between"><div><div class="text-h6">Tu pedido</div><div class="text-caption text-muted">Revisa los productos y solicita tu pedido.</div></div><q-btn flat round icon="close" v-close-popup/></q-card-section>
        <q-separator dark/>
        <q-card-section>
          <q-list v-if="cart.length" separator dark>
            <q-item v-for="i in cart" :key="i.id_producto"><q-item-section><q-item-label>{{i.Nombre}}</q-item-label><q-item-label caption>Bs {{money(i.precio_unitario)}} c/u</q-item-label></q-item-section><q-item-section side><div class="row items-center"><q-btn flat round dense icon="remove" @click="change(i,-1)"/><span class="q-mx-sm text-white">{{i.cantidad}}</span><q-btn flat round dense icon="add" @click="change(i,1)"/><q-btn flat round dense color="red-4" icon="delete" @click="del(i)"/></div></q-item-section></q-item>
          </q-list>
          <div v-else class="q-pa-lg text-center text-muted">Tu carrito está vacío.</div>
          <div class="text-right text-h6 q-mt-md">Total: <span class="text-green-3">Bs {{money(total)}}</span></div>
          <q-separator dark class="q-my-md"/>

          <q-banner v-if="clientLogged" class="account-banner q-mb-md" rounded><template #avatar><q-icon name="verified_user" color="green-4"/></template><div class="text-weight-bold">Comprando como {{clientName}}</div><div class="text-caption">Tus datos ya están cargados. Puedes corregirlos aquí y se guardarán en tu cuenta al confirmar.</div></q-banner>
          <div class="row items-center justify-between q-mb-sm"><div class="text-subtitle1 text-weight-bold">{{ clientLogged ? 'Tus datos' : 'Datos para el pedido' }}</div><q-btn v-if="!clientLogged" flat dense color="cyan-4" label="¿Ya tienes cuenta? Iniciar sesión" to="/login"/></div>
          <div class="row q-col-gutter-md">
            <div class="col-12 col-md-6"><q-input v-model="customer.Nombre" outlined dense dark label="Nombre *" :error="Boolean(checkoutErrors.Nombre)" :error-message="checkoutErrors.Nombre" @update:model-value="clearCheckoutError('Nombre')"/></div>
            <div class="col-12 col-md-6"><q-input v-model="customer.Apellido" outlined dense dark label="Apellido *" :error="Boolean(checkoutErrors.Apellido)" :error-message="checkoutErrors.Apellido" @update:model-value="clearCheckoutError('Apellido')"/></div>
            <div class="col-12 col-md-6"><q-input v-model="customer.Telefono" outlined dense dark label="Teléfono / WhatsApp *" :error="Boolean(checkoutErrors.Telefono)" :error-message="checkoutErrors.Telefono" @update:model-value="clearCheckoutError('Telefono')"/></div>
            <div v-if="clientLogged" class="col-12 col-md-6"><q-input v-model="customer.Email" outlined dense dark type="email" label="Correo *"/></div>
          </div>

          <div class="row q-col-gutter-md q-mt-sm">
            <div class="col-12 col-md-6"><q-select v-model="checkout.tipo_entrega" :options="deliveryOptions" outlined dense dark label="Forma de entrega *" :error="Boolean(checkoutErrors.tipo_entrega)" :error-message="checkoutErrors.tipo_entrega" @update:model-value="clearCheckoutError('tipo_entrega')"/></div>
            <div class="col-12 col-md-6">
              <q-card flat class="payment-box q-pa-md">
                <div class="row items-center no-wrap q-gutter-md">
                  <img src="/yape-qr.png" alt="QR Yape" class="payment-qr" />
                  <div><div class="text-weight-bold">Pago por QR / Yape</div><div class="text-caption text-grey-4">Solicita el pedido y luego adjunta el comprobante desde Mis pedidos.</div></div>
                </div>
              </q-card>
            </div>
            <div v-if="checkout.tipo_entrega==='Delivery'" class="col-12"><q-input v-model="checkout.direccion_entrega" outlined dense dark label="Dirección / referencia para delivery *" :error="Boolean(checkoutErrors.direccion_entrega)" :error-message="checkoutErrors.direccion_entrega" @update:model-value="clearCheckoutError('direccion_entrega')"/></div>
            <div class="col-12"><q-input v-model="checkout.notas_cliente" type="textarea" autogrow outlined dense dark label="Nota para el pedido (opcional)"/></div>
          </div>
          <q-banner v-if="clientLogged" class="q-mt-md info-banner" rounded><template #avatar><q-icon name="payments" color="amber-4"/></template>El pedido queda como solicitud. Recién pasará a <b>Confirmado</b> cuando adjuntes el comprobante y el personal verifique el pago.</q-banner>
          <q-banner class="q-mt-md info-banner" rounded><template #avatar><q-icon name="inventory_2" color="cyan-4"/></template>Solicitar el pedido no descuenta stock público. La reserva se realiza cuando el pago se verifica y el pedido queda confirmado.</q-banner>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md"><q-btn flat label="Seguir comprando" v-close-popup/><q-btn class="action-primary" icon="send" label="Solicitar pedido" :disable="!cart.length" :loading="sending" @click="send"/></q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="success" persistent><q-card class="panel text-white text-center q-pa-lg success-card"><q-icon name="check_circle" color="green-4" size="68px"/><div class="text-h5 q-mt-md">Solicitud recibida</div><div class="text-muted q-mt-sm">Pedido #{{orderId}}</div><div class="tracking-code q-mt-md">{{trackingCode}}</div><div class="text-caption text-muted q-mt-sm">{{clientLogged?'La solicitud quedó guardada en Mis pedidos. Adjunta allí el comprobante para que pueda ser confirmada.':'Guarda este código para consultar el estado.'}}</div><div class="row q-gutter-sm justify-center q-mt-lg"><q-btn outline color="cyan-4" label="Seguir comprando" @click="success=false"/><q-btn v-if="clientLogged" class="action-primary" icon="payments" label="Ir a mis pedidos" @click="success=false;router.push('/mi-cuenta')"/><q-btn v-else class="action-primary" icon="local_shipping" label="Ver estado" @click="goTracking"/></div></q-card></q-dialog>
  </q-page>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref, watch } from 'vue'
import { useQuasar, QBadge, QBtn, QCard, QCardActions, QCardSection, QChip, QImg } from 'quasar'
import { useRouter } from 'vue-router'
import { clientApi } from '../services/api'
import { authState, isAdminLogged, isClientLogged, refreshClientSession } from '../services/auth'
import { mediaUrl, productImage } from '../utils/media'

const $q=useQuasar(),router=useRouter();const products=ref([]),categories=ref([]),config=reactive({mostrar_stock:true,delivery_habilitado:true,recojo_habilitado:true});const cart=ref(JSON.parse(localStorage.getItem('robokit-cart')||'[]'));const search=ref(''),category=ref('all'),loading=ref(false),cartDialog=ref(false),detailDialog=ref(false),selected=ref(null),slide=ref(null),sending=ref(false),success=ref(false),orderId=ref(null),trackingCode=ref('');const customer=reactive({Nombre:'',Apellido:'',Telefono:'',Email:''});const checkout=reactive({tipo_entrega:'Recojo',metodo_pago:'QR',direccion_entrega:'',notas_cliente:''});const checkoutErrors=reactive({Nombre:'',Apellido:'',Telefono:'',tipo_entrega:'',direccion_entrega:'',general:''})
const money=v=>Number(v||0).toFixed(2),availableStock=p=>Number(p?.Disponible??p?.Stock??0);const clientLogged=computed(()=>isClientLogged.value);const adminLogged=computed(()=>isAdminLogged.value);const clientName=computed(()=>authState.clientUser?.name||'cliente');const cartCount=computed(()=>cart.value.reduce((s,i)=>s+Number(i.cantidad||0),0));const total=computed(()=>cart.value.reduce((s,i)=>s+Number(i.cantidad||0)*Number(i.precio_unitario||0),0));const selectedImages=computed(()=>selected.value?.imagenes||[]);const categoryOptions=computed(()=>[{label:'Todas las categorías',value:'all'},...categories.value.map(c=>({label:c.Nombre,value:c.id}))]);const deliveryOptions=computed(()=>[...(config.recojo_habilitado!==false?['Recojo']:[]),...(config.delivery_habilitado!==false?['Delivery']:[])]);const featuredProducts=computed(()=>products.value.filter(p=>p.destacado&&availableStock(p)>0).slice(0,4));const filtered=computed(()=>{const q=search.value.trim().toLowerCase();return products.value.filter(p=>{if(availableStock(p)<=0)return false;const t=`${p.Nombre||''} ${p.Descripcion||''}`.toLowerCase();return(!q||t.includes(q))&&(category.value==='all'||Number(p.id_categoria)===Number(category.value))})})
watch(cart,v=>localStorage.setItem('robokit-cart',JSON.stringify(v)),{deep:true});watch(deliveryOptions,(opts)=>{if(!opts.includes(checkout.tipo_entrega))checkout.tipo_entrega=opts[0]||''},{immediate:true})
const fillClient=()=>{const c=authState.clientProfile||{};customer.Nombre=c.Nombre||'';customer.Apellido=c.Apellido||'';customer.Telefono=c.Telefono||'';customer.Email=c.Email||authState.clientUser?.email||'';checkout.direccion_entrega=c.Direccion_envio||checkout.direccion_entrega||''};const load=async()=>{loading.value=true;try{const[p,c,cf]=await Promise.all([clientApi.get('/tienda/productos'),clientApi.get('/tienda/categorias'),clientApi.get('/tienda/config')]);products.value=p.data.productos||[];categories.value=c.data.categorias||[];Object.assign(config,cf.data.config||{});if(clientLogged.value){try{await refreshClientSession();fillClient()}catch{/* interceptor gestiona token inválido */}}}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo cargar el catálogo.'})}finally{loading.value=false}}
const openDetail=p=>{selected.value=p;slide.value=p?.imagenes?.[0]?.id||null;detailDialog.value=true};const add=p=>{if(!p||availableStock(p)<=0)return;const f=cart.value.find(i=>i.id_producto===p.id);if(f){if(f.cantidad<availableStock(p))f.cantidad++}else cart.value.push({id_producto:p.id,Nombre:p.Nombre,cantidad:1,precio_unitario:Number(p.Precio),stock:availableStock(p)});$q.notify({type:'positive',message:'Producto agregado al carrito.'})};const change=(i,d)=>{i.cantidad=Math.max(1,Math.min(i.stock,i.cantidad+d))};const del=i=>{cart.value=cart.value.filter(x=>x!==i)}
const clearCheckoutError=(field)=>{checkoutErrors[field]='';checkoutErrors.general=''};const validateCheckout=()=>{Object.keys(checkoutErrors).forEach(k=>checkoutErrors[k]='');if(!customer.Nombre.trim())checkoutErrors.Nombre='El nombre es obligatorio.';if(!customer.Apellido.trim())checkoutErrors.Apellido='El apellido es obligatorio.';if(!customer.Telefono.trim())checkoutErrors.Telefono='El teléfono o WhatsApp es obligatorio.';if(!checkout.tipo_entrega)checkoutErrors.tipo_entrega='Debes seleccionar una forma de entrega.';if(checkout.tipo_entrega==='Delivery'&&!checkout.direccion_entrega.trim())checkoutErrors.direccion_entrega='La dirección es obligatoria para delivery.';return !Object.entries(checkoutErrors).some(([k,v])=>k!=='general'&&Boolean(v))};const syncProfile=async()=>{if(!clientLogged.value)return;const{data}=await clientApi.put('/auth/cliente/perfil',{Nombre:customer.Nombre,Apellido:customer.Apellido,Telefono:customer.Telefono,Direccion_envio:checkout.direccion_entrega||authState.clientProfile?.Direccion_envio||null,email:customer.Email});authState.clientProfile=data.cliente;authState.clientUser=data.user;localStorage.setItem('robokit_client_profile',JSON.stringify(data.cliente));localStorage.setItem('robokit_client_user',JSON.stringify(data.user))};const send=async()=>{if(!validateCheckout())return;sending.value=true;try{if(clientLogged.value)await syncProfile();const payload={tipo_entrega:checkout.tipo_entrega,metodo_pago:checkout.metodo_pago,direccion_entrega:checkout.direccion_entrega,notas_cliente:checkout.notas_cliente,items:cart.value.map(({id_producto,cantidad})=>({id_producto,cantidad}))};if(!clientLogged.value)payload.cliente={Nombre:customer.Nombre,Apellido:customer.Apellido,Telefono:customer.Telefono};const{data}=await clientApi.post('/tienda/pedidos',payload);orderId.value=data.id||data.pedido?.id;trackingCode.value=data.codigo_seguimiento||data.pedido?.codigo||'';cart.value=[];cartDialog.value=false;success.value=true;await load()}catch(e){const ve=e.validationErrors||{};checkoutErrors.Nombre=ve['cliente.Nombre']||ve.Nombre||'';checkoutErrors.Apellido=ve['cliente.Apellido']||ve.Apellido||'';checkoutErrors.Telefono=ve['cliente.Telefono']||ve.Telefono||'';checkoutErrors.tipo_entrega=ve.tipo_entrega||'';checkoutErrors.direccion_entrega=ve.direccion_entrega||'';if(!checkoutErrors.Nombre&&!checkoutErrors.Apellido&&!checkoutErrors.Telefono&&!checkoutErrors.tipo_entrega&&!checkoutErrors.direccion_entrega)$q.notify({type:'negative',message:e.userMessage||'No se pudo registrar el pedido.'})}finally{sending.value=false}}
const goTracking=()=>{success.value=false;router.push(`/seguimiento/${encodeURIComponent(trackingCode.value)}`)};onMounted(load)

const ProductCard=defineComponent({name:'ProductCard',props:{product:{type:Object,required:true}},emits:['view','add'],setup(props,{emit}){return()=>h(QCard,{flat:true,class:'product-card full-height column'},()=>[h('div',{class:'product-image cursor-pointer',onClick:()=>emit('view',props.product)},[h(QImg,{src:productImage(props.product),height:'210px',fit:'contain',class:'bg-black'}),props.product.destacado?h(QBadge,{class:'stock-badge',color:'purple-8'},()=> 'Destacado'):null,props.product.capacitaciones?.length?h(QBadge,{class:'guide-badge',color:'cyan-9'},()=> 'Guía disponible'):null]),h(QCardSection,{class:'col'},()=>[h('div',{class:'text-subtitle1 text-weight-bold ellipsis-2-lines'},props.product.Nombre),h('div',{class:'text-caption text-muted q-mt-xs ellipsis-2-lines'},props.product.Descripcion||'Equipo y componente de robótica.'),h('div',{class:'row items-end justify-between q-mt-md'},[h('div',{},[props.product.precio_anterior?h('div',{class:'text-caption text-grey-6 old-price'},`Bs ${money(props.product.precio_anterior)}`):null,h('div',{class:'text-h6 text-green-3'},`Bs ${money(props.product.Precio)}`)]),config.mostrar_stock!==false?h(QChip,{dense:true,color:availableStock(props.product)<=5?'orange-10':'green-10',textColor:'white'},()=>`${availableStock(props.product)} disp.`):null])]),h(QCardActions,{class:'q-pa-md q-pt-none row q-gutter-sm'},()=>[h(QBtn,{outline:true,color:'cyan-4',icon:'visibility',label:'Ver',class:'col',onClick:()=>emit('view',props.product)}),h(QBtn,{class:'action-primary col',icon:'add_shopping_cart',label:'Agregar',disable:availableStock(props.product)<=0,onClick:()=>emit('add',props.product)})])])}})
</script>

<style scoped>
.store-page{background:#07101d;color:#e8f1fb;min-height:100vh}.store-wrap{max-width:1320px;margin:0 auto}.hero{background:radial-gradient(circle at 85% 20%,rgba(14,165,233,.2),transparent 35%),linear-gradient(135deg,#0d1d31,#0a1422);border:1px solid #1d3853;border-radius:20px}.hero-title{font-size:clamp(2rem,5vw,3.4rem);line-height:1.02;font-weight:900;max-width:760px}.hero-copy{color:#91a6bd;font-size:1rem;max-width:720px}.product-card{background:#0d1828;border:1px solid #1d3148;border-radius:16px;overflow:hidden;transition:.2s ease}.product-card:hover{transform:translateY(-4px);border-color:#22d3ee;box-shadow:0 18px 45px rgba(0,0,0,.24)}.product-image{position:relative;background:#030812}.stock-badge{position:absolute;top:12px;right:12px}.guide-badge{position:absolute;left:12px;bottom:12px}.guide-banner{background:#0a2232;border:1px solid rgba(34,211,238,.28);color:#d5f4fb}.empty-state{background:#0c1726;border:1px dashed #2a3d55;border-radius:16px}.detail-card{width:620px;max-width:94vw;border-radius:16px;overflow:hidden}.cart-card{width:820px;max-width:96vw;border-radius:16px}.success-card{width:460px;max-width:94vw;border-radius:16px}.tracking-code{display:inline-block;padding:10px 14px;border-radius:10px;background:#07111f;border:1px solid #22d3ee;color:#67e8f9;font-weight:800;letter-spacing:.08em}.payment-box{background:#091a2a;border:1px solid rgba(34,211,238,.22);border-radius:12px}.payment-qr{width:92px;height:108px;object-fit:cover;border-radius:8px}.info-banner,.account-banner{background:#0a2232;border:1px solid rgba(34,211,238,.24);color:#b9dceb}.old-price{text-decoration:line-through}.pre-line{white-space:pre-line}.contain-slide{background-size:contain!important;background-repeat:no-repeat!important;background-position:center!important}
</style>
