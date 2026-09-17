<template>
  <q-page class="page-wrap">
    <div class="page-head row items-start justify-between q-col-gutter-md">
      <div>
        <div class="eyebrow">SEGURIDAD Y EQUIPO</div>
        <h1>Personal y accesos</h1>
        <p>
          Crea cuentas para trabajadores y administradores. Todos ingresan desde el mismo
          <strong>/login</strong> y el sistema habilita las opciones según su rol.
        </p>
      </div>

      <div class="row q-gutter-sm">
        <q-btn outline color="cyan-5" icon="refresh" label="Actualizar" :loading="loading" @click="loadStaff" />
        <q-btn color="primary" icon="person_add" label="Nuevo acceso" @click="openCreate" />
      </div>
    </div>

    <div class="stats-grid q-mb-lg">
      <q-card flat bordered class="stat-card">
        <q-card-section>
          <div class="stat-label">Personal registrado</div>
          <div class="stat-value">{{ staff.length }}</div>
        </q-card-section>
      </q-card>
      <q-card flat bordered class="stat-card">
        <q-card-section>
          <div class="stat-label">Administradores activos</div>
          <div class="stat-value">{{ activeAdmins }}</div>
        </q-card-section>
      </q-card>
      <q-card flat bordered class="stat-card">
        <q-card-section>
          <div class="stat-label">Trabajadores activos</div>
          <div class="stat-value">{{ activeWorkers }}</div>
        </q-card-section>
      </q-card>
      <q-card flat bordered class="stat-card">
        <q-card-section>
          <div class="stat-label">Accesos desactivados</div>
          <div class="stat-value">{{ inactiveCount }}</div>
        </q-card-section>
      </q-card>
    </div>

    <q-card flat bordered class="access-card">
      <q-card-section class="row items-center q-col-gutter-md">
        <div class="col-12 col-md-7">
          <q-input
            v-model="search"
            outlined
            dense
            clearable
            debounce="250"
            placeholder="Buscar por nombre, correo o rol"
          >
            <template #prepend><q-icon name="search" /></template>
          </q-input>
        </div>
        <div class="col-12 col-md-5 text-md-right text-caption text-blue-grey-4">
          Solo un administrador puede gestionar estas cuentas.
        </div>
      </q-card-section>

      <q-separator dark />

      <q-table
        flat
        dark
        :rows="filteredStaff"
        :columns="columns"
        row-key="id"
        :loading="loading"
        :pagination="pagination"
        hide-pagination
        class="staff-table"
        no-data-label="Todavía no hay personal registrado."
      >
        <template #body-cell-name="props">
          <q-td :props="props">
            <div class="row items-center no-wrap q-gutter-sm">
              <q-avatar size="38px" color="blue-9" text-color="cyan-3" icon="person" />
              <div>
                <div class="text-weight-bold">{{ props.row.name }}</div>
                <div class="text-caption text-blue-grey-4">{{ props.row.email }}</div>
              </div>
            </div>
          </q-td>
        </template>

        <template #body-cell-role="props">
          <q-td :props="props">
            <q-chip
              dense
              square
              :color="roleColor(props.row.role)"
              text-color="white"
              :icon="roleIcon(props.row.role)"
            >
              {{ roleLabel(props.row.role) }}
            </q-chip>
          </q-td>
        </template>

        <template #body-cell-is_active="props">
          <q-td :props="props">
            <q-badge rounded :color="props.row.is_active ? 'positive' : 'grey-7'">
              {{ props.row.is_active ? 'Activo' : 'Desactivado' }}
            </q-badge>
          </q-td>
        </template>

        <template #body-cell-actions="props">
          <q-td :props="props">
            <div class="row justify-end q-gutter-xs no-wrap">
              <q-btn
                flat
                round
                dense
                color="cyan-4"
                icon="edit"
                @click="openEdit(props.row)"
              >
                <q-tooltip>Editar acceso</q-tooltip>
              </q-btn>
              <q-btn
                flat
                round
                dense
                :disable="isCurrentUser(props.row)"
                :color="props.row.is_active ? 'orange-5' : 'positive'"
                :icon="props.row.is_active ? 'person_off' : 'person_add_alt'"
                @click="toggleStatus(props.row)"
              >
                <q-tooltip>
                  {{ isCurrentUser(props.row) ? 'No puedes desactivar tu propia sesión' : (props.row.is_active ? 'Desactivar' : 'Activar') }}
                </q-tooltip>
              </q-btn>
            </div>
          </q-td>
        </template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialogOpen" persistent>
      <q-card class="dialog-card">
        <q-card-section class="row items-center justify-between">
          <div>
            <div class="text-h5 text-weight-bold">{{ editingId ? 'Editar acceso' : 'Crear acceso' }}</div>
            <div class="text-caption text-blue-grey-4">
              {{ editingId ? 'Actualiza los permisos o la contraseña.' : 'La persona podrá ingresar inmediatamente desde /login.' }}
            </div>
          </div>
          <q-btn flat round dense icon="close" @click="closeDialog" />
        </q-card-section>

        <q-separator dark />

        <q-card-section class="q-gutter-md">
          <q-input
            v-model="form.name"
            outlined
            dark
            label="Nombre *"
            :error="Boolean(errors.name)"
            :error-message="errors.name"
            @update:model-value="clearError('name')"
          />

          <q-input
            v-model.trim="form.email"
            outlined
            dark
            type="email"
            label="Correo *"
            autocomplete="off"
            :error="Boolean(errors.email)"
            :error-message="errors.email"
            @update:model-value="clearError('email')"
          />

          <q-select
            v-model="form.role"
            outlined
            dark
            emit-value
            map-options
            label="Rol *"
            :options="roleOptions"
            :error="Boolean(errors.role)"
            :error-message="errors.role"
            @update:model-value="clearError('role')"
          >
            <template #option="scope">
              <q-item v-bind="scope.itemProps">
                <q-item-section avatar><q-icon :name="roleIcon(scope.opt.value)" /></q-item-section>
                <q-item-section>
                  <q-item-label>{{ scope.opt.label }}</q-item-label>
                  <q-item-label caption>{{ scope.opt.description }}</q-item-label>
                </q-item-section>
              </q-item>
            </template>
          </q-select>

          <q-toggle
            v-model="form.is_active"
            color="positive"
            label="Acceso activo"
            :disable="editingCurrentUser"
          />

          <q-separator dark spaced />

          <div class="text-subtitle2">
            {{ editingId ? 'Cambiar contraseña (opcional)' : 'Contraseña inicial *' }}
          </div>

          <q-input
            v-model="form.password"
            outlined
            dark
            :type="showPassword ? 'text' : 'password'"
            :label="editingId ? 'Nueva contraseña' : 'Contraseña *'"
            autocomplete="new-password"
            :error="Boolean(errors.password)"
            :error-message="errors.password"
            @update:model-value="clearError('password')"
          >
            <template #append>
              <q-icon
                class="cursor-pointer"
                :name="showPassword ? 'visibility_off' : 'visibility'"
                @click="showPassword = !showPassword"
              />
            </template>
          </q-input>

          <q-input
            v-model="form.password_confirmation"
            outlined
            dark
            :type="showPassword ? 'text' : 'password'"
            :label="editingId ? 'Confirmar nueva contraseña' : 'Confirmar contraseña *'"
            autocomplete="new-password"
            :error="Boolean(errors.password_confirmation)"
            :error-message="errors.password_confirmation"
            @update:model-value="clearError('password_confirmation')"
          />

          <q-banner v-if="editingCurrentUser" dense rounded class="bg-blue-grey-9 text-blue-grey-2">
            <template #avatar><q-icon name="info" color="cyan-4" /></template>
            Esta es tu cuenta actual. Por seguridad no puedes desactivarte ni quitarte el rol de administrador si eres el último administrador activo.
          </q-banner>
        </q-card-section>

        <q-separator dark />

        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat label="Cancelar" @click="closeDialog" />
          <q-btn
            color="primary"
            icon="save"
            :label="editingId ? 'Guardar cambios' : 'Crear acceso'"
            :loading="saving"
            @click="save"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { adminApi } from '../services/api'
