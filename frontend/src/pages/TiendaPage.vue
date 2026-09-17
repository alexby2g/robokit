<template>
  <q-page class="q-pa-md cyber-page-container">
    <div style="max-width: 1200px; margin: 0 auto">
      <!-- ============================= -->
      <!-- ENCABEZADO DE LA TIENDA -->
      <!-- ============================= -->

      <div class="cyber-dash-header q-pa-lg q-mb-lg">
        <div class="row items-center justify-between q-col-gutter-md">
          <div class="col-12 col-md-8">
            <div class="row items-center q-gutter-x-xs q-mb-xs">
              <span class="text-caption text-cyan-4 font-mono"> // ROBOTICA STORE </span>

              <span class="text-grey-6 font-mono">•</span>

              <span class="text-caption text-pink-4 font-mono"> STORE: ONLINE </span>
            </div>

            <div class="text-h3 text-weight-bolder text-white cyber-title q-mb-sm">
              CATÁLOGO
              <span style="color: var(--neon-pink)"> CYBERNETIC </span>
            </div>

            <p class="q-mb-none text-dim font-rajdhani">
              Explora nuestro catálogo de kits, componentes y sistemas de robótica disponibles.
            </p>
          </div>

          <div class="col-12 col-md-4 flex flex-center md:justify-end">
            <q-btn
              outline
              class="btn-view-neon q-px-lg"
              icon="sync"
              label="SINCRONIZAR"
              @click="sincronizarServidor"
            />
          </div>
        </div>
      </div>

      <!-- ============================= -->
      <!-- INFORMACIÓN DE LA TIENDA -->
      <!-- ============================= -->

      <div class="row q-col-gutter-md q-mb-lg">
        <div class="col-12 col-sm-4">
          <div class="stat-card border-cyan">
            <div class="row items-center justify-between">
              <span class="text-caption font-mono text-cyan-4"> PRODUCTOS </span>

              <q-icon name="inventory_2" color="cyan-4" size="20px" />
            </div>

            <div class="text-h4 text-weight-bold text-white q-my-xs">
              {{ productos.length }}
            </div>

            <div class="text-caption text-grey-5 font-mono">Productos disponibles</div>
          </div>
        </div>

        <div class="col-12 col-sm-4">
          <div class="stat-card border-pink">
            <div class="row items-center justify-between">
              <span class="text-caption font-mono text-pink-4"> ESTADO </span>

              <q-icon name="cloud_done" color="pink-4" size="20px" />
            </div>

            <div class="text-h4 text-weight-bold text-pink-4 q-my-xs">ONLINE</div>

            <div class="text-caption text-grey-5 font-mono">Conectado al servidor</div>
          </div>
        </div>

        <div class="col-12 col-sm-4">
          <div class="stat-card border-purple">
            <div class="row items-center justify-between">
              <span class="text-caption font-mono text-purple-4"> SISTEMA </span>

              <q-icon name="security" color="purple-4" size="20px" />
            </div>

            <div class="text-h4 text-weight-bold text-purple-4 q-my-xs">SECURE</div>

            <div class="text-caption text-grey-5 font-mono">Canal protegido</div>
          </div>
        </div>
      </div>

      <!-- ============================= -->
      <!-- TÍTULO DEL CATÁLOGO -->
      <!-- ============================= -->

      <div class="row items-center justify-between q-mb-md">
        <div class="cyber-title text-h6 text-cyan-3 flex items-center">
          <q-icon name="bolt" class="q-mr-xs text-pink-5" />

          KITS Y COMPONENTES
        </div>

        <div class="text-caption text-grey-6 font-mono">
          {{ productos.length }} ITEMS DETECTADOS
        </div>
      </div>

      <!-- ============================= -->
      <!-- CARGANDO -->
      <!-- ============================= -->

      <div v-if="cargando" class="row justify-center q-pa-xl">
        <div class="column items-center">
          <q-spinner-orbit color="cyan-4" size="60px" />

          <div class="text-cyan-4 font-mono q-mt-md">SYNCHRONIZING INVENTORY...</div>
        </div>
      </div>

      <!-- ============================= -->
      <!-- PRODUCTOS -->
      <!-- ============================= -->

      <div v-else-if="productos.length > 0" class="row q-col-gutter-md">
        <div
          v-for="prod in productos"
          :key="'store-' + prod.id"
          class="col-12 col-sm-6 col-md-4 col-lg-3"
        >
          <q-card
            class="product-card full-height column justify-between"
            @click="abrirDetalle(prod)"
          >
            <!-- IMAGEN -->

            <div>
              <div class="card-img-wrapper">
                <q-img
                  :src="getImagenUrl(prod)"
                  height="180px"
                  fit="contain"
                  style="background-color: #03060d; padding: 8px"
                >
                  <template v-slot:error>
                    <div class="absolute-full flex flex-center text-cyan-5 column bg-dark">
                      <q-icon name="smart_toy" size="50px" color="cyan-4" />

                      <span class="text-caption font-mono q-mt-xs"> IMAGE_NULL </span>
                    </div>
                  </template>
                </q-img>

                <div class="sys-tag">AVAILABLE</div>
              </div>

              <!-- INFORMACIÓN -->

              <q-card-section class="q-pa-md">
                <div class="text-subtitle1 text-weight-bold cyber-title text-white ellipsis">
                  {{ prod.Nombre || prod.nombre }}
                </div>

                <div class="text-caption text-grey-5 q-mt-sm line-clamp-2 caption-text">
                  {{ prod.Descripcion || prod.descripcion }}
                </div>
              </q-card-section>
            </div>

            <!-- PRECIO -->

            <q-card-section class="q-pa-md flex justify-between items-center">
              <span class="price-neon text-weight-bold">
                Bs.
                {{ parseFloat(prod.Precio || prod.precio || 0).toFixed(2) }}
              </span>

              <q-btn
                dense
                flat
                label="VER"
                color="cyan-4"
                class="btn-view-neon q-px-sm"
                @click.stop="abrirDetalle(prod)"
              />
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- ============================= -->
      <!-- SIN PRODUCTOS -->
      <!-- ============================= -->

      <div v-else class="text-center q-pa-xl border-cyan bg-dark-card">
        <q-icon name="wifi_off" size="48px" color="pink-5" />

        <div class="text-h6 q-mt-sm cyber-title text-pink-4">CATÁLOGO NO DISPONIBLE</div>

        <div class="text-caption font-mono text-grey-5 q-mt-sm">
          No se encontraron productos en el servidor.
        </div>
      </div>
    </div>

    <!-- ============================= -->
    <!-- MODAL DETALLE -->
    <!-- ============================= -->

    <q-dialog v-model="dialogoDetalle">
      <q-card
        style="width: 450px; max-width: 90vw; border-radius: 0"
        class="dialog-dark text-white"
      >
        <!-- IMAGEN -->

        <q-img
          :src="getImagenUrl(productoSeleccionado)"
          fit="contain"
          height="240px"
          style="
            background-color: #03060d;
            padding: 16px;
            border-bottom: 1px solid rgba(0, 240, 255, 0.3);
          "
        >
          <template v-slot:error>
            <div
              class="absolute-full flex flex-center text-cyan-4 column"
              style="background-color: #080d1a"
            >
              <q-icon name="smart_toy" size="60px" color="cyan-4" />

              <span class="text-caption font-mono q-mt-xs"> SYS_PREVIEW_NULL </span>
            </div>
          </template>
        </q-img>

        <!-- INFORMACIÓN -->

        <q-card-section>
          <div class="text-h6 text-weight-bold cyber-title text-cyan-3">
            {{
              productoSeleccionado ? productoSeleccionado.Nombre || productoSeleccionado.nombre : ''
            }}
          </div>

          <div class="text-h6 price-neon text-weight-bolder q-my-xs">
            Bs.
            {{
              productoSeleccionado
                ? parseFloat(
                    productoSeleccionado.Precio || productoSeleccionado.precio || 0,
                  ).toFixed(2)
                : '0.00'
            }}
          </div>

          <p
            v-if="
              productoSeleccionado &&
              (productoSeleccionado.Descripcion || productoSeleccionado.descripcion)
            "
            class="text-body2 text-grey-4 q-mt-md"
          >
            {{ productoSeleccionado.Descripcion || productoSeleccionado.descripcion }}
          </p>
        </q-card-section>

        <!-- BOTONES -->

        <q-card-actions
          align="between"
          style="
            background-color: rgba(5, 7, 13, 0.95);
            border-top: 1px solid rgba(0, 240, 255, 0.2);
          "
        >
          <q-btn flat label="CERRAR HUD" color="cyan-4" class="btn-view-neon" v-close-popup />

          <q-btn
            unelevated
            class="btn-buy-neon q-px-md"
            icon="shopping_cart"
            label="ADQUIRIR"
            @click="comprarProducto(productoSeleccionado)"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import api from '../services/api'
