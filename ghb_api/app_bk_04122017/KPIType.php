<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class KPIType extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'kpi_type';
	protected $primaryKey = 'kpi_type_id';
	public $incrementing = true;
	//public $timestamps = false;
	//protected $guarded = array();
	
	protected $fillable = array('kpi_type_name','is_active');
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}