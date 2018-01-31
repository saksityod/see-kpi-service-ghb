<?php

namespace App\Http\Controllers;

use App\SystemConfiguration;
use App\AppraisalFrequency;

use Auth;
use DB;
use File;
use Validator;
use Excel;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SystemConfigController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
    public function index()
    {
		try {
			$item = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		
		return response()->json($item);
    }
	
	public function month_list()
	{
		$items = DB::select("
			select month_id, month_name
			from period_month
			order by month_id asc
		");
		return response()->json($items);
	}
	
	public function frequency_list()
	{
		$items = DB::select("
			select frequency_id, frequency_name
			from appraisal_frequency
			where frequency_month_value > 0
			order by frequency_id asc
		");
		return response()->json($items);	
	}
	
	public function update(Request $request)
	{
		$errors = array();
		try {
			$item = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		
		
		$validator = Validator::make($request->all(), [
			'current_appraisal_year' => 'required|integer',
			'period_start_month_id' => 'required|integer',
			'appraisal_frequency_id' => 'required|integer',
			'bonus_frequency_id' => 'required|integer',
			'bonus_prorate' => 'required|max:100',
			'daily_bonus_rate' => 'required|numeric',
			'monthly_bonus_rate' => 'required|numeric',
			'nof_date_bonus' => 'required|integer',
			'salary_raise_frequency_id' => 'required|integer',
			'result_type' => 'required|integer',
			'raise_type' => 'required|integer',
			'theme_color' => 'required|max:15',
			'email_reminder_flag' => 'boolean',
			'mail_driver' => 'max:50',
			'mail_host' => 'max:50',
			'mail_port' => 'max:50',
			'mail_username' => 'max:50',
			'mail_password' => 'max:50',
			'mail_encryption' => 'max:50',
			'web_domain' => 'max:255'
		]);

		if ($validator->fails()) {
			$errors = $validator->errors()->toArray();		
			return response()->json(['status' => 400, 'data' => $errors]);
		} else {
			$af = AppraisalFrequency::find($request->appraisal_frequency_id);
			$bf = AppraisalFrequency::find($request->bonus_frequency_id);
			$sf = AppraisalFrequency::find($request->salary_raise_frequency_id);
			
			if ($bf->frequency_month_value < $af->frequency_month_value) {
				$errors[] = "Bonus Frequency cannot be less than Appraisal Frequency.";
			}
			
			if ($sf->frequency_month_value < $af->frequency_month_value) {
				$errors[] = "Salary Raise Frequency cannot be less than Appraisal Frequency.";
			}			
			
			if (!empty($errors)) {
				return response()->json(['status' => 400, 'data' => $errors]);
			}
			
			$item->fill($request->all());
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);
				
	}
	
}
