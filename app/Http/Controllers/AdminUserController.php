<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminUserService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    protected $adminUserService;

    public function __construct(AdminUserService $adminUserService)
    {
        $this->adminUserService = $adminUserService;
    }

    /**
     * Liste de tous les utilisateurs (avec filtres et pagination)
     */
    public function index(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $query = User::with('roles');

        // Filtrage par recherche (nom ou email)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtrage par rôle
        if ($request->filled('role')) {
            $query->role($request->role); // method from Spatie\Permission\Traits\HasRoles
        }

        // Filtrage par statut
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Pagination
        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json(['users' => $users]);
    }

    /**
     * Bloquer / Débloquer un utilisateur
     */
    public function toggleBlock(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Administrateur', 'Super-Administrateur'])) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        if ($request->user()->id == $id) {
            return response()->json(['message' => 'Vous ne pouvez pas vous bloquer vous-même.'], 400);
        }

        $user = User::findOrFail($id);
        
        // Empêcher de bloquer un super-admin si on n'est pas super-admin
        if ($user->hasRole('Super-Administrateur') && !$request->user()->hasRole('Super-Administrateur')) {
            return response()->json(['message' => 'Vous ne pouvez pas bloquer un Super-Administrateur.'], 403);
        }

        try {
            $updatedUser = $this->adminUserService->toggleBlockStatus($user);
            $action = $updatedUser->status === 'blocked' ? 'user.blocked' : 'user.unblocked';
            $message = $updatedUser->status === 'blocked' ? 'Utilisateur bloqué avec succès.' : 'Utilisateur débloqué avec succès.';
            AuditLogService::log($action, $updatedUser, ['user_name' => $updatedUser->name, 'email' => $updatedUser->email]);
            return response()->json(['message' => $message, 'user' => $updatedUser->load('roles')]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la modification du statut.'], 500);
        }
    }

    /**
     * Modifier les rôles d'un utilisateur
     */
    public function syncRoles(Request $request, $id)
    {
        if (!$request->user()->hasRole('Super-Administrateur')) {
            return response()->json(['message' => 'Seul un Super-Administrateur peut modifier les rôles.'], 403);
        }

        if ($request->user()->id == $id) {
            return response()->json(['message' => 'Vous ne pouvez pas modifier vos propres rôles depuis cette interface.'], 400);
        }

        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name'
        ]);

        $user = User::findOrFail($id);

        try {
            $updatedUser = $this->adminUserService->syncRoles($user, $request->roles);
            AuditLogService::log('user.roles_changed', $updatedUser, ['user_name' => $updatedUser->name, 'new_roles' => $request->roles]);
            return response()->json(['message' => 'Rôles mis à jour avec succès.', 'user' => $updatedUser->load('roles')]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la mise à jour des rôles.'], 500);
        }
    }
}
