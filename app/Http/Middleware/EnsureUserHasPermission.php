<?php

namespace App\Http\Middleware;

use App\Support\Authorization\DepartmentScope;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * @param  array<int, string>  ...$permissions
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            return new JsonResponse([
                'success' => false,
                'status' => 401,
                'message' => __('auth.unauthenticated'),
                'data' => null,
                'errors' => null,
            ], 401);
        }

        if ($permissions === []) {
            return $next($request);
        }

        $departmentId = DepartmentScope::resolveDepartmentId($request);

        if (! $user->hasPermission($permissions, $departmentId)) {
            return new JsonResponse([
                'success' => false,
                'status' => 403,
                'message' => __('auth.forbidden'),
                'data' => null,
                'errors' => null,
            ], 403);
        }

        return $next($request);
    }
}
