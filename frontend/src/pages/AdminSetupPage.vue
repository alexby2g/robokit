<template>
  <q-page class="auth-page flex flex-center q-pa-md">
    <q-card class="auth-card text-white">
      <q-card-section class="q-pa-lg">
        <div class="text-h5 text-weight-bold">Configurar administrador</div>
        <div class="text-caption text-grey-5 q-mt-xs">Solo funciona mientras no exista un administrador en ROBOKIT.</div>
      </q-card-section>
      <q-card-section class="q-px-lg q-gutter-md">
        <q-input v-model="form.name" dark outlined label="Nombre" />
        <q-input v-model="form.email" dark outlined type="email" label="Correo" />
        <q-input v-model="form.password" dark outlined type="password" label="Contraseña (mínimo 8 caracteres)" />
        <q-input v-model="form.password_confirmation" dark outlined type="password" label="Confirmar contraseña" @keyup.enter="save" />
        <q-btn class="full-width action-primary" size="lg" label="Crear administrador" :loading="loading" @click="save" />
        <q-btn flat class="full-width" label="Volver" to="/admin/login" />
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useRouter } from 'vue-router'
import { adminApi } from '../services/api'
import { setAdminSession } from '../services/auth'
const $q = useQuasar(); const router = useRouter(); const loading = ref(false)
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const save = async () => {
  if (!form.name || !form.email || form.password.length < 8 || form.password !== form.password_confirmation) return $q.notify({ type: 'warning', message: 'Revisa los datos y la confirmación de contraseña.' })
  loading.value = true
  try { const { data } = await adminApi.post('/auth/admin/setup', form); setAdminSession(data); router.replace('/admin/dashboard') }
  catch (e) { $q.notify({ type: 'negative', message: e.userMessage || 'No se pudo crear el administrador.' }) }
  finally { loading.value = false }
}
</script>

<style scoped>.auth-page{min-height:100vh;background:#07101d}.auth-card{width:460px;max-width:96vw;background:#0d1828;border:1px solid #1e3954;border-radius:20px}</style>
