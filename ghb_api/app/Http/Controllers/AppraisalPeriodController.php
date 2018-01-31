<?php

namespace App\Http\Controllers;

use App\AppraisalPeriod;
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

class AppraisalPeriodController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function appraisal_year_list(Request $request)
	{
		
		/*
		$items = DB::select("
			SELECT date_format(date_add(current_date,interval -1 year),'%Y') appraisal_year_id,
			date_format(date_add(current_date,interval -1 year),'%Y') appraisal_year,
			if(date_format(date_add(current_date,interval -1 year),'%Y') = (select current_appraisal_year from system_config),1,0) default_value
			union
			SELECT date_format(current_date,'%Y') appraisal_year_id,
			date_format(current_date,'%Y') appraisal_year,
			if(date_format(current_date,'%Y') = (select current_appraisal_year from system_config),1,0) default_value
			union
			SELECT date_format(date_add(current_date,interval 1 year),'%Y') appraisal_year_id,
			date_format(date_add(current_date,interval 1 year),'%Y') appraisal_year,
			if(date_format(date_add(current_date,interval 1 year),'%Y') = (select current_appraisal_year from system_config),1,0) default_value
			union
			select current_appraisal_year appraisal_year_id, current_appraisal_year appraisal_year, 1 default_value
			from system_config
			order by appraisal_year asc
		");
		*/
		$items = DB::select("
		SELECT (select current_appraisal_year-1 from system_config) appraisal_year_id,
			(select current_appraisal_year-1 from system_config) appraisal_year,
			if((select current_appraisal_year-1 from system_config) = (select current_appraisal_year from system_config),1,0) default_value
			union
			SELECT (select current_appraisal_year from system_config) appraisal_year_id,
			(select current_appraisal_year from system_config) appraisal_year,
			if((select current_appraisal_year from system_config) = (select current_appraisal_year from system_config),1,0) default_value
			union
			SELECT (select current_appraisal_year+1 from system_config) appraisal_year_id,
			(select current_appraisal_year+1 from system_config) appraisal_year,
			if((select current_appraisal_year+1 from system_config) = (select current_appraisal_year from system_config),1,0) default_value
			union
			select current_appraisal_year appraisal_year_id, current_appraisal_year appraisal_year, 1 default_value
			from system_config
			order by appraisal_year asc
		");
		return response()->json($items);
	}
	
	public function start_month_list()
	{
		$items = DB::select("
			select a.month_id start_month, a.month_name start_month_name, if(b.config_id is null,0,1) default_month
			from period_month a
			left outer join system_config b
			on a.month_id = b.period_start_month_id		
			order by a.month_id
		");
		return response()->json($items);
	}
	
	public function auto_desc(Request $request)
	{
		$items = DB::select("
			select distinct appraisal_period_desc
			from appraisal_period
			where appraisal_period_desc like ?
			limit 10
		", array('%'.$request->appraisal_period_desc.'%'));
		return response()->json($items);
	}
	
	public function frequency_list()
	{
		$items = DB::select("
			select a.*, af.config_id default_appraisal_frequency, bf.config_id default_bonus_frequency, sf.config_id default_salary_frequency
			from appraisal_frequency a
			left outer join system_config af
			on a.frequency_id = af.appraisal_frequency_id
			left outer join system_config bf
			on a.frequency_id = bf.bonus_frequency_id
			left outer join system_config sf
			on a.frequency_id = sf.salary_raise_frequency_id
			where frequency_month_value > 0
			order by frequency_month_value asc
		");
		return response()->json($items);
	}
	
	public function add_frequency_list()
	{
		$items = DB::select("
			select a.*, af.config_id default_appraisal_frequency, bf.config_id default_bonus_frequency, sf.config_id default_salary_frequency
			from appraisal_frequency a
			left outer join system_config af
			on a.frequency_id = af.appraisal_frequency_id
			left outer join system_config bf
			on a.frequency_id = bf.bonus_frequency_id
			left outer join system_config sf
			on a.frequency_id = sf.salary_raise_frequency_id
			where frequency_month_value = 0
			order by frequency_month_value asc
		");
		return response()->json($items);	
	}
	
	public function index(Request $request)
	{		
		$qinput = array();
		$query = "
			SELECT a.period_id, a.appraisal_year, a.period_no, a.appraisal_period_desc, a.start_date, a.end_date, if(b.frequency_month_value=0,1,0) edit_flag
			FROM appraisal_period a
			left outer join appraisal_frequency b
			on a.appraisal_frequency_id = b.frequency_id
			where 1 = 1		
		";
		
		empty($request->appraisal_year) ?: ($query .= " and appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
		empty($request->appraisal_period_desc) ?: ($query .= " and appraisal_period_desc like ? " AND $qinput[] = '%'.$request->appraisal_period_desc.'%');
		
		$qfooter = " order by a.period_id asc ";
		$items = DB::select($query.$qfooter,$qinput);
		return response()->json($items);
	}
	
	public function store(Request $request)
	{	
		$validator = Validator::make($request->all(), [
			'appraisal_year' => 'required|integer', 
			'period_no' => 'required|integer',
			'appraisal_frequency_id' => 'required|integer',
			'appraisal_period_desc' => 'required|max:255|unique:appraisal_period',
			'start_date' => 'required|date|date_format:Y-m-d',
			'end_date' => 'required|date|date_format:Y-m-d',					
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new AppraisalPeriod;
			$item->fill($request->all());
			$item->is_bonus = 0;
			$item->is_raise = 0;
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function create(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'appraisal_year' => 'required|integer', 
			'start_month' => 'required|integer',
			'start_year' => 'required|integer',
			'appraisal_frequency_id' => 'required|integer',
			'appraisal_period_desc' => 'required|max:250',
			'bonus_frequency_id' => 'required|integer',
			'bonus_period_desc' => 'required|max:250',
			'salary_raise_frequency_id' => 'required|integer',
			'salary_period_desc' => 'required|max:250'				
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$errors = array();
			$af = AppraisalFrequency::find($request->appraisal_frequency_id)->frequency_month_value;
			$bf = AppraisalFrequency::find($request->bonus_frequency_id)->frequency_month_value;
			$sf = AppraisalFrequency::find($request->salary_raise_frequency_id)->frequency_month_value;
			
			if ($bf < $af) {
				$errors[] = "Bonus Frequency cannot be less than Appraisal Frequency.";
			}
			
			if ($sf < $af) {
				$errors[] = "Salary Raise Frequency cannot be less than Appraisal Frequency.";
			}			
			
			if (!empty($errors)) {
				return response()->json(['status' => 400, 'data' => $errors]);
			}			
			
			$a_date = $request->start_year . '-' . $request->start_month . '-' . '01';
			
			$a_range = 12 / $af;
			
			$bp = 1;
			$sp = 1;
			
			$next_b_date = date("Y-m-d", strtotime("+" . $bf  . " months", strtotime($a_date)));
			$next_s_date = date("Y-m-d", strtotime("+" . $sf  . " months", strtotime($a_date)));
			$next_b_end_date = date("Y-m-t", strtotime("+" . $bf - 1 . " months", strtotime($a_date)));
			$next_s_end_date = date("Y-m-t", strtotime("+" . $sf - 1 . " months", strtotime($a_date)));
			
			foreach (range(1,$a_range,1) as $p) {
				$b_flag = 0;
				$s_flag = 0;
				$s_date = date("Y-m-d", strtotime($a_date));
				$e_date = date("Y-m-t", strtotime("+" . $af - 1 . " months", strtotime($s_date)));
				$a_date = date("Y-m-d", strtotime("+" . $af  . " months", strtotime($a_date)));
				
				if ($e_date == $next_b_end_date) {
					$b_flag = 1;
					$next_b_end_date = date("Y-m-t", strtotime("+" . $bf - 1 . " months", strtotime($a_date)));
				}
				
				if ($e_date == $next_s_end_date) {
					$s_flag = 1;
					$next_s_end_date = date("Y-m-t", strtotime("+" . $sf - 1 . " months", strtotime($a_date)));
				}
				
				if ($s_date == $next_b_date) {
					$bp += 1;
					$next_b_date = date("Y-m-d", strtotime("+" . $bf - 1 . " months", strtotime($a_date)));
				}
				
				if ($s_date == $next_s_date) {
					$sp += 1;
					$next_s_date = date("Y-m-d", strtotime("+" . $sf - 1 . " months", strtotime($a_date)));
				}					
				
				$item = new AppraisalPeriod;
				$item->appraisal_year = $request->appraisal_year;
				$item->period_no = $p;
				$item->appraisal_period_desc = $request->appraisal_period_desc . ' ' . $p;
				$item->appraisal_frequency_id = $request->appraisal_frequency_id;
				$item->bonus_period_desc = $request->bonus_period_desc . ' ' . $bp;
				$item->bonus_frequency_id = $request->bonus_frequency_id;
				$item->is_bonus = $b_flag;
				$item->salary_period_desc = $request->salary_period_desc . ' ' . $sp;
				$item->salary_raise_frequency_id = $request->salary_raise_frequency_id;
				$item->is_raise = $s_flag;
				$item->start_date = $s_date;
				$item->end_date = $e_date;
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
				$item->save();
				
			//	echo $p . ' = ' . $s_date . ':' . $e_date . "|bp = " . $bp . " |b_flag = " . $b_flag . "|sp = " . $sp . "|s_flag = " . $s_flag . "\n";
			}
		}
	
		return response()->json(['status' => 200]);	
	}	
	
	public function show($period_id)
	{
		try {
			$item = AppraisalPeriod::findOrFail($period_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Period not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $period_id)
	{
		try {
			$item = AppraisalPeriod::findOrFail($period_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Period not found.']);
		}
		
		$validator = Validator::make($request->all(), [
			'appraisal_year' => 'required|integer', 
			'period_no' => 'required|integer',
			'appraisal_period_desc' => 'required|max:255|unique:appraisal_period',
			'start_date' => 'required|date|date_format:Y-m-d',
			'end_date' => 'required|date|date_format:Y-m-d',					
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item->fill($request->all());
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);
				
	}
	
	public function destroy($period_id)
	{
		try {
			$item = AppraisalPeriod::findOrFail($period_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Period not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Appraisal Period is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
