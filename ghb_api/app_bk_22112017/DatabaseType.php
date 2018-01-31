<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DatabaseType extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	//const CREATED_AT = 'created_dttm';
	//const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'database_type';
	protected $primaryKey = 'database_type_id';
	public $incrementing = true;
	
	public $timestamps = false;
	//protected $guarded = array();
	protected $fillable = array('database_type');
	//protected $fillable = array('appraisal_level_id','grade','begin_score','end_score','salary_raise_amount','is_active');
	//protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}