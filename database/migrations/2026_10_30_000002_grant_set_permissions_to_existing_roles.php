<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Existing roles get the set permission matching each product permission they hold.
     */
    private const MAP = [
        'view_products' => 'view_sets',
        'create_products' => 'create_sets',
        'edit_products' => 'edit_sets',
        'delete_products' => 'delete_sets',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (DB::table('roles')->get(['id', 'permissions']) as $role) {
            $permissions = json_decode($role->permissions ?? '[]', true) ?: [];

            foreach (self::MAP as $product => $set) {
                if (in_array($product, $permissions, true) && !in_array($set, $permissions, true)) {
                    $permissions[] = $set;
                }
            }

            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values($permissions))]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (DB::table('roles')->get(['id', 'permissions']) as $role) {
            $permissions = json_decode($role->permissions ?? '[]', true) ?: [];
            $permissions = array_values(array_diff($permissions, array_values(self::MAP)));

            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
        }
    }
};
