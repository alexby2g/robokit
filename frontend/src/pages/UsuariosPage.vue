<template>
  <q-page padding class="bg-dark-neon">

    <!-- ENCABEZADO Y BOTÓN CREAR -->
    <div class="row items-center justify-between q-pa-md q-mb-lg card-dark-neon">
      <div class="text-h5 text-cyan-3 text-weight-bold glow-title row items-center">
        <q-avatar color="cyan-10" text-color="cyan-3" icon="people" class="q-mr-sm" size="42px" />
        <span>Directorio de Clientes</span>
      </div>
      <q-btn
        unelevated
        class="btn-create-neon"
        icon="person_add"
        label="Nuevo Registro"
        @click="abrirDialogo()"
      />
    </div>

    <!-- CARD PRINCIPAL DE CLIENTES -->
    <q-card flat class="card-dark-neon">
      <q-inner-loading :showing="loading" class="bg-dark-neon">
        <q-spinner-gears size="50px" color="cyan-4" />
      </q-inner-loading>

      <q-list separator dark v-if="usuarios.length > 0">
        <q-item v-for="user in usuarios" :key="user.id" class="q-py-md item-hover-neon">
          <q-item-section avatar>
            <q-avatar color="cyan-10" text-color="cyan-3" class="text-weight-bolder text-subtitle1 border-neon-subtle">
              {{ user.Nombre ? user.Nombre.charAt(0) : 'U' }}
            </q-avatar>
          </q-item-section>

          <q-item-section>
            <q-item-label class="text-subtitle1 text-weight-bold text-slate-100">
              {{ user.Nombre }} {{ user.Apellido }}
            </q-item-label>
            <q-item-label caption class="text-slate-400 q-mt-xs">
              <q-icon name="phone" size="xs" color="cyan-4" class="q-mr-xs" />
              Teléfono: {{ user.Telefono || 'No registrado' }}
            </q-item-label>
            <q-item-label caption class="text-slate-400">
              <q-icon name="place" size="xs" color="cyan-4" class="q-mr-xs" />
              Dirección: {{ user.Direccion_envio || 'Sin dirección registrada' }}
            </q-item-label>
          </q-item-section>

          <!-- SECCIÓN DE ACCIONES CRUD -->
          <q-item-section side>
            <div class="row q-gutter-xs items-center">

              <!-- 1. Botón Ver Detalle (Show) -->
              <q-btn
                flat
                round
                dense
                color="cyan-4"
                icon="visibility"
                class="btn-icon-hover"
                @click="verDetalle(user)"
              >
                <q-tooltip anchor="top middle" self="bottom middle" class="bg-slate-800 text-cyan-3 border-neon-subtle text-body2">
                  Ver detalles
                </q-tooltip>
              </q-btn>

              <!-- 2. Botón Editar -->
              <q-btn
                flat
                round
                dense
                color="amber-4"
                icon="edit"
                class="btn-icon-hover"
                @click="abrirDialogo(user)"
              >
                <q-tooltip anchor="top middle" self="bottom middle" class="bg-slate-800 text-amber-3 border-neon-subtle text-body2">
                  Editar cliente
                </q-tooltip>
              </q-btn>

              <!-- 3. Botón Eliminar -->
              <q-btn
                flat
                round
                dense
                color="red-4"
                icon="delete_outline"
                class="btn-icon-hover"
                @click="eliminar(user.id)"
              >
                <q-tooltip anchor="top middle" self="bottom middle" class="bg-slate-800 text-red-3 border-neon-subtle text-body2">
                  Eliminar registro
                </q-tooltip>
              </q-btn>

            </div>
          </q-item-section>
        </q-item>
      </q-list>

      <div v-else-if="!loading" class="text-center q-pa-xl text-slate-400">
        <q-icon name="people_hide" size="55px" color="cyan-6" />
        <div class="text-h6 q-mt-sm text-slate-300">No hay clientes registrados en el sistema.</div>
      </div>
    </q-card>

    <!-- DIÁLOGO PARA CREAR / EDITAR -->
    <q-dialog v-model="dialogo" persistent>
      <q-card style="width: 400px; max-width: 90vw; border-radius: 16px;" class="dialog-dark text-white">
        <q-card-section class="dialog-header-neon text-cyan-3">
          <div class="text-h6 text-weight-bold glow-title">
            {{ form.id ? 'Modificar Ficha de Cliente' : 'Registrar Nuevo Cliente' }}
          </div>
        </q-card-section>

        <q-card-section class="column q-gutter-md q-pt-md">
          <q-input outlined dense dark color="cyan-4" v-model="form.Nombre" label="Nombre(s) *" />
          <q-input outlined dense dark color="cyan-4" v-model="form.Apellido" label="Apellido(s) *" />
          <q-input outlined dense dark color="cyan-4" v-model="form.Telefono" label="Teléfono / Celular" type="tel" />
          <q-input
            outlined
            dense
            dark
            color="cyan-4"
            v-model="form.Direccion_envio"
            label="Dirección de Envío"
            type="textarea"
            rows="3"
          />
        </q-card-section>

        <q-separator dark style="background-color: #334155;" />

        <q-card-actions align="right" class="q-pa-md bg-dark-neon">
          <q-btn flat label="Cancelar" color="slate-400" v-close-popup />
          <q-btn
            unelevated
            label="Guardar Registro"
            class="btn-create-neon"
            @click="guardar"
            :loading="saving"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- DIÁLOGO DE VER DETALLE (SHOW) -->
    <q-dialog v-model="dialogoDetalle">
      <q-card style="width: 420px; max-width: 90vw; border-radius: 16px;" class="dialog-dark text-white">
        <q-card-section class="dialog-header-neon text-cyan-3 row items-center justify-between">
          <div class="text-h6 flex items-center text-weight-bold glow-title">
            <q-icon name="badge" class="q-mr-sm" color="cyan-4" /> Ficha del Cliente
          </div>
          <q-btn icon="close" flat round dense color="slate-400" v-close-popup />
        </q-card-section>

        <q-card-section class="q-pa-md" v-if="usuarioSeleccionado">
          <div class="text-center q-mb-md">
            <q-avatar size="70px" color="cyan-10" text-color="cyan-3" class="text-h4 text-weight-bolder border-neon-subtle">
              {{ usuarioSeleccionado.Nombre ? usuarioSeleccionado.Nombre.charAt(0) : 'U' }}
            </q-avatar>
            <div class="text-h6 text-weight-bold q-mt-sm text-slate-100">
              {{ usuarioSeleccionado.Nombre }} {{ usuarioSeleccionado.Apellido }}
            </div>
            <div class="text-caption text-cyan-4 text-weight-bold">ID Cliente: #{{ usuarioSeleccionado.id }}</div>
          </div>

          <q-separator dark style="background-color: #334155;" class="q-my-sm" />

          <q-list class="q-pt-sm" dark>
            <q-item class="q-px-none">
              <q-item-section avatar min-width="40px">
                <q-icon name="phone" color="cyan-4" size="sm" />
              </q-item-section>
              <q-item-section>
                <q-item-label caption class="text-slate-400">Teléfono / Celular</q-item-label>
                <q-item-label class="text-subtitle2 text-weight-medium text-slate-100">
                  {{ usuarioSeleccionado.Telefono || 'No registrado' }}
                </q-item-label>
              </q-item-section>
            </q-item>

            <q-item class="q-px-none">
              <q-item-section avatar min-width="40px">
                <q-icon name="place" color="cyan-4" size="sm" />
              </q-item-section>
              <q-item-section>
                <q-item-label caption class="text-slate-400">Dirección de Envío</q-item-label>
                <q-item-label class="text-subtitle2 text-weight-medium text-slate-100">
                  {{ usuarioSeleccionado.Direccion_envio || 'Sin dirección registrada' }}
                </q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>

        <q-separator dark style="background-color: #334155;" />

        <q-card-actions align="right" class="bg-dark-neon q-pa-sm">
          <q-btn flat label="Cerrar" color="slate-400" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>

  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../boot/axios'
