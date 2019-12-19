<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SOProjectItemResult extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'so_project_item_result';
	protected $primaryKey = 'so_project_item_result_id';
	public $incrementing = true;
	
	//public $timestamps = false;
	//protected $guarded = array();
	protected $fillable = array('so_project_item_result_id','so_result_id','period_id','so_item_id','so_item_desc','project_item_id',
							'project_item_desc','target_value','forecast_value','actual_value','percent_achievement','percent_forecast',
							'result_threshold_group_id','weight_percent','weight_score','color_code');
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}