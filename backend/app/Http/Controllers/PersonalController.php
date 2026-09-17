<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersonalController extends Controller
{
    private const STAFF_ROLES = ['admin', 'trabajador', 'caja', 'almacen'];

    public function index()
    {
        $personal = User::query()
            ->whereIn('role', self::STAFF_ROLES)
            ->select('id', 'name', 'email', 'role', 'is_active', 'created_at', 'updated_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'OK' => true,
            'personal' => $personal,
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
            'role' => ['required', Rule::in(self::STAFF_ROLES)],
            'is_active' => ['required', 'boolean'],
        ], $this->messages());

        $user = User::create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => (bool) $data['is_active'],
            'usuario_id' => null,
        ]);

        return response()->json([
            'OK' => true,
            'mensaje' => 'Acceso creado correctamente.',
            'personal' => $this->payload($user),
        ], 201);
    }

    public function show(int $id)
    {
        $user = $this->findStaff($id);

        return response()->json([
            'OK' => true,
            'personal' => $this->payload($user),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $target = $this->findStaff($id);
        $current = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'email', 'max:190',
                Rule::unique('users', 'email')->ignore($target->id),
            ],
            'role' => ['required', Rule::in(self::STAFF_ROLES)],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'max:100', 'confirmed'],
        ], $this->messages());

        $newRole = $data['role'];
        $newActive = (bool) $data['is_active'];

        if ($current && (int) $current->id === (int) $target->id) {
            if (!$newActive) {
                throw ValidationException::withMessages([
                    'is_active' => ['No puedes desactivar tu propia cuenta mientras tienes la sesión iniciada.'],
                ]);
            }

            if ($target->role === 'admin' && $newRole !== 'admin') {
                throw ValidationException::withMessages([
                    'role' => ['No puedes quitarte a ti mismo el rol de administrador.'],
                ]);
            }
        }

        if ($this->isLastActiveAdmin($target) && ($newRole !== 'admin' || !$newActive)) {
            throw ValidationException::withMessages([
                'role' => ['Debe permanecer al menos un administrador activo en el sistema.'],
            ]);
        }

        $roleOrStatusChanged = $target->role !== $newRole || (bool) $target->is_active !== $newActive;

        DB::transaction(function () use ($target, $data, $newRole, $newActive, $roleOrStatusChanged, $current) {
            $target->name = trim($data['name']);
            $target->email = strtolower(trim($data['email']));
            $target->role = $newRole;
            $target->is_active = $newActive;

            if (!empty($data['password'])) {
                $target->password = $data['password'];
            }

            $target->save();

            if (($roleOrStatusChanged || !empty($data['password'])) && (!$current || (int) $current->id !== (int) $target->id)) {
                $target->tokens()->delete();
            }
        });

        return response()->json([
            'OK' => true,
            'mensaje' => 'Acceso actualizado correctamente.',
            'personal' => $this->payload($target->fresh()),
        ]);
    }

    public function toggle(Request $request, int $id)
    {
        $target = $this->findStaff($id);
        $current = $request->user();

        if ($current && (int) $current->id === (int) $target->id) {
            throw ValidationException::withMessages([
                'is_active' => ['No puedes desactivar tu propia cuenta mientras tienes la sesión iniciada.'],
            ]);
        }

        $newActive = !(bool) $target->is_active;
        if ($this->isLastActiveAdmin($target) && !$newActive) {
            throw ValidationException::withMessages([
                'is_active' => ['Debe permanecer al menos un administrador activo en el sistema.'],
            ]);
        }

        $target->update(['is_active' => $newActive]);
        if (!$newActive) {
            $target->tokens()->delete();
        }

        return response()->json([
            'OK' => true,
            'mensaje' => $newActive ? 'Acceso activado correctamente.' : 'Acceso desactivado correctamente.',
            'personal' => $this->payload($target->fresh()),
        ]);
    }

    private function findStaff(int $id): User
    {
        return User::query()
            ->whereIn('role', self::STAFF_ROLES)
            ->findOrFail($id);
    }

    private function isLastActiveAdmin(User $user): bool
    {
        if ($user->role !== 'admin' || !$user->is_active) {
            return false;
        }

        return User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->count() <= 1;
    }

    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    private function roleOptions(): array
    {
        return [
            [
                'value' => 'admin',
                'label' => 'Administrador',
                'description' => 'Acceso total, configuración y gestión de personal.',
            ],
            [
                'value' => 'trabajador',
                'label' => 'Trabajador',
                'description' => 'Operación diaria: clientes, productos, inventario, compras, ventas y pedidos.',
            ],
            [
                'value' => 'caja',
                'label' => 'Caja / ventas',
                'description' => 'Clientes, ventas, pedidos online y reportes.',
            ],
            [
                'value' => 'almacen',
                'label' => 'Almacén',
                'description' => 'Productos, categorías, inventario, compras y pedidos online.',
            ],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no debe superar 120 caracteres.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no debe superar 100 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'role.required' => 'Debes seleccionar un rol.',
            'role.in' => 'El rol seleccionado no es válido.',
            'is_active.required' => 'Debes indicar si la cuenta estará activa.',
            'is_active.boolean' => 'El estado de la cuenta no es válido.',
        ];
    }
}