import { useQuasar } from 'quasar'

const $q = useQuasar()
const usuarios = ref([])
const loading = ref(false)
const saving = ref(false)

// Estados para Crear/Editar
const dialogo = ref(false)
const form = ref({ id: null, Nombre: '', Apellido: '', Direccion_envio: '', Telefono: '' })

// Estados para Ver Detalle (Show)
const dialogoDetalle = ref(false)
const usuarioSeleccionado = ref(null)

const cargarDatos = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/usuarios')
    if (data.OK) {
      usuarios.value = data.usuarios
    }
  } catch (error) {
    console.error('Error al cargar usuarios:', error)
    $q.notify({
      color: 'negative',
      message: 'Error al conectar con el servidor',
      icon: 'report_problem'
    })
  } finally {
    loading.value = false
  }
}

const abrirDialogo = (usuario = null) => {
  if (usuario) {
    form.value = { ...usuario }
  } else {
    form.value = { id: null, Nombre: '', Apellido: '', Direccion_envio: '', Telefono: '' }
  }
  dialogo.value = true
}

const verDetalle = (usuario) => {
  usuarioSeleccionado.value = usuario
  dialogoDetalle.value = true
}

const guardar = async () => {
  if (!form.value.Nombre || !form.value.Apellido) {
    $q.notify({ color: 'warning', message: 'Por favor, rellene los campos obligatorios (*)', icon: 'warning' })
    return
  }

  saving.value = true
  try {
    let respuesta
    if (form.value.id) {
      respuesta = await api.put(`/usuarios/${form.value.id}`, form.value)
    } else {
      respuesta = await api.post('/usuarios', form.value)
    }

    if (respuesta.data.OK) {
      $q.notify({ color: 'positive', message: respuesta.data.mensaje || 'Operación exitosa', icon: 'check' })
      dialogo.value = false
      cargarDatos()
    }
  } catch (error) {
    console.error('Error al guardar:', error)
    const errorMsg = error.response?.data?.message || 'Error en el servidor al guardar'
    $q.notify({ color: 'negative', message: errorMsg, icon: 'error' })
  } finally {
    saving.value = false
  }
}

