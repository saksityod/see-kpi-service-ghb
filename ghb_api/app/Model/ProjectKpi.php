<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ProjectKpi extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';	 
    protected $table = 'project_kpis';
	protected $primaryKey = 'id';
	public $incrementing = true;
	
	//public $timestamps = false;
	//protected $guarded = array();
	protected $fillable = array('name','uom_id','value_type_id','function_type','is_active' );
	protected $hidden = ['created_by', 'updated_by', 'created_at', 'updated_at'];
}