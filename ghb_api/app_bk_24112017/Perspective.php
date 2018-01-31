<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Perspective extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'perspective';
	protected $primaryKey = 'perspective_id';
	public $incrementing = true;
	//public $timestamps = false;
	//protected $guarded = array();

	protected $fillable = array('perspective_name','perspective_abbr','color_code','is_active');
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}