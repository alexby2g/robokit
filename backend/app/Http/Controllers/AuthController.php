<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const ADMIN_ROLES = ['admin', 'trabajador', 'caja', 'almacen'];
    private const ALLOWED_ROLES = ['admin', 'trabajador', 'caja', 'almacen', 'cliente'];

    public function adminStatus()
    {
        return response()->json([
            'setup_required' => !User::query()->where('role', 'admin')->exists(),
        ]);
    }

    public function setupAdmin(Request $request)
    {
        if (User::query()->where('role', 'admin')->exists()) {
            return response()->json(['message' => 'El administrador inicial ya fue configurado.'], 409);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ], $this->authValidationMessages());

        $user = User::create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => $data['password'],
            'role' => 'admin',
            'is_active' => true,
        ]);

        return $this->tokenResponse($user, 'Administrador creado correctamente.', 'robokit-web');
    }

    /**
     * LOGIN ÚNICO ROBOKIT.
     * El mismo correo/contraseña sirve para admin, trabajador, caja, almacén y cliente.
     * El frontend decide a qué pantalla redirigir según user.role.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        return $this->attemptLogin($data['email'], $data['password']);
    }

    /** Compatibilidad con versiones anteriores del frontend. */
    public function adminLogin(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        return $this->attemptLogin($data['email'], $data['password'], self::ADMIN_ROLES);
    }

    /** Compatibilidad con versiones anteriores del frontend. */
    public function clientLogin(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        return $this->attemptLogin($data['email'], $data['password'], ['cliente']);
    }

    private function attemptLogin(string $email, string $password, ?array $allowedRoles = null)
    {
        $email = strtolower(trim($email));
        $allowedRoles ??= self::ALLOWED_ROLES;

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['No existe una cuenta registrada con ese correo.'],
            ]);
        }

        if (!in_array($user->role, $allowedRoles, true)) {
            return response()->json([
                'message' => 'Esta cuenta no tiene permisos para ingresar desde este acceso.',
                'errors' => ['email' => ['Esta cuenta no tiene permisos para ingresar desde este acceso.']],
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Tu cuenta está desactivada. Contacta al administrador.',
                'errors' => ['email' => ['Tu cuenta está desactivada. Contacta al administrador.']],
            ], 403);
        }

        if (!Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['La contraseña es incorrecta.'],
            ]);
        }

        $message = $user->role === 'cliente'
            ? 'Sesión iniciada correctamente.'
            : 'Acceso al sistema autorizado.';

        return $this->tokenResponse($user, $message, 'robokit-web');
    }

    public function clientRegister(Request $request)
    {
        $data = $request->validate([
            'Nombre' => ['required', 'string', 'max:100'],
            'Apellido' => ['required', 'string', 'max:100'],
            'Telefono' => ['required', 'string', 'max:30'],
            'Direccion_envio' => ['nullable', 'string', 'max:500'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ], [
            'Nombre.required' => 'El nombre es obligatorio.',
            'Nombre.max' => 'El nombre no debe superar 100 caracteres.',
            'Apellido.required' => 'El apellido es obligatorio.',
            'Apellido.max' => 'El apellido no debe superar 100 caracteres.',
            'Telefono.required' => 'El teléfono o WhatsApp es obligatorio.',
            'Telefono.max' => 'El teléfono no debe superar 30 caracteres.',
            'Direccion_envio.max' => 'La dirección no debe superar 500 caracteres.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'email.unique' => 'Ya existe una cuenta con ese correo. Inicia sesión.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no debe superar 100 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $email = strtolower(trim($data['email']));

        $result = DB::transaction(function () use ($data, $email) {
            $cliente = DB::table('usuario')
                ->where('Telefono', $data['Telefono'])
                ->orWhere('Email', $email)
                ->first();

            if ($cliente && User::query()->where('usuario_id', $cliente->id)->exists()) {
                throw ValidationException::withMessages(['email' => ['Ese cliente ya tiene una cuenta. Inicia sesión.']]);
            }

            if ($cliente) {
                $clienteId = (int) $cliente->id;
                DB::table('usuario')->where('id', $clienteId)->update([
                    'Nombre' => $data['Nombre'],
                    'Apellido' => $data['Apellido'],
                    'Telefono' => $data['Telefono'],
                    'Direccion_envio' => $data['Direccion_envio'] ?? $cliente->Direccion_envio,
                    'Email' => $email,
                ]);
            } else {
                $clienteId = (int) DB::table('usuario')->insertGetId([
                    'Nombre' => $data['Nombre'],
                    'Apellido' => $data['Apellido'],
                    'Telefono' => $data['Telefono'],
                    'Direccion_envio' => $data['Direccion_envio'] ?? null,
                    'Email' => $email,
                ]);
            }

            $user = User::create([
                'name' => trim($data['Nombre'].' '.$data['Apellido']),
                'email' => $email,
                'password' => $data['password'],
                'role' => 'cliente',
                'usuario_id' => $clienteId,
                'is_active' => true,
            ]);

            return [$user, $clienteId];
        });

        [$user] = $result;
        return $this->tokenResponse($user, 'Cuenta creada correctamente.', 'robokit-web');
    }

    public function me(Request $request)
    {
        return response()->json($this->userPayload($request->user()));
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function updateClientProfile(Request $request)
    {
        $user = $request->user();
        abort_if(!$user?->usuario_id, 422, 'La cuenta no está vinculada a un cliente.');

        $data = $request->validate([
            'Nombre' => ['required', 'string', 'max:100'],
            'Apellido' => ['required', 'string', 'max:100'],
            'Telefono' => ['required', 'string', 'max:30'],
            'Direccion_envio' => ['nullable', 'string', 'max:500'],
            'email' => [
                'required', 'email', 'max:190',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ], [
            'Nombre.required' => 'El nombre es obligatorio.',
            'Apellido.required' => 'El apellido es obligatorio.',
            'Telefono.required' => 'El teléfono es obligatorio.',
            'Direccion_envio.max' => 'La dirección no debe superar 500 caracteres.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'email.unique' => 'Ese correo ya está siendo usado por otra cuenta.',
        ]);

        DB::transaction(function () use ($data, $user) {
            DB::table('usuario')->where('id', $user->usuario_id)->update([
                'Nombre' => $data['Nombre'],
                'Apellido' => $data['Apellido'],
                'Telefono' => $data['Telefono'],
                'Direccion_envio' => $data['Direccion_envio'] ?? null,
                'Email' => strtolower(trim($data['email'])),
            ]);
            $user->update([
                'name' => trim($data['Nombre'].' '.$data['Apellido']),
                'email' => strtolower(trim($data['email'])),
            ]);
        });

        return response()->json([
            'message' => 'Tus datos fueron actualizados.',
            ...$this->userPayload($user->fresh()),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ], [
            'current_password.required' => 'La contraseña actual es obligatoria.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la nueva contraseña no coincide.',
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contraseña actual es incorrecta.'],
            ]);
        }

        $user->update(['password' => $data['password']]);
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();
        return response()->json(['message' => 'Contraseña actualizada.']);
    }

    public function clientOrders(Request $request)
    {
        $user = $request->user();
        abort_if(!$user?->usuario_id, 422, 'La cuenta no está vinculada a un cliente.');

        $pedidos = DB::table('pedido')
            ->where('id_usuario', $user->usuario_id)
            ->where('canal', 'Online')
            ->orderByDesc('id')
            ->get();

        foreach ($pedidos as $pedido) {
            $pedido->items = DB::table('pedido_producto as pp')
                ->join('producto as pr', 'pr.id', '=', 'pp.id_producto')
                ->select('pp.id_producto', 'pp.cantidad', 'pp.precio_unitario', 'pp.subtotal', 'pr.Nombre')
                ->where('pp.id_pedido', $pedido->id)
                ->get();

            foreach ($pedido->items as $item) {
                $item->capacitaciones = Schema::hasTable('curso_producto')
                    ? DB::table('curso_producto as cp')
                        ->join('cursos as cu', 'cu.id', '=', 'cp.curso_id')
                        ->where('cp.producto_id', $item->id_producto)
                        ->where('cu.estado', 'activo')
                        ->select('cu.id', 'cu.titulo', 'cu.descripcion')
                        ->orderByDesc('cu.id')->get()
                    : collect();
            }

            $pedido->pago = Schema::hasTable('pagos')
                ? DB::table('pagos')->where('pedido_id', $pedido->id)->first()
                : null;
        }

        return response()->json(['pedidos' => $pedidos]);
    }

    private function tokenResponse(User $user, string $message, string $tokenName)
    {
        $token = $user->createToken($tokenName)->plainTextToken;
        return response()->json([
            'message' => $message,
            'token' => $token,
            ...$this->userPayload($user),
        ]);
    }

    private function userPayload(User $user): array
    {
        $cliente = null;
        if ($user->usuario_id) {
            $cliente = DB::table('usuario')->where('id', $user->usuario_id)->first();
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
                'usuario_id' => $user->usuario_id,
            ],
            'cliente' => $cliente,
        ];
    }

    private function authValidationMessages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }
}
