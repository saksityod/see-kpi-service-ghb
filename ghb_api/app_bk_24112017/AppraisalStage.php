<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AppraisalStage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	//const CREATED_AT = 'created_dttm';
	//const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'appraisal_stage';
	protected $primaryKey = 'stage_id';
	public $incrementing = true;
	
	public $timestamps = false;
	//protected $guarded = array();
	protected $fillable = array('appraisal_type_id','from_stage_id','to_stage_id','from_action','to_action','status','edit_flag','hr_see','assignment_flag','appraisal_flag');
	//protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}