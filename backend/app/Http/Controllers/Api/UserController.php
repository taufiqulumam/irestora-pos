<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureCanManageUsers($request);

        $users = User::with(['role:id,name', 'outlet:id,name'])
            ->when($request->user()->role->name !== 'admin', fn ($query) => $query->where('outlet_id', $request->user()->outlet_id))
            ->when($request->string('search')->trim()->value(), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->string('role_id')->value(), fn ($query, string $roleId) => $query->where('role_id', $roleId))
            ->latest()
            ->get();

        return ApiResponse::success('Users retrieved', UserResource::collection($users));
    }

    public function roles(Request $request): JsonResponse
    {
        $this->ensureCanManageUsers($request);

        $query = Role::query()->orderBy('name');
        if ($request->user()->role->name !== 'admin') {
            $query->whereIn('name', ['cashier', 'supervisor']);
        }

        return ApiResponse::success('Roles retrieved', $query->get(['id', 'name']));
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureCanManageUsers($request);

        $data = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'roleId' => ['required', 'uuid', 'exists:roles,id'],
            'outletId' => ['nullable', 'uuid', 'exists:outlets,id'],
        ]);

        $role = Role::findOrFail($data['roleId']);
        $this->ensureCanManageRole($request, $role);
        $this->ensureRoleOutletIsValid($request, $role, $data['outletId'] ?? null);

        $user = User::create([
            'full_name' => $data['fullName'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'role_id' => $role->id,
            'outlet_id' => $data['outletId'] ?? null,
            'is_active' => true,
        ]);

        return ApiResponse::success('User created', new UserResource($user->load(['role', 'outlet'])), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureCanManageUsers($request);

        $data = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:20'],
            'roleId' => ['required', 'uuid', 'exists:roles,id'],
            'outletId' => ['nullable', 'uuid', 'exists:outlets,id'],
            'isActive' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $role = Role::findOrFail($data['roleId']);
        $this->ensureCanManageRole($request, $role);
        $this->ensureTargetIsInScope($request, $user);
        $this->ensureRoleOutletIsValid($request, $role, $data['outletId'] ?? null);

        $user->fill([
            'full_name' => $data['fullName'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role_id' => $role->id,
            'outlet_id' => $data['outletId'] ?? null,
            'is_active' => $data['isActive'],
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return ApiResponse::success('User updated', new UserResource($user->load(['role', 'outlet'])));
    }

    private function ensureCanManageUsers(Request $request): void
    {
        abort_unless(
            $request->user()?->role?->name === 'admin' || $request->user()?->hasPermissionTo('user.view'),
            403,
            'Anda tidak memiliki izin mengelola pegawai.'
        );
    }

    private function ensureCanManageRole(Request $request, Role $role): void
    {
        $user = $request->user();
        if ($user->role->name === 'admin') {
            return;
        }

        $permission = 'user.create.' . $role->name;
        abort_unless(in_array($role->name, ['cashier', 'supervisor'], true) && $user->hasPermissionTo($permission), 403, 'Manager hanya dapat mengelola role kasir dan supervisor.');
    }

    private function ensureTargetIsInScope(Request $request, User $target): void
    {
        if ($request->user()->role->name !== 'admin') {
            abort_unless($target->outlet_id === $request->user()->outlet_id, 403, 'Pegawai berada di luar outlet Anda.');
        }
    }

    private function ensureRoleOutletIsValid(Request $request, Role $role, ?string $outletId): void
    {
        abort_if($role->name === 'admin' && $outletId !== null, 422, 'Admin role cannot be assigned to an outlet.');
        abort_if($role->name !== 'admin' && $outletId === null, 422, 'An outlet is required for this role.');

        if ($request->user()->role->name !== 'admin') {
            abort_unless($outletId === $request->user()->outlet_id, 403, 'Manager hanya dapat mengelola pegawai di outlet sendiri.');
        }
    }
}