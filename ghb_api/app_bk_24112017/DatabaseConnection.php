<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DatabaseConnection extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'database_connection';
	protected $primaryKey = 'connection_id';
	public $incrementing = true;
	//public $timestamps = false;
	//protected $guarded = array();
	protected $fillable = array('connection_name', 'database_type_id', 'ip_address', 'port', 'database_name', 'user_name', 'password');
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}