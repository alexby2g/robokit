# ROBOKIT — Production Ready v1

Esta carpeta parte de los frontend/backend reales enviados el 17/09/2026.

## Arquitectura objetivo
- GitHub: repositorio
- Vercel: `frontend/` (Quasar/Vue)
- Render: `backend/` (Laravel API)
- Neon: PostgreSQL

## Cambios preparados
1. `.env` local eliminado del paquete; solo quedan `.env.example` sin secretos.
2. Frontend usa `VITE_API_URL`.
3. CORS usa `FRONTEND_URL`.
4. Se añadió migración base para crear las tablas históricas en una base Neon vacía.
5. Se corrigieron expresiones SQL con columnas legacy en mayúscula para PostgreSQL.
6. `render.yaml` y `frontend/vercel.json` incluidos.
7. Registros y logs locales no se incluyen en Git.

## Antes del despliegue
### Backend local
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

### Frontend local
```bash
npm install
cp .env.example .env
# Para local cambia VITE_API_URL a http://127.0.0.1:8000/api
npm run dev
```

## Vercel
Root Directory: `frontend`
Build Command: `npm run build`
Output Directory: `dist/spa`
Variable: `VITE_API_URL=https://TU-RENDER.onrender.com/api`

## Render
Root Directory: `backend`
Puede usar `render.yaml`. Configura APP_KEY, APP_URL, FRONTEND_URL y credenciales Neon.

## Neon
Crear una base PostgreSQL vacía. El backend ahora incluye una migración base para las tablas legacy. Después ejecutar `php artisan migrate --force`.

## Importante: imágenes
La app sigue pudiendo servir imágenes desde `storage/app/public` mediante `/api/media/...`, pero el disco local de Render no debe considerarse almacenamiento permanente. Antes de producción final conviene mover imágenes/comprobantes a almacenamiento externo (Cloudinary o S3-compatible). Ese es el siguiente paso recomendado después de verificar Neon/Render/Vercel.