import { productImage } from '../utils/media'

const $q = useQuasar()

// =====================================
// ESTADOS
// =====================================

const productos = ref([])

const cargando = ref(true)

const dialogoDetalle = ref(false)

const productoSeleccionado = ref(null)

// =====================================
// IMÁGENES DE RESPALDO
// =====================================

const imagenesRespaldo = [
  'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?auto=format&fit=crop&w=600&q=80',

  'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&q=80',

  'https://images.unsplash.com/photo-1508739773434-c26b3d09e071?auto=format&fit=crop&w=600&q=80',

  'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=600&q=80',

  'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=600&q=80',

  'https://images.unsplash.com/photo-1531297484001-80022131f5a1?auto=format&fit=crop&w=600&q=80',
]

// =====================================
// CARGAR PRODUCTOS
// =====================================

const cargarProductos = async () => {
  cargando.value = true

  try {
    const res = await api.get('/productos')

    if (res.data && res.data.OK) {
      productos.value = res.data.productos
    } else if (Array.isArray(res.data)) {
      productos.value = res.data
    } else if (res.data && res.data.productos) {
      productos.value = res.data.productos
    }
  } catch (err) {
    console.warn('API Laravel no detectada.', err)

    // Catálogo local de respaldo

    productos.value = [
      {
        id: 101,
        Nombre: 'CYBER-ARM MK-IV',
        Descripcion:
          'Prótesis de respuesta neuronal de alta frecuencia con recubrimiento de titanio.',
        Precio: 1450.0,
        imagenes: [
          {
            ruta: 'productos/cyber-arm.png',
          },
        ],
      },

      {
        id: 102,
        Nombre: 'INTERFACE NEURAL V2',
        Descripcion: 'Bio-chip implantable de sincronización cognitiva directa.',
        Precio: 890.5,
        imagenes: [],
      },

      {
        id: 103,
        Nombre: 'OPTIC HUD SCANNER',
        Descripcion: 'Visor táctico de realidad aumentada con análisis de amenazas en tiempo real.',
        Precio: 320.0,
        imagenes: [],
      },

      {
        id: 104,
        Nombre: 'PROCESADOR CUÁNTICO N-1',
        Descripcion: 'Unidad central de procesamiento de matriz de plasma.',
        Precio: 2100.0,
        imagenes: [],
      },
    ]
  } finally {
    cargando.value = false
  }
}

