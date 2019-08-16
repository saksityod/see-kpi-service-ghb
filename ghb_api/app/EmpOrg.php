<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EmpOrg extends Model
{
 	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';
    protected $table = 'emp_org';
	protected $primaryKey = null;
	public $incrementing = false;
	public $timestamps = true;
	protected $guarded = array();
}
