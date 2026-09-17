<template>
  <q-page class="q-pa-lg">
    <div class="row items-center justify-between q-col-gutter-md q-mb-lg">
      <div class="col-12 col-md-auto">
        <div class="text-h4 text-weight-bold">Capacitación</div>
        <div class="text-subtitle1 text-grey-6 q-mt-xs">Crea guías para que el cliente aprenda a utilizar los productos.</div>
      </div>
      <div class="col-12 col-md-auto row q-gutter-sm">
        <q-btn outline color="cyan-4" icon="open_in_new" label="Ver como cliente" @click="openPublic" />
        <q-btn class="action-primary" icon="add" label="Nueva capacitación" @click="openCreate" />
      </div>
    </div>

    <q-banner rounded class="info-banner q-mb-lg">
      <template #avatar><q-icon name="school" color="cyan-4" /></template>
      Relaciona cada capacitación con uno o varios productos. Al cliente le aparecerá la guía directamente desde el catálogo.
    </q-banner>

    <div v-if="loading" class="flex flex-center q-pa-xl"><q-spinner color="cyan-4" size="52px" /></div>
    <div v-else-if="courses.length" class="row q-col-gutter-lg">
      <div v-for="course in courses" :key="course.id" class="col-12 col-md-6 col-xl-4">
        <q-card flat class="panel course-card full-height column">
          <q-card-section class="row items-center justify-between q-pb-sm">
            <div class="row q-gutter-xs">
              <q-btn unelevated color="amber-8" icon="edit" label="Editar" @click="editCourse(course)" />
              <q-btn flat color="cyan-4" icon="visibility" label="Vista cliente" @click="previewCourse(course)" />
            </div>
            <q-btn flat round color="red-4" icon="delete_outline" @click="removeCourse(course)"><q-tooltip>Eliminar capacitación</q-tooltip></q-btn>
          </q-card-section>

          <q-img v-if="course.imagen" :src="mediaUrl(course.imagen)" height="190px" fit="cover" />
          <div v-else class="course-cover flex flex-center"><q-icon name="school" size="78px" color="cyan-3" /></div>

          <q-card-section class="col">
            <div class="row items-start justify-between q-gutter-sm">
              <div class="text-h6 text-weight-bold col">{{ course.titulo }}</div>
              <q-chip dense :color="course.estado === 'activo' ? 'green-9' : 'blue-grey-8'" text-color="white">{{ course.estado === 'activo' ? 'Publicado' : 'Oculto' }}</q-chip>
            </div>
            <div class="text-body2 text-grey-5 q-mt-sm ellipsis-3-lines">{{ course.descripcion || 'Sin descripción.' }}</div>

            <div class="q-mt-md">
              <div class="text-caption text-grey-6 text-uppercase text-weight-bold">Productos relacionados</div>
              <div v-if="course.productos?.length" class="row q-gutter-xs q-mt-xs">
                <q-chip v-for="p in course.productos" :key="p.id" dense outline color="cyan-4">{{ p.Nombre }}</q-chip>
              </div>
              <div v-else class="text-caption text-orange-4 q-mt-xs">Todavía no está relacionada con un producto.</div>
            </div>
          </q-card-section>

          <q-separator dark />
          <q-card-section class="row items-center justify-between">
            <div class="text-grey-4"><q-icon name="menu_book" class="q-mr-xs" />{{ course.modulos?.length || 0 }} módulos</div>
            <q-btn flat color="cyan-4" icon-right="arrow_forward" label="Administrar módulos" @click="editCourse(course)" />
          </q-card-section>
        </q-card>
      </div>
    </div>

    <q-card v-else flat class="panel q-pa-xl text-center">
      <q-icon name="school" size="72px" color="blue-grey-5" />
      <div class="text-h6 q-mt-md">Aún no tienes capacitaciones</div>
      <div class="text-grey-6 q-mt-xs">Crea la primera guía de uso para tus productos.</div>
      <q-btn class="action-primary q-mt-lg" icon="add" label="Crear capacitación" @click="openCreate" />
    </q-card>

    <q-dialog v-model="createDialog" persistent>
      <q-card class="panel text-white" style="width:760px;max-width:96vw">
        <q-card-section class="row items-center justify-between">
          <div><div class="text-h6">Nueva capacitación</div><div class="text-caption text-grey-5">Crea la guía y luego agrega sus módulos.</div></div>
          <q-btn flat round icon="close" v-close-popup />
        </q-card-section>
        <q-separator dark />
        <q-card-section>
          <div class="row q-col-gutter-md">
            <div class="col-12"><q-input v-model="form.titulo" outlined dense dark label="Título *" :error="Boolean(errors.titulo)" :error-message="errors.titulo" @update:model-value="clearError('titulo')" /></div>
            <div class="col-12"><q-input v-model="form.descripcion" type="textarea" rows="3" outlined dense dark label="Descripción" /></div>
            <div class="col-12 col-md-7"><q-select v-model="form.productos" :options="products" option-value="id" option-label="Nombre" emit-value map-options multiple use-chips outlined dense dark label="Productos relacionados" /></div>
            <div class="col-12 col-md-5"><q-select v-model="form.estado" :options="statusOptions" emit-value map-options outlined dense dark label="Estado *" /></div>
            <div class="col-12"><q-file v-model="form.imagen_archivo" outlined dense dark accept="image/*,.heic,.heif,.avif,.tif,.tiff" max-file-size="12582912" label="Imagen de portada (opcional)"><template #prepend><q-icon name="image" /></template></q-file></div>
          </div>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md"><q-btn flat label="Cancelar" v-close-popup /><q-btn class="action-primary" icon="save" label="Crear y agregar módulos" :loading="saving" @click="createCourse" /></q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useRouter } from 'vue-router'