import { authState, refreshAdminSession } from '../services/auth'

const $q = useQuasar()
const loading = ref(false)
const saving = ref(false)
const dialogOpen = ref(false)
const editingId = ref(null)
const search = ref('')
const showPassword = ref(false)
const staff = ref([])
const serverRoles = ref([])
const errors = reactive({})

const pagination = { rowsPerPage: 0 }
const columns = [
  { name: 'name', label: 'PERSONA', field: 'name', align: 'left', sortable: true },
  { name: 'role', label: 'ROL', field: 'role', align: 'left', sortable: true },
  { name: 'is_active', label: 'ESTADO', field: 'is_active', align: 'left', sortable: true },
  { name: 'created_at', label: 'CREADO', field: 'created_at', align: 'left', format: (value) => formatDate(value) },
  { name: 'actions', label: '', field: 'id', align: 'right' },
]

const form = reactive({
  name: '',
  email: '',
  role: 'trabajador',
  is_active: true,
  password: '',
  password_confirmation: '',
})

const defaultRoles = [
  { value: 'admin', label: 'Administrador', description: 'Acceso total y gestión de personal.' },
  { value: 'trabajador', label: 'Trabajador', description: 'Acceso operativo al negocio, sin administrar accesos.' },
  { value: 'caja', label: 'Caja / ventas', description: 'Clientes, ventas, pedidos online y reportes.' },
  { value: 'almacen', label: 'Almacén', description: 'Productos, inventario, compras y pedidos online.' },
]

