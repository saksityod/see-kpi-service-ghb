<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class StrategicObjective extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'strategic_objective';
	protected $primaryKey = 'so_id';
	// public $incrementing = false;
	//public $timestamps = false;
	// protected $guarded = array();
	protected $fillable = ['so_name', 'so_abbr', 'color_code', 'is_active', 'created_by', 'updated_by'];
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}