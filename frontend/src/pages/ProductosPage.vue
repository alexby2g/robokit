<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-lg">
      <div><div class="text-h4 page-title">Productos y catálogo</div><div class="page-subtitle">Edita precios, publicación, ofertas e imágenes. La tienda pública se actualiza al guardar.</div></div>
      <div class="row q-gutter-sm"><q-btn outline color="cyan-4" icon="open_in_new" label="Ver catálogo" @click="openStore"/><q-btn class="action-primary" icon="add" label="Nuevo producto" @click="open()"/></div>
    </div>

    <div class="row q-col-gutter-md">
      <div class="col-12 col-sm-6 col-lg-4" v-for="p in products" :key="p.id">
        <q-card flat class="panel full-height column">
          <q-card-section class="row items-center justify-between q-py-sm"><q-btn unelevated color="amber-8" icon="edit" label="Editar" @click="open(p)"/><q-btn flat round color="red-4" icon="delete_outline" @click="remove(p)"><q-tooltip>Eliminar producto</q-tooltip></q-btn></q-card-section>
          <div class="relative-position">
            <q-img :src="productImage(p)" height="190px" fit="contain" class="bg-black"><template #error><div class="absolute-full flex flex-center column text-muted"><q-icon name="image_not_supported" size="48px"/><div>Sin imagen</div></div></template></q-img>
            <q-badge class="absolute-top-left q-ma-sm" :color="statusColor(p.estado_publicacion)">{{ p.estado_publicacion || 'Publicado' }}</q-badge>
            <q-badge v-if="p.destacado" class="absolute-top-right q-ma-sm" color="purple-7">Destacado</q-badge>
            <q-badge class="absolute-bottom-left q-ma-sm" color="blue-grey-8" text-color="white"><q-icon name="photo_library" class="q-mr-xs"/>{{ (p.imagenes || []).length }} {{ (p.imagenes || []).length === 1 ? 'imagen' : 'imágenes' }}</q-badge>
          </div>
          <q-card-section class="col">
            <div class="row items-start justify-between"><div class="text-subtitle1 text-weight-bold ellipsis" style="max-width:70%">{{p.Nombre}}</div><q-chip dense :color="Number(p.Disponible ?? p.Stock)<=5?'orange-10':'green-10'" text-color="white">{{p.Disponible ?? p.Stock}} disp.</q-chip></div>
            <div class="row items-center q-gutter-sm"><div class="text-h6 text-green-3">Bs {{money(p.Precio)}}</div><div v-if="p.precio_anterior" class="text-caption text-grey-6 old-price">Bs {{money(p.precio_anterior)}}</div></div>
            <div class="text-caption text-muted q-mt-sm ellipsis-2-lines">{{p.Descripcion||'Sin descripción.'}}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <div v-if="!products.length&&!loading" class="panel q-pa-xl text-center text-muted">No hay productos registrados.</div>
    <q-inner-loading :showing="loading"><q-spinner color="cyan-4" size="48px"/></q-inner-loading>

    <q-dialog v-model="dialog" persistent>
      <q-card class="panel text-white" style="width:900px;max-width:96vw">
        <q-card-section class="row items-center justify-between"><div><div class="text-h6">{{form.id?'Editar producto':'Nuevo producto'}}</div><div class="text-caption text-muted">Publicado = visible en la tienda. Oculto/Borrador = solo administración.</div></div><q-btn flat round icon="close" v-close-popup/></q-card-section>
        <q-separator dark/>
        <q-card-section>
          <div v-if="visibleImages.length" class="q-mb-md">
            <div class="text-subtitle2 q-mb-sm">Imágenes actuales</div>
            <div class="row q-gutter-sm">
              <div v-for="img in visibleImages" :key="img.id" class="image-tile" :class="{ principal: Number(principalId) === Number(img.id) }">
                <q-img :src="mediaUrl(img.ruta)" width="120px" height="90px" fit="contain" class="bg-black rounded-borders"/>
                <div class="row justify-center q-mt-xs"><q-btn dense flat round size="sm" :color="Number(principalId)===Number(img.id)?'amber':'grey-5'" icon="star" @click="principalId=img.id"><q-tooltip>Imagen principal</q-tooltip></q-btn><q-btn dense flat round size="sm" color="red-4" icon="delete" @click="markDelete(img.id)"/></div>
              </div>
            </div>
          </div>

          <q-file v-model="files" multiple :accept="acceptedImageTypes" max-file-size="12582912" outlined dense dark label="Agregar imágenes (máximo 8 por producto)" @rejected="rejectFile"><template #prepend><q-icon name="add_photo_alternate"/></template></q-file>
          <div class="text-caption text-muted q-mt-xs">Puedes seleccionar varias. JPG, PNG, WEBP, AVIF, HEIC y otros formatos de imagen, máximo 12 MB c/u.</div>

          <div class="row q-col-gutter-md q-mt-sm">
            <div class="col-12 col-md-7"><q-input v-model="form.Nombre" outlined dense dark label="Nombre *"/></div>
            <div class="col-12 col-md-5"><q-select v-model="form.id_categoria" :options="categories" option-value="id" option-label="Nombre" emit-value map-options outlined dense dark label="Categoría *"/></div>
            <div class="col-6 col-md-3"><q-input v-model.number="form.Precio" type="number" min="0" step="0.01" outlined dense dark label="Precio *" prefix="Bs"/></div>
            <div class="col-6 col-md-3"><q-input v-model.number="form.precio_anterior" type="number" min="0" step="0.01" outlined dense dark label="Precio anterior" prefix="Bs" hint="Para mostrar oferta"/></div>
            <div class="col-6 col-md-3"><q-input v-model.number="form.Stock" type="number" min="0" outlined dense dark label="Stock *" :readonly="Boolean(form.id)"/></div>
            <div class="col-6 col-md-3"><q-input v-model.number="form.orden_catalogo" type="number" min="0" outlined dense dark label="Orden catálogo"/></div>
            <div class="col-12 col-md-6"><q-select v-model="form.estado_publicacion" :options="['Borrador','Publicado','Oculto']" outlined dense dark label="Estado de publicación"/></div>
            <div class="col-12 col-md-6 flex items-center"><q-toggle v-model="form.destacado" color="purple-4" label="Destacar en el catálogo"/></div>
            <div class="col-12"><q-input v-model="form.Descripcion" type="textarea" rows="4" outlined dense dark label="Descripción / especificaciones"/></div>
          </div>
          <q-banner v-if="form.id" class="q-mt-md bg-blue-grey-10 text-grey-4" rounded><template #avatar><q-icon name="inventory_2" color="cyan-4"/></template>El stock físico se modifica desde Inventario para conservar el historial.</q-banner>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md"><q-btn flat label="Cancelar" v-close-popup/><q-btn class="action-primary" label="Guardar y publicar cambios" :loading="saving" @click="save"/></q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import {computed,onMounted,ref} from 'vue';import {useQuasar} from 'quasar';import {useRouter} from 'vue-router';import api from '../services/api';import {acceptedImageTypes,mediaUrl,productImage} from '../utils/media'