const roleOptions = computed(() => serverRoles.value.length ? serverRoles.value : defaultRoles)
const currentUserId = computed(() => Number(authState.adminUser?.id || 0))
const editingCurrentUser = computed(() => editingId.value && Number(editingId.value) === currentUserId.value)

const filteredStaff = computed(() => {
  const term = String(search.value || '').trim().toLowerCase()
  if (!term) return staff.value
  return staff.value.filter((person) => [person.name, person.email, roleLabel(person.role)]
    .some((value) => String(value || '').toLowerCase().includes(term)))
})

const activeAdmins = computed(() => staff.value.filter((person) => person.role === 'admin' && person.is_active).length)
const activeWorkers = computed(() => staff.value.filter((person) => person.role !== 'admin' && person.is_active).length)
const inactiveCount = computed(() => staff.value.filter((person) => !person.is_active).length)

const roleLabel = (role) => ({
  admin: 'Administrador',
  trabajador: 'Trabajador',
  caja: 'Caja / ventas',
  almacen: 'Almacén',
}[role] || role)

const roleIcon = (role) => ({
  admin: 'admin_panel_settings',
  trabajador: 'badge',
  caja: 'point_of_sale',
  almacen: 'inventory_2',
}[role] || 'person')

const roleColor = (role) => ({
  admin: 'deep-purple-6',
  trabajador: 'blue-7',
  caja: 'teal-7',
  almacen: 'orange-8',
}[role] || 'grey-7')

function formatDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString('es-BO')
}

const isCurrentUser = (person) => Number(person.id) === currentUserId.value

function clearErrors() {
  Object.keys(errors).forEach((key) => delete errors[key])
}

function clearError(field) {
  delete errors[field]
}

function resetForm() {
  form.name = ''
  form.email = ''
  form.role = 'trabajador'
  form.is_active = true
  form.password = ''
  form.password_confirmation = ''
  showPassword.value = false
  clearErrors()
}

function openCreate() {
  editingId.value = null
  resetForm()
  dialogOpen.value = true
}

function openEdit(person) {
  editingId.value = person.id
  resetForm()
  form.name = person.name || ''
  form.email = person.email || ''
  form.role = person.role || 'trabajador'
  form.is_active = Boolean(person.is_active)
  dialogOpen.value = true
}

