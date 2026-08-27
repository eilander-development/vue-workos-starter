<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class CatalogModel extends Model
{
    protected $connection = 'catalog';
}
