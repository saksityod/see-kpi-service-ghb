<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class jobLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	//const CREATED_AT = 'created_dttm';
	//const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'job_log';
	protected $primaryKey = 'job_log_id';
	public $incrementing = false;
	public $timestamps = false;
	//protected $guarded = array();
	// protected $fillable = array(
	// 	'job_log_name',
	// 	'param_start_date',
	// 	'param_end_date',
	// 	'destination_address',
	// 	'cc',
	// 	'bcc',
	// 	'sender_name',
	// 	'sender_address',
	// 	'path_log_file',
	// 	'subject_error',
	// 	'comment_error',
	// 	'status'
	// );
	//protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}