// =====================================
// SINCRONIZAR
// =====================================

const sincronizarServidor = () => {
  cargarProductos()

  $q.notify({
    message: 'Sincronización con el servidor ejecutada',

    color: 'dark',

    textColor: 'cyan-4',

    icon: 'sync',

    position: 'bottom-right',
  })
}

// =====================================
// RESOLVER IMAGEN
// =====================================

const getImagenUrl = (prod) => productImage(prod) || imagenesRespaldo[Math.abs(parseInt(prod?.id || 0)) % imagenesRespaldo.length]

// =====================================
// ABRIR DETALLE
// =====================================

const abrirDetalle = (prod) => {
  productoSeleccionado.value = prod

  dialogoDetalle.value = true
}

// =====================================
// COMPRAR
// =====================================

const comprarProducto = (prod) => {
  if (!prod) return

  dialogoDetalle.value = false

  $q.notify({
    message: 'COMPONENTE ADQUIRIDO: ' + (prod.Nombre || prod.nombre),

    color: 'pink-10',

    textColor: 'cyan-2',

    icon: 'shopping_cart',

    position: 'bottom-right',
  })
}

// =====================================
// INICIO
// =====================================

onMounted(() => {
  cargarProductos()
})
</script>

<style scoped>
:root {
  --neon-cyan: #38bdf8;
  --neon-pink: #ec4899;
  --neon-purple: #8b5cf6;
  --neon-yellow: #ffe600;
  --neon-green: #10b981;
}

