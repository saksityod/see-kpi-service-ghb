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
				
			try {
				$data = ['items' => $items, 'emp_name' => $e->emp_name, 'web_domain' => $config->web_domain];
				
				$from = 'gjtestmail2017@gmail.com';
				$to = [$e->email];
				$cc = [$e->chief_email];
				
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
		
		Config::set('mail.driver',$config->mail_driver);
		Config::set('mail.host',$config->mail_host);
		Config::set('mail.port',$config->mail_port);
		Config::set('mail.encryption',$config->mail_encryption);
		Config::set('mail.username',$config->mail_username);
		Config::set('mail.password',$config->mail_password);	
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
