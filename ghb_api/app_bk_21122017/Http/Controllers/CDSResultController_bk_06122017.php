<?php

namespace App\Http\Controllers;

use App\CDS;
use App\CDSResult;
use App\PeriodMonth;
use App\SystemConfiguration;

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

class CDSResultController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function import(Request $request)
	{
		set_time_limit(0);
		$errors = array();
		foreach ($request->file() as $f) {
			$items = Excel::load($f, function($reader){})->get();			
			foreach ($items as $i) {
				
				if ($i->appraisal_type_id == 2) {
					$validator = Validator::make($i->toArray(), [
						'employee_id' => 'required|max:50',
						'appraisal_type_id' => 'integer',
						'organization_id' => 'required|integer',
						'position_id' => 'required|integer',
						'cds_id' => 'required|integer',
						'year' => 'required|integer',
						'month' => 'required|integer',
						'level_id' => 'required|integer',
						'cds_value' => 'required|numeric|digits_between:1,15',
					]);

					if ($validator->fails()) {
						$errors[] = ['employee_id' => $i->employee_id, 'errors' => $validator->errors()];
					} else {
						$month_name = PeriodMonth::find($i->month);
						if (empty($month_name)) {
							$errors[] = ['employee_id' => $i->employee_id, 'errors' => 'Invalid Month.'];
						} else {
							try {
								$result_check = CDSResult::where("emp_id",$i->employee_id)->where("cds_id",$i->cds_id)->where('year',$i->year)->where('appraisal_month_no',$i->month);
								
								if ($result_check->count() == 0) {
									$a_date = $i->year."-".$i->month."-01";
									//echo date("Y-m-t", strtotime($a_date));
									$cds_result = new CDSResult;
									$cds_result->appraisal_type_id = $i->appraisal_type_id;
									$cds_result->emp_id = $i->employee_id;
									$cds_result->cds_id = $i->cds_id;
									$cds_result->year = $i->year;
									$cds_result->org_id = $i->organization_id;
									$cds_result->position_id = $i->position_id;
									$cds_result->level_id = $i->level_id;
									$cds_result->appraisal_month_no = $i->month;
									$cds_result->appraisal_month_name = $month_name->month_name;
									$cds_result->cds_value = $i->cds_value;
									$cds_result->etl_dttm = date("Y-m-t", strtotime($a_date));
									$cds_result->created_by = Auth::id();
									$cds_result->updated_by = Auth::id();						
									$cds_result->save();							
								} else {
									CDSResult::where("emp_id",$i->employee_id)->where("cds_id",$i->cds_id)->where('year',$i->year)->where('appraisal_month_no',$i->month)->update(['cds_value' => $i->cds_value, 'updated_by' => Auth::id()]);							
								}

							} catch (Exception $e) {
								$errors[] = ['employee_id' => $i->employee_id, 'errors' => substr($e,0,254)];
							}
						}
					}					
				} else {
				
					$validator = Validator::make($i->toArray(), [
						'organization_id' => 'required|integer',
						'appraisal_type_id' => 'required|integer',
						'cds_id' => 'required|integer',
						'year' => 'required|integer',
						'month' => 'required|integer',
						'level_id' => 'required|integer',
						'cds_value' => 'required|numeric|digits_between:1,15',
					]);

					if ($validator->fails()) {
						$errors[] = ['org_id' => $i->organization_id, 'errors' => $validator->errors()];
					} else {
						$month_name = PeriodMonth::find($i->month);
						if (empty($month_name)) {
							$errors[] = ['org_id' => $i->organization_id, 'errors' => 'Invalid Month.'];
						} else {
							try {
								$result_check = CDSResult::where("org_id",$i->organization_id)->where("cds_id",$i->cds_id)->where('year',$i->year)->where('appraisal_month_no',$i->month);
								
								if ($result_check->count() == 0) {
									$a_date = $i->year."-".$i->month."-01";
									$cds_result = new CDSResult;
									$cds_result->appraisal_type_id = $i->appraisal_type_id;
									$cds_result->org_id = $i->organization_id;
									$cds_result->cds_id = $i->cds_id;
									$cds_result->year = $i->year;
									$cds_result->level_id = $i->level_id;
									$cds_result->appraisal_month_no = $i->month;
									$cds_result->appraisal_month_name = $month_name->month_name;
									$cds_result->cds_value = $i->cds_value;
									$cds_result->etl_dttm = date("Y-m-t", strtotime($a_date));
									$cds_result->created_by = Auth::id();
									$cds_result->updated_by = Auth::id();						
									$cds_result->save();							
								} else {
									CDSResult::where("org_id",$i->organization_id)->where("cds_id",$i->cds_id)->where('year',$i->year)->where('appraisal_month_no',$i->month)->update(['cds_value' => $i->cds_value, 'updated_by' => Auth::id()]);							
								}

							} catch (Exception $e) {
								$errors[] = ['organization_id' => $i->organization_id, 'errors' => substr($e,0,254)];
							}
						}
					}						
				}
			}
		}
		return response()->json(['status' => 200, 'errors' => $errors]);
	}	
	
	public function export_bk(Request $request)
	{
		$qinput = array();

		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		
		
		// if ($request->current_appraisal_year != $config->current_appraisal_year) {
			// return response()->json(['status' => 400, 'data' => 'Selected Year does not match Current Appraisal Year in System Configuration']);
		// }
		
		$checkyear = DB::select("
			select 1
			from appraisal_period
			where appraisal_year = ?
			and date(?) between start_date and end_date		
		", array($config->current_appraisal_year, $request->current_appraisal_year . str_pad($request->month_id,2,'0',STR_PAD_LEFT) . '01'));
		
		if (empty($checkyear)) {
			return 'Appraisal Period not found for the Current Appraisal Year.';
		}
		
		
		
		$query = "
			select distinct r.level_id, al.appraisal_level_name, r.org_id, org.org_name, r.emp_id, e.emp_name, r.position_id, po.position_name, cds.cds_id, cds.cds_name, ifnull(cr.cds_value,0) as cds_value, ap.appraisal_year
			from appraisal_item_result r
			left outer join employee e on r.emp_id = e.emp_id 
			inner join appraisal_item i on r.item_id = i.item_id
			left outer join appraisal_item_position p on i.item_id = p.item_id
			inner join kpi_cds_mapping m on i.item_id = m.item_id
			inner join cds on m.cds_id = cds.cds_id
			inner join appraisal_period ap on r.period_id = ap.period_id
			inner join system_config sys on ap.appraisal_year = sys.current_appraisal_year
			inner join emp_result er on r.emp_result_id = er.emp_result_id	
			left outer join position po on r.position_id = po.position_id
			left outer join org on r.org_id = org.org_id
			left outer join appraisal_level al on r.level_id = al.level_id
			left outer join cds_result cr on cds.cds_id = cr.cds_id
			and cr.year = {$request->current_appraisal_year}
			and cr.appraisal_month_no = {$request->month_id}
			where cds.is_sql = 0	
		";
		
		// $qinput[] = $request->current_appraisal_year;
		// $qinput[] = $request->month_id;
		
		//empty($request->current_appraisal_year) ?: ($query .= " AND appraisal_year = ? " AND $qinput[] = $request->current_appraisal_year);
		//empty($request->month_id) ?: ($query .= " And appraisal_month_no = ? " AND $qinput[] = $request->month_id);
		
		if (!empty($request->current_appraisal_year) && !empty($request->month_id)) {
			$current_date = $request->current_appraisal_year . str_pad($request->month_id,2,'0',STR_PAD_LEFT) . '01';
			$query .= " and date(?) between ap.start_date and ap.end_date ";
			$qinput[] = $current_date;
		}
		
		empty($request->level_id) ?: ($query .= " And r.level_id = ? " AND $qinput[] = $request->level_id);
		empty($request->org_id) ?: ($query .= " And cr.org_id = ? " AND $qinput[] = $request->org_id);
		empty($request->position_id) ?: ($query .= " And p.position_id = ? " AND $qinput[] = $request->position_id);
		empty($request->emp_id) ?: ($query .= " And e.emp_id = ? " AND $qinput[] = $request->emp_id);
		empty($request->appraisal_type_id) ?: ($query .= " And er.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
		
		$qfooter = " Order by r.emp_id, cds.cds_id ";





		
		$items = DB::select($query . $qfooter, $qinput);
		// echo $query;
		// echo "<br>";
		// print_r($qinput);


		
		$filename = "CDS_Result";  //. date('dm') .  substr(date('Y') + 543,2,2);
		$x = Excel::create($filename, function($excel) use($items, $filename, $request) {
			$excel->sheet($filename, function($sheet) use($items, $request) {
				
				if ($request->appraisal_type_id == 2) {
					$sheet->appendRow(array('Appraisal Type ID', 'Level ID', 'Level Name', 'Organization ID', 'Organization Name', 'Employee ID', 'Employee Name', 'Position ID', 'Position Name', 'CDS ID', 'CDS Name', 'Year', 'Month', 'CDS Value'));

					foreach ($items as $i) {
						// empty($i->appraisal_year) ? $appraisal_year = $request->current_appraisal_year : $appraisal_year = $i->appraisal_year;
						// empty($i->month_id) ? $month_id = $request->month_id : $month_id = $i->month_id;
						// empty($i->cds_value) ? $cds_value = 0 : $cds_value = $i->cds_value;
						
						$sheet->appendRow(array(
							$request->appraisal_type_id,
							$i->level_id,
							$i->appraisal_level_name,
							$i->org_id,
							$i->org_name,
							$i->emp_id, 
							$i->emp_name,
							$i->position_id,
							$i->position_name,
							$i->cds_id, 
							$i->cds_name, 
							$request->current_appraisal_year, 
							$request->month_id,
							$i->cds_value
							));
					}
				} else {
					$sheet->appendRow(array('Appraisal Type ID', 'Level ID', 'Level Name', 'Organization ID', 'Organization Name', 'CDS ID', 'CDS Name', 'Year', 'Month', 'CDS Value'));


					foreach ($items as $i) {
						// empty($i->appraisal_year) ? $appraisal_year = $request->current_appraisal_year : $appraisal_year = $i->appraisal_year;
						// empty($i->month_id) ? $month_id = $request->month_id : $month_id = $i->month_id;
						// empty($i->cds_value) ? $cds_value = 0 : $cds_value = $i->cds_value;
						
						$sheet->appendRow(array(
							$request->appraisal_type_id,
							$i->level_id,
							$i->appraisal_level_name,
							$i->org_id,
							$i->org_name,
							$i->cds_id, 
							$i->cds_name, 
							$request->current_appraisal_year, 
							$request->month_id,
							$i->cds_value
							));
					}				
				}
			});

		})->export('xls');



	}
	//add by nong
	public function export(Request $request)
	{
		$qinput = array();

		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		
		
		// if ($request->current_appraisal_year != $config->current_appraisal_year) {
			// return response()->json(['status' => 400, 'data' => 'Selected Year does not match Current Appraisal Year in System Configuration']);
		// }
		
		$checkyear = DB::select("
			select 1
			from appraisal_period
			where appraisal_year = ?
			and date(?) between start_date and end_date		
		", array($config->current_appraisal_year, $request->current_appraisal_year . str_pad($request->month_id,2,'0',STR_PAD_LEFT) . '01'));
		
		if (empty($checkyear)) {
			return 'Appraisal Period not found for the Current Appraisal Year.';
		}
		
		
		/*
		$query = "
			select distinct cr.level_id,  cr.org_id, al.appraisal_level_name,r.emp_id,r.position_id, org.org_name,  e.emp_name,  po.position_name, cds.cds_id, cds.cds_name, ifnull(cr.cds_value,0) as cds_value, ap.appraisal_year
			from appraisal_item_result r
			left outer join employee e on r.emp_id = e.emp_id 
			inner join appraisal_item i on r.item_id = i.item_id
			left outer join appraisal_item_position p on i.item_id = p.item_id
			inner join kpi_cds_mapping m on i.item_id = m.item_id
			inner join cds on m.cds_id = cds.cds_id
			inner join appraisal_period ap on r.period_id = ap.period_id
			inner join system_config sys on ap.appraisal_year = sys.current_appraisal_year
			inner join emp_result er on r.emp_result_id = er.emp_result_id	
			left outer join position po on r.position_id = po.position_id
			left outer join cds_result cr on cds.cds_id = cr.cds_id
			inner join org on cr.org_id = org.org_id
			inner join appraisal_level al on cr.level_id = al.level_id
			and cr.year = {$request->current_appraisal_year}
			and cr.appraisal_month_no = {$request->month_id}
			where cds.is_sql = 0	
		";
		*/

		$query = "
			select distinct cr.level_id, cr.org_id, al.appraisal_level_name,
			cr.emp_id,cr.position_id, org.org_name, e.emp_name, po.position_name,
			 cds.cds_id, cds.cds_name, ifnull(cr.cds_value,0) as cds_value,
			 ap.appraisal_year 
			from appraisal_item_result r 

			inner join appraisal_item i on r.item_id = i.item_id 
			left outer join appraisal_item_position p on i.item_id = p.item_id 
			inner join kpi_cds_mapping m on i.item_id = m.item_id 
			inner join cds on m.cds_id = cds.cds_id 
			inner join appraisal_period ap on r.period_id = ap.period_id 
			inner join system_config sys on ap.appraisal_year = sys.current_appraisal_year 
			inner join emp_result er on r.emp_result_id = er.emp_result_id 

			left outer join cds_result cr on cds.cds_id = cr.cds_id 

			inner join org on cr.org_id = org.org_id 
			inner join appraisal_level al on cr.level_id = al.level_id 
			left outer join employee e on cr.emp_id = e.emp_id 
			left outer join position po on cr.position_id = po.position_id 


			
			where cds.is_sql = 0	
			and cr.year = {$request->current_appraisal_year}
			and cr.appraisal_month_no = {$request->month_id}
		";

		
		// $qinput[] = $request->current_appraisal_year;
		// $qinput[] = $request->month_id;
		
		//empty($request->current_appraisal_year) ?: ($query .= " AND appraisal_year = ? " AND $qinput[] = $request->current_appraisal_year);
		//empty($request->month_id) ?: ($query .= " And appraisal_month_no = ? " AND $qinput[] = $request->month_id);
		
		if (!empty($request->current_appraisal_year) && !empty($request->month_id)) {
			$current_date = $request->current_appraisal_year . str_pad($request->month_id,2,'0',STR_PAD_LEFT) . '01';
			$query .= " and date(?) between ap.start_date and ap.end_date ";
			$qinput[] = $current_date;
		}
		
		empty($request->level_id) ?: ($query .= " And cr.level_id = ? " AND $qinput[] = $request->level_id);
		empty($request->org_id) ?: ($query .= " And cr.org_id = ? " AND $qinput[] = $request->org_id);
		empty($request->position_id) ?: ($query .= " And cr.position_id = ? " AND $qinput[] = $request->position_id);
		empty($request->emp_id) ?: ($query .= " And cr.emp_id = ? " AND $qinput[] = $request->emp_id);
		empty($request->appraisal_type_id) ?: ($query .= " And cr.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
		
		$qfooter = " Order by cr.emp_id, cds.cds_id ";





		
		$items = DB::select($query . $qfooter, $qinput);

		
				   // echo $query;
				   // echo "<br>";
				   // print_r($qinput);
				
		//echo "111";
		//echo count($items);
		if(count($items)<=0){
				//echo "222";
			$items =[];
			$qinput=[]; 
				$query = "
					select distinct r.level_id, al.appraisal_level_name, r.org_id, org.org_name, r.emp_id, e.emp_name, r.position_id, po.position_name, cds.cds_id, cds.cds_name, 0.00  as cds_value, ap.appraisal_year
					from appraisal_item_result r
					left outer join employee e on r.emp_id = e.emp_id 
					inner join appraisal_item i on r.item_id = i.item_id
					left outer join appraisal_item_position p on i.item_id = p.item_id
					inner join kpi_cds_mapping m on i.item_id = m.item_id
					inner join cds on m.cds_id = cds.cds_id
					inner join appraisal_period ap on r.period_id = ap.period_id
					inner join system_config sys on ap.appraisal_year = sys.current_appraisal_year
					inner join emp_result er on r.emp_result_id = er.emp_result_id	
					left outer join position po on r.position_id = po.position_id
					left outer join org on r.org_id = org.org_id
					left outer join appraisal_level al on r.level_id = al.level_id
					left outer join cds_result cr on cds.cds_id = cr.cds_id
					and cr.year = {$request->current_appraisal_year}
					and cr.appraisal_month_no = {$request->month_id}
					where cds.is_sql = 0	
				";
				
				// $qinput[] = $request->current_appraisal_year;
				// $qinput[] = $request->month_id;
				
				//empty($request->current_appraisal_year) ?: ($query .= " AND appraisal_year = ? " AND $qinput[] = $request->current_appraisal_year);
				//empty($request->month_id) ?: ($query .= " And appraisal_month_no = ? " AND $qinput[] = $request->month_id);
				
				if (!empty($request->current_appraisal_year) && !empty($request->month_id)) {
					$current_date = $request->current_appraisal_year . str_pad($request->month_id,2,'0',STR_PAD_LEFT) . '01';
					$query .= " and date(?) between ap.start_date and ap.end_date ";
					$qinput[] = $current_date;
				}
				
				empty($request->level_id) ?: ($query .= " And r.level_id = ? " AND $qinput[] = $request->level_id);
				empty($request->org_id) ?: ($query .= " And r.org_id = ? " AND $qinput[] = $request->org_id);
				empty($request->position_id) ?: ($query .= " And p.position_id = ? " AND $qinput[] = $request->position_id);
				empty($request->emp_id) ?: ($query .= " And e.emp_id = ? " AND $qinput[] = $request->emp_id);
				empty($request->appraisal_type_id) ?: ($query .= " And er.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
				
				$qfooter = " Order by r.emp_id, cds.cds_id ";





				
				$items = DB::select($query . $qfooter, $qinput);
				   /*
				   echo $query;
				   echo "<br>";
				   print_r($qinput);
				   */
		}
		
		 

		
		$filename = "CDS_Result";  //. date('dm') .  substr(date('Y') + 543,2,2);
		$x = Excel::create($filename, function($excel) use($items, $filename, $request) {
			$excel->sheet($filename, function($sheet) use($items, $request) {
				
				if ($request->appraisal_type_id == 2) {
					$sheet->appendRow(array('Appraisal Type ID', 'Level ID', 'Level Name', 'Organization ID', 'Organization Name', 'Employee ID', 'Employee Name', 'Position ID', 'Position Name', 'CDS ID', 'CDS Name', 'Year', 'Month', 'CDS Value'));

					foreach ($items as $i) {
						// empty($i->appraisal_year) ? $appraisal_year = $request->current_appraisal_year : $appraisal_year = $i->appraisal_year;
						// empty($i->month_id) ? $month_id = $request->month_id : $month_id = $i->month_id;
						// empty($i->cds_value) ? $cds_value = 0 : $cds_value = $i->cds_value;
						
						$sheet->appendRow(array(
							$request->appraisal_type_id,
							$i->level_id,
							$i->appraisal_level_name,
							$i->org_id,
							$i->org_name,
							$i->emp_id, 
							$i->emp_name,
							$i->position_id,
							$i->position_name,
							$i->cds_id, 
							$i->cds_name, 
							$request->current_appraisal_year, 
							$request->month_id,
							$i->cds_value
							));
					}
				} else {
					$sheet->appendRow(array('Appraisal Type ID', 'Level ID', 'Level Name', 'Organization ID', 'Organization Name', 'CDS ID', 'CDS Name', 'Year', 'Month', 'CDS Value'));


					foreach ($items as $i) {
						// empty($i->appraisal_year) ? $appraisal_year = $request->current_appraisal_year : $appraisal_year = $i->appraisal_year;
						// empty($i->month_id) ? $month_id = $request->month_id : $month_id = $i->month_id;
						// empty($i->cds_value) ? $cds_value = 0 : $cds_value = $i->cds_value;
						
						$sheet->appendRow(array(
							$request->appraisal_type_id,
							$i->level_id,
							$i->appraisal_level_name,
							$i->org_id,
							$i->org_name,
							$i->cds_id, 
							$i->cds_name, 
							$request->current_appraisal_year, 
							$request->month_id,
							$i->cds_value
							));
					}				
				}
			});

		})->export('xls');
		


	}
	
	public function year_list()
	{
		$items = DB::select("
			select current_appraisal_year
			from 
			(
				select current_appraisal_year 
				from system_config
				union
				select current_appraisal_year - 1
				from system_config
				union
				select distinct year
				from cds_result
			) a
			order by current_appraisal_year desc
		");
		return response()->json($items);
	}
	
	public function month_list()
	{
		$items = DB::select("
			Select month_id, month_name
			From period_month
			Order by month_id
		");
		return response()->json($items);
	}	
	
    public function al_list()
    {
		$items = DB::select("
			select level_id, appraisal_level_name
			from appraisal_level
			where is_active = 1
			and is_hr = 0
			order by level_id
		");
		return response()->json($items);
    }
		
	public function auto_position_name(Request $request)
	{
		$items = DB::select("
			Select distinct position_id, position_name
			From position
			Where position_name like ? and is_active = 1
			Order by position_name		
		", array('%'.$request->position_name.'%'));
		return response()->json($items);
	}
	
	public function auto_emp_name(Request $request)
	{
		$items = DB::select("
			Select distinct e.emp_id, e.emp_name
			From employee e
			where e.emp_name like ? and e.is_active = 1
			Order by e.emp_name		
		", array('%'.$request->emp_name.'%'));
		return response()->json($items);
	}
	
	public function index(Request $request)
	{

		$qinput = array();
		$query = "
			select r.cds_result_id, r.emp_id, e.emp_code, e.emp_name, r.org_id, o.org_code, o.org_name, l.appraisal_level_name, r.cds_id, s.cds_name, r.year, m.month_name, r.cds_value
			From cds_result r 
			left outer join employee e
			on r.emp_id = e.emp_id
			left outer join cds s
			on r.cds_id = s.cds_id
			left outer join appraisal_level l
			on r.level_id = l.level_id
			left outer join period_month m
			on r.appraisal_month_no = m.month_id
			left outer join org o
			on r.org_id = o.org_id
			left outer join position p
			on r.position_id = p.position_id
			where 1 = 1
			and l.is_hr = 0
		";
				
		empty($request->current_appraisal_year) ?: ($query .= " AND year = ? " AND $qinput[] = $request->current_appraisal_year);
		empty($request->appraisal_type_id) ?: ($query .= " AND r.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
		empty($request->month_id) ?: ($query .= " And r.appraisal_month_no = ? " AND $qinput[] = $request->month_id);
		empty($request->level_id) ?: ($query .= " And r.level_id = ? " AND $qinput[] = $request->level_id);
		empty($request->org_id) ?: ($query .= " And r.org_id = ? " AND $qinput[] = $request->org_id);
		empty($request->position_id) ?: ($query .= " And p.position_id = ? " AND $qinput[] = $request->position_id);
		empty($request->emp_id) ?: ($query .= " And r.emp_id = ? " AND $qinput[] = $request->emp_id);
		
		$qfooter = " Order by l.appraisal_level_name, e.emp_name, r.cds_id ";

		// echo $query . $qfooter;
		// echo"<br>";
		// print_r($qinput);
		
		$items = DB::select($query . $qfooter, $qinput);
		
		
		// Get the current page from the url if it's not set default to 1
		empty($request->page) ? $page = 1 : $page = $request->page;
		
		// Number of items per page
		empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;
		
		$offSet = ($page * $perPage) - $perPage; // Start displaying items from this number

		// Get only the items you need using array_slice (only get 10 items since that's what you need)
		$itemsForCurrentPage = array_slice($items, $offSet, $perPage, false);
		
		// Return the paginator with only 10 items but with the count of all items and set the it on the correct page
		$result = new LengthAwarePaginator($itemsForCurrentPage, count($items), $perPage, $page);			


		return response()->json($result);
	}
	
	public function destroy($cds_result_id)
	{
		try {
			$item = CDSResult::findOrFail($cds_result_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'CDS Result not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this CDS Result is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}		
	
}
