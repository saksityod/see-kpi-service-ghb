<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UsageLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = null;	 
    protected $table = 'usage_log';
	protected $primaryKey = null;
	public $incrementing = false;
	//public $timestamps = false;
	//protected $guarded = array();

	//protected $fillable = array('structure_id','target_score','threshold_group_id','threshold_name','color_code');
	//protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}