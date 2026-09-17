<template>
  <q-page padding class="bg-dark-neon">
    <!-- ENCABEZADO Y BOTÓN CREAR -->
    <div class="row items-center justify-between q-mb-lg">
      <div class="text-h5 text-cyan-3 text-weight-bold glow-title row items-center">
        <q-icon name="category" class="q-mr-sm" color="cyan-4" /> Categorías de Kits
      </div>
      <q-btn
        unelevated
        class="btn-create-neon"
        icon="add"
        label="Nueva Categoría"
        @click="abrirDialogo()"
      />
    </div>

    <!-- TARJETA CONTENEDORA DE CATEGORÍAS -->
    <q-card flat class="card-dark-neon">
      <q-inner-loading :showing="loading" class="bg-dark-neon">
        <q-spinner-gears size="50px" color="cyan-4" />
      </q-inner-loading>

      <q-list separator dark v-if="categorias.length > 0">
        <q-item v-for="cat in categorias" :key="cat.id" class="q-py-md item-hover-neon">
          <q-item-section avatar>
            <q-avatar color="cyan-10" text-color="cyan-3" icon="folder" />
          </q-item-section>

          <q-item-section>
            <q-item-label class="text-subtitle1 text-weight-bold text-slate-100">
              {{ cat.Nombre }}
            </q-item-label>
            <q-item-label caption class="text-slate-400">
              {{ cat.Descripcion || 'Sin descripción disponible' }}
            </q-item-label>
          </q-item-section>

          <q-item-section side>
            <div class="row q-gutter-xs">
              <q-btn
                outline
                dense
                color="cyan-4"
                icon="visibility"
                label="Productos"
                class="btn-action-neon"
                @click="verProductos(cat)"
              />
              <q-btn
                outline
                dense
                color="amber-4"
                icon="edit"
                label="Editar"
                class="btn-action-neon"
                @click="abrirDialogo(cat)"
              />
              <q-btn
                outline
                dense
                color="red-4"
                icon="delete"
                label="Eliminar"
                class="btn-action-neon"
                @click="eliminar(cat.id)"
              />
            </div>
          </q-item-section>
        </q-item>
      </q-list>

      <div v-else-if="!loading" class="text-center q-pa-xl text-slate-400">
        <q-icon name="folder_off" size="50px" color="cyan-6" />
        <div class="text-h6 q-mt-sm text-slate-300">No hay categorías registradas.</div>
      </div>
    </q-card>

    <!-- FORMULARIO DE CATEGORÍA (CREAR/EDITAR) NEÓN -->
    <q-dialog v-model="dialogo" persistent>
      <q-card style="width: 400px; max-width: 90vw; border-radius: 16px;" class="dialog-dark text-white">
        <q-card-section class="dialog-header-neon text-cyan-3">
          <div class="text-h6 text-weight-bold glow-title">
            {{ form.id ? 'Editar Categoría' : 'Nueva Categoría' }}
          </div>
        </q-card-section>

        <q-card-section class="column q-gutter-md q-pt-md">
          <q-input
            outlined
            dense
            dark
            color="cyan-4"
            v-model="form.Nombre"
            label="Nombre de Categoría *"
            :rules="[(val) => !!val || 'Requerido']"
          />
          <q-input
            outlined
            dense
            dark
            color="cyan-4"
            v-model="form.Descripcion"
            label="Descripción"
            type="textarea"
            rows="3"
          />
        </q-card-section>

        <q-separator dark style="background-color: #334155;" />

        <q-card-actions align="right" class="q-pa-md bg-dark-neon">
          <q-btn flat label="Cancelar" color="slate-400" v-close-popup />
          <q-btn
            unelevated
            label="Guardar"
            class="btn-create-neon"
            @click="guardar"
            :loading="saving"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- DIÁLOGO PARA MOSTRAR PRODUCTOS VINCULADOS -->
    <q-dialog v-model="dialogoProductos">
      <q-card style="width: 550px; max-width: 90vw;" class="column max-height-80vh dialog-dark text-white">
        <q-card-section class="dialog-header-neon row items-center justify-between text-cyan-3">
          <div class="text-h6 text-weight-bold glow-title">
            Kits en: {{ categoriaSeleccionada?.Nombre }}
          </div>
          <q-btn flat round dense icon="close" color="cyan-4" v-close-popup />
        </q-card-section>

        <!-- Barra de búsqueda agregada dentro del diálogo -->
        <q-card-section class="q-pa-sm" style="background-color: #0b0f19;">
          <q-input
            v-model="filtroTexto"
            outlined
            dense
            dark
            color="cyan-4"
            placeholder="Buscar por nombre de kit o componente..."
            clearable
            @clear="filtroTexto = ''"
          >
            <template v-slot:prepend>
              <q-icon name="filter_alt" color="cyan-4" />
            </template>
          </q-input>
        </q-card-section>

        <q-card-section class="col scroll q-pa-none" style="min-height: 200px; position: relative;">
          <q-inner-loading :showing="loadingProductos" class="bg-dark-neon">
            <q-spinner-gears size="40px" color="cyan-4" />
          </q-inner-loading>

          <!-- Renderizado de productos filtrados -->
          <q-list separator dark v-if="productosFiltrados.length > 0">
            <q-item v-for="prod in productosFiltrados" :key="prod.id" class="q-py-md items-center item-hover-neon">

              <!-- Miniatura Multimedia Relacional Dark -->
              <q-item-section avatar>
                <q-avatar square size="55px" class="bordered rounded-borders overflow-hidden shadow-1" style="background-color: #0b0f19;">
                  <q-img
                    :src="productImage(prod)"
                  >
                    <template v-slot:error>
                      <div class="absolute-full flex flex-center text-cyan-5" style="background-color: #0b0f19;">
                        <q-icon name="smart_toy" size="24px" />
                      </div>
                    </template>
                  </q-img>
                </q-avatar>
              </q-item-section>

              <!-- Detalles del Componente -->
              <q-item-section>
                <q-item-label class="text-weight-bold text-slate-100 text-subtitle2">{{ prod.Nombre }}</q-item-label>
                <q-item-label caption class="row items-center q-mt-xs">
                  <q-chip
                    dense
                    class="text-weight-bold"
                    :color="prod.Stock > 5 ? 'teal-10' : 'amber-10'"
                    :text-color="prod.Stock > 5 ? 'teal-2' : 'amber-2'"
                  >
                    Stock: {{ prod.Stock }} u.
                  </q-chip>
                </q-item-label>
              </q-item-section>

              <!-- Precio Neón -->
              <q-item-section side>
                <span class="text-h6 text-weight-bolder price-neon">
                  Bs. {{ parseFloat(prod.Precio).toFixed(2) }}
                </span>
              </q-item-section>
            </q-item>
          </q-list>

          <!-- Mensajes informativos contextuales -->
          <div v-else-if="!loadingProductos" class="text-center q-pa-xl text-slate-400">
            <template v-if="productosOriginales.length === 0">
              <q-icon name="precision_manufacturing" size="40px" color="cyan-6" />
              <div class="text-subtitle1 q-mt-sm text-slate-300">No hay productos vinculados a esta categoría.</div>
            </template>
            <template v-else>
              <q-icon name="find_in_page" size="40px" color="cyan-6" />
              <div class="text-subtitle1 q-mt-sm text-slate-300">Ningún producto coincide con la búsqueda.</div>
            </template>
          </div>
        </q-card-section>

        <q-separator dark style="background-color: #334155;" />

        <q-card-actions align="right" class="q-pa-sm bg-dark-neon">
          <q-btn flat label="Cerrar" color="cyan-4" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '../boot/axios'
