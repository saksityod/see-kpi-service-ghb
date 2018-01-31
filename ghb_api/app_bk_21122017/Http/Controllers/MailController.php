<?php

namespace App\Http\Controllers;

use App\SystemConfiguration;

use Mail;
use Auth;
use DB;
use File;
use Validator;
use Excel;
use Exception;
use Config;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MailController extends Controller
{

	public function __construct()
	{

	  // $this->middleware('jwt.auth');
	}
	
	public function monthly() {
	
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}			
		
		Config::set('mail.driver',$config->mail_driver);
		Config::set('mail.host',$config->mail_host);
		Config::set('mail.port',$config->mail_port);
		Config::set('mail.encryption',$config->mail_encryption);
		Config::set('mail.username',$config->mail_username);
		Config::set('mail.password',$config->mail_password);
		
		$emp_list = DB::select("
			SELECT distinct c.emp_id, c.emp_name, c.email, e.email chief_email
			FROM monthly_appraisal_item_result a
			left outer join emp_result b
			on a.emp_result_id = b.emp_result_id
			inner join employee c
			on b.emp_id = c.emp_id
			left outer join appraisal_item d
			on a.item_id = d.item_id
			left outer join employee e
			on c.chief_emp_code = e.emp_code
			where d.remind_condition_id = 1
			and a.actual_value < a.target_value
			and b.appraisal_type_id = 2
			and a.year = date_format(current_date,'%Y')		
		");
		

		$error = [];
		
		foreach ($emp_list as $e) {
			$items = DB::select("
				SELECT a.item_result_id, d.item_name, c.emp_id, c.emp_name, c.email, e.email chief_email,
				sum(a.actual_value) actual_value, sum(a.target_value) target_value
				FROM monthly_appraisal_item_result a
				left outer join emp_result b
				on a.emp_result_id = b.emp_result_id
				inner join employee c
				on b.emp_id = c.emp_id
				left outer join appraisal_item d
				on a.item_id = d.item_id
				left outer join employee e
				on c.chief_emp_code = e.emp_code
				where d.remind_condition_id = 1
				and b.appraisal_type_id = 2
				and a.year = date_format(current_date,'%Y')
				and a.appraisal_month_no <= date_format(current_date,'%c')
				and c.emp_id = ?
				group by a.item_result_id, d.item_name, c.emp_id, c.emp_name, c.email, e.email
				having sum(a.actual_value) < sum(a.target_value)
				order by d.item_name asc
			", array($e->emp_id));
				
			$admin_emails = DB::select("
				select a.email
				from employee a
				left outer join appraisal_level b
				on a.level_id = b.level_id
				where is_hr = 1			
			");				
				
			try {
				$data = ['items' => $items, 'emp_name' => $e->emp_name, 'web_domain' => $config->web_domain];
				
				$from = 'gjtestmail2017@gmail.com';
				$to = [$e->email];
				$cc = [$e->chief_email];
				
				foreach ($admin_emails as $ae) {
					$cc[] = $ae->email;
				}				
				
				Mail::send('emails.remind', $data, function($message) use ($from, $to, $cc)
				{
					$message->from($from, 'SEE-KPI System');
					$message->to($to);
					$message->cc($cc);
					$message->subject('Action Plan Required');
				});			
			} catch (Exception $e) {
				$error[] = $e->getMessage();
			}		
		}
		//return view('emails.remind',['items' => $items, 'emp_name' => 'hello', 'web_domain' => $config->web_domain]);
		return response()->json(['status' => 200, 'error' => $error]);
	}
	
	public function quarterly() {
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}			
		$error = [];
		Config::set('mail.driver',$config->mail_driver);
		Config::set('mail.host',$config->mail_host);
		Config::set('mail.port',$config->mail_port);
		Config::set('mail.encryption',$config->mail_encryption);
		Config::set('mail.username',$config->mail_username);
		Config::set('mail.password',$config->mail_password);	
		
		$check_quarter = DB::select("
			select date_format(date_add(current_date,interval - 1 month),'%b') remind_month, a.*
			from (
			SELECT period_id, date_add(date(start_date) - interval day(start_date) day + interval 1 day,interval 0 month) quarter_1,
			date_add(date(start_date) - interval day(start_date) day + interval 1 day,interval 3 month) quarter_2,
			date_add(date(start_date) - interval day(start_date) day + interval 1 day,interval 6 month) quarter_3,
			date_add(date(start_date) - interval day(start_date) day + interval 1 day,interval 9 month) quarter_4
			FROM appraisal_period
			where appraisal_year = ?
			and appraisal_frequency_id = 4
			) a
			where date(date_format(current_date,'%Y-%m-01')) in (quarter_1,quarter_2,quarter_3,quarter_4)	
		", array($config->current_appraisal_year));
		
		if (empty($check_quarter)) {
			return response()->json(['status' => 200, 'data' => 'No quarter to remind']);
		} else {
			foreach ($check_quarter as $c) {
				$employees = DB::select("
					SELECT distinct emp_id
					FROM monthly_appraisal_item_result
					where period_id = ?
					and year = ?
					and appraisal_month_name = ?
					and emp_id is not null
					and remind_flag = 1
					and ifnull(email_flag,0) = 0
				",array($c->period_id, $config->current_appraisal_year, $c->remind_month));
				
				foreach ($employees as $e) {
					$items = DB::select("
						SELECT a.item_result_id, d.item_name, c.emp_id, c.emp_name, c.email, e.email chief_email,
						a.actual_value, a.target_value
						FROM monthly_appraisal_item_result a
						left outer join emp_result b
						on a.emp_result_id = b.emp_result_id
						inner join employee c
						on b.emp_id = c.emp_id
						left outer join appraisal_item d
						on a.item_id = d.item_id
						left outer join employee e
						on c.chief_emp_code = e.emp_code
						where a.remind_flag = 1
						and ifnull(a.email_flag,0) = 0
						and a.emp_id = ?
						and a.period_id = ?
						and a.year = ?
						and a.appraisal_month_name = ?
						order by d.item_name asc
					", array($e->emp_id, $c->period_id, $config->current_appraisal_year, $c->remind_month));
					
					$admin_emails = DB::select("
						select a.email
						from employee a
						left outer join appraisal_level b
						on a.level_id = b.level_id
						where is_hr = 1			
					");						
					
					try {
						$data = ['items' => $items, 'emp_name' => $e->emp_name, 'web_domain' => $config->web_domain];
						
						$from = 'gjtestmail2017@gmail.com';
						$to = [$e->email];
						$cc = [$e->chief_email];
						foreach ($admin_emails as $ae) {
							$cc[] = $ae->email;
						}						
						Mail::send('emails.remind', $data, function($message) use ($from, $to, $cc)
						{
							$message->from($from, 'SEE-KPI System');
							$message->to($to);
							$message->cc($cc);
							$message->subject('Action Plan Required');
						});			
						
						foreach ($items as $i) {
							DB::table('monthly_appraisal_item_result')->where('item_result_id', $i->item_result_id)->update(['email_flag' => 1]);		
						}
						
					} catch (Exception $e) {
						$error[] = $e->getMessage();
					}							
				}
			}
			return response()->json(['status' => 200, 'error' => $error]);
		}
		
	}

	public function send()
	{	
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}			
		
		Config::set('mail.driver',$config->mail_driver);
		Config::set('mail.host',$config->mail_host);
		Config::set('mail.port',$config->mail_port);
		Config::set('mail.encryption',$config->mail_encryption);
		Config::set('mail.username',$config->mail_username);
		Config::set('mail.password',$config->mail_password);
		
		$mail_body = "
			Hello from SEE KPI,

			You have been appraised please click https://www.google.com

			Best Regards,

			From Going Jesse Team
		";
		$error = '';
		try {
			$data = ["chief_emp_name" => "the boss", "emp_name" => "the bae", "status" => "excellent"];
			
			$from = 'gjtestmail2017@gmail.com';
			$to = ['msuksang@gmail.com','methee@goingjesse.com'];
			
			Mail::send('emails.status', $data, function($message) use ($from, $to)
			{
				$message->from($from, 'SEE-KPI System');
				$message->to($to)->subject('คุณได้รับการประเมิน!');
			});			
		} catch (Exception $e) {
			$error = $e->getMessage();
		}
		
		// Mail::later(5,'emails.welcome', array('msg' => $mail_body), function($message)
		// {
			// $message->from('msuksang@gmail.com', 'TYW Team');

			// $message->to('methee@goingjesse.com')->subject('You have been Appraised :-)');
		// });	
		
		return response()->json(['error' => $error]);	
		
	}
}
