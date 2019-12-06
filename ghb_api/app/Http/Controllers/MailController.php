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

		$org_list = DB::select("
			SELECT DISTINCT o.org_id, o.org_name, o.org_email
			FROM monthly_appraisal_item_result mai
			LEFT OUTER JOIN emp_result emp ON mai.emp_result_id = emp.emp_result_id
			INNER JOIN org o ON emp.org_id = o.org_id
			LEFT OUTER JOIN appraisal_item ai ON mai.item_id = ai.item_id
			WHERE ai.remind_condition_id = 1
			AND mai.actual_value < mai.target_value
			AND emp.appraisal_type_id = 1
			AND mai.year = date_format(current_date,'%Y')
			AND o.org_email is not null
			AND o.org_email != ''
		");

		$error = [];

		foreach ($org_list as $e) {
			$items = DB::select("
				SELECT mai.item_result_id, ai.item_name, o.org_id, o.org_name, o.org_email
				, SUM(mai.actual_value) actual_value, SUM(mai.target_value) target_value
				FROM monthly_appraisal_item_result mai
				LEFT OUTER JOIN emp_result emp ON mai.emp_result_id = emp.emp_result_id
				INNER JOIN org o ON emp.org_id = o.org_id
				LEFT OUTER JOIN appraisal_item ai ON mai.item_id = ai.item_id
				WHERE ai.remind_condition_id = 1
				AND emp.appraisal_type_id = 1
				AND mai.year = date_format(current_date,'%Y')
				AND mai.appraisal_month_no <= date_format(current_date,'%c')
				AND o.org_id = ?
				GROUP BY mai.item_result_id, ai.item_name, o.org_id, o.org_name, o.org_email
				HAVING SUM(mai.actual_value) < SUM(mai.target_value)
				ORDER BY ai.item_name ASC
			", array($e->org_id));

			$admin_emails = DB::select("
				SELECT em.email
				FROM employee em
				LEFT OUTER JOIN appraisal_level le ON em.level_id = le.level_id
				WHERE le.is_hr = 1
			");

			try {
				$data = ['items' => $items, 'emp_name' => $e->org_name, 'web_domain' => $config->web_domain];

				$from = config("mail.from.address");
				$to = [$e->org_email];
				// $cc = [];
				foreach ($admin_emails as $ae) {
					$to[] = $ae->email;
				}

				Mail::send('emails.remind', $data, function($message) use ($from, $to)
				{
					$message->from($from, config("mail.from.name"));
					$message->to($to);
					// $message->cc($cc);
					$message->subject('Action Plan Required');
				});
			} catch (Exception $e) {
				$error[] = $e->getMessage();
			}
		}

		//---------------------------------- Send Mail By Employee [Start]--------------------------------------------------------
		$emp_list = DB::select("
			SELECT DISTINCT em.emp_id, em.emp_name, em.email, em.chief_emp_code
			, (SELECT ch_em.email FROM employee ch_em WHERE ch_em.emp_code = em.chief_emp_code) email_chief
			FROM monthly_appraisal_item_result mai
			LEFT OUTER JOIN emp_result emp ON emp.emp_result_id = mai.emp_result_id
			INNER JOIN employee em ON emp.emp_id = em.emp_id
			LEFT OUTER JOIN appraisal_item ai ON ai.item_id = mai.item_id
			WHERE ai.remind_condition_id = 1
			AND mai.actual_value < mai.target_value
			AND emp.appraisal_type_id = 2
			AND mai.year = date_format(current_date,'%Y')
			AND em.email IS NOT NULL
			AND em.email != ''
		");

		foreach ($emp_list as $e) {
			$items = DB::select("
				SELECT mai.item_result_id, ai.item_name, em.emp_id, em.emp_name, em.email
				, SUM(mai.actual_value) actual_value, SUM(mai.target_value) target_value
				FROM monthly_appraisal_item_result mai
				LEFT OUTER JOIN emp_result emp ON mai.emp_result_id = emp.emp_result_id
				INNER JOIN employee em ON emp.emp_id = em.emp_id
				LEFT OUTER JOIN appraisal_item ai ON mai.item_id = ai.item_id
				WHERE ai.remind_condition_id = 1
				AND emp.appraisal_type_id = 2
				AND mai.year = date_format(current_date,'%Y')
				AND mai.appraisal_month_no <= date_format(current_date,'%c')
				AND em.emp_id = ?
				GROUP BY mai.item_result_id, ai.item_name, em.emp_id, em.emp_name, em.email
				HAVING SUM(mai.actual_value) < SUM(mai.target_value)
				ORDER BY ai.item_name ASC
			", array($e->emp_id));

			try {
				$data = ['items' => $items, 'emp_name' => $e->emp_name, 'web_domain' => $config->web_domain];

				$from = config("mail.from.address");
				$to = [$e->email, $e->email_chief];

				Mail::send('emails.remind', $data, function($message) use ($from, $to)
				{
					$message->from($from, config("mail.from.name"));
					$message->to($to);
					$message->subject('Action Plan Required');
				});
			} catch (Exception $e) {
				$error[] = $e->getMessage();
			}
		}
		//---------------------------------- Send Mail By Employee [End]--------------------------------------------------------

		// License Verification //
		try{
			$empAssign = Config::get("session.license_assign");
			if((!empty($empAssign))&&$empAssign!=0){
				$this->LicenseVerification();
			}
		} catch (Exception $e) {
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
			$to = ['nicharee@goingjesse.com']; // jahja
			//$to = ['msuksang@gmail.com','methee@goingjesse.com'];

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

	public function LicenseVerification(){
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
		$from = $config->mail_username;

		//-- Get customer info--//
		$org = DB::table('Org')
			->where('parent_org_code', '')
			->orWhereNull('parent_org_code')
			->first();
		$org = (empty($org) ? $config->mail_username : $org);
		$empActive = DB::table('employee')->count();

		$data = [
			"customer_name" => $org->org_name,
			"assinged" => Config::get("session.license_assign"),
			"active" => ($empActive-1)
		];

		$error = '';
		try {
			Mail::send('emails.license_verification', $data, function($message) use ($from)
			{
				$message
					->from($from, Config::get("session.license_mail_sender_name"))
					->to(Config::get("session.license_mail_to"))
					->subject(Config::get("session.license_mail_subject"));
			});
		} catch (Exception $e) {
			$error = $e->getMessage();
		}
		return response()->json(['error' => $error]);
	}
}