import { useQuasar } from 'quasar'
import { productImage } from '../utils/media'

const $q = useQuasar()
const categorias = ref([])
const loading = ref(false)
const saving = ref(false)
const dialogo = ref(false)

const dialogoProductos = ref(false)
const loadingProductos = ref(false)
const categoriaSeleccionada = ref(null)

// Separamos la lista de la API de la lista visual mutada por el filtro
const productosOriginales = ref([])
const filtroTexto = ref('')

const form = ref({ id: null, Nombre: '', Descripcion: '' })

// PROPIEDAD COMPUTADA: Filtra reactivamente sobre la memoria del cliente
const productosFiltrados = computed(() => {
  if (!filtroTexto.value) {
    return productosOriginales.value
  }
  const busqueda = filtroTexto.value.toLowerCase().trim()
  return productosOriginales.value.filter((prod) => {
    return prod.Nombre ? prod.Nombre.toLowerCase().includes(busqueda) : false
  })
})

// 1. OBTENER LAS CATEGORÍAS (GET)
const cargarDatos = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/categorias')
    if (data.OK) {
      categorias.value = data.categorias
    }
  } catch (error) {
    console.error('Error cargando categorías:', error)
    $q.notify({
      color: 'negative',
      message: 'Error al conectar con el servidor de categorías',
      icon: 'report_problem'
    })
  } finally {
    loading.value = false
  }
}

