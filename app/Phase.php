<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Phase extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'phase';
	protected $primaryKey = 'phase_id';
	public $incrementing = true;
	//public $timestamps = false;
	//protected $guarded = array();

	protected $fillable = array('phase_name','item_result_id','is_active');
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}