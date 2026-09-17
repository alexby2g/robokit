<template>
  <q-page class="q-pa-lg">
    <div class="editor-toolbar row items-center justify-between q-gutter-md q-mb-lg">
      <div class="row items-center q-gutter-sm"><q-btn flat round icon="arrow_back" color="cyan-4" to="/admin/capacitacion"/><div><div class="text-h5 text-weight-bold">Editar capacitación</div><div class="text-caption text-grey-5">Completa contexto, pasos y solución de problemas para que la guía sea realmente útil.</div></div></div>
      <div class="row q-gutter-sm"><q-btn outline color="cyan-4" icon="visibility" label="Ver como cliente" @click="preview"/><q-btn class="action-primary" icon="save" label="Guardar cambios" :loading="savingCourse" @click="saveCourse"/></div>
    </div>

    <div v-if="loading" class="flex flex-center q-pa-xl"><q-spinner color="cyan-4" size="54px"/></div>
    <template v-else-if="course">
      <q-card flat class="panel q-mb-lg">
        <q-card-section>
          <div class="row q-col-gutter-lg">
            <div class="col-12 col-lg-8">
              <div class="row q-col-gutter-md">
                <div class="col-12"><q-input v-model="course.titulo" outlined dense dark label="Título *"/></div>
                <div class="col-12"><q-input v-model="course.descripcion" type="textarea" rows="3" outlined dense dark label="¿De qué trata esta guía?"/></div>
                <div class="col-12"><q-input v-model="course.objetivo" type="textarea" rows="3" outlined dense dark label="Objetivo de aprendizaje" hint="Qué podrá hacer el cliente al terminar la guía."/></div>
                <div class="col-12 col-md-4"><q-select v-model="course.nivel" :options="levelOptions" outlined dense dark clearable label="Nivel"/></div>
                <div class="col-12 col-md-4"><q-input v-model.number="course.duracion_minutos" type="number" outlined dense dark label="Duración estimada (min)"/></div>
                <div class="col-12 col-md-4"><q-select v-model="course.estado" :options="statusOptions" emit-value map-options outlined dense dark label="Estado *"/></div>
                <div class="col-12"><q-input v-model="course.materiales" type="textarea" autogrow outlined dense dark label="Materiales / requisitos" hint="Un elemento por línea: cable USB, Arduino IDE, batería, etc."/></div>
                <div class="col-12"><q-input v-model="course.recomendaciones" type="textarea" autogrow outlined dense dark label="Recomendaciones generales" hint="Consejos de seguridad, preparación o uso antes de comenzar."/></div>
                <div class="col-12"><q-select v-model="selectedProducts" :options="products" option-value="id" option-label="Nombre" emit-value map-options multiple use-chips outlined dense dark label="Productos relacionados"/></div>
                <div class="col-12"><q-file v-model="coverFile" outlined dense dark accept="image/*,.heic,.heif,.avif,.tif,.tiff" max-file-size="12582912" label="Reemplazar imagen de portada"><template #prepend><q-icon name="add_photo_alternate"/></template></q-file></div>
              </div>
            </div>
            <div class="col-12 col-lg-4"><q-img v-if="course.imagen" :src="mediaUrl(course.imagen)" height="240px" fit="cover" class="rounded-borders"/><div v-else class="course-cover flex flex-center rounded-borders"><q-icon name="school" size="72px" color="cyan-3"/></div><q-banner class="context-banner q-mt-md" rounded><template #avatar><q-icon name="lightbulb" color="amber-4"/></template>Relaciona la guía con productos para que aparezca automáticamente después de una compra.</q-banner></div>
          </div>
        </q-card-section>
      </q-card>

      <q-card flat class="panel">
        <q-card-section class="row items-center justify-between"><div><div class="text-h6 text-weight-bold">Módulos de la guía</div><div class="text-caption text-grey-5">Usa menos módulos, pero más completos. 3 a 6 suele ser más claro que muchos pasos pequeños.</div></div><q-btn class="action-primary" icon="add" label="Agregar módulo" @click="openModule()"/></q-card-section>
        <q-separator dark/>
        <q-list v-if="modules.length" separator dark>
          <q-item v-for="(module,index) in modules" :key="module.id" class="q-py-md">
            <q-item-section avatar><q-avatar color="blue-grey-9" text-color="cyan-3">{{index+1}}</q-avatar></q-item-section>
            <q-item-section><q-item-label class="text-weight-bold">{{module.titulo}}</q-item-label><q-item-label caption>{{module.descripcion||'Sin resumen.'}}</q-item-label><div class="row q-gutter-xs q-mt-xs"><q-chip dense :color="module.estado==='activo'?'green-9':'blue-grey-8'" text-color="white">{{module.estado==='activo'?'Visible':'Oculto'}}</q-chip><q-chip v-if="module.video" dense outline color="cyan-4" icon="play_circle">Video</q-chip><q-chip v-if="module.material" dense outline color="amber-4" icon="description">Material</q-chip><q-chip v-if="module.problemas_comunes" dense outline color="orange-4" icon="build">Solución de problemas</q-chip></div></q-item-section>
            <q-item-section side><div class="row q-gutter-xs"><q-btn flat round dense color="amber-4" icon="edit" @click="openModule(module)"><q-tooltip>Editar módulo</q-tooltip></q-btn><q-btn flat round dense color="cyan-4" icon="arrow_upward" :disable="index===0" @click="move(index,-1)"/><q-btn flat round dense color="cyan-4" icon="arrow_downward" :disable="index===modules.length-1" @click="move(index,1)"/><q-btn flat round dense color="red-4" icon="delete_outline" @click="removeModule(module)"/></div></q-item-section>
          </q-item>
        </q-list>
        <div v-else class="q-pa-xl text-center text-grey-6"><q-icon name="menu_book" size="52px"/><div class="q-mt-sm">Aún no hay módulos. Agrega el primer bloque práctico de la guía.</div></div>
      </q-card>
    </template>

    <q-dialog v-model="moduleDialog" persistent>
      <q-card class="panel text-white" style="width:860px;max-width:96vw">
        <q-card-section class="row items-center justify-between"><div><div class="text-h6">{{moduleForm.id?'Editar módulo':'Nuevo módulo'}}</div><div class="text-caption text-grey-5">Cada módulo puede incluir explicación, pasos, consejos y solución de problemas.</div></div><q-btn flat round icon="close" v-close-popup/></q-card-section>
        <q-separator dark/>
        <q-card-section><div class="row q-col-gutter-md">
          <div class="col-12 col-md-8"><q-input v-model="moduleForm.titulo" outlined dense dark label="Título del módulo *"/></div>
          <div class="col-12 col-md-4"><q-select v-model="moduleForm.estado" :options="moduleStatusOptions" emit-value map-options outlined dense dark label="Visibilidad *"/></div>
          <div class="col-12"><q-input v-model="moduleForm.descripcion" type="textarea" rows="2" outlined dense dark label="Resumen corto"/></div>
          <div class="col-12"><q-input v-model="moduleForm.contenido" type="textarea" autogrow outlined dense dark label="Explicación / contexto" hint="Explica qué se hará y por qué."/></div>
          <div class="col-12"><q-input v-model="moduleForm.pasos" type="textarea" autogrow outlined dense dark label="Pasos numerados" hint="Ejemplo: 1. Conecta... 2. Abre... 3. Verifica..."/></div>
          <div class="col-12 col-md-6"><q-input v-model="moduleForm.consejos" type="textarea" autogrow outlined dense dark label="Consejos y buenas prácticas"/></div>
          <div class="col-12 col-md-6"><q-input v-model="moduleForm.problemas_comunes" type="textarea" autogrow outlined dense dark label="Problemas comunes y solución"/></div>
          <div class="col-12 col-md-6"><q-input v-model="moduleForm.video" outlined dense dark label="Enlace de video (opcional)"/></div>
          <div class="col-12 col-md-6"><q-input v-model="moduleForm.material" outlined dense dark label="Enlace de material/PDF (opcional)"/></div>
        </div></q-card-section>
        <q-card-actions align="right" class="q-pa-md"><q-btn flat label="Cancelar" v-close-popup/><q-btn class="action-primary" icon="save" label="Guardar módulo" :loading="savingModule" @click="saveModule"/></q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import { mediaUrl } from '../utils/media'

