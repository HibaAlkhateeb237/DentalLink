<?php

namespace App\Support;

class EmployeeRoles
{
    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        return [
            'receptionist',
            'lab_technician',
            'department_manager',
            'delivery',
        ];
    }
}
