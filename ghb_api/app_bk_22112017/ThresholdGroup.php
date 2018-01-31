<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ThresholdGroup extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'threshold_group';
	protected $primaryKey = 'threshold_group_id';
	public $incrementing = true;
	//public $timestamps = false;
	//protected $guarded = array();

	protected $fillable = array('threshold_group_name','is_active');
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}