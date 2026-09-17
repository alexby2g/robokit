<template>
  <q-page class="public-course q-pa-md q-pb-xl">
    <div class="wrap">
      <div class="row items-center q-gutter-sm q-mb-lg"><q-btn flat round icon="arrow_back" color="cyan-4" to="/capacitacion"/><div><div class="text-h4 text-weight-bold">{{course?.titulo||'Capacitación'}}</div><div class="text-grey-5">{{course?.descripcion||'Guía paso a paso.'}}</div></div></div>
      <div v-if="loading" class="flex flex-center q-pa-xl"><q-spinner color="cyan-4" size="54px"/></div>
      <template v-else-if="course">
        <q-card flat class="panel q-mb-lg overflow-hidden">
          <div class="row">
            <div class="col-12 col-md-4"><q-img v-if="course.imagen" :src="mediaUrl(course.imagen)" height="290px" fit="cover"/><div v-else class="course-cover flex flex-center"><q-icon name="school" size="82px" color="cyan-3"/></div></div>
            <q-card-section class="col-12 col-md-8 q-pa-lg">
              <div class="row q-gutter-sm"><q-chip color="green-9" text-color="white" icon="verified">Guía publicada</q-chip><q-chip v-if="course.nivel" outline color="cyan-4" icon="signal_cellular_alt">{{course.nivel}}</q-chip><q-chip v-if="course.duracion_minutos" outline color="amber-4" icon="schedule">{{course.duracion_minutos}} min aprox.</q-chip></div>
              <div class="text-h5 text-weight-bold q-mt-sm">{{course.titulo}}</div>
              <div class="text-grey-4 q-mt-sm pre-line">{{course.descripcion}}</div>
              <div v-if="course.objetivo" class="info-block q-mt-md"><div class="block-title"><q-icon name="flag"/> Objetivo</div><div class="pre-line">{{course.objetivo}}</div></div>
              <div v-if="course.productos?.length" class="q-mt-lg"><div class="text-caption text-grey-6 text-uppercase">Productos relacionados</div><div class="row q-gutter-xs q-mt-xs"><q-chip v-for="p in course.productos" :key="p.id" outline color="cyan-4" clickable to="/tienda">{{p.Nombre}}</q-chip></div></div>
            </q-card-section>
          </div>
        </q-card>

        <div class="row q-col-gutter-md q-mb-lg" v-if="course.materiales||course.recomendaciones">
          <div v-if="course.materiales" class="col-12 col-md-6"><q-card flat class="panel q-pa-md full-height"><div class="block-title"><q-icon name="inventory_2"/> Antes de comenzar</div><div class="pre-line text-grey-4 q-mt-sm">{{course.materiales}}</div></q-card></div>
          <div v-if="course.recomendaciones" class="col-12 col-md-6"><q-card flat class="panel q-pa-md full-height"><div class="block-title"><q-icon name="tips_and_updates"/> Recomendaciones</div><div class="pre-line text-grey-4 q-mt-sm">{{course.recomendaciones}}</div></q-card></div>
        </div>

        <q-card flat class="panel">
          <q-card-section><div class="text-h6 text-weight-bold">Contenido de la guía</div><div class="text-caption text-grey-5">Avanza en orden. Cada módulo agrupa contexto, pasos y ayuda.</div></q-card-section><q-separator dark/>
          <q-list v-if="course.modulos?.length" separator dark>
            <q-expansion-item v-for="(m,index) in course.modulos" :key="m.id" :default-opened="index===0" header-class="text-white">
              <template #header><q-item-section avatar><q-avatar color="blue-grey-9" text-color="cyan-3">{{index+1}}</q-avatar></q-item-section><q-item-section><q-item-label class="text-weight-bold">{{m.titulo}}</q-item-label><q-item-label caption>{{m.descripcion||'Paso de la guía'}}</q-item-label></q-item-section></template>
              <q-card class="bg-transparent"><q-card-section class="guide-body">
                <section v-if="m.contenido"><div class="section-title">Contexto</div><div class="text-grey-3 pre-line guide-content">{{m.contenido}}</div></section>
                <section v-if="m.pasos"><div class="section-title q-mt-lg">Pasos</div><div class="steps-box pre-line">{{m.pasos}}</div></section>
                <section v-if="m.consejos"><div class="section-title q-mt-lg">Consejos</div><q-banner rounded class="tip-box"><template #avatar><q-icon name="tips_and_updates" color="amber-4"/></template><div class="pre-line">{{m.consejos}}</div></q-banner></section>
                <section v-if="m.problemas_comunes"><div class="section-title q-mt-lg">Si algo no funciona</div><q-banner rounded class="problem-box"><template #avatar><q-icon name="build" color="orange-4"/></template><div class="pre-line">{{m.problemas_comunes}}</div></q-banner></section>
                <div v-if="!m.contenido&&!m.pasos" class="text-grey-5">{{m.descripcion||'Sin instrucciones adicionales.'}}</div>
                <div class="row q-gutter-sm q-mt-lg"><q-btn v-if="m.video" outline color="cyan-4" icon="play_circle" label="Ver video" :href="m.video" target="_blank"/><q-btn v-if="m.material" outline color="amber-4" icon="description" label="Abrir material" :href="m.material" target="_blank"/></div>
              </q-card-section></q-card>
            </q-expansion-item>
          </q-list>
          <div v-else class="q-pa-xl text-center text-grey-6">Esta capacitación todavía no tiene módulos visibles.</div>
        </q-card>
      </template>
    </div>
  </q-page>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import { publicApi } from '../services/api'
import { mediaUrl } from '../utils/media'
const $q=useQuasar(),route=useRoute(),router=useRouter(),course=ref(null),loading=ref(false)
const load=async()=>{loading.value=true;try{const{data}=await publicApi.get(`/capacitacion/${route.params.id}`);course.value=data.curso}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se encontró la capacitación.'});router.push('/capacitacion')}finally{loading.value=false}}
onMounted(load)
</script>
<style scoped>.public-course{background:#07101d;color:#e8f1fb;min-height:100vh}.wrap{max-width:1180px;margin:0 auto}.course-cover{height:290px;background:linear-gradient(135deg,#0c2742,#0e7490)}.pre-line{white-space:pre-line}.guide-content{line-height:1.75}.info-block,.steps-box{background:#091727;border:1px solid #1d3148;border-radius:12px;padding:14px}.block-title,.section-title{font-weight:800;color:#a5f3fc;display:flex;align-items:center;gap:8px}.tip-box{background:#172013;border:1px solid rgba(251,191,36,.25);color:#f8f2d5}.problem-box{background:#23170d;border:1px solid rgba(251,146,60,.3);color:#f5dfce}.guide-body{padding:22px}</style>
