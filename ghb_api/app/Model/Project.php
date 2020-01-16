<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';	 
    protected $table = 'projects';
	protected $primaryKey = 'id';
	// public $incrementing = false;
	//public $timestamps = false;
	// protected $guarded = array();
	protected $fillable = ['name', 'objective', 'org_id', 'emp_id', 'date_start', 'date_end', 'value', 'risk',  'is_active'];
	protected $hidden = ['created_by', 'updated_by', 'created_at', 'updated_at'];
}