function closeDialog() {
  dialogOpen.value = false
  editingId.value = null
  resetForm()
}

function validateLocal() {
  clearErrors()
  if (!form.name.trim()) errors.name = 'El nombre es obligatorio.'
  if (!form.email.trim()) errors.email = 'El correo es obligatorio.'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) errors.email = 'El correo no tiene un formato válido.'
  if (!form.role) errors.role = 'Debes seleccionar un rol.'

  const passwordRequired = !editingId.value
  if (passwordRequired && !form.password) errors.password = 'La contraseña es obligatoria.'
  if (form.password && form.password.length < 8) errors.password = 'La contraseña debe tener al menos 8 caracteres.'
  if (form.password && form.password !== form.password_confirmation) {
    errors.password_confirmation = 'Las contraseñas no coinciden.'
  }

  return Object.keys(errors).length === 0
}

async function loadStaff() {
  loading.value = true
  try {
    const { data } = await adminApi.get('/personal')
    staff.value = data.personal || []
    serverRoles.value = data.roles || []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.userMessage || 'No se pudo cargar el personal.' })
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!validateLocal()) return
  saving.value = true
  clearErrors()

  const payload = {
    name: form.name.trim(),
    email: form.email.trim().toLowerCase(),
    role: form.role,
    is_active: Boolean(form.is_active),
    password: form.password || null,
    password_confirmation: form.password_confirmation || null,
  }

  try {
    let response
    if (editingId.value) {
      response = await adminApi.put(`/personal/${editingId.value}`, payload)
    } else {
      response = await adminApi.post('/personal', payload)
    }

    $q.notify({ type: 'positive', message: response.data?.mensaje || 'Acceso guardado.' })

    if (editingCurrentUser.value) {
      await refreshAdminSession()
    }

    closeDialog()
    await loadStaff()
  } catch (error) {
    Object.assign(errors, error.validationErrors || {})
    $q.notify({ type: 'negative', message: error.userMessage || 'Revisa los datos ingresados.' })
  } finally {
    saving.value = false
  }
}

function toggleStatus(person) {
  if (isCurrentUser(person)) return

  const action = person.is_active ? 'desactivar' : 'activar'
  $q.dialog({
    title: `${person.is_active ? 'Desactivar' : 'Activar'} acceso`,
    message: `¿Quieres ${action} el acceso de ${person.name}?`,
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      const { data } = await adminApi.put(`/personal/${person.id}/estado`)
      $q.notify({ type: 'positive', message: data.mensaje || 'Estado actualizado.' })
      await loadStaff()
    } catch (error) {
      $q.notify({ type: 'negative', message: error.userMessage || 'No se pudo cambiar el estado.' })
    }
  })
}

onMounted(loadStaff)
</script>

<style scoped>
.page-wrap {
  padding: 28px;
  background: #071321;
  color: #edf7ff;
  min-height: 100%;
}
.page-head h1 {
  margin: 4px 0 6px;
  font-size: clamp(30px, 4vw, 44px);
  line-height: 1.08;
  font-weight: 800;
}
.page-head p {
  max-width: 780px;
  margin: 0 0 22px;
  color: #91a7ba;
  font-size: 15px;
}
.eyebrow {
  color: #4dd8ef;
  font-size: 12px;
  letter-spacing: .13em;
  font-weight: 800;
}
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}
.stat-card,
.access-card,
.dialog-card {
  background: #0d1b2c;
  border-color: #1e3852;
  color: #eef7ff;
}
.stat-label {
  color: #8da5ba;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: .06em;
}
.stat-value {
  margin-top: 5px;
  font-size: 30px;
  line-height: 1;
  font-weight: 800;
}
.access-card {
  border-radius: 14px;
  overflow: hidden;
}
.staff-table {
  background: transparent;
}
.dialog-card {
  width: min(620px, 94vw);
  border: 1px solid #24415d;
  border-radius: 15px;
}
@media (max-width: 900px) {
  .page-wrap { padding: 18px; }
  .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 520px) {
  .stats-grid { grid-template-columns: 1fr; }
}
</style>