const eliminar = (id) => {
  $q.dialog({
    title: 'Confirmar eliminación',
    message: '¿Está seguro de que desea eliminar permanentemente este registro de cliente?',
    ok: { label: 'Eliminar Registro', color: 'negative', unelevated: true },
    cancel: { label: 'Cancelar', flat: true, color: 'grey-7' },
    persistent: true
  }).onOk(async () => {
    try {
      const { data } = await api.delete(`/usuarios/${id}`)
      if (data.OK) {
        $q.notify({ color: 'positive', message: data.mensaje, icon: 'delete' })
        cargarDatos()
      }
    } catch (error) {
      console.error('Error al eliminar:', error)
      $q.notify({ color: 'negative', message: 'No se pudo eliminar al usuario', icon: 'error' })
    }
  })
}

onMounted(() => cargarDatos())
</script>

<style scoped>
/* --- ESTILOS GLOBAL NEÓN DARK --- */

.bg-dark-neon {
  background-color: #0f172a;
}

/* Tarjetas Principales Dark */
.card-dark-neon {
  background-color: #1e293b !important;
  border: 1px solid #334155;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}

/* Botón Crear Neón */
.btn-create-neon {
  background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%) !important;
  color: #ffffff !important;
  font-weight: bold;
  box-shadow: 0 0 12px rgba(6, 182, 212, 0.4);
  border-radius: 8px;
}

.btn-create-neon:hover {
  box-shadow: 0 0 18px rgba(6, 182, 212, 0.7);
}

/* Hover suave en listas */
.item-hover-neon {
  transition: background-color 0.2s ease;
}

.item-hover-neon:hover {
  background-color: rgba(51, 65, 85, 0.5);
}

/* Botones de acción */
.btn-icon-hover {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.btn-icon-hover:hover {
  transform: scale(1.15);
}

/* Bordes y Luces Neón */
.border-neon-subtle {
  border: 1px solid #38bdf8;
}

/* Modal Ventana Dark */
.dialog-dark {
  background-color: #1e293b !important;
  border: 1px solid #38bdf8;
  box-shadow: 0 0 25px rgba(56, 189, 248, 0.3);
}

.dialog-header-neon {
  background-color: #0f172a;
  border-bottom: 1px solid #334155;
}

/* Brillos de Texto */
.glow-title {
  text-shadow: 0 0 10px rgba(56, 189, 248, 0.35);
}

.text-slate-100 { color: #f1f5f9; }
.text-slate-300 { color: #cbd5e1; }
.text-slate-400 { color: #94a3b8; }
.bg-slate-800 { background-color: #1e293b; }
</style>
