<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SOResult extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'so_result';
	protected $primaryKey = 'so_result_id';
	public $incrementing = true;
	
	//public $timestamps = false;
	//protected $guarded = array();
	protected $fillable = array('period_id','form_type','result_score','color_code','result_threshold_group_id');
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}