<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UsersTableColumnsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure users table has requested profile columns.
     */
    public function test_users_table_contains_birthdate_and_location_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('users', ['birthdate', 'location'])
        );
    }
}
