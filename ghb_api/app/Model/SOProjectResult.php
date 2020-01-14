<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SOProjectResult extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'so_project_result';
	protected $primaryKey = 'so_project_result_id';
	public $incrementing = true;
	
	//public $timestamps = false;
	//protected $guarded = array();
	protected $fillable = array('so_project_item_result_id','period_id','year','month_no','month_name','forecast_value','result_value');
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}