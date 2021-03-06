<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ItemOrg extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';
    protected $table = 'appraisal_item_org';
	protected $primaryKey = null;
	public $incrementing = false;
	public $timestamps = true;
	protected $guarded = array();
}