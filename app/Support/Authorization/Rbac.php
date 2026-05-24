<?php

namespace App\Support\Authorization;

final class Rbac
{
    /**
     * @return list<string>
     */
    public static function roles(): array
    {
        return [
            'doctor',
            'receptionist',
            'department_manager',
            'lab_technician',
            'lab_manager',
            'system_admin',
            'delivery',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function permissionsByRole(): array
    {
        $basePermissionsByRole = [
            'doctor' => [
                'orders.create',
                'orders.view-own',
                'orders.update-own',
            ],
            'receptionist' => [
                'orders.view',
                'orders.price',
                'delivery.assign',
                'payments.create',
                'payments.view',
                'payments.view-own',
            ],
            'department_manager' => [
                'departments.view',
                'tasks.view-department',
                'tasks.assign',
                'tasks.approve',
            ],
            'lab_technician' => [
                'tasks.view-assigned',
                'tasks.start',
                'tasks.finish',
            ],
            'lab_manager' => [
                'labs.view',
                'labs.manage',
                'departments.manage',
                'orders.view',
                'payments.manage',
                'reports.view',
            ],
            'delivery' => [
                'delivery.view-assigned',
                'delivery.update-status',
            ],
        ];

        $allPermissions = collect($basePermissionsByRole)
            ->flatten()
            ->merge([
                'orders.update',
                'orders.delete',
                'delivery.view',
                'delivery.assign',
                'delivery.update-any',
                'delivery.cancel',
                'users.assign-role',
            ])
            ->unique()
            ->values()
            ->all();

        return [
            ...$basePermissionsByRole,
            'system_admin' => $allPermissions,
        ];
    }

    /**
     * @return list<string>
     */
    public static function permissions(): array
    {
        $permissions = [];

        foreach (self::permissionsByRole() as $rolePermissions) {
            $permissions = [...$permissions, ...$rolePermissions];
        }

        return array_values(array_unique($permissions));
    }
}