// Consulta y asigna a productosOriginales
const verProductos = async (categoria) => {
  categoriaSeleccionada.value = categoria
  dialogoProductos.value = true
  loadingProductos.value = true
  productosOriginales.value = []
  filtroTexto.value = '' // Resetea el cuadro de búsqueda

  try {
    const { data } = await api.get('/productos')
    const todosLosProductos = data.productos || []

    productosOriginales.value = todosLosProductos.filter(
      (prod) => prod.id_categoria === categoria.id
    )
  } catch (error) {
    console.error('Error cargando productos de la categoría:', error)
    $q.notify({
      color: 'negative',
      message: 'No se pudieron recuperar los productos vinculados.',
      icon: 'error'
    })
  } finally {
    loadingProductos.value = false
  }
}

const abrirDialogo = (cat = null) => {
  if (cat) {
    form.value = { ...cat }
  } else {
    form.value = { id: null, Nombre: '', Descripcion: '' }
  }
  dialogo.value = true
}

// 2. CREAR O ACTUALIZAR (POST / PUT)
const guardar = async () => {
  if (!form.value.Nombre) {
    $q.notify({ color: 'warning', message: 'El nombre es obligatorio', icon: 'warning' })
    return
  }

  saving.value = true
  try {
    let respuesta
    if (form.value.id) {
      respuesta = await api.put(`/categorias/${form.value.id}`, form.value)
    } else {
      const datosParaEnviar = { ...form.value }
      delete datosParaEnviar.id
      respuesta = await api.post('/categorias', datosParaEnviar)
    }

    if (respuesta.data.OK) {
      $q.notify({ color: 'positive', message: respuesta.data.mensaje || 'Categoría guardada', icon: 'check' })
      dialogo.value = false
      cargarDatos()
    }
  } catch (error) {
    console.error('Error al guardar categoría:', error)
    const errorMsg = error.response?.data?.message || 'Error al procesar la solicitud en el servidor'
    $q.notify({ color: 'negative', message: errorMsg, icon: 'error' })
  } finally {
    saving.value = false
  }
}

// 3. ELIMINAR CATEGORÍA (DELETE)
const eliminar = (id) => {
  $q.dialog({
    title: 'Confirmar eliminación',
    message: '¿Está seguro de que desea eliminar esta categoría? Esto podría afectar a los productos vinculados.',
    ok: { label: 'Eliminar', color: 'negative', unelevated: true },
    cancel: { label: 'Cancelar', flat: true, color: 'grey-7' },
    persistent: true
  }).onOk(async () => {
    try {
      const { data } = await api.delete(`/categorias/${id}`)
      if (data.OK) {
        $q.notify({ color: 'positive', message: data.mensaje || 'Categoría eliminada', icon: 'delete' })
        cargarDatos()
      }
    } catch (error) {
      console.error('Error al eliminar categoría:', error)
      $q.notify({ color: 'negative', message: 'No se pudo eliminar la categoría', icon: 'error' })
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

/* Tarjeta Principal Dark */
.card-dark-neon {
  background-color: #1e293b !important;
  border: 1px solid #334155;
  border-radius: 16px;
  overflow: hidden;
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

/* Botones de acción transparentes neón */
.btn-action-neon {
  border-radius: 6px;
  transition: all 0.2s ease;
}

.btn-action-neon:hover {
  background-color: rgba(56, 189, 248, 0.1);
  box-shadow: 0 0 8px currentColor;
}

/* Efecto hover suave en items de la lista */
.item-hover-neon {
  transition: background-color 0.2s ease;
}

.item-hover-neon:hover {
  background-color: rgba(51, 65, 85, 0.5);
}

/* Ventanas Modales Dark */
.dialog-dark {
  background-color: #1e293b !important;
  border: 1px solid #38bdf8;
  box-shadow: 0 0 25px rgba(56, 189, 248, 0.3);
}

.dialog-header-neon {
  background-color: #0f172a;
  border-bottom: 1px solid #334155;
}

/* Precios Verde Neón */
.price-neon {
  color: #4ade80 !important;
  text-shadow: 0 0 8px rgba(74, 222, 128, 0.4);
}

/* Resplandores de Texto */
.glow-title {
  text-shadow: 0 0 10px rgba(56, 189, 248, 0.35);
}

.text-slate-100 { color: #f1f5f9; }
.text-slate-300 { color: #cbd5e1; }
.text-slate-400 { color: #94a3b8; }
</style>
