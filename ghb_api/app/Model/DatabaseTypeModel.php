<?php
/**
 * Created by PhpStorm.
 * User: imake
 * Date: 1/17/18
 * Time: 12:16 PM
 */

namespace App\Model;
use Illuminate\Database\Eloquent\Model;

class DatabaseTypeModel  extends Model
{
    protected $table = 'database_type';
    protected $primaryKey = 'database_type_id';
    public $timestamps = false;
}