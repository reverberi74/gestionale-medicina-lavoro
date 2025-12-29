<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base model for registry/control-plane tables.
 * Forces the "registry" connection regardless of database.default.
 */
abstract class RegistryModel extends Model
{
    protected $connection = 'registry';
}