const $q=useQuasar(),router=useRouter();const products=ref([]),categories=ref([]),loading=ref(false),saving=ref(false),dialog=ref(false),files=ref([]),principalId=ref(null),deletedIds=ref([])
const empty=()=>({id:null,Nombre:'',Precio:0,precio_anterior:null,Stock:0,Descripcion:'',id_categoria:null,estado_publicacion:'Borrador',destacado:false,orden_catalogo:0,imagenes:[]});const form=ref(empty());const money=v=>Number(v||0).toFixed(2)
const visibleImages=computed(()=>(form.value.imagenes||[]).filter(i=>!deletedIds.value.includes(Number(i.id))));const statusColor=s=>({Publicado:'green-8',Borrador:'blue-grey-7',Oculto:'red-8'}[s]||'blue-grey-7')
const load=async()=>{loading.value=true;try{const[p,c]=await Promise.all([api.get('/productos'),api.get('/categorias')]);products.value=p.data.productos||[];categories.value=c.data.categorias||[]}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudieron cargar productos'})}finally{loading.value=false}}
const open=p=>{files.value=[];deletedIds.value=[];form.value=p?JSON.parse(JSON.stringify(p)):empty();principalId.value=(form.value.imagenes||[]).find(i=>i.es_principal)?.id||(form.value.imagenes||[])[0]?.id||null;dialog.value=true}
const markDelete=id=>{deletedIds.value.push(Number(id));if(Number(principalId.value)===Number(id))principalId.value=visibleImages.value[0]?.id||null};const rejectFile=()=> $q.notify({type:'warning',message:'Formato no válido o archivo mayor a 12 MB.'})
const save=async()=>{if(!String(form.value.Nombre||'').trim())return $q.notify({type:'warning',message:'El nombre del producto es obligatorio.'});if(!form.value.id_categoria)return $q.notify({type:'warning',message:'Debes seleccionar una categoría.'});if(form.value.Precio===''||form.value.Precio===null||form.value.Precio===undefined)return $q.notify({type:'warning',message:'El precio es obligatorio.'});if(Number(form.value.Precio)<0)return $q.notify({type:'warning',message:'El precio no puede ser negativo.'});if(form.value.Stock===''||form.value.Stock===null||form.value.Stock===undefined)return $q.notify({type:'warning',message:'El stock es obligatorio.'});if(Number(form.value.Stock)<0)return $q.notify({type:'warning',message:'El stock no puede ser negativo.'});if((visibleImages.value.length+(files.value?.length||0))>8)return $q.notify({type:'warning',message:'Máximo 8 imágenes por producto.'});saving.value=true;const fd=new FormData();['Nombre','Precio','precio_anterior','Stock','Descripcion','id_categoria','estado_publicacion','orden_catalogo'].forEach(k=>fd.append(k,form.value[k]??''));fd.append('destacado',form.value.destacado?'1':'0');if(principalId.value)fd.append('imagen_principal_id',principalId.value);if(deletedIds.value.length)fd.append('eliminar_imagenes',deletedIds.value.join(','));for(const f of files.value||[])fd.append('imagenes[]',f);try{if(form.value.id){fd.append('_method','PUT');await api.post(`/productos/${form.value.id}`,fd)}else await api.post('/productos',fd);dialog.value=false;$q.notify({type:'positive',message:'Producto guardado. La tienda quedó sincronizada.'});await load()}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo guardar el producto'})}finally{saving.value=false}}
const remove=p=>$q.dialog({title:'Eliminar producto',message:`¿Eliminar ${p.Nombre}? Si tiene historial, mejor cámbialo a Oculto.`,cancel:true,persistent:true}).onOk(async()=>{try{await api.delete(`/productos/${p.id}`);await load()}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo eliminar'})}})
const openStore=()=>window.open(router.resolve('/tienda').href,'_blank','noopener');onMounted(load)
</script>

<style scoped>.old-price{text-decoration:line-through}.image-tile{padding:6px;border:1px solid #263b52;border-radius:10px}.image-tile.principal{border-color:#fbbf24;box-shadow:0 0 0 1px #fbbf24 inset}</style>
