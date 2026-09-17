<template>
  <q-page class="store-page flex flex-center q-pa-md q-py-xl">
    <q-card class="auth-card text-white">
      <q-card-section class="q-pa-lg">
        <div class="text-h5 text-weight-bold">Crear cuenta</div>
        <div class="text-caption text-grey-5">Tus pedidos quedarán guardados en tu perfil.</div>
      </q-card-section>

      <q-card-section class="q-px-lg">
        <q-banner v-if="errors.general" rounded class="error-banner q-mb-md">
          <template #avatar><q-icon name="error" color="red-4" /></template>
          {{ errors.general }}
        </q-banner>

        <div class="row q-col-gutter-md">
          <div class="col-12 col-sm-6">
            <q-input v-model="form.Nombre" dark outlined label="Nombre *" :error="Boolean(errors.Nombre)" :error-message="errors.Nombre" @update:model-value="clearError('Nombre')" />
          </div>
          <div class="col-12 col-sm-6">
            <q-input v-model="form.Apellido" dark outlined label="Apellido *" :error="Boolean(errors.Apellido)" :error-message="errors.Apellido" @update:model-value="clearError('Apellido')" />
          </div>
          <div class="col-12">
            <q-input v-model="form.Telefono" dark outlined label="Teléfono / WhatsApp *" :error="Boolean(errors.Telefono)" :error-message="errors.Telefono" @update:model-value="clearError('Telefono')" />
          </div>
          <div class="col-12">
            <q-input v-model="form.Direccion_envio" dark outlined label="Dirección (opcional)" :error="Boolean(errors.Direccion_envio)" :error-message="errors.Direccion_envio" @update:model-value="clearError('Direccion_envio')" />
          </div>
          <div class="col-12">
            <q-input v-model="form.email" dark outlined type="email" label="Correo *" :error="Boolean(errors.email)" :error-message="errors.email" @update:model-value="clearError('email')" />
          </div>
          <div class="col-12 col-sm-6">
            <q-input v-model="form.password" dark outlined type="password" label="Contraseña *" :error="Boolean(errors.password)" :error-message="errors.password" @update:model-value="clearError('password')" />
          </div>
          <div class="col-12 col-sm-6">
            <q-input v-model="form.password_confirmation" dark outlined type="password" label="Confirmar contraseña *" :error="Boolean(errors.password_confirmation)" :error-message="errors.password_confirmation" @update:model-value="clearError('password_confirmation')" />
          </div>
        </div>

        <q-btn class="full-width action-primary q-mt-lg" size="lg" label="Crear mi cuenta" :loading="loading" @click="register" />
        <q-btn flat class="full-width q-mt-sm" label="Ya tengo cuenta" to="/login" />
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useRouter } from 'vue-router'
import { publicApi } from '../services/api'
import { setClientSession } from '../services/auth'

const $q = useQuasar()
const router = useRouter()
const loading = ref(false)
const form = reactive({
  Nombre: '',
  Apellido: '',
  Telefono: '',
  Direccion_envio: '',
  email: '',
  password: '',
  password_confirmation: '',
})
const errors = reactive({
  Nombre: '',
  Apellido: '',
  Telefono: '',
  Direccion_envio: '',
  email: '',
  password: '',
  password_confirmation: '',
  general: '',
})

const clearError = (field) => {
  errors[field] = ''
  errors.general = ''
}

const validate = () => {
  Object.keys(errors).forEach((key) => { errors[key] = '' })

  if (!form.Nombre.trim()) errors.Nombre = 'El nombre es obligatorio.'
  if (!form.Apellido.trim()) errors.Apellido = 'El apellido es obligatorio.'
  if (!form.Telefono.trim()) errors.Telefono = 'El teléfono o WhatsApp es obligatorio.'

  const email = form.email.trim()
  if (!email) errors.email = 'El correo es obligatorio.'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.email = 'El correo no tiene un formato válido.'

  if (!form.password) errors.password = 'La contraseña es obligatoria.'
  else if (form.password.length < 8) errors.password = 'La contraseña debe tener al menos 8 caracteres.'

  if (!form.password_confirmation) errors.password_confirmation = 'Debes confirmar la contraseña.'
  else if (form.password !== form.password_confirmation) errors.password_confirmation = 'Las contraseñas no coinciden.'

  // Dirección es opcional: solo se valida si el usuario escribió algo.
  if (form.Direccion_envio && form.Direccion_envio.length > 500) {
    errors.Direccion_envio = 'La dirección no debe superar 500 caracteres.'
  }

  return !Object.entries(errors).some(([key, value]) => key !== 'general' && Boolean(value))
}

const register = async () => {
  if (!validate()) return

  loading.value = true
  try {
    const { data } = await publicApi.post('/auth/cliente/registro', {
      ...form,
      Nombre: form.Nombre.trim(),
      Apellido: form.Apellido.trim(),
      Telefono: form.Telefono.trim(),
      email: form.email.trim().toLowerCase(),
    })
    setClientSession(data)
    $q.notify({ type: 'positive', message: 'Cuenta creada correctamente.' })
    await router.replace('/mi-cuenta')
  } catch (e) {
    const backendErrors = e.validationErrors || {}
    Object.entries(backendErrors).forEach(([field, message]) => {
      if (field in errors) errors[field] = message
    })
    if (!Object.values(backendErrors).length) errors.general = e.userMessage || 'No se pudo crear la cuenta.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.store-page { min-height: 75vh; background: #07101d; }
.auth-card { width: 650px; max-width: 96vw; background: #0d1828; border: 1px solid #1e3954; border-radius: 20px; }
.error-banner { background: rgba(185, 28, 28, .18); border: 1px solid rgba(248, 113, 113, .45); color: #fecaca; }
</style>
