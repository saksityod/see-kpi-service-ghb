<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ValueType extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'value_type';
	protected $primaryKey = 'value_type_id';
	public $incrementing = true;
	
	//public $timestamps = false;
	//protected $guarded = array();
	protected $fillable = array('value_type_name');
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}