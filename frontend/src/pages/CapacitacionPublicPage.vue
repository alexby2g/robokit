<template>
  <q-page class="public-training q-pa-md q-pb-xl">
    <div class="wrap">
      <section class="hero q-pa-lg q-mb-lg">
        <q-chip dense outline color="cyan-4" text-color="cyan-3" icon="school">CAPACITACIÓN</q-chip>
        <div class="text-h3 text-weight-bold q-mt-sm">Aprende a usar tus productos ROBOKIT</div>
        <div class="text-grey-5 q-mt-sm">Guías paso a paso, videos y materiales relacionados con los productos del catálogo.</div>
      </section>

      <div v-if="loading" class="flex flex-center q-pa-xl"><q-spinner color="cyan-4" size="54px" /></div>
      <div v-else-if="courses.length" class="row q-col-gutter-lg">
        <div v-for="course in courses" :key="course.id" class="col-12 col-sm-6 col-lg-4">
          <q-card flat class="panel course-card full-height column">
            <q-img v-if="course.imagen" :src="mediaUrl(course.imagen)" height="210px" fit="cover" />
            <div v-else class="course-cover flex flex-center"><q-icon name="school" size="78px" color="cyan-3" /></div>
            <q-card-section class="col">
              <div class="text-h6 text-weight-bold">{{ course.titulo }}</div>
              <div class="text-body2 text-grey-5 q-mt-sm ellipsis-3-lines">{{ course.descripcion || 'Guía de uso disponible.' }}</div>
              <div v-if="course.productos?.length" class="q-mt-md">
                <div class="text-caption text-grey-6">Útil para:</div>
                <div class="row q-gutter-xs q-mt-xs"><q-chip v-for="p in course.productos" :key="p.id" dense outline color="cyan-4">{{ p.Nombre }}</q-chip></div>
              </div>
            </q-card-section>
            <q-card-section class="row items-center justify-between q-pt-none">
              <div class="text-grey-4"><q-icon name="menu_book" class="q-mr-xs" />{{ course.modulos?.length || 0 }} módulos</div>
              <q-btn class="action-primary" icon="play_arrow" label="Ver guía" :to="`/capacitacion/${course.id}`" />
            </q-card-section>
          </q-card>
        </div>
      </div>
      <q-card v-else flat class="panel q-pa-xl text-center"><q-icon name="school" size="70px" color="blue-grey-5" /><div class="text-h6 q-mt-md">No hay capacitaciones publicadas todavía.</div></q-card>
    </div>
  </q-page>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { publicApi } from '../services/api'
import { mediaUrl } from '../utils/media'

const $q=useQuasar(),courses=ref([]),loading=ref(false)
const load=async()=>{loading.value=true;try{const{data}=await publicApi.get('/capacitacion');courses.value=data.cursos||[]}catch(e){$q.notify({type:'negative',message:e.userMessage||'No se pudieron cargar las capacitaciones.'})}finally{loading.value=false}}
onMounted(load)
</script>

<style scoped>
.public-training{background:#07101d;color:#e8f1fb;min-height:100vh}.wrap{max-width:1320px;margin:0 auto}.hero{background:radial-gradient(circle at 85% 20%,rgba(14,165,233,.2),transparent 35%),linear-gradient(135deg,#0d1d31,#0a1422);border:1px solid #1d3853;border-radius:20px}.course-card{border:1px solid #1d3148;border-radius:16px;overflow:hidden}.course-cover{height:210px;background:linear-gradient(135deg,#0c2742,#0e7490)}
</style>
