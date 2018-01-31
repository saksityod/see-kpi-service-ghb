<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class KPICDSMapping extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = null;
    protected $table = 'kpi_cds_mapping';
	protected $primaryKey = null;
	public $incrementing = false;
	//public $timestamps = false;
	protected $guarded = array();
	//protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}