<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-lg">
      <div><div class="text-h4 page-title">Catálogo online</div><div class="page-subtitle">Edita lo que el público verá sin tocar código.</div></div>
      <q-btn outline color="cyan-4" icon="open_in_new" label="Ver tienda pública" @click="openStore" />
    </div>

    <div class="row q-col-gutter-lg">
      <div class="col-12 col-lg-7">
        <q-card flat class="panel text-white">
          <q-card-section><div class="text-h6 text-weight-bold">Identidad y portada</div><div class="text-caption text-muted">Los cambios se publican inmediatamente al guardar.</div></q-card-section>
          <q-separator dark />
          <q-card-section>
            <div class="row q-col-gutter-md">
              <div class="col-12 col-md-7"><q-input v-model="form.nombre_tienda" outlined dark label="Nombre de la tienda" /></div>
              <div class="col-12 col-md-5"><q-input v-model="form.subtitulo" outlined dark label="Subtítulo" /></div>
              <div class="col-12"><q-input v-model="form.hero_titulo" outlined dark label="Título principal" /></div>
              <div class="col-12"><q-input v-model="form.hero_texto" type="textarea" autogrow outlined dark label="Texto de portada" /></div>
              <div class="col-12 col-md-6"><q-input v-model="form.whatsapp" outlined dark label="WhatsApp (opcional)" /></div>
              <div class="col-12 col-md-6"><q-toggle v-model="form.mostrar_stock" color="cyan" label="Mostrar stock al público" /></div>
              <div class="col-12 col-md-6"><q-toggle v-model="form.recojo_habilitado" color="cyan" label="Permitir recojo" /></div>
              <div class="col-12 col-md-6"><q-toggle v-model="form.delivery_habilitado" color="cyan" label="Permitir delivery" /></div>
            </div>
          </q-card-section>
          <q-card-actions align="right" class="q-pa-md"><q-btn class="action-primary" icon="publish" label="Publicar cambios" :loading="saving" @click="save" /></q-card-actions>
        </q-card>
      </div>

      <div class="col-12 col-lg-5">
        <q-card flat class="panel text-white q-pa-md q-mb-lg">
          <div class="text-overline text-cyan-3">VISTA RÁPIDA</div>
          <div class="text-h4 text-weight-bold q-mt-sm">{{ form.hero_titulo || 'Título principal' }}</div>
          <div class="text-grey-5 q-mt-sm">{{ form.hero_texto || 'Texto de portada' }}</div>
          <div class="q-mt-lg row q-gutter-sm"><q-chip color="green-10" text-color="white">{{ published }} publicados</q-chip><q-chip color="blue-grey-8" text-color="white">{{ hidden }} ocultos/borradores</q-chip><q-chip color="purple-8" text-color="white">{{ featured }} destacados</q-chip></div>
        </q-card>
        <q-card flat class="panel text-white">
          <q-card-section class="row items-center justify-between"><div><div class="text-h6">Productos</div><div class="text-caption text-muted">Publicación, ofertas e imágenes.</div></div><q-btn flat color="cyan-4" icon="edit" label="Administrar" to="/admin/productos" /></q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'; import { useQuasar } from 'quasar'; import { useRouter } from 'vue-router'; import api from '../services/api'
const $q=useQuasar(),router=useRouter(),saving=ref(false),products=ref([]);const form=reactive({nombre_tienda:'ROBOKIT STORE',subtitulo:'Robótica, kits y componentes',hero_titulo:'',hero_texto:'',whatsapp:'',mostrar_stock:true,delivery_habilitado:true,recojo_habilitado:true})
const published=computed(()=>products.value.filter(p=>p.estado_publicacion==='Publicado').length);const hidden=computed(()=>products.value.length-published.value);const featured=computed(()=>products.value.filter(p=>p.destacado).length)
const load=async()=>{try{const[c,p]=await Promise.all([api.get('/catalogo/config'),api.get('/productos')]);Object.assign(form,c.data.config||{});products.value=p.data.productos||[]}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo cargar el catálogo.'})}}
const save=async()=>{saving.value=true;try{const{data}=await api.put('/catalogo/config',form);Object.assign(form,data.config||{});$q.notify({type:'positive',message:'Catálogo publicado en línea.'})}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo guardar.'})}finally{saving.value=false}}
const openStore=()=>window.open(router.resolve('/tienda').href,'_blank','noopener');onMounted(load)
</script>
