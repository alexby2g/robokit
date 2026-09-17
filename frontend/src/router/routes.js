const routes = [
  { path: '/', redirect: '/tienda' },

  {
    path: '/tienda',
    component: () => import('../layouts/PublicStoreLayout.vue'),
    children: [
      { path: '', name: 'tienda', component: () => import('../pages/CatalogoVentasPage.vue') },
    ],
  },
  {
    path: '/seguimiento',
    component: () => import('../layouts/PublicStoreLayout.vue'),
    children: [
      { path: '', name: 'seguimiento', component: () => import('../pages/SeguimientoPedidoPage.vue') },
      { path: ':codigo', name: 'seguimiento-codigo', component: () => import('../pages/SeguimientoPedidoPage.vue') },
    ],
  },
  {
    path: '/capacitacion',
    component: () => import('../layouts/PublicStoreLayout.vue'),
    children: [{ path: '', name: 'capacitacion-publica', component: () => import('../pages/CapacitacionPublicPage.vue') }],
  },
  {
    path: '/capacitacion/:id',
    component: () => import('../layouts/PublicStoreLayout.vue'),
    children: [{ path: '', name: 'curso-publico', component: () => import('../pages/CursoPublicoPage.vue') }],
  },
  {
    path: '/login',
    component: () => import('../layouts/PublicStoreLayout.vue'),
    children: [{ path: '', name: 'login', component: () => import('../pages/ClienteLoginPage.vue') }],
  },
  {
    path: '/registro',
    component: () => import('../layouts/PublicStoreLayout.vue'),
    children: [{ path: '', name: 'cliente-registro', component: () => import('../pages/ClienteRegistroPage.vue') }],
  },
  {
    path: '/mi-cuenta',
    component: () => import('../layouts/PublicStoreLayout.vue'),
    meta: { requiresClient: true },
    children: [{ path: '', name: 'mi-cuenta', component: () => import('../pages/MiCuentaPage.vue') }],
  },

  { path: '/admin/login', redirect: '/login' },
  { path: '/admin/setup', name: 'admin-setup', component: () => import('../pages/AdminSetupPage.vue') },
  {
    path: '/admin',
    component: () => import('../layouts/MainLayout.vue'),
    meta: { requiresAdmin: true },
    children: [
      { path: '', redirect: '/admin/dashboard' },
      { path: 'dashboard', name: 'dashboard', component: () => import('../pages/IndexPage.vue') },
      { path: 'catalogo', name: 'catalogo-admin', component: () => import('../pages/CatalogoAdminPage.vue'), meta: { roles: ['admin'] } },
      { path: 'clientes', name: 'clientes', component: () => import('../pages/UsuariosPage.vue'), meta: { roles: ['admin', 'trabajador', 'caja'] } },
      { path: 'usuarios', redirect: '/admin/clientes' },
      { path: 'productos', name: 'productos', component: () => import('../pages/ProductosPage.vue'), meta: { roles: ['admin', 'trabajador', 'almacen'] } },
      { path: 'categorias', name: 'categorias', component: () => import('../pages/CategoriasPage.vue'), meta: { roles: ['admin', 'trabajador', 'almacen'] } },
      { path: 'inventario', name: 'inventario', component: () => import('../pages/InventarioPage.vue'), meta: { roles: ['admin', 'trabajador', 'almacen'] } },
      { path: 'compras', name: 'compras', component: () => import('../pages/ComprasPage.vue'), meta: { roles: ['admin', 'trabajador', 'almacen'] } },
      { path: 'pedidos', name: 'ventas', component: () => import('../pages/PedidosPage.vue'), meta: { roles: ['admin', 'trabajador', 'caja'] } },
      { path: 'pedidos-online', name: 'pedidos-online', component: () => import('../pages/PedidosOnlinePage.vue'), meta: { roles: ['admin', 'trabajador', 'caja', 'almacen'] } },
      { path: 'pagos', name: 'pagos', component: () => import('../pages/PagosPage.vue'), meta: { roles: ['admin', 'trabajador', 'caja'] } },
      { path: 'reportes', name: 'reportes', component: () => import('../pages/ReportesPage.vue'), meta: { roles: ['admin', 'trabajador', 'caja'] } },
      { path: 'capacitacion', name: 'capacitacion', component: () => import('../pages/CapacitacionPage.vue'), meta: { roles: ['admin'] } },
      { path: 'personal', name: 'personal-accesos', component: () => import('../pages/PersonalAccesosPage.vue'), meta: { roles: ['admin'] } },
      { path: 'capacitacion/:id/editar', name: 'curso-detalle', component: () => import('../pages/CursoDetallePage.vue'), meta: { roles: ['admin'] } },
    ],
  },

  { path: '/:catchAll(.*)*', component: () => import('../pages/ErrorNotFound.vue') },
]

export default routes
