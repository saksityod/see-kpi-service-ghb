<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AppraisalStructure extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'appraisal_structure';
	protected $primaryKey = 'structure_id';
	public $incrementing = true;
	//public $timestamps = false;
	//protected $guarded = array();
	
	protected $fillable = array('seq_no','structure_name','nof_target_score','form_id','is_active');
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}