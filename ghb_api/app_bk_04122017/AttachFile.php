<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AttachFile extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
    protected $table = 'appraisal_item_result_doc';
	protected $primaryKey = 'result_doc_id';
	public $incrementing = true;
	//public $timestamps = false;
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = null;	

	protected $guarded = array();
	//protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}