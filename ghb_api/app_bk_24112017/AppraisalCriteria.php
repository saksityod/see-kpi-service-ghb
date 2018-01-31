<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AppraisalCriteria extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'appraisal_criteria';
	protected $primaryKey = null;
	public $incrementing = false;
	//public $timestamps = false;
	//protected $guarded = array();
	protected $fillable = array('structure_id','appraisal_level_id','weight_percent');
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}