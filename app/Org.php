<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Org extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'org';
	protected $primaryKey = 'org_id';
	public $incrementing = true;
	//public $timestamps = false;
	protected $guarded = array();
	
	protected $fillable = array('org_id','org_code','org_name','org_abbr','parent_org_code','level_id','latitude','longitude','province_code','is_active');
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}