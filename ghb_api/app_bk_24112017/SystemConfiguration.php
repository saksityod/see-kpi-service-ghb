<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SystemConfiguration extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'system_config';
	protected $primaryKey = 'config_id';
	public $incrementing = true;
	//public $timestamps = false;
	//protected $guarded = array();
	protected $fillable = array('current_appraisal_year','period_start_month_id','appraisal_frequency_id','bonus_frequency_id','bonus_prorate','daily_bonus_rate','monthly_bonus_rate','nof_date_bonus','salary_raise_frequency_id','result_type','raise_type','threshold','theme_color','email_reminder_flag','mail_driver','mail_host','mail_port','mail_username','mail_password','mail_encryption','web_domain');
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}