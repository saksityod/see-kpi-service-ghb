<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ProjectItem extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'project_item';
	protected $primaryKey = 'project_item_id';
	public $incrementing = true;
	
	//public $timestamps = false;
	//protected $guarded = array();
	protected $fillable = array('project_item_name','uom_id','value_type_id','function_type','is_active' );
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}