.cyber-page-container {
  background-color: #030712;
  min-height: 100vh;
}

.font-mono {
  font-family: 'Share Tech Mono', monospace;
}

.font-rajdhani {
  font-family: 'Rajdhani', sans-serif;
  font-size: 15px;
  line-height: 1.5;
}

.cyber-title {
  font-family: 'Orbitron', sans-serif;
  letter-spacing: 1.5px;
  text-transform: uppercase;
}

.text-dim {
  color: #94a3b8;
}

.caption-text {
  font-size: 11px;
}

.bg-dark-card {
  background: rgba(16, 23, 38, 0.6);
}

.cyber-dash-header {
  background: linear-gradient(135deg, rgba(16, 23, 38, 0.9) 0%, rgba(10, 15, 26, 0.95) 100%);

  border: 1px solid rgba(56, 189, 248, 0.3);

  border-left: 4px solid #38bdf8;

  box-shadow: 0 0 25px rgba(0, 0, 0, 0.6);

  clip-path: polygon(
    0 0,
    calc(100% - 18px) 0,
    100% 18px,
    100% 100%,
    18px 100%,
    0 calc(100% - 18px)
  );
}

.stat-card {
  background: rgba(16, 23, 38, 0.6);

  padding: 16px;

  border-radius: 8px;

  border-left-width: 4px;

  border-left-style: solid;
}

.border-cyan {
  border-left-color: #38bdf8;
}

.border-pink {
  border-left-color: #ec4899;
}

.border-purple {
  border-left-color: #8b5cf6;
}

.product-card {
  background-color: #101726 !important;

  border: 1px solid rgba(0, 240, 255, 0.25);

  position: relative;

  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);

  cursor: pointer;

  clip-path: polygon(
    0 0,
    calc(100% - 16px) 0,
    100% 16px,
    100% 100%,
    16px 100%,
    0 calc(100% - 16px)
  );
}

.product-card::before {
  content: '';

  position: absolute;

  top: 0;

  right: 0;

  width: 16px;

  height: 16px;

  background: #38bdf8;

  clip-path: polygon(100% 0, 0 0, 100% 100%);
}

.product-card:hover {
  transform: translateY(-5px) scale(1.01);

  border-color: #38bdf8;

  box-shadow:
    0 0 25px rgba(0, 240, 255, 0.4),
    inset 0 0 15px rgba(0, 240, 255, 0.1) !important;
}

.product-card:hover::before {
  background: #ec4899;
}

.card-img-wrapper {
  border-bottom: 1px solid rgba(0, 240, 255, 0.2);

  position: relative;

  overflow: hidden;
}

.sys-tag {
  position: absolute;

  bottom: 8px;

  left: 8px;

  background: rgba(5, 7, 13, 0.85);

  border: 1px solid #38bdf8;

  color: #38bdf8;

  font-family: 'Share Tech Mono', monospace;

  font-size: 10px;

  padding: 2px 6px;
}

.price-neon {
  color: #ffe600 !important;

  font-family: 'Orbitron', sans-serif;

  text-shadow: 0 0 10px rgba(255, 230, 0, 0.5);

  font-size: 0.9rem;
}

.btn-buy-neon {
  background: #ec4899 !important;

  color: #ffffff !important;

  font-family: 'Orbitron', sans-serif;

  font-weight: 700;

  font-size: 11px;

  letter-spacing: 1px;

  box-shadow: 0 0 12px rgba(255, 0, 85, 0.5);

  clip-path: polygon(8px 0, 100% 0, calc(100% - 8px) 100%, 0 100%);
}

.btn-view-neon {
  color: #38bdf8 !important;

  font-family: 'Share Tech Mono', monospace;

  border: 1px solid rgba(0, 240, 255, 0.3);
}

.dialog-dark {
  background-color: #0b0f19 !important;

  border: 2px solid #38bdf8;

  box-shadow: 0 0 35px rgba(0, 240, 255, 0.4);

  clip-path: polygon(
    0 0,
    calc(100% - 20px) 0,
    100% 20px,
    100% 100%,
    20px 100%,
    0 calc(100% - 20px)
  );
}

.line-clamp-2 {
  display: -webkit-box;

  -webkit-line-clamp: 2;

  line-clamp: 2;

  -webkit-box-orient: vertical;

  overflow: hidden;
}
</style>
