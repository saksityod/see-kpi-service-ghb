<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WorkflowStage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = null;
	const UPDATED_AT = null;	 
    protected $table = 'appraisal_stage';
	protected $primaryKey = 'stage_id';
	public $incrementing = false;
	public $timestamps = false;
	protected $guarded = array();
	//protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}