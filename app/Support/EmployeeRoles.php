<?php

namespace App\Support;

class EmployeeRoles
{
    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        return self::system();
    }

    /**
     * The four default employee roles assignable by a lab manager.
     *
     * @return list<string>
     */
    public static function system(): array
    {
        return [
            'receptionist',
            'lab_technician',
            'department_manager',
            'delivery',
        ];
    }

    /**
     * Roles that are NOT employee roles — these are never listed
     * or managed as employees.
     *
     * @return list<string>
     */
    public static function nonEmployeeRoles(): array
    {
        return [
            'doctor',
            'lab_manager',
            'system_admin',
        ];
    }
}
