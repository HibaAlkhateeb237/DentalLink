<?php

namespace App\Http\Middleware;

use App\Support\Authorization\DepartmentScope;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  array<int, string>  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
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

        if ($roles === []) {
            return $next($request);
        }

        $departmentId = DepartmentScope::resolveDepartmentId($request);

        if (! $user->hasRole($roles, $departmentId)) {
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
