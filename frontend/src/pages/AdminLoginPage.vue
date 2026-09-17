<template>
  <q-page class="auth-page flex flex-center q-pa-md">
    <q-card class="auth-card text-white">
      <q-card-section class="text-center q-pa-lg">
        <q-avatar size="72px" class="brand-avatar"><q-icon name="admin_panel_settings" size="38px" /></q-avatar>
        <div class="text-h5 text-weight-bold q-mt-md">Panel ROBOKIT</div>
        <div class="text-caption text-grey-5">Acceso exclusivo para personal autorizado</div>
      </q-card-section>
      <q-card-section class="q-px-lg">
        <q-input v-model="form.email" dark outlined label="Correo" type="email" class="q-mb-md"><template #prepend><q-icon name="mail" /></template></q-input>
        <q-input v-model="form.password" dark outlined label="Contraseña" :type="show ? 'text' : 'password'" @keyup.enter="login">
          <template #prepend><q-icon name="lock" /></template>
          <template #append><q-btn flat round dense :icon="show ? 'visibility_off' : 'visibility'" @click="show = !show" /></template>
        </q-input>
        <q-btn class="full-width q-mt-lg action-primary" size="lg" label="Ingresar al panel" icon="login" :loading="loading" @click="login" />
        <q-btn v-if="setupRequired" flat class="full-width q-mt-sm" color="amber-4" label="Configurar primer administrador" to="/admin/setup" />
      </q-card-section>
      <q-card-actions align="center" class="q-pb-lg"><q-btn flat color="cyan-4" icon="storefront" label="Ir a la tienda" to="/tienda" /></q-card-actions>
    </q-card>
  </q-page>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import { adminApi } from '../services/api'
import { setAdminSession } from '../services/auth'

const $q = useQuasar()
const router = useRouter()
const route = useRoute()
const form = reactive({ email: '', password: '' })
const loading = ref(false)
const show = ref(false)
const setupRequired = ref(false)

onMounted(async () => {
  try {
    const { data } = await adminApi.get('/auth/admin/status')
    setupRequired.value = Boolean(data.setup_required)
  } catch { /* login sigue disponible */ }
})

const login = async () => {
  if (!form.email || !form.password) return $q.notify({ type: 'warning', message: 'Completa correo y contraseña.' })
  loading.value = true
  try {
    const { data } = await adminApi.post('/auth/admin/login', form)
    setAdminSession(data)
    router.replace(route.query.redirect || '/admin/dashboard')
  } catch (e) {
    $q.notify({ type: 'negative', message: e.userMessage || 'No se pudo iniciar sesión.' })
  } finally { loading.value = false }
}
</script>

<style scoped>
.auth-page { min-height: 100vh; background: radial-gradient(circle at 80% 15%, rgba(34,211,238,.16), transparent 28%), #07101d; }
.auth-card { width: 430px; max-width: 96vw; background: #0d1828; border: 1px solid #1e3954; border-radius: 20px; }
.brand-avatar { background: linear-gradient(135deg,#0891b2,#2563eb); color: white; }
</style>
