<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EmpThreshold extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'emp_threshold';
	protected $primaryKey = 'emp_threshold_id';
	public $incrementing = true;
	//public $timestamps = false;
	protected $guarded = array();
	
	//protected $fillable = array('position_name','is_active');
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}