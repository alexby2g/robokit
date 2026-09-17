<template>
  <q-page class="store-page flex flex-center q-pa-md q-py-xl">
    <q-card class="auth-card text-white">
      <q-card-section class="text-center q-pa-lg">
        <q-icon name="account_circle" color="cyan-4" size="58px" />
        <div class="text-h5 text-weight-bold q-mt-sm">Ingresar a ROBOKIT</div>
        <div class="text-caption text-grey-5">Usa tu correo y contraseña para acceder.</div>
      </q-card-section>

      <q-card-section class="q-px-lg q-gutter-md">
        <q-banner v-if="errors.general" rounded class="error-banner">
          <template #avatar><q-icon name="error" color="red-4" /></template>
          {{ errors.general }}
        </q-banner>

        <q-input
          v-model="form.email"
          dark
          outlined
          type="email"
          label="Correo *"
          autocomplete="email"
          :error="Boolean(errors.email)"
          :error-message="errors.email"
          @update:model-value="clearError('email')"
          @keyup.enter="login"
        >
          <template #prepend><q-icon name="mail" /></template>
        </q-input>

        <q-input
          v-model="form.password"
          dark
          outlined
          :type="show ? 'text' : 'password'"
          label="Contraseña *"
          autocomplete="current-password"
          :error="Boolean(errors.password)"
          :error-message="errors.password"
          @update:model-value="clearError('password')"
          @keyup.enter="login"
        >
          <template #prepend><q-icon name="lock" /></template>
          <template #append>
            <q-btn flat round dense :icon="show ? 'visibility_off' : 'visibility'" @click="show = !show" />
          </template>
        </q-input>

        <q-btn
          class="full-width action-primary"
          size="lg"
          label="Iniciar sesión"
          icon="login"
          :loading="loading"
          @click="login"
        />

        <div class="text-center text-caption text-grey-5">¿Aún no tienes cuenta?</div>
        <q-btn outline color="cyan-4" class="full-width" label="Crear cuenta" to="/registro" />
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import { publicApi } from '../services/api'
import { setUnifiedSession } from '../services/auth'

const $q = useQuasar()
const router = useRouter()
const route = useRoute()
const loading = ref(false)
const show = ref(false)
const form = reactive({ email: '', password: '' })
const errors = reactive({ email: '', password: '', general: '' })

const clearError = (field) => {
  errors[field] = ''
  errors.general = ''
}

const validate = () => {
  errors.email = ''
  errors.password = ''
  errors.general = ''

  const email = form.email.trim()
  if (!email) errors.email = 'El correo es obligatorio.'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.email = 'El correo no tiene un formato válido.'

  if (!form.password) errors.password = 'La contraseña es obligatoria.'

  return !errors.email && !errors.password
}

const login = async () => {
  if (!validate()) return

  loading.value = true
  try {
    const { data } = await publicApi.post('/auth/login', {
      email: form.email.trim().toLowerCase(),
      password: form.password,
    })

    const sessionType = setUnifiedSession(data)

    $q.notify({
      type: 'positive',
      message: sessionType === 'admin' ? 'Bienvenido al sistema ROBOKIT.' : 'Sesión iniciada correctamente.',
    })

    if (sessionType === 'admin') {
      const requested = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/admin')
        ? route.query.redirect
        : '/admin/dashboard'
      await router.replace(requested)
    } else {
      const requested = typeof route.query.redirect === 'string' && !route.query.redirect.startsWith('/admin')
        ? route.query.redirect
        : '/mi-cuenta'
      await router.replace(requested)
    }
  } catch (e) {
    const backendErrors = e.validationErrors || {}
    errors.email = backendErrors.email || ''
    errors.password = backendErrors.password || ''

    if (!errors.email && !errors.password) {
      errors.general = e.userMessage || 'No se pudo iniciar sesión.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.store-page { min-height: 75vh; background: #07101d; }
.auth-card { width: 440px; max-width: 96vw; background: #0d1828; border: 1px solid #1e3954; border-radius: 20px; }
.error-banner { background: rgba(185, 28, 28, .18); border: 1px solid rgba(248, 113, 113, .45); color: #fecaca; }
</style>