const $q=useQuasar(),route=useRoute(),router=useRouter(),loading=ref(false),savingCourse=ref(false),savingModule=ref(false),course=ref(null),products=ref([]),selectedProducts=ref([]),coverFile=ref(null),moduleDialog=ref(false)
const statusOptions=[{label:'Publicado',value:'activo'},{label:'Oculto / borrador',value:'inactivo'}],moduleStatusOptions=[{label:'Visible para cliente',value:'activo'},{label:'Oculto',value:'inactivo'}],levelOptions=['Principiante','Intermedio','Avanzado']
const emptyModule=()=>({id:null,titulo:'',descripcion:'',contenido:'',pasos:'',consejos:'',problemas_comunes:'',video:'',material:'',estado:'activo'})
const moduleForm=ref(emptyModule()),modules=computed(()=>[...(course.value?.modulos||[])].sort((a,b)=>Number(a.orden)-Number(b.orden)||Number(a.id)-Number(b.id)))
const load=async()=>{loading.value=true;try{const[c,p]=await Promise.all([api.get(`/admin/cursos/${route.params.id}`),api.get('/productos')]);course.value=c.data.curso;products.value=p.data.productos||[];selectedProducts.value=(course.value.productos||[]).map(x=>x.id)}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo cargar la capacitación.'});router.push('/admin/capacitacion')}finally{loading.value=false}}
const saveCourse=async()=>{if(!String(course.value?.titulo||'').trim())return $q.notify({type:'warning',message:'El título de la capacitación es obligatorio.'});savingCourse.value=true;const fd=new FormData();fd.append('_method','PUT');for(const k of ['titulo','descripcion','objetivo','nivel','duracion_minutos','materiales','recomendaciones','estado']){const v=course.value[k];fd.append(k,v??'')}for(const id of selectedProducts.value||[])fd.append('productos[]',id);if(coverFile.value)fd.append('imagen_archivo',coverFile.value);try{const{data}=await api.post(`/admin/cursos/${course.value.id}`,fd);course.value=data.curso;selectedProducts.value=(course.value.productos||[]).map(x=>x.id);coverFile.value=null;$q.notify({type:'positive',message:'Guía actualizada y sincronizada con la tienda.'})}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudieron guardar los cambios.'})}finally{savingCourse.value=false}}
const openModule=(m=null)=>{moduleForm.value=m?JSON.parse(JSON.stringify(m)):emptyModule();moduleDialog.value=true}
const saveModule=async()=>{if(!String(moduleForm.value.titulo||'').trim())return $q.notify({type:'warning',message:'El título del módulo es obligatorio.'});savingModule.value=true;const payload={id_curso:Number(course.value.id),titulo:moduleForm.value.titulo,descripcion:moduleForm.value.descripcion||null,contenido:moduleForm.value.contenido||null,pasos:moduleForm.value.pasos||null,consejos:moduleForm.value.consejos||null,problemas_comunes:moduleForm.value.problemas_comunes||null,video:moduleForm.value.video||null,material:moduleForm.value.material||null,estado:moduleForm.value.estado,orden:moduleForm.value.id?moduleForm.value.orden:modules.value.length+1};try{if(moduleForm.value.id)await api.put(`/admin/modulos/${moduleForm.value.id}`,payload);else await api.post('/admin/modulos',payload);moduleDialog.value=false;await load();$q.notify({type:'positive',message:'Módulo guardado.'})}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo guardar el módulo.'})}finally{savingModule.value=false}}
const removeModule=m=>$q.dialog({title:'Eliminar módulo',message:`¿Eliminar “${m.titulo}”?`,cancel:true,persistent:true,ok:{label:'Eliminar',color:'negative'}}).onOk(async()=>{try{await api.delete(`/admin/modulos/${m.id}`);await load()}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo eliminar.'})}})
const move=async(index,delta)=>{const ordered=modules.value;const target=index+delta;if(target<0||target>=ordered.length)return;[ordered[index],ordered[target]]=[ordered[target],ordered[index]];try{await api.put(`/admin/cursos/${course.value.id}/modulos/reordenar`,{modulos:ordered.map(m=>m.id)});await load()}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudo cambiar el orden.'})}}
const preview=()=>window.open(router.resolve(`/capacitacion/${course.value.id}`).href,'_blank','noopener')
onMounted(load)
</script>

<style scoped>.editor-toolbar{position:sticky;top:72px;z-index:10;background:rgba(7,16,29,.96);padding:12px;border:1px solid #1d3148;border-radius:14px;backdrop-filter:blur(12px)}.course-cover{height:240px;background:linear-gradient(135deg,#0c2742,#0e7490)}.context-banner{background:#0a2232;border:1px solid rgba(34,211,238,.24);color:#c9e9f2}</style>
