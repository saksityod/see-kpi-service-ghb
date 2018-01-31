<?php
/**
 * Created by PhpStorm.
 * User: imake
 * Date: 12/20/17
 * Time: 4:24 PM
 */

namespace App\Model;
use Illuminate\Database\Eloquent\Model;

class DatabaseConnectionModel  extends Model
{
    protected $table = 'database_connection';
    protected $primaryKey = 'connection_id';
    public $timestamps = false;
}