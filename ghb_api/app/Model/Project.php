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
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'project';
	protected $primaryKey = 'project_id';
	// public $incrementing = false;
	//public $timestamps = false;
	// protected $guarded = array();
	protected $fillable = ['project_name', 'objective', 'org_id', 'emp_id', 'project_start_date', 'project_end_date', 'project_value', 'project_risk',  'is_active'];
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}