import api from '../services/api'
import { mediaUrl } from '../utils/media'

const $q = useQuasar()
const router = useRouter()
const courses = ref([])
const products = ref([])
const loading = ref(false)
const saving = ref(false)
const createDialog = ref(false)
const errors = reactive({ titulo: '' })
const statusOptions = [{ label: 'Publicado', value: 'activo' }, { label: 'Oculto / borrador', value: 'inactivo' }]
const emptyForm = () => ({ titulo: '', descripcion: '', estado: 'inactivo', productos: [], imagen_archivo: null })
const form = ref(emptyForm())

const load = async () => {
  loading.value = true
  try {
    const [c, p] = await Promise.all([api.get('/admin/cursos'), api.get('/productos')])
    courses.value = c.data.cursos || []
    products.value = p.data.productos || []
  } catch (e) {
    $q.notify({ type: 'negative', message: e.userMessage || 'No se pudieron cargar las capacitaciones.' })
  } finally { loading.value = false }
}

const openCreate = () => { form.value = emptyForm(); errors.titulo = ''; createDialog.value = true }
const clearError = (field) => { errors[field] = '' }
const createCourse = async () => {
  errors.titulo = ''
  if (!String(form.value.titulo || '').trim()) { errors.titulo = 'El título de la capacitación es obligatorio.'; return }
  saving.value = true
  const fd = new FormData()
  fd.append('titulo', form.value.titulo)
  fd.append('descripcion', form.value.descripcion || '')
  fd.append('estado', form.value.estado)
  for (const id of form.value.productos || []) fd.append('productos[]', id)
  if (form.value.imagen_archivo) fd.append('imagen_archivo', form.value.imagen_archivo)
  try {
    const { data } = await api.post('/admin/cursos', fd)
    createDialog.value = false
    $q.notify({ type: 'positive', message: 'Capacitación creada. Ahora agrega sus módulos.' })
    router.push(`/admin/capacitacion/${data.curso.id}/editar`)
  } catch (e) {
    errors.titulo = e.validationErrors?.titulo || ''
    if (!errors.titulo) $q.notify({ type: 'negative', message: e.userMessage || 'No se pudo crear la capacitación.' })
  } finally { saving.value = false }
}

const editCourse = (course) => router.push(`/admin/capacitacion/${course.id}/editar`)
const previewCourse = (course) => window.open(router.resolve(`/capacitacion/${course.id}`).href, '_blank', 'noopener')
const openPublic = () => window.open(router.resolve('/capacitacion').href, '_blank', 'noopener')
const removeCourse = (course) => $q.dialog({ title: 'Eliminar capacitación', message: `¿Eliminar “${course.titulo}” y todos sus módulos?`, cancel: true, persistent: true, ok: { label: 'Eliminar', color: 'negative' } }).onOk(async () => {
  try { await api.delete(`/admin/cursos/${course.id}`); $q.notify({ type: 'positive', message: 'Capacitación eliminada.' }); await load() }
  catch (e) { $q.notify({ type: 'negative', message: e.userMessage || 'No se pudo eliminar.' }) }
})

onMounted(load)
</script>

<style scoped>
.info-banner{background:#0a2232;border:1px solid rgba(34,211,238,.24);color:#b9dceb}.course-card{border:1px solid #1d3148;border-radius:16px;overflow:hidden}.course-cover{height:190px;background:linear-gradient(135deg,#0c2742,#0e7490)}
</style>
