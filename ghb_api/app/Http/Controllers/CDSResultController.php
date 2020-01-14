<?php

namespace App\Http\Controllers;

use App\CDS;
use App\CDSResult;
use App\PeriodMonth;
use App\SystemConfiguration;
use App\Employee;
use App\Org;
use App\CDSFile;
use App\AppraisalLevel;
use App\ReasonCdsResult;

use Auth;
use DB;
use File;
use Validator;
use Excel;
use Exception;
use Log;
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

		$emp = Employee::find(Auth::id());
		$level = AppraisalLevel::find($emp->level_id);
		$is_hr = $level->is_hr;

		foreach ($request->file() as $f) {
			$items = Excel::load($f, function ($reader) { })->get();
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
						'level_id' => 'required|integer'
						//'cds_value' => 'required|numeric',
					]);

					if ($validator->fails()) {
						$errors[] = ['employee_id' => $i->employee_id, 'errors' => $validator->errors()];
					} else {
						$month_name = PeriodMonth::find($i->month);
						// $a_date = $i['year'] . "-" . $i['month'] . "-01";
						$a_date = $i['year'] . "-" . $i['month'] . "-" . cal_days_in_month(CAL_GREGORIAN, $i['month'], $i['year']);
						if (empty($month_name)) {
							$errors[] = ['employee_id' => $i->employee_id, 'errors' => 'Invalid Month.'];
						} else {
							try {
								$result_check = CDSResult::where("emp_id",$i->employee_id)
								->where("cds_id",$i->cds_id)
								->where('year',$i->year)
								->where('appraisal_month_no',$i->month)
								->where('appraisal_type_id',$i->appraisal_type_id)
								->where('position_id',$i->position_id)
								->where('org_id',$i->organization_id);
								
								if ($result_check->count() == 0) {

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

									if ($is_hr == 1){
										$cds_result->forecast = $i->forecast;
										$cds_result->forecast_bu = $i->forecast_bu;
										$cds_result->cds_value = $i->cds_value;
									}else if ($is_hr == 1 && $i->level_id == 2 ){
										$cds_result->forecast = $i->forecast;
										$cds_result->forecast_bu = $i->forecast_bu;
										$cds_result->cds_value = $i->cds_value;
									}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3 && $i->level_id == 2 ){
										$cds_result->forecast_bu = $i->forecast_bu;
									}else if ($emp->level_id == 2 && $i->level_id == 2 ){
										$cds_result->forecast = $i->forecast;
										$cds_result->cds_value = $i->cds_value;
									}else if ($is_hr == 0 ){
										$cds_result->forecast_bu = $i->forecast_bu;
										$cds_result->cds_value = $i->cds_value;
									}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3){
										$cds_result->forecast_bu = $i->forecast_bu;
									}else if ($emp->level_id == 2){
										$cds_result->forecast = $i->forecast;
										$cds_result->cds_value = $i->cds_value;
									}

									$cds_result->etl_dttm = date("Y-m-t", strtotime($a_date));
									$cds_result->created_by = Auth::id();
									$cds_result->updated_by = Auth::id();
									$cds_result->save();
								} else {

									$update_data = array();
									if ($is_hr == 1){
										$update_data = [
											'forecast' => $i->forecast
											,'forecast_bu' => $i->forecast_bu
											,'cds_value' => $i->cds_value
											,'etl_dttm'=>date("Y-m-t", strtotime($a_date))
											,'updated_by' => Auth::id()
										];
									}else if ($is_hr == 1 && $i->level_id == 2 ){
										$update_data = [
											'forecast' => $i->forecast
											,'forecast_bu' => $i->forecast_bu
											,'cds_value' => $i->cds_value
											,'etl_dttm'=>date("Y-m-t", strtotime($a_date))
											,'updated_by' => Auth::id()
										];
									}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3 && $i->level_id == 2 ){
										$update_data = [
											'forecast_bu' => $i->forecast_bu
											,'etl_dttm'=>date("Y-m-t", strtotime($a_date))
											,'updated_by' => Auth::id()
										];
									}else if ($emp->level_id == 2 && $i->level_id == 2 ){
										$update_data = [
											'forecast' => $i->forecast
											,'cds_value' => $i->cds_value
											,'etl_dttm'=>date("Y-m-t", strtotime($a_date))
											,'updated_by' => Auth::id()
										];
									}else if ($is_hr == 0 ){
										$update_data = [
											'forecast_bu' => $i->forecast_bu
											,'cds_value' => $i->cds_value
											,'etl_dttm'=>date("Y-m-t", strtotime($a_date))
											,'updated_by' => Auth::id()
										];
									}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3){
										$update_data = [
											'forecast_bu' => $i->forecast_bu
											,'etl_dttm'=>date("Y-m-t", strtotime($a_date))
											,'updated_by' => Auth::id()
										];
									}else if ($emp->level_id == 2){
										$update_data = [
											'forecast' => $i->forecast
											,'cds_value' => $i->cds_value
											,'etl_dttm'=>date("Y-m-t", strtotime($a_date))
											,'updated_by' => Auth::id()
										];
									}

									CDSResult::where("emp_id",$i->employee_id)
									->where("cds_id",$i->cds_id)
									->where('year',$i->year)
									->where('appraisal_month_no',$i->month)
									->where('appraisal_type_id',$i->appraisal_type_id)
									->where('position_id',$i->position_id)
									->where('org_id',$i->organization_id)
									->update($update_data);							
								}
							} catch (Exception $e) {
								$errors[] = ['employee_id' => $i->employee_id, 'errors' => substr($e, 0, 254)];
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
						// 'cds_value' => 'required|numeric',
					]);

					if ($validator->fails()) {
						$errors[] = ['org_id' => $i->organization_id, 'errors' => $validator->errors()];
					} else {
						$month_name = PeriodMonth::find($i->month);
						// $a_date = $i['year'] . "-" . $i['month'] . "-01";
						$a_date = $i['year'] . "-" . $i['month'] . "-" . cal_days_in_month(CAL_GREGORIAN, $i['month'], $i['year']);
						if (empty($month_name)) {
							$errors[] = ['org_id' => $i->organization_id, 'errors' => 'Invalid Month.'];
						} else {
							try {
								$result_check = CDSResult::where("org_id",$i->organization_id)
								->where("cds_id",$i->cds_id)
								->where('year',$i->year)
								->where('appraisal_month_no',$i->month)
								->where('appraisal_type_id',$i->appraisal_type_id);
								
								
								if ($result_check->count() == 0) {

									$cds_result = new CDSResult;
									$cds_result->appraisal_type_id = $i->appraisal_type_id;
									$cds_result->org_id = $i->organization_id;
									$cds_result->cds_id = $i->cds_id;
									$cds_result->year = $i->year;
									$cds_result->level_id = $i->level_id;
									$cds_result->appraisal_month_no = $i->month;
									$cds_result->appraisal_month_name = $month_name->month_name;

									if ($is_hr == 1){
										$cds_result->forecast = $i->forecast;
										$cds_result->forecast_bu = $i->forecast_bu;
										$cds_result->cds_value = $i->cds_value;
									}else if ($is_hr == 1 && $i->level_id == 2 ){
										$cds_result->forecast = $i->forecast;
										$cds_result->forecast_bu = $i->forecast_bu;
										$cds_result->cds_value = $i->cds_value;
									}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3 && $i->level_id == 2 ){
										$cds_result->forecast_bu = $i->forecast_bu;
									}else if ($emp->level_id == 2 && $i->level_id == 2 ){
										$cds_result->forecast = $i->forecast;
										$cds_result->cds_value = $i->cds_value;
									}else if ($is_hr == 0 ){
										$cds_result->forecast_bu = $i->forecast_bu;
										$cds_result->cds_value = $i->cds_value;
									}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3){
										$cds_result->forecast_bu = $i->forecast_bu;
									}else if ($emp->level_id == 2){
										$cds_result->forecast = $i->forecast;
										$cds_result->cds_value = $i->cds_value;
									}
									
									$cds_result->etl_dttm = date("Y-m-t", strtotime($a_date));
									$cds_result->created_by = Auth::id();
									$cds_result->updated_by = Auth::id();
									$cds_result->save();
								} else {
									$update_data = array();
									if ($is_hr == 1){
										$update_data = [
											'forecast' => $i->forecast
											,'forecast_bu' => $i->forecast_bu
											,'cds_value' => $i->cds_value
											,'etl_dttm'=>date("Y-m-t", strtotime($a_date))
											,'updated_by' => Auth::id()
										];
									}else if ($is_hr == 1 && $i->level_id == 2 ){
										$update_data = [
											'forecast' => $i->forecast
											,'forecast_bu' => $i->forecast_bu
											,'cds_value' => $i->cds_value
											,'etl_dttm'=>date("Y-m-t", strtotime($a_date))
											,'updated_by' => Auth::id()
										];
									}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3 && $i->level_id == 2 ){
										$update_data = [
											'forecast_bu' => $i->forecast_bu
											,'etl_dttm'=>date("Y-m-t", strtotime($a_date))
											,'updated_by' => Auth::id()
										];
									}else if ($emp->level_id == 2 && $i->level_id == 2 ){
										$update_data = [
											'forecast' => $i->forecast
											,'cds_value' => $i->cds_value
											,'etl_dttm'=>date("Y-m-t", strtotime($a_date))
											,'updated_by' => Auth::id()
										];
									}else if ($is_hr == 0 ){
										$update_data = [
											'forecast_bu' => $i->forecast_bu
											,'cds_value' => $i->cds_value
											,'etl_dttm'=>date("Y-m-t", strtotime($a_date))
											,'updated_by' => Auth::id()
										];
									}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3){
										$update_data = [
											'forecast_bu' => $i->forecast_bu
											,'etl_dttm'=>date("Y-m-t", strtotime($a_date))
											,'updated_by' => Auth::id()
										];
									}else if ($emp->level_id == 2){
										$update_data = [
											'forecast' => $i->forecast
											,'cds_value' => $i->cds_value
											,'etl_dttm'=>date("Y-m-t", strtotime($a_date))
											,'updated_by' => Auth::id()
										];
									}

									CDSResult::where("org_id",$i->organization_id)
									->where("cds_id",$i->cds_id)
									->where('year',$i->year)
									->where('appraisal_month_no',$i->month)
									->where('appraisal_type_id',$i->appraisal_type_id)
									->update($update_data);							
								}
							} catch (Exception $e) {
								$errors[] = ['organization_id' => $i->organization_id, 'errors' => substr($e, 0, 254)];
							}
						}
					}
				}
			}
		}
		return response()->json(['status' => 200, 'errors' => $errors]);
	}

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

		$emp = Employee::find(Auth::id());
		$level = AppraisalLevel::find($emp->level_id);
		$is_hr = $level->is_hr;
		$org = Org::find($emp->org_id);		
		
		$all_emp = DB::select("
			SELECT sum(b.is_all_employee) count_no
			from employee a
			left outer join appraisal_level b
			on a.level_id = b.level_id
			where emp_code = ?
		", array(Auth::id()));

		$all_org = DB::select("
			SELECT sum(is_show_corporate) count_no
			from employee
			where emp_code = ?
		", array(Auth::id()));
		if($all_org[0]->count_no > 0){
			$co = DB::select("
				select group_concat(o.org_code) org_code
				from org o
				where level_id = 2
				");
			
		}
		$is_show_corporate = empty($co[0]->org_code) ? '' : " OR FIND_IN_SET(org.org_code,'".$co[0]->org_code."') ";

		//# สิทธิ์ โดยเมื่อ User Login เข้ามาแล้วส่วนของหน่วยงานที่แสดงใน Parameter ให้เช็ค org_id ที่ table emp_multi_org_mapping เพิ่ม
		$muti_org =  DB::select("
		select group_concat(o.org_code) org_code
		from emp_org e
		inner join org o on e.org_id = o.org_id
		where emp_id = ?
		", array($emp->emp_id));

		$is_muti_org = empty($muti_org[0]->org_code) ? '' : " OR FIND_IN_SET(org.org_code,'".$muti_org[0]->org_code."') ";


		if ($all_emp[0]->count_no > 0) {
			$is_all_sql = "";
			$is_all_sql_org = "";
		} else {
			$is_all_sql = " and (e.emp_code = '{$emp->emp_code}' or e.chief_emp_code = '{$emp->emp_code}') ";
			$is_all_sql_org = " and (org.org_code = '{$org->org_code}' or org.parent_org_code = '{$org->org_code}' {$is_show_corporate} {$is_muti_org} ) ";
		}

		if ($is_hr == 0) {
			$is_hr_sql = " and cds.is_hr = 0 ";
		} else {
			$is_hr_sql = "";
		}

		$checkyear = DB::select("
			select 1
			from appraisal_period
			where appraisal_year = ?
			and date(?) between start_date and end_date		
		", array($config->current_appraisal_year, $request->current_appraisal_year . str_pad($request->month_id, 2, '0', STR_PAD_LEFT) . '01'));

		if (empty($checkyear)) {
			return 'Appraisal Period not found for the Current Appraisal Year.';
		}


		if ($request->appraisal_type_id == 2) {
			$query = "
				select distinct r.level_id, al.appraisal_level_name, r.org_id, org.org_name, r.emp_id, e.emp_name, r.position_id, po.position_name, cds.cds_id, cds.cds_name, uom.uom_name
					, ifnull(cr.cds_value,'') as cds_value
					, ifnull(cr.forecast,'') as forecast
					, ifnull(cr.forecast_bu,'') as forecast_bu
					, ap.appraisal_year
				from appraisal_item_result r
				left outer join employee e on r.emp_id = e.emp_id 
				inner join appraisal_item i on r.item_id = i.item_id
				inner join uom on uom.uom_id = i.uom_id
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
				and cr.emp_id = e.emp_id
				and r.org_id = cr.org_id
				and r.position_id = cr.position_id
			";
			
			empty($request->org_id) ?: ($query .= " And cr.org_id = " . $request->org_id);
			$query .= "
				and cr.year = {$request->current_appraisal_year}
				and cr.appraisal_month_no = {$request->month_id}
				and cr.appraisal_type_id = {$request->appraisal_type_id}
			";
			empty($request->level_id) ?: ($query .= " And cr.level_id = ? " and $qinput[] = $request->level_id);
			$query .= "
				where cds.is_sql = 0	
			" . $is_all_sql . $is_hr_sql;

			empty($request->level_id) ?: ($query .= " And r.level_id = ? " and $qinput[] = $request->level_id);
			empty($request->emp_id) ?: ($query .= " And e.emp_id = ? " and $qinput[] = $request->emp_id);
		} else {
			$query = "
				select distinct r.level_id, al.appraisal_level_name, r.org_id, org.org_name, r.position_id, po.position_name, cds.cds_id, cds.cds_name, uom.uom_name
					, ifnull(cr.cds_value,'') as cds_value
					, ifnull(cr.forecast,'') as forecast
					, ifnull(cr.forecast_bu,'') as forecast_bu
					, ap.appraisal_year
				from appraisal_item_result r
				inner join appraisal_item i on r.item_id = i.item_id
				inner join uom on uom.uom_id = i.uom_id
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
				and cr.org_id = org.org_id
				and r.org_id = cr.org_id
			";
			empty($request->org_id) ?: ($query .= " And cr.org_id = " . $request->org_id);
			$query .= "
				and cr.year = {$request->current_appraisal_year}
				and cr.appraisal_month_no = {$request->month_id}
				and cr.appraisal_type_id = {$request->appraisal_type_id}
				where cds.is_sql = 0	
			" . $is_all_sql_org . $is_hr_sql;
		}
		if (!empty($request->current_appraisal_year) && !empty($request->month_id)) {
			$current_date = $request->current_appraisal_year . str_pad($request->month_id, 2, '0', STR_PAD_LEFT) . '01';
			$query .= " and date(?) between ap.start_date and ap.end_date ";
			$qinput[] = $current_date;
		}

		empty($request->level_id) ?: ($query .= " And org.level_id = ? " and $qinput[] = $request->level_id);
		empty($request->org_id) ?: ($query .= " And r.org_id = ? " and $qinput[] = $request->org_id);
		empty($request->position_id) ?: ($query .= " And r.position_id = ? " and $qinput[] = $request->position_id);
		empty($request->appraisal_type_id) ?: ($query .= " And er.appraisal_type_id = ? " and $qinput[] = $request->appraisal_type_id);

		$qfooter = " Order by r.emp_id, cds.cds_id ";

		$items = DB::select($query . $qfooter, $qinput);
				
		$filename = "CDS_Result";  //. date('dm') .  substr(date('Y') + 543,2,2);
		$x = Excel::create($filename, function($excel) use($items, $filename, $request
			, $emp, $is_hr) {
			$excel->sheet($filename, function($sheet) use($items, $request
				, $emp, $is_hr) {
				
				if ($request->appraisal_type_id == 2) {
					
					$field = array('Appraisal Type ID', 'Level ID', 'Level Name', 'Organization ID', 'Organization Name', 'Employee ID', 'Employee Name', 'Position ID', 'Position Name', 'CDS ID', 'CDS Name', 'UOM','Year', 'Month');

					// manage header name ตามสิทธิ์ user
					
					if ($is_hr == 1 && $request->level_id == 2 ){
						array_push($field, 'Forecast', 'Forecast BU', 'CDS Value');
					}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3 && $request->level_id == 2 ){
						array_push($field, 'Forecast BU');
					}else if ($emp->level_id == 2 && $request->level_id == 2 ){
						array_push($field, 'Forecast', 'CDS Value');
					}else if ($is_hr == 0 ){
						array_push($field, 'Forecast BU', 'CDS Value');
					}else if ($is_hr == 1){
						array_push($field, 'Forecast', 'Forecast BU', 'CDS Value');
					}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3){
						array_push($field,'Forecast BU');
					}else if ($emp->level_id == 2){
						array_push($field, 'Forecast', 'CDS Value');
					}

					$sheet->appendRow($field);

					foreach ($items as $i) {

						$field_data = array(
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
							$i->uom_name, 
							$request->current_appraisal_year, 
							$request->month_id
						);

						// manage data ตามสิทธิ์ user
						if ($is_hr == 1 && $request->level_id == 2 ){
							array_push($field_data, $i->forecast, $i->forecast_bu, $i->cds_value);
						}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3 && $request->level_id == 2 ){
							array_push($field_data,$i->forecast_bu);
						}else if ($emp->level_id == 2 && $request->level_id == 2 ){
							array_push($field_data, $i->forecast, $i->cds_value);
						}else if ($is_hr == 0 ){
							array_push($field_data, $i->forecast_bu, $i->cds_value);
						}else if ($is_hr == 1){
							array_push($field_data, $i->forecast, $i->forecast_bu, $i->cds_value);
						}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3){
							array_push($field_data, $i->forecast_bu);
						}else if ($emp->level_id == 2){
							array_push($field_data, $i->forecast, $i->cds_value);
						}
					
						
						$sheet->appendRow($field_data);
					}
				} else {

					$field = array('Appraisal Type ID', 'Level ID', 'Level Name', 'Organization ID', 'Organization Name', 'CDS ID', 'CDS Name', 'UOM','Year', 'Month');

					// manage header name ตามสิทธิ์ user
					// if ($is_hr == 1){
					// 	array_push($field, 'Forecast', 'Forecast BU', 'CDS Value');
					// }else if ($emp->is_show_corporate == 1 && $emp->level_id == 3){
					// 	array_push($field,'Forecast BU');
					// }else if ($emp->level_id == 2){
					// 	array_push($field, 'Forecast', 'CDS Value');
					// }
					// manage header name ตามสิทธิ์ user
					if ($is_hr == 1 && $request->level_id == 2 ){
						array_push($field, 'Forecast', 'Forecast BU', 'CDS Value');
					}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3 && $request->level_id == 2 ){
						array_push($field, 'Forecast BU');
					}else if ($emp->level_id == 2 && $request->level_id == 2 ){
						array_push($field, 'Forecast', 'CDS Value');
					}else if ($is_hr == 0 ){
						array_push($field, 'Forecast BU', 'CDS Value');
					}else if ($is_hr == 1){
						array_push($field, 'Forecast', 'Forecast BU', 'CDS Value');
					}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3){
						array_push($field,'Forecast BU');
					}else if ($emp->level_id == 2){
						array_push($field, 'Forecast', 'CDS Value');
					}


					$sheet->appendRow($field);

					foreach ($items as $i) {

						$field_data = array(
							$request->appraisal_type_id,
							$i->level_id,
							$i->appraisal_level_name,
							$i->org_id,
							$i->org_name,
							$i->cds_id, 
							$i->cds_name, 
							$i->uom_name, 
							$request->current_appraisal_year, 
							$request->month_id
						);

						// manage data ตามสิทธิ์ user
						if ($is_hr == 1 && $request->level_id == 2 ){
							array_push($field_data, $i->forecast, $i->forecast_bu, $i->cds_value);
						}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3 && $request->level_id == 2 ){
							array_push($field_data,$i->forecast_bu);
						}else if ($emp->level_id == 2 && $request->level_id == 2 ){
							array_push($field_data, $i->forecast, $i->cds_value);
						}else if ($is_hr == 0 ){
							array_push($field_data, $i->forecast_bu, $i->cds_value);
						}else if ($is_hr == 1){
							array_push($field_data, $i->forecast, $i->forecast_bu, $i->cds_value);
						}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3){
							array_push($field_data, $i->forecast_bu);
						}else if ($emp->level_id == 2){
							array_push($field_data, $i->forecast, $i->cds_value);
						}
						
						$sheet->appendRow($field_data);
					}				
				}
			});
		})->export('xls');
	}
	//add by nong
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
		", array($config->current_appraisal_year, $request->current_appraisal_year . str_pad($request->month_id, 2, '0', STR_PAD_LEFT) . '01'));

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
			cr.emp_id,cr.position_id, org.org_name, e.emp_code, e.emp_name, po.position_name,
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
			$current_date = $request->current_appraisal_year . str_pad($request->month_id, 2, '0', STR_PAD_LEFT) . '01';
			$query .= " and date(?) between ap.start_date and ap.end_date ";
			$qinput[] = $current_date;
		}

		empty($request->level_id) ?: ($query .= " And cr.level_id = ? " and $qinput[] = $request->level_id);
		empty($request->level_id_emp) ?: ($query .= " And e.level_id = ? " and $qinput[] = $request->level_id_emp);
		empty($request->org_id) ?: ($query .= " And cr.org_id = ? " and $qinput[] = $request->org_id);
		empty($request->position_id) ?: ($query .= " And cr.position_id = ? " and $qinput[] = $request->position_id);
		empty($request->emp_id) ?: ($query .= " And cr.emp_id = ? " and $qinput[] = $request->emp_id);
		empty($request->appraisal_type_id) ?: ($query .= " And cr.appraisal_type_id = ? " and $qinput[] = $request->appraisal_type_id);

		$qfooter = " Order by cr.emp_id, cds.cds_id ";






		$items = DB::select($query . $qfooter, $qinput);


		// echo $query;
		// echo "<br>";
		// print_r($qinput);

		//echo "111";
		//echo count($items);
		if (count($items) <= 0) {
			//echo "222";
			$items = [];
			$qinput = [];
			$query = "
					select distinct r.level_id, al.appraisal_level_name, r.org_id, org.org_name, r.emp_id, e.emp_name, e.emp_code,r.position_id, po.position_name, cds.cds_id, cds.cds_name, 0.00  as cds_value, ap.appraisal_year
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
				$current_date = $request->current_appraisal_year . str_pad($request->month_id, 2, '0', STR_PAD_LEFT) . '01';
				$query .= " and date(?) between ap.start_date and ap.end_date ";
				$qinput[] = $current_date;
			}

			empty($request->level_id) ?: ($query .= " And org.level_id = ? " and $qinput[] = $request->level_id);
			empty($request->level_id_emp) ?: ($query .= " And e.level_id = ? " and $qinput[] = $request->level_id_emp);
			empty($request->org_id) ?: ($query .= " And r.org_id = ? " and $qinput[] = $request->org_id);
			empty($request->position_id) ?: ($query .= " And p.position_id = ? " and $qinput[] = $request->position_id);
			empty($request->emp_id) ?: ($query .= " And e.emp_id = ? " and $qinput[] = $request->emp_id);
			empty($request->appraisal_type_id) ?: ($query .= " And er.appraisal_type_id = ? " and $qinput[] = $request->appraisal_type_id);

			$qfooter = " Order by r.emp_id, cds.cds_id ";






			$items = DB::select($query . $qfooter, $qinput);
			/*
				   echo $query;
				   echo "<br>";
				   print_r($qinput);
				   */
		}




		$filename = "CDS_Result";  //. date('dm') .  substr(date('Y') + 543,2,2);
		$x = Excel::create($filename, function ($excel) use ($items, $filename, $request) {
			$excel->sheet($filename, function ($sheet) use ($items, $request) {

				if ($request->appraisal_type_id == 2) {
					$sheet->appendRow(array('Appraisal Type ID', 'Level ID', 'Level Name', 'Organization ID', 'Organization Name', 'Employee ID', 'Employee Code', 'Employee Name', 'Position ID', 'Position Name', 'CDS ID', 'CDS Name', 'Year', 'Month', 'CDS Value'));

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
							$i->emp_code,
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
			order by (
			 SELECT current_appraisal_year
			 FROM system_config
			) DESC
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
		$emp = Employee::find(Auth::id());
		$co = Org::find($emp->org_id);

		$re_emp = array();

		$emp_list = array();

		$emps = DB::select("
			select distinct org_code
			from org
			where parent_org_code = ?
			", array($co->org_code));

		foreach ($emps as $e) {
			$emp_list[] = $e->org_code;
			$re_emp[] = $e->org_code;
		}

		$emp_list = array_unique($emp_list);

		// Get array keys
		$arrayKeys = array_keys($emp_list);
		// Fetch last array key
		$lastArrayKey = array_pop($arrayKeys);
		//iterate array
		$in_emp = '';
		foreach ($emp_list as $k => $v) {
			if ($k == $lastArrayKey) {
				//during array iteration this condition states the last element.
				$in_emp .= "'" . $v . "'";
			} else {
				$in_emp .= "'" . $v . "'" . ',';
			}
		}

		do {
			empty($in_emp) ? $in_emp = "null" : null;

			$emp_list = array();

			$emp_items = DB::select("
				select distinct org_code
				from org
				where parent_org_code in ({$in_emp})
				and parent_org_code != org_code
				and is_active = 1			
				");

			foreach ($emp_items as $e) {
				$emp_list[] = $e->org_code;
				$re_emp[] = $e->org_code;
			}

			$emp_list = array_unique($emp_list);

			// Get array keys
			$arrayKeys = array_keys($emp_list);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach ($emp_list as $k => $v) {
				if ($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}
		} while (!empty($emp_list));

		$re_emp[] = $co->org_code;
		$re_emp = array_unique($re_emp);

		// Get array keys
		$arrayKeys = array_keys($re_emp);
		// Fetch last array key
		$lastArrayKey = array_pop($arrayKeys);
		//iterate array
		$in_emp = '';
		foreach ($re_emp as $k => $v) {
			if ($k == $lastArrayKey) {
				//during array iteration this condition states the last element.
				$in_emp .= "'" . $v . "'";
			} else {
				$in_emp .= "'" . $v . "'" . ',';
			}
		}

		empty($in_emp) ? $in_emp = "null" : null;

		// $items = DB::select("
		// 	Select distinct position_id, position_name
		// 	From position
		// 	Where position_name like ? and is_active = 1
		// 	Order by position_name		
		// ", array('%'.$request->position_name.'%'));

		$items = DB::select("
			Select distinct p.position_id, p.position_name
			From position p
			inner join employee e on e.position_id = p.position_id
			inner join org on org.org_id = e.org_id 
			Where p.position_name like ?
			and p.is_active = 1
			and org.org_code in ({$in_emp})
			Order by p.position_name asc		
		", array('%' . $request->position_name . '%'));

		return response()->json($items);
	}

	public function auto_emp_name(Request $request)
	{
		// $items = DB::select("
		// Select distinct e.emp_id, e.emp_code, e.emp_name
		// From employee e
		// where e.emp_name like ? and e.is_active = 1
		// Order by e.emp_name		
		// ", array('%'.$request->emp_name.'%'));

		$emp = Employee::find(Auth::id());
		$all_emp = DB::select("
			SELECT sum(b.is_all_employee) count_no
			from employee a
			left outer join appraisal_level b
			on a.level_id = b.level_id
			where emp_code = ?
		", array(Auth::id()));

		$co = Org::find($emp->org_id);

		$re_emp = array();

		$emp_list = array();

		$emps = DB::select("
			select distinct org_code
			from org
			where parent_org_code = ?
			", array($co->org_code));

		foreach ($emps as $e) {
			$emp_list[] = $e->org_code;
			$re_emp[] = $e->org_code;
		}

		$emp_list = array_unique($emp_list);

		// Get array keys
		$arrayKeys = array_keys($emp_list);
		// Fetch last array key
		$lastArrayKey = array_pop($arrayKeys);
		//iterate array
		$in_emp = '';
		foreach ($emp_list as $k => $v) {
			if ($k == $lastArrayKey) {
				//during array iteration this condition states the last element.
				$in_emp .= "'" . $v . "'";
			} else {
				$in_emp .= "'" . $v . "'" . ',';
			}
		}

		do {
			empty($in_emp) ? $in_emp = "null" : null;

			$emp_list = array();

			$emp_items = DB::select("
				select distinct org_code
				from org
				where parent_org_code in ({$in_emp})
				and parent_org_code != org_code
				and is_active = 1			
				");

			foreach ($emp_items as $e) {
				$emp_list[] = $e->org_code;
				$re_emp[] = $e->org_code;
			}

			$emp_list = array_unique($emp_list);

			// Get array keys
			$arrayKeys = array_keys($emp_list);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach ($emp_list as $k => $v) {
				if ($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}
		} while (!empty($emp_list));

		$re_emp[] = $co->org_code;
		$re_emp = array_unique($re_emp);

		// Get array keys
		$arrayKeys = array_keys($re_emp);
		// Fetch last array key
		$lastArrayKey = array_pop($arrayKeys);
		//iterate array
		$in_emp = '';
		foreach ($re_emp as $k => $v) {
			if ($k == $lastArrayKey) {
				//during array iteration this condition states the last element.
				$in_emp .= "'" . $v . "'";
			} else {
				$in_emp .= "'" . $v . "'" . ',';
			}
		}

		empty($in_emp) ? $in_emp = "null" : null;

		empty($request->org_id) ? $org = "" : $org = " and org_id = " . $request->org_id . " ";

		if ($all_emp[0]->count_no > 0) {
			$items = DB::select("
				Select emp_id, emp_code, emp_name
				From employee
				Where emp_name like ?
				and is_active = 1
			" . $org . "
				Order by emp_name asc
			", array('%' . $request->emp_name . '%'));
		} else {
			// $items = DB::select("
			// 	Select emp_id, emp_code, emp_name
			// 	From employee
			// 	Where (chief_emp_code = ? or emp_code = ?)
			// 	And emp_name like ?
			// " . $org . "				
			// 	and is_active = 1
			// 	Order by emp_name
			// ", array($emp->emp_code, $emp->emp_code,'%'.$request->emp_name.'%'));

			$items = DB::select("
				Select e.emp_id, e.emp_code, e.emp_name
				From employee e
				inner join org on org.org_id = e.org_id
				Where org.org_code in ({$in_emp})
				And e.emp_name like ?
			" . $org . "				
				and e.is_active = 1
				Order by e.emp_name asc
			", array('%' . $request->emp_name . '%'));
		}
		return response()->json($items);
	}

	public function item_desc_list(Request $request, $cds_result_id)
	{
		$current_date = $request->current_appraisal_year . str_pad($request->month_id,2,'0',STR_PAD_LEFT) . '01';

		if ($request->appraisal_type_id == "1"){

			$items = DB::select("
				SELECT air.item_result_id
				, ap.period_id
				, ap.appraisal_period_desc
				, ai.item_name
				, air.item_desc
				FROM kpi_cds_mapping km
				INNER JOIN cds ON cds.cds_id = km.cds_id
				INNER JOIN appraisal_item ai ON ai.item_id = km.item_id
				INNER JOIN appraisal_item_result air ON air.item_id = ai.item_id 
				INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
				INNER JOIN cds_result cr ON cr.org_id = air.org_id
					AND cr.level_id = air.level_id
					AND cr.cds_id = cds.cds_id
					AND date(".$current_date.") BETWEEN ap.start_date AND ap.end_date
				WHERE cr.cds_result_id = ".$cds_result_id." 
				AND air.item_desc IS NOT NULL
				AND air.item_desc != ''
				GROUP BY ai.item_id, air.item_desc
				ORDER BY ap.period_id, ai.item_id, air.item_desc ");


		}else if ($request->appraisal_type_id == "2"){

			$items = DB::select("
				SELECT air.item_result_id
				, ap.period_id
				, ap.appraisal_period_desc
				, ai.item_name
				, air.item_desc
				FROM kpi_cds_mapping km
				INNER JOIN cds ON cds.cds_id = km.cds_id
				INNER JOIN appraisal_item ai ON ai.item_id = km.item_id
				INNER JOIN appraisal_item_result air ON air.item_id = ai.item_id 
				INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
				INNER JOIN cds_result cr ON cr.org_id = air.org_id
					AND cr.level_id = air.level_id
					AND cr.cds_id = cds.cds_id
					AND cr.emp_id = air.emp_id
					AND cr.position_id = air.position_id
					AND date(".$current_date.") BETWEEN ap.start_date AND ap.end_date
				WHERE cr.cds_result_id = ".$cds_result_id." 
				AND air.item_desc IS NOT NULL
				AND air.item_desc != ''
				GROUP BY ai.item_id, air.item_desc
				ORDER BY ap.period_id, ai.item_id, air.item_desc ");

		}

		return response()->json($items);
	}

	public function detail_list($cds_result_id)
	{
		$items = DB::select("
			SELECT reason_cds_result_id
			, reason_cds_result_name
			, cds_result_id
			FROM reason_cds_result
			WHERE cds_result_id = ".$cds_result_id."
			ORDER BY reason_cds_result_id ASC
		");

		return response()->json($items);
	}

	public function detail_store(Request $request, $cds_result_id)
	{
	
		try{
			$item = new ReasonCdsResult;
			$item->reason_cds_result_name = $request->detail_name;
			$item->cds_result_id = $cds_result_id;
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}catch (Exception $e){
			return response()->json(['status' => 400, 'data' => $e->errorInfo]);
		}
		return response()->json(['status' => 200]);
	}

	public function detail_update(Request $request, $cds_result_id)
	{
		try{
			$item = ReasonCdsResult::find($request->reason_cds_result_id);
			$item->reason_cds_result_name = $request->detail_name;
			$item->updated_by = Auth::id();
			$item->save();
		}catch (Exception $e){
			return response()->json(['status' => 400, 'data' => $e->errorInfo]);
		}
		return response()->json(['status' => 200]);
	}

	public function detail_del($reason_cds_result_id)
	{
		try {
			$item = ReasonCdsResult::findOrFail($reason_cds_result_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 400, 'data' => 'Reason CDS Result not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this CDS Result is in use.']);
			} else {
				return response()->json(['status' => 400, 'data' => $e->errorInfo]);
			}
		}
		
		return response()->json(['status' => 200]);
	}

	public function reason_cds_result_list($cds_result_id, $reason_cds_result_id)
	{
		try {
			$item = ReasonCdsResult::findOrFail($reason_cds_result_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Reason not found.']);
		}
		return response()->json($item);
	}
	
	public function index(Request $request)
	{
		$emp = Employee::find(Auth::id());
		$level = AppraisalLevel::find($emp->level_id);
		$is_hr = $level->is_hr;
		//$org = Org::find($emp->org_id);

		//-------------------- [start] ตรวจสอบสิทธิ์ในการแก้ไข --------------------

		if ($is_hr == 1){
			$is_edit_cds_value = 1;
			$is_edit_forecast = 1;
			$is_edit_forecast_bu = 1;
		}else if ($emp->is_show_corporate == 1 && $emp->level_id == 3){
			$is_edit_cds_value = 0;
			$is_edit_forecast = 0;
			$is_edit_forecast_bu = 1;
		}else if ($emp->level_id == 2){
			$is_edit_cds_value = 1;
			$is_edit_forecast = 1;
			$is_edit_forecast_bu = 0;
		}else {
			$is_edit_cds_value = 0;
			$is_edit_forecast = 0;
			$is_edit_forecast_bu = 0;
		}
		
		//-------------------- [end] ตรวจสอบสิทธิ์ในการแก้ไข --------------------

		//$emp = Employee::find(Auth::id());
		$co = Org::find($emp->org_id);

		$re_emp = array();

		$emp_list = array();

		$emps = DB::select("
			select distinct org_code
			from org
			where parent_org_code = ?
			", array($co->org_code));

		foreach ($emps as $e) {
			$emp_list[] = $e->org_code;
			$re_emp[] = $e->org_code;
		}

		$emp_list = array_unique($emp_list);

		// Get array keys
		$arrayKeys = array_keys($emp_list);
		// Fetch last array key
		$lastArrayKey = array_pop($arrayKeys);
		//iterate array
		$in_emp = '';
		foreach ($emp_list as $k => $v) {
			if ($k == $lastArrayKey) {
				//during array iteration this condition states the last element.
				$in_emp .= "'" . $v . "'";
			} else {
				$in_emp .= "'" . $v . "'" . ',';
			}
		}

		do {
			empty($in_emp) ? $in_emp = "null" : null;

			$emp_list = array();

			$emp_items = DB::select("
				select distinct org_code
				from org
				where parent_org_code in ({$in_emp})
				and parent_org_code != org_code
				and is_active = 1			
				");

			foreach ($emp_items as $e) {
				$emp_list[] = $e->org_code;
				$re_emp[] = $e->org_code;
			}

			$emp_list = array_unique($emp_list);

			// Get array keys
			$arrayKeys = array_keys($emp_list);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach ($emp_list as $k => $v) {
				if ($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}
		} while (!empty($emp_list));

		$re_emp[] = $co->org_code;
		$re_emp = array_unique($re_emp);

		// Get array keys
		$arrayKeys = array_keys($re_emp);
		// Fetch last array key
		$lastArrayKey = array_pop($arrayKeys);
		//iterate array
		$in_emp = '';
		foreach ($re_emp as $k => $v) {
			if ($k == $lastArrayKey) {
				//during array iteration this condition states the last element.
				$in_emp .= "'" . $v . "'";
			} else {
				$in_emp .= "'" . $v . "'" . ',';
			}
		}

		empty($in_emp) ? $in_emp = "null" : null;


		$all_emp = DB::select("
			SELECT sum(b.is_all_employee) count_no
			from employee a
			left outer join appraisal_level b
			on a.level_id = b.level_id
			where emp_code = ?
		", array(Auth::id()));


		if ($all_emp[0]->count_no > 0) {
			$is_all_sql = "";
			$is_all_sql_org = "";
		} else {
			$is_all_sql = " and (e.emp_code = '{$emp->emp_code}' or e.chief_emp_code = '{$emp->emp_code}') ";
			//$is_all_sql_org = " and (org.org_code = '{$org->org_code}' or org.parent_org_code = '{$org->org_code}') ";
			$is_all_sql_org = " and org.org_code in ({$in_emp})";
		}

		if ($is_hr == 0) {
			$is_hr_sql = " and cds.is_hr = 0 ";
		} else {
			$is_hr_sql = "";
		}


		// $qinput = array();
		// $query = "
		// select r.cds_result_id, r.emp_id, e.emp_code, e.emp_name, r.org_id, o.org_code, o.org_name, l.appraisal_level_name, r.cds_id, s.cds_name, r.year, m.month_name, r.cds_value
		// From cds_result r
		// left outer join employee e
		// on r.emp_id = e.emp_id
		// left outer join cds s
		// on r.cds_id = s.cds_id
		// left outer join appraisal_level l
		// on r.level_id = l.level_id
		// left outer join period_month m
		// on r.appraisal_month_no = m.month_id
		// left outer join org o
		// on r.org_id = o.org_id
		// left outer join position p
		// on r.position_id = p.position_id
		// where 1 = 1
		// and l.is_hr = 0
		// ";

		// empty($request->current_appraisal_year) ?: ($query .= " AND r.year = ? " AND $qinput[] = $request->current_appraisal_year);
		// empty($request->appraisal_type_id) ?: ($query .= " AND r.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
		// empty($request->month_id) ?: ($query .= " And r.appraisal_month_no = ? " AND $qinput[] = $request->month_id);
		// empty($request->level_id) ?: ($query .= " And o.level_id = ? " AND $qinput[] = $request->level_id);
		// empty($request->level_id_emp) ?: ($query .= " And r.level_id = ? " AND $qinput[] = $request->level_id_emp);
		// empty($request->org_id) ?: ($query .= " And r.org_id = ? " AND $qinput[] = $request->org_id);
		// empty($request->position_id) ?: ($query .= " And p.position_id = ? " AND $qinput[] = $request->position_id);
		// empty($request->emp_id) ?: ($query .= " And r.emp_id = ? " AND $qinput[] = $request->emp_id);

		// $qfooter = " Order by l.appraisal_level_name, e.emp_name, r.cds_id ";

		// echo $query . $qfooter;
		// echo"<br>";
		// print_r($qinput);

		$qinput = array();

		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}


		$checkyear = DB::select("
			select 1
			from appraisal_period
			where appraisal_year = ?
			and date(?) between start_date and end_date		
		", array($config->current_appraisal_year, $request->current_appraisal_year . str_pad($request->month_id, 2, '0', STR_PAD_LEFT) . '01'));

		if (empty($checkyear)) {
			return 'Appraisal Period not found for the Current Appraisal Year.';
		}


		if ($request->appraisal_type_id == 2) {
			$query = "
				select distinct r.level_id, al.appraisal_level_name, r.org_id, org.org_name, r.emp_id, e.emp_code, e.emp_name, r.position_id, po.position_name, cds.cds_id, cds.cds_name, uom_name, cr.cds_result_id, ifnull(cr.cds_value,'') as cds_value
					, ifnull(cr.forecast,'') as forecast
					, ifnull(cr.forecast_bu,'') as forecast_bu
					, {$request->current_appraisal_year} year, {$request->month_id} month
					, ".$is_edit_forecast." as is_edit_forecast
					, ".$is_edit_forecast_bu." as is_edit_forecast_bu
					, ".$is_edit_cds_value." as is_edit_cds_value
					, SUM(COALESCE(dsc.item_desc, 0)) as is_item_desc
				from appraisal_item_result r
				left outer join employee e on r.emp_id = e.emp_id 
				inner join appraisal_item i on r.item_id = i.item_id
				inner join uom on uom.uom_id = i.uom_id
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
				and cr.emp_id = e.emp_id
				and r.org_id = cr.org_id
				and r.position_id = cr.position_id
			";
			empty($request->org_id) ?: ($query .= " And cr.org_id = " . $request->org_id);
			$query .= "
				and cr.year = {$request->current_appraisal_year}
				and cr.appraisal_month_no = {$request->month_id}
				and cr.appraisal_type_id = {$request->appraisal_type_id} ";
			empty($request->level_id) ?: ($query .= " And cr.level_id = ? " and $qinput[] = $request->level_id);
			$query .= "
				LEFT JOIN (
					SELECT (CASE WHEN ISNULL(air.item_desc) OR air.item_desc = '' THEN 0 ELSE 1 END) item_desc
					, air.item_result_id
					, km.cds_id
					FROM kpi_cds_mapping km
					INNER JOIN appraisal_item_result air ON km.item_id = air.item_id
				) dsc ON dsc.item_result_id = r.item_result_id
					AND dsc.cds_id = cr.cds_id
				where cds.is_sql = 0	
			" . $is_all_sql . $is_hr_sql;
			
			empty($request->level_id) ?: ($query .= " And r.level_id = ? " AND $qinput[] = $request->level_id);
			empty($request->emp_id) ?: ($query .= " And e.emp_id = ? " AND $qinput[] = $request->emp_id);

			$qgroup = "GROUP BY r.level_id, al.appraisal_level_name, r.org_id, org.org_name, r.emp_id, e.emp_code, e.emp_name, r.position_id, po.position_name, cds.cds_id, cds.cds_name, uom_name, cr.cds_result_id, ifnull(cr.cds_value,''), ifnull(cr.forecast,''), ifnull(cr.forecast_bu,'')";					
			
		} else {
			$query = "
				select distinct r.level_id, al.appraisal_level_name, r.org_id, org.org_code, org.org_name, r.position_id, po.position_name, cds.cds_id, cds.cds_name, uom.uom_name, cr.cds_result_id, ifnull(cr.cds_value,'') as cds_value
					, ifnull(cr.forecast,'') as forecast
					, ifnull(cr.forecast_bu,'') as forecast_bu
					, {$request->current_appraisal_year} year
					, {$request->month_id} month
					, ".$is_edit_forecast." as is_edit_forecast
					, ".$is_edit_forecast_bu." as is_edit_forecast_bu
					, ".$is_edit_cds_value." as is_edit_cds_value
					, SUM(COALESCE(dsc.item_desc, 0)) as is_item_desc
				from appraisal_item_result r
				inner join appraisal_item i on r.item_id = i.item_id
				inner join uom on uom.uom_id = i.uom_id
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
				and cr.org_id = org.org_id
				and r.org_id = cr.org_id

			";
			empty($request->org_id) ?: ($query .= " And cr.org_id = " . $request->org_id);

			$query .= "
				and cr.year = {$request->current_appraisal_year}
				and cr.appraisal_month_no = {$request->month_id}
				and cr.appraisal_type_id = {$request->appraisal_type_id}
				LEFT JOIN (
					SELECT (CASE WHEN ISNULL(air.item_desc) OR air.item_desc = '' THEN 0 ELSE 1 END) item_desc
					, air.item_result_id
					, km.cds_id
					FROM kpi_cds_mapping km
					INNER JOIN appraisal_item_result air ON km.item_id = air.item_id
				) dsc ON dsc.item_result_id = r.item_result_id
					AND dsc.cds_id = cr.cds_id
				where cds.is_sql = 0	
			" . $is_all_sql_org . $is_hr_sql;

			$qgroup = "GROUP BY r.level_id, al.appraisal_level_name, r.org_id, org.org_code, org.org_name, r.position_id, po.position_name, cds.cds_id, cds.cds_name, uom.uom_name, cr.cds_result_id, ifnull(cr.cds_value,''), ifnull(cr.forecast,''), ifnull(cr.forecast_bu,'')";
		
		}

		if (!empty($request->current_appraisal_year) && !empty($request->month_id)) {
			$current_date = $request->current_appraisal_year . str_pad($request->month_id, 2, '0', STR_PAD_LEFT) . '01';
			$query .= " and date(?) between ap.start_date and ap.end_date ";
			$qinput[] = $current_date;
		}

		empty($request->level_id) ?: ($query .= " And org.level_id = ? " and $qinput[] = $request->level_id);
		empty($request->org_id) ?: ($query .= " And r.org_id = ? " and $qinput[] = $request->org_id);
		empty($request->position_id) ?: ($query .= " And r.position_id = ? " and $qinput[] = $request->position_id);
		empty($request->appraisal_type_id) ?: ($query .= " And er.appraisal_type_id = ? " and $qinput[] = $request->appraisal_type_id);

		$qfooter = " Order by r.emp_id, cds.cds_id ";
			
		
		$items = DB::select($query .$qgroup . $qfooter, $qinput);
		
		
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


	// Performance Tuning
	// public function index_v2_old(Request $request)
	// {
	// 	$employee = Employee::find(Auth::id());
	// 	$levelOfEmp = AppraisalLevel::find($employee->level_id); // $is_hr = $level->is_hr;
	// 	$orgOfEmp = Org::find($employee->org_id);
	// 	$childOrgOfEmp = Org::where('parent_org_code', $orgOfEmp->org_code)->get();
		
	// 	$reEmp = array_unique($childOrgOfEmp->pluck('org_code')->toArray());
	// 	$orgList = array_unique($childOrgOfEmp->pluck('org_code')->toArray());

	// 	// convert array to comma string with single quotes.
	// 	$orgListStr = "'" . implode ( "', '", $orgList ) . "'";

	// 	do {				
	// 		empty($orgListStr) ? $orgListStr = "null" : null;
	// 		$emp_list = array();

	// 		$emp_items = DB::select("
	// 			select distinct org_code
	// 			from org
	// 			where parent_org_code in ({$in_emp})
	// 			and parent_org_code != org_code
	// 			and is_active = 1			
	// 			");

	// 		foreach ($emp_items as $e) {
	// 			$emp_list[] = $e->org_code;
	// 			$re_emp[] = $e->org_code;
	// 		}			

	// 		$emp_list = array_unique($emp_list);

	// 				// Get array keys
	// 		$arrayKeys = array_keys($emp_list);
	// 				// Fetch last array key
	// 		$lastArrayKey = array_pop($arrayKeys);
	// 				//iterate array
	// 		$in_emp = '';
	// 		foreach($emp_list as $k => $v) {
	// 			if($k == $lastArrayKey) {
	// 						//during array iteration this condition states the last element.
	// 				$in_emp .= "'" . $v . "'";
	// 			} else {
	// 				$in_emp .= "'" . $v . "'" . ',';
	// 			}
	// 		}		
	// 	} while (!empty($emp_list));		

	// 	while ($a <= 10) {
	// 		# code...
	// 	}

		// $re_emp[] = $co->org_code;
		// $re_emp = array_unique($re_emp);

		// 		// Get array keys
		// $arrayKeys = array_keys($re_emp);
		// 		// Fetch last array key
		// $lastArrayKey = array_pop($arrayKeys);
		// 		//iterate array
		// $in_emp = '';
		// foreach($re_emp as $k => $v) {
		// 	if($k == $lastArrayKey) {
		// 				//during array iteration this condition states the last element.
		// 		$in_emp .= "'" . $v . "'";
		// 	} else {
		// 		$in_emp .= "'" . $v . "'" . ',';
		// 	}
		// }				

		// empty($in_emp) ? $in_emp = "null" : null;
		
		
		// $all_emp = DB::select("
		// 	SELECT sum(b.is_all_employee) count_no
		// 	from employee a
		// 	left outer join appraisal_level b
		// 	on a.level_id = b.level_id
		// 	where emp_code = ?
		// ", array(Auth::id()));

		
		// if ($all_emp[0]->count_no > 0) {
		// 	$is_all_sql = "";
		// 	$is_all_sql_org = "";
		// } else {
		// 	$is_all_sql = " and (e.emp_code = '{$emp->emp_code}' or e.chief_emp_code = '{$emp->emp_code}') ";
		// 	//$is_all_sql_org = " and (org.org_code = '{$org->org_code}' or org.parent_org_code = '{$org->org_code}') ";
		// 	$is_all_sql_org = " and org.org_code in ({$in_emp})";
		// }
		
		// if ($is_hr == 0) {
		// 	$is_hr_sql = " and cds.is_hr = 0 ";
		// } else {
		// 	$is_hr_sql = "";
		// }
		
		
		// $qinput = array();

		// try {
		// 	$config = SystemConfiguration::firstOrFail();
		// } catch (ModelNotFoundException $e) {
		// 	return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		// }		
		
		
		// $checkyear = DB::select("
		// 	select 1
		// 	from appraisal_period
		// 	where appraisal_year = ?
		// 	and date(?) between start_date and end_date		
		// ", array($config->current_appraisal_year, $request->current_appraisal_year . str_pad($request->month_id,2,'0',STR_PAD_LEFT) . '01'));
		
		// if (empty($checkyear)) {
		// 	return 'Appraisal Period not found for the Current Appraisal Year.';
		// }
		
		
		// if ($request->appraisal_type_id == 2) {
		// 	$query = "
		// 		select distinct r.level_id, al.appraisal_level_name, r.org_id, org.org_name, r.emp_id, e.emp_code, e.emp_name, r.position_id, po.position_name, cds.cds_id, cds.cds_name, uom_name, cr.cds_result_id, ifnull(cr.cds_value,'') as cds_value, {$request->current_appraisal_year} year, {$request->month_id} month
		// 		from appraisal_item_result r
		// 		left outer join employee e on r.emp_id = e.emp_id 
		// 		inner join appraisal_item i on r.item_id = i.item_id
		// 		inner join uom on uom.uom_id = i.uom_id
		// 		left outer join appraisal_item_position p on i.item_id = p.item_id
		// 		inner join kpi_cds_mapping m on i.item_id = m.item_id
		// 		inner join cds on m.cds_id = cds.cds_id
		// 		inner join appraisal_period ap on r.period_id = ap.period_id
		// 		inner join system_config sys on ap.appraisal_year = sys.current_appraisal_year
		// 		inner join emp_result er on r.emp_result_id = er.emp_result_id	
		// 		left outer join position po on r.position_id = po.position_id
		// 		left outer join org on r.org_id = org.org_id
		// 		left outer join appraisal_level al on r.level_id = al.level_id
		// 		left outer join cds_result cr on cds.cds_id = cr.cds_id
		// 		and cr.emp_id = e.emp_id
		// 		and r.org_id = cr.org_id
		// 		and r.position_id = cr.position_id
		// 	";
		// 	empty($request->org_id) ?: ($query .= " And cr.org_id = " . $request->org_id);
		// 	$query .= "
		// 		and cr.year = {$request->current_appraisal_year}
		// 		and cr.appraisal_month_no = {$request->month_id}
		// 		and cr.appraisal_type_id = {$request->appraisal_type_id} ";
		// 	empty($request->level_id) ?: ($query .= " And cr.level_id = ? " AND $qinput[] = $request->level_id);
		// 	$query .= "
		// 		where cds.is_sql = 0	
		// 	" . $is_all_sql . $is_hr_sql;
			
		// 	empty($request->level_id) ?: ($query .= " And r.level_id = ? " AND $qinput[] = $request->level_id);
		// 	empty($request->emp_id) ?: ($query .= " And e.emp_id = ? " AND $qinput[] = $request->emp_id);					
			
		// } else {
		// 	$query = "
		// 		select distinct r.level_id, al.appraisal_level_name, r.org_id, org.org_code, org.org_name, r.position_id, po.position_name, cds.cds_id, cds.cds_name, uom.uom_name, cr.cds_result_id, ifnull(cr.cds_value,'') as cds_value, {$request->current_appraisal_year} year, {$request->month_id} month
		// 		from appraisal_item_result r
		// 		inner join appraisal_item i on r.item_id = i.item_id
		// 		inner join uom on uom.uom_id = i.uom_id
		// 		left outer join appraisal_item_position p on i.item_id = p.item_id
		// 		inner join kpi_cds_mapping m on i.item_id = m.item_id
		// 		inner join cds on m.cds_id = cds.cds_id
		// 		inner join appraisal_period ap on r.period_id = ap.period_id
		// 		inner join system_config sys on ap.appraisal_year = sys.current_appraisal_year
		// 		inner join emp_result er on r.emp_result_id = er.emp_result_id	
		// 		left outer join position po on r.position_id = po.position_id
		// 		left outer join org on r.org_id = org.org_id
		// 		left outer join appraisal_level al on r.level_id = al.level_id
		// 		left outer join cds_result cr on cds.cds_id = cr.cds_id
		// 		and cr.org_id = org.org_id
		// 		and r.org_id = cr.org_id
		// 	";
		// 	empty($request->org_id) ?: ($query .= " And cr.org_id = " . $request->org_id);
		// 	$query .= "
		// 		and cr.year = {$request->current_appraisal_year}
		// 		and cr.appraisal_month_no = {$request->month_id}
		// 		and cr.appraisal_type_id = {$request->appraisal_type_id}
		// 		where cds.is_sql = 0	
		// 	" . $is_all_sql_org . $is_hr_sql;
		
		// }
		
		// if (!empty($request->current_appraisal_year) && !empty($request->month_id)) {
		// 	$current_date = $request->current_appraisal_year . str_pad($request->month_id,2,'0',STR_PAD_LEFT) . '01';
		// 	$query .= " and date(?) between ap.start_date and ap.end_date ";
		// 	$qinput[] = $current_date;
		// }
		
		// empty($request->level_id) ?: ($query .= " And org.level_id = ? " AND $qinput[] = $request->level_id);
		// empty($request->org_id) ?: ($query .= " And r.org_id = ? " AND $qinput[] = $request->org_id);
		// empty($request->position_id) ?: ($query .= " And r.position_id = ? " AND $qinput[] = $request->position_id);
		// empty($request->appraisal_type_id) ?: ($query .= " And er.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
		
		// $qfooter = " Order by r.emp_id, cds.cds_id ";
			
		
		// $items = DB::select($query . $qfooter, $qinput);
		
		
		// // Get the current page from the url if it's not set default to 1
		// empty($request->page) ? $page = 1 : $page = $request->page;
		
		// // Number of items per page
		// empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;
		
		// $offSet = ($page * $perPage) - $perPage; // Start displaying items from this number

		// // Get only the items you need using array_slice (only get 10 items since that's what you need)
		// $itemsForCurrentPage = array_slice($items, $offSet, $perPage, false);
		
		// // Return the paginator with only 10 items but with the count of all items and set the it on the correct page
		// $result = new LengthAwarePaginator($itemsForCurrentPage, count($items), $perPage, $page);


		// return response()->json($result);
	// }
	
	
	public function update(Request $request)
	{
		$errors = array();
		foreach ($request->cds_results as $i) {

			if ($i['appraisal_type_id'] == 2) {
				$validator = Validator::make($i, [
					'emp_id' => 'required|max:50',
					'appraisal_type_id' => 'integer',
					'org_id' => 'required|integer',
					'position_id' => 'required|integer',
					'cds_id' => 'required|integer',
					'year' => 'required|integer',
					'month' => 'required|integer',
					'level_id' => 'required|integer',
				]);

				if ($validator->fails()) {
					$errors[] = ['emp_id' => $i['emp_id'], 'errors' => $validator->errors()];
				} else {
					$month_name = PeriodMonth::find($i['month']);
					// $a_date = $i['year'] . "-" . $i['month'] . "-01";
					$a_date = $i['year'] . "-" . $i['month'] . "-" . cal_days_in_month(CAL_GREGORIAN, $i['month'], $i['year']);

					if (empty($month_name)) {
						$errors[] = ['emp_id' => $i['emp_id'], 'errors' => 'Invalid Month.'];
					} else {
						try {
							//	$result_check = CDSResult::where("emp_id",$i->emp_id)->where("cds_id",$i->cds_id)->where('year',$i->year)->where('appraisal_month_no',$i->month);
							if ($i['cds_value'] != '') {
								if (empty($i['cds_result_id'])) {

									//echo date("Y-m-t", strtotime($a_date));
									$cds_result = new CDSResult;
									$cds_result->appraisal_type_id = $i['appraisal_type_id'];
									$cds_result->emp_id = $i['emp_id'];
									$cds_result->cds_id = $i['cds_id'];
									$cds_result->year = $i['year'];
									$cds_result->org_id = $i['org_id'];
									$cds_result->position_id = $i['position_id'];
									$cds_result->level_id = $i['level_id'];
									$cds_result->appraisal_month_no = $i['month'];
									$cds_result->appraisal_month_name = $month_name->month_name;
									$cds_result->cds_value = $i['cds_value'];
									$cds_result->etl_dttm = date("Y-m-t", strtotime($a_date));
									$cds_result->created_by = Auth::id();
									$cds_result->updated_by = Auth::id();
									$cds_result->save();
								} else {
									// CDSResult::where("emp_id",$i->emp_id)->where("cds_id",$i->cds_id)->where('year',$i->year)->where('appraisal_month_no',$i->month)->update(['cds_value' => $i->cds_value,'etl_dttm'=>date("Y-m-t", strtotime($a_date)),
									// 'updated_by' => Auth::id()]);		
									$cds_result = CDSResult::find($i['cds_result_id']);
									$cds_result->cds_value = $i['cds_value'];
									$cds_result->etl_dttm = date("Y-m-t", strtotime($a_date));
									$cds_result->updated_by = Auth::id();
									$cds_result->save();
								}
							}
						} catch (Exception $e) {
							$errors[] = ['emp_id' => $i['emp_id'], 'errors' => substr($e, 0, 254)];
						}
					}
				}
			} else {

				$validator = Validator::make($i, [
					'org_id' => 'required|integer',
					'appraisal_type_id' => 'required|integer',
					'cds_id' => 'required|integer',
					'year' => 'required|integer',
					'month' => 'required|integer',
					'level_id' => 'required|integer',
				]);

				if ($validator->fails()) {
					$errors[] = ['org_id' => $i['org_id'], 'errors' => $validator->errors()];
				} else {
					$month_name = PeriodMonth::find($i['month']);
					$a_date = $i['year'] . "-" . $i['month'] . "-01";
					if (empty($month_name)) {
						$errors[] = ['org_id' => $i['org_id'], 'errors' => 'Invalid Month.'];
					} else {
						try {
							//$result_check = CDSResult::where("org_id",$i->org_id)->where("cds_id",$i->cds_id)->where('year',$i->year)->where('appraisal_month_no',$i->month);

							if ($i['cds_value'] != '') {
								if (empty($i['cds_result_id'])) {

									$cds_result = new CDSResult;
									$cds_result->appraisal_type_id = $i['appraisal_type_id'];
									$cds_result->org_id = $i['org_id'];
									$cds_result->cds_id = $i['cds_id'];
									$cds_result->year = $i['year'];
									$cds_result->level_id = $i['level_id'];
									$cds_result->appraisal_month_no = $i['month'];
									$cds_result->appraisal_month_name = $month_name->month_name;
									$cds_result->cds_value = $i['cds_value'];
									$cds_result->etl_dttm = date("Y-m-t", strtotime($a_date));
									$cds_result->created_by = Auth::id();
									$cds_result->updated_by = Auth::id();
									$cds_result->save();
								} else {
									// CDSResult::where("org_id",$i->org_id)->where("cds_id",$i->cds_id)->where('year',$i->year)->where('appraisal_month_no',$i->month)->update(['cds_value' => $i->cds_value,'etl_dttm'=>date("Y-m-t", strtotime($a_date)), 'updated_by' => Auth::id()]);		
									$cds_result = CDSResult::find($i['cds_result_id']);
									$cds_result->cds_value = $i['cds_value'];
									$cds_result->etl_dttm = date("Y-m-t", strtotime($a_date));
									$cds_result->updated_by = Auth::id();
									$cds_result->save();
								}
							}
						} catch (Exception $e) {
							$errors[] = ['org_id' => $i['org_id'], 'errors' => substr($e, 0, 254)];
						}
					}
				}
			}
		}
		return response()->json(['status' => 200, 'errors' => $errors]);
	}
	

	public function update_cdsResult(Request $request)
	{
		foreach ($request->cdsResult as $cds) {

			$item = CDSResult::find($cds['cds_result_id']);

			$cds['cds_value'] = (($cds['cds_value'] == "") ? null : $cds['cds_value']);
			$cds['forecast'] = (($cds['forecast'] == "") ? null : $cds['forecast']);
			$cds['forecast_bu'] = (($cds['forecast_bu'] == "") ? null : $cds['forecast_bu']);

			try {
				if (empty($item)){

					$cds_result = new CDSResult;
					$cds_result->year = $cds['year'];
					$cds_result->appraisal_type_id = $cds['appraisal_type_id'];
					$cds_result->cds_id = $cds['cds_id'];
					$cds_result->position_id = $cds['position_id'];
					$cds_result->level_id = $cds['level_id'];
					$cds_result->appraisal_month_no = $cds['appraisal_month_no'];
					$cds_result->appraisal_month_name = $cds['appraisal_month_name'];
					$cds_result->cds_value = $cds['cds_value'];
					$cds_result->forecast  = $cds['forecast'];
					// $cds_result->forecast = $cds['forecast'];
					$cds_result->forecast_bu = $cds['forecast_bu'];
					// $cds_result->forecast_bu = $cds['forecast_bu'];
					if ($cds['appraisal_type_id'] == "1"){
						$cds_result->org_id = $cds['org_id'];
					}else if ($cds['appraisal_type_id'] == "2"){
						$cds_result->emp_id = $cds['emp_id'];
					}
					$cds_result->created_by = Auth::id();
					$cds_result->updated_by = Auth::id();
					$cds_result->save();

				} else {

						$item->forecast = $cds['forecast'];
						// $item->forecast = $cds['forecast'];
						$item->forecast_bu = $cds['forecast_bu'];
						// $item->forecast_bu = $cds['forecast_bu'];
						$item->cds_value = $cds['cds_value'];
						$item->updated_by = Auth::id();
						$item->save();

				}
			} catch (Exception $e) {
				return response()->json(['status' => 400, 'data' => substr($e,0,254)]);
			}
		}
		
		return response()->json(['status' => 200]);
		
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

	public function cds_result_upload_files(Request $request, $cds_result_id)
	{



		$result = array();

		$path = $_SERVER['DOCUMENT_ROOT'] . '/ghb_api/public/cds_result_files/' . $cds_result_id . '/';
		foreach ($request->file() as $f) {
			$filename = iconv('UTF-8', 'windows-874', $f->getClientOriginalName());
			$f->move($path, $filename);
			//$f->move($path,$f->getClientOriginalName());
			//echo $filename;

			$item = CDSFile::firstOrNew(array('doc_path' => 'cds_result_files/' . $cds_result_id . '/' . $f->getClientOriginalName()));

			$item->cds_result_id = $cds_result_id;
			$item->created_by = Auth::id();

			//print_r($item);
			$item->save();
			$result[] = $item;
			//echo "hello".$f->getClientOriginalName();

		}

		return response()->json(['status' => 200, 'data' => $result]);
	}

	public function cds_result_files_list(Request $request)
	{
		$items = DB::select("
			SELECT cds_result_doc_id,doc_path
			FROM cds_result_doc
			where  cds_result_id=?
			order by cds_result_doc_id;
		", array($request->cds_result_id));

		return response()->json($items);
	}


	public function delete_file(Request $request)
	{

		try {
			$item = CDSFile::findOrFail($request->cds_result_doc_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'File not found.']);
		}
		//$_SERVER['DOCUMENT_ROOT'] . '/see_api/public/attach_files/' . $item_result_id . '/';
		$filename = iconv('UTF-8', 'windows-874', $item->doc_path);
		File::Delete($_SERVER['DOCUMENT_ROOT'] . '/ghb_api/public/' . $filename);
		$item->delete();

		return response()->json(['status' => 200]);
	}

	public function index_v2 (Request $request) {
		$emp = Employee::find(Auth::id());
		$level = AppraisalLevel::find($emp->level_id);
		$is_hr = $level->is_hr;
		$co = Org::find($emp->org_id);

		$re_emp = array();

		$emp_list = array();

		$emps = DB::select("
			select distinct org_code
			from org
			where parent_org_code = ?
			", array($co->org_code));

		foreach ($emps as $e) {
			$emp_list[] = $e->org_code;
			$re_emp[] = $e->org_code;
		}

		$emp_list = array_unique($emp_list);

		// Get array keys
		$arrayKeys = array_keys($emp_list);
		// Fetch last array key
		$lastArrayKey = array_pop($arrayKeys);
		//iterate array
		$in_emp = '';
		foreach ($emp_list as $k => $v) {
			if ($k == $lastArrayKey) {
				//during array iteration this condition states the last element.
				$in_emp .= "'" . $v . "'";
			} else {
				$in_emp .= "'" . $v . "'" . ',';
			}
		}

		do {
			empty($in_emp) ? $in_emp = "null" : null;

			$emp_list = array();

			$emp_items = DB::select("
				select distinct org_code
				from org
				where parent_org_code in ({$in_emp})
				and parent_org_code != org_code
				and is_active = 1			
				");

			foreach ($emp_items as $e) {
				$emp_list[] = $e->org_code;
				$re_emp[] = $e->org_code;
			}

			$emp_list = array_unique($emp_list);

			// Get array keys
			$arrayKeys = array_keys($emp_list);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach ($emp_list as $k => $v) {
				if ($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}
		} while (!empty($emp_list));

		$re_emp[] = $co->org_code;

		//# สิทธิ์ โดยเมื่อ User Login เข้ามาแล้วส่วนของหน่วยงานที่แสดงใน Parameter ให้เช็ค org_id ที่ table emp_multi_org_mapping เพิ่ม
		$muti_org =  DB::select("
		select o.org_code
		from emp_org e
		inner join org o on e.org_id = o.org_id
		where emp_id = ?
		", array($emp->emp_id));
	
		foreach ($muti_org as $e) {
			$re_emp[] = $e->org_code;
		}

		$re_emp = array_unique($re_emp);

		// Get array keys
		$arrayKeys = array_keys($re_emp);
		// Fetch last array key
		$lastArrayKey = array_pop($arrayKeys);
		//iterate array
		$in_emp = '';
		foreach ($re_emp as $k => $v) {
			if ($k == $lastArrayKey) {
				//during array iteration this condition states the last element.
				$in_emp .= "'" . $v . "'";
			} else {
				$in_emp .= "'" . $v . "'" . ',';
			}
		}

		empty($in_emp) ? $in_emp = "null" : null;


		$all_emp = DB::select("
			SELECT sum(b.is_all_employee) count_no
			from employee a
			left outer join appraisal_level b
			on a.level_id = b.level_id
			where emp_code = ?
		", array(Auth::id()));

		$organization = Org::findOrFail(1);

		if ($all_emp[0]->count_no > 0) {
			$is_all_sql = "";
			$is_all_sql_org = "";
		} else if ($all_emp[0]->count_no == 0 && $emp->is_show_corporate == 1) {
			$is_all_sql = " and (e.emp_code = '{$emp->emp_code}' or e.chief_emp_code = '{$emp->emp_code}') ";
			$is_all_sql_org = " and ( o.org_code in ({$in_emp}, {$organization->org_code}) OR (eo.emp_id = {$emp->emp_id})) ";
		} else {
			$is_all_sql = " and (e.emp_code = '{$emp->emp_code}' or e.chief_emp_code = '{$emp->emp_code}') ";
			$is_all_sql_org = " and ( o.org_code in ({$in_emp}) OR (eo.emp_id = {$emp->emp_id})) ";
		}

		if ($is_hr == 0) {
			$is_hr_sql = " and c.is_hr = 0 ";
		} else {
			$is_hr_sql = "";
		}

		$qinput = array();

		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}

		$checkyear = DB::select("
		SELECT DISTINCT
			1 
		FROM
			appraisal_period 
		WHERE
			appraisal_year = ?
		", array($config->current_appraisal_year));

		if (empty($checkyear)) {
			return 'Appraisal Period not found for the Current Appraisal Year.';
		}

		$query = "
		SELECT
			c.cds_id,
			c.cds_name,
			{$request->appraisal_type_id} as appraisal_type_id,
			res.org_id,
			res.org_code,
			res.org_name,
			res.emp_id,
			res.emp_name,
			res.position_id,
			res.position_name,
			u.uom_name,
			res.level_id,
			res.appraisal_level_name,
			res.year 
		FROM cds c
		INNER JOIN kpi_cds_mapping kcm ON kcm.cds_id = c.cds_id
		INNER JOIN appraisal_item ai ON ai.item_id = kcm.item_id
		INNER JOIN uom u ON u.uom_id = ai.uom_id
		INNER JOIN (
			SELECT DISTINCT
				air.item_id,
				kc.cds_id,
				o.org_id,
				o.org_code,
				o.org_name,
				e.emp_id,
				e.emp_name,
				p.position_id,
				p.position_name,
				al.level_id,
				al.appraisal_level_name,
				ap.appraisal_year AS `year`,
				cr.appraisal_type_id
			FROM appraisal_item_result air
			INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
			INNER JOIN kpi_cds_mapping kc ON kc.item_id = air.item_id
			LEFT OUTER JOIN cds_result cr ON cr.cds_id = kc.cds_id
				AND cr.year = ap.appraisal_year
				AND cr.emp_id = air.emp_id
				AND cr.org_id = air.org_id
				AND cr.level_id = air.level_id
			LEFT OUTER JOIN org o ON o.org_id = air.org_id
			LEFT OUTER JOIN employee e ON e.emp_id = air.emp_id
			LEFT OUTER JOIN position p ON p.position_id = air.position_id
			LEFT OUTER JOIN appraisal_level al ON al.level_id = air.level_id
			LEFT OUTER JOIN emp_org eo ON eo.org_id = cr.org_id 
		";

		$query .= "
		WHERE ap.appraisal_year = {$request->current_appraisal_year}
			AND (cr.appraisal_type_id = {$request->appraisal_type_id} OR cr.appraisal_type_id IS NULL)
		";

		empty($request->level_id) ?: ($query .= " And air.level_id = ? " and $qinput[] = $request->level_id);
		empty($request->org_id) ?: ($query .= " And air.org_id = ? " and $qinput[] = $request->org_id);
		empty($request->emp_id) ?: ($query .= " And air.emp_id = ? " and $qinput[] = $request->emp_id);
		empty($request->position_id) ?: ($query .= " And air.position_id = ? " and $qinput[] = $request->position_id);
		if ($request->appraisal_type_id == 2) {
			$query .= $is_all_sql;
		} else {
			$query .= $is_all_sql_org;
		}

		$query .= ") res ON res.item_id = kcm.item_id";
		
		// pagination param settings
		empty($request->page) ? $page = 1 : $page = $request->page;
		empty($request->size) ? $perPage = 10 : $perPage = $request->size;

		$offset = $perPage * ($page - 1);

		$qfooter = "
			WHERE c.is_sql = 0 {$is_hr_sql}
			ORDER BY 
				res.org_name,
				c.cds_name
			LIMIT {$perPage} 
			OFFSET {$offset}
		";

		$cdsItems = DB::select($query . $qfooter, $qinput);

		$countQuery = "
		SELECT DISTINCT
			COUNT(c.cds_id) as cdsCount
		FROM cds c
		INNER JOIN kpi_cds_mapping kcm ON kcm.cds_id = c.cds_id
		INNER JOIN appraisal_item ai ON ai.item_id = kcm.item_id
		INNER JOIN uom u ON u.uom_id = ai.uom_id
		INNER JOIN (
			SELECT DISTINCT
				air.item_id,
				kc.cds_id,
				o.org_id,
				o.org_code,
				o.org_name,
				e.emp_id,
				e.emp_name,
				p.position_id,
				p.position_name,
				al.level_id,
				al.appraisal_level_name,
				ap.appraisal_year AS `year`,
				cr.appraisal_type_id
			FROM appraisal_item_result air
			INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
			INNER JOIN kpi_cds_mapping kc ON kc.item_id = air.item_id
			LEFT OUTER JOIN cds_result cr ON cr.cds_id = kc.cds_id
				AND cr.year = ap.appraisal_year
				AND cr.emp_id = air.emp_id
				AND cr.org_id = air.org_id
				AND cr.level_id = air.level_id
			LEFT OUTER JOIN org o ON o.org_id = air.org_id
			LEFT OUTER JOIN employee e ON e.emp_id = air.emp_id
			LEFT OUTER JOIN position p ON p.position_id = air.position_id
			LEFT OUTER JOIN appraisal_level al ON al.level_id = air.level_id
			LEFT OUTER JOIN emp_org eo ON eo.org_id = cr.org_id 
		";
		$countQuery .= "
		WHERE ap.appraisal_year = {$request->current_appraisal_year}
			AND (cr.appraisal_type_id = {$request->appraisal_type_id} OR cr.appraisal_type_id IS NULL)
		";
		empty($request->level_id) ?: ($countQuery .= " And air.level_id = ? " and $qcinput[] = $request->level_id);
		empty($request->org_id) ?: ($countQuery .= " And air.org_id = ? " and $qcinput[] = $request->org_id);
		empty($request->emp_id) ?: ($countQuery .= " And air.emp_id = ? " and $qcinput[] = $request->emp_id);
		empty($request->position_id) ?: ($countQuery .= " And air.position_id = ? " and $qcinput[] = $request->position_id);
		if ($request->appraisal_type_id == 2) {
			$countQuery .= $is_all_sql;
		} else {
			$countQuery .= $is_all_sql_org;
		}
		$countQuery .= ") res ON res.item_id = kcm.item_id WHERE 1=1 {$is_hr_sql}";

		$cdsItemsCount = DB::select($countQuery, $qinput);

		$uniqueCdsIds = [];
		foreach ($cdsItems as $cdsItem) {
			$uniqueCdsIds[] = $cdsItem->cds_id;
		}
		$uniqueCdsIds = array_unique($uniqueCdsIds);
		
		$cdsIdsString = "''";
		if (!empty($uniqueCdsIds)) {
			$cdsIdsString = $cdsIdsString . ", " . implode(", ", $uniqueCdsIds);
		}

		$cdsValuesFilterInput = [];
		$cdsValuesBaseQuery = "
		SELECT DISTINCT
			cr.cds_result_id,
			cr.year,
			cr.appraisal_month_no,
			cr.org_id,
			cr.level_id,
			cr.cds_id,
			cr.cds_value,
			cr.forecast corporate_forecast_value,
			cr.forecast_bu bu_forecast_value,
			cr.appraisal_type_id,
			cr.position_id,
			cr.level_id,
			cr.appraisal_month_name,
			cr.emp_id
		FROM
			cds_result cr
			INNER JOIN kpi_cds_mapping kcm ON kcm.cds_id = cr.cds_id
			LEFT OUTER JOIN appraisal_item_result air ON air.item_id = kcm.item_id
			AND cr.emp_id = air.emp_id 
			AND cr.org_id = air.org_id 
			AND cr.level_id = air.level_id 
			AND cr.position_id = air.position_id  
		";
		$cdsValuesBaseQuery .= "
		WHERE cr.year = {$request->current_appraisal_year}
			AND cr.appraisal_type_id = {$request->appraisal_type_id}
			AND cr.cds_id IN ( {$cdsIdsString} )
		";
		empty($request->level_id) ?: ($cdsValuesBaseQuery .= " And cr.level_id = ? " and $cdsValuesFilterInput[] = $request->level_id);
		empty($request->org_id) ?: ($cdsValuesBaseQuery .= " And cr.org_id = ? " and $cdsValuesFilterInput[] = $request->org_id);
		empty($request->emp_id) ?: ($cdsValuesBaseQuery .= " And e.emp_id = ? " and $cdsValuesFilterInput[] = $request->emp_id);
		empty($request->position_id) ?: ($cdsValuesBaseQuery .= " And cr.position_id = ? " and $cdsValuesFilterInput[] = $request->position_id);

		$cdsValuesQueryFooter = "
		ORDER BY
			cr.year,
			cr.org_id,
			cr.level_id,
			cr.cds_id,
			cr.appraisal_month_no
		";

		$cdsValueItems = DB::select($cdsValuesBaseQuery . $cdsValuesQueryFooter, $cdsValuesFilterInput);

		foreach ($cdsItems as $item) {
			$mappedCdsValues = [];
			foreach ($cdsValueItems as $cdsValue) {
				if ($cdsValue->cds_id == $item->cds_id && $cdsValue->org_id == $item->org_id && $cdsValue->level_id == $item->level_id) {
					$mappedCdsValues[] = $cdsValue;
				}
			}

			$item->months = $mappedCdsValues;
		}

		$total = ceil($cdsItemsCount[0]->cdsCount / $perPage);
		$result = ['current_page' => $page, 'per_page' => $perPage, 'total' => $total, 'data' => $cdsItems];

		return response()->json($result);
	}

	public function update_cdsResult_v2(Request $request)
	{
		foreach ($request->cdsResult as $cds) {
			
			
			
			$item = null;
			if (!empty($cds['cds_result_id'])) {
				$item = CDSResult::find($cds['cds_result_id']);
			} 

			if (empty($cds['cds_value'])) {
				$cds['cds_value'] = null;
			}
			if (empty($cds['corporate_forecast_value'])) {
				$cds['corporate_forecast_value'] = null;
			}
			if (empty($cds['bu_forecast_value'])) {
				$cds['bu_forecast_value'] = null;
			}

			try {
				if (empty($item)){
					$cds_result = new CDSResult();
					$cds_result->year = $cds['year'];
					$cds_result->appraisal_type_id = $cds['appraisal_type_id'];
					$cds_result->cds_id = $cds['cds_id'];
					$cds_result->position_id = $cds['position_id'];
					$cds_result->level_id = $cds['level_id'];
					$cds_result->appraisal_month_no = $cds['appraisal_month_no'];
					$cds_result->appraisal_month_name = $cds['appraisal_month_name'];
					$cds_result->cds_value = $cds['cds_value'];
					// Rock : swap corporate_forecast_value  <---> forcast
					// $cds_result->forecast = $cds['forecast_bu'];
					// $cds_result->forecast_bu = $cds['bu_forecast_value'];
					$cds_result->forecast = $cds['corporate_forecast_value'];
					$cds_result->forecast_bu = $cds['bu_forecast_value'];
					if ($cds['appraisal_type_id'] == "1"){
						$cds_result->org_id = $cds['org_id'];
					}else if ($cds['appraisal_type_id'] == "2"){
						$cds_result->emp_id = $cds['emp_id'];
					}
					// Rock : fixed etl_dttm
					$a_date = $cds_result->year . "-" . $cds_result->appraisal_month_no . "-" . cal_days_in_month(CAL_GREGORIAN, $cds_result->appraisal_month_no, $cds_result->year);
					$cds_result->etl_dttm = date("Y-m-t", strtotime($a_date));
					$cds_result->created_by = Auth::id();
					$cds_result->updated_by = Auth::id();
					$cds_result->save();
				} else {
					// Rock : swap corporate_forecast_value  <---> forcast
					// $item->corporate_forecast_value = $cds['forecast_bu'];
					// $item->bu_forecast_value = $cds['bu_forecast_value'];
					$item->forecast = $cds['corporate_forecast_value'];
					$item->forecast_bu = $cds['bu_forecast_value'];
					$item->cds_value = $cds['cds_value'];
					// Rock : fixed etl_dttm
					$a_date = $item->year . "-" . $item->appraisal_month_no . "-" . cal_days_in_month(CAL_GREGORIAN, $item->appraisal_month_no, $item->year);
					$item->etl_dttm = date("Y-m-t", strtotime($a_date));
					$item->updated_by = Auth::id();
					$item->save();
				}
			} catch (Exception $e) {
				return response()->json(['status' => 400, 'data' => substr($e,0,254)]);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}

	public function al_list_v2()
    {
		$emp = Employee::find(Auth::id());

		$all_emp = DB::select("
			SELECT sum(b.is_all_employee) count_no
			from employee a
			left outer join appraisal_level b
			on a.level_id = b.level_id
			where emp_code = ?
		", array(Auth::id()));
		
		if ($all_emp[0]->count_no > 0) {
			$items = DB::select("
				Select level_id, appraisal_level_name
				From appraisal_level 
				Where is_active = 1 
				and is_hr = 0
				Order by level_id asc			
			");
		} else {
			$co = Org::find($emp->org_id);

			$re_emp = array();
				
			$emp_list = array();

			$emps = DB::select("
				select distinct org_code
				from org
				where parent_org_code = ?
				", array($co->org_code));

			foreach ($emps as $e) {
				$emp_list[] = $e->org_code;
				$re_emp[] = $e->org_code;
			}
			
			$emp_list = array_unique($emp_list);

				// Get array keys
			$arrayKeys = array_keys($emp_list);
				// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
				//iterate array
			$in_emp = '';
			foreach($emp_list as $k => $v) {
				if($k == $lastArrayKey) {
						//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}	

			do {				
				empty($in_emp) ? $in_emp = "null" : null;

				$emp_list = array();			

				$emp_items = DB::select("
					select distinct org_code
					from org
					where parent_org_code in ({$in_emp})
					and parent_org_code != org_code
					and is_active = 1			
					");

				foreach ($emp_items as $e) {
					$emp_list[] = $e->org_code;
					$re_emp[] = $e->org_code;
				}			

				$emp_list = array_unique($emp_list);

					// Get array keys
				$arrayKeys = array_keys($emp_list);
					// Fetch last array key
				$lastArrayKey = array_pop($arrayKeys);
					//iterate array
				$in_emp = '';
				foreach($emp_list as $k => $v) {
					if($k == $lastArrayKey) {
							//during array iteration this condition states the last element.
						$in_emp .= "'" . $v . "'";
					} else {
						$in_emp .= "'" . $v . "'" . ',';
					}
				}		
			} while (!empty($emp_list));		

			$re_emp[] = $co->org_code;

			//# สิทธิ์ โดยเมื่อ User Login เข้ามาแล้วส่วนของหน่วยงานที่แสดงใน Parameter ให้เช็ค org_id ที่ table emp_multi_org_mapping เพิ่ม
			$muti_org =  DB::select("
				select o.org_code
				from emp_org e
				inner join org o on e.org_id = o.org_id
				where emp_id = ?
				", array($emp->emp_id));
			
			foreach ($muti_org as $e) {
				$re_emp[] = $e->org_code;
			}

			$re_emp = array_unique($re_emp);
			
				// Get array keys
			$arrayKeys = array_keys($re_emp);
				// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
				//iterate array
			$in_emp = '';
			foreach($re_emp as $k => $v) {
				if($k == $lastArrayKey) {
						//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}				

			empty($in_emp) ? $in_emp = "null" : null;
			
			if ($emp->is_show_corporate == 1) {
				$items = DB::select("
					select DISTINCT al.level_id, al.appraisal_level_name
					from org
					inner join appraisal_level al on al.level_id = org.level_id
					where org_code in({$in_emp}, '50000001')
					and al.is_hr = 0
					order by al.level_id asc
				");
			} else {
				$items = DB::select("
					select DISTINCT al.level_id, al.appraisal_level_name
					from org
					inner join appraisal_level al on al.level_id = org.level_id
					where org_code in({$in_emp})
					and al.is_hr = 0
					order by al.level_id asc
				");
			}
		}
		
		return response()->json($items);
	}
	
	public function org_list_v2(Request $request)
	{		
		
		$emp = Employee::find(Auth::id());
		$co = Org::find($emp->org_id);
		
		$all_emp = DB::select("
			SELECT sum(b.is_all_employee) count_no
			from employee a
			left outer join appraisal_level b
			on a.level_id = b.level_id
			where emp_code = ?
		", array(Auth::id()));
		
		empty($request->level_id) ? $level = "" : $level = " and a.level_id = '{$request->level_id}'";
		empty($request->org_code) ? $org = "" : $org = " and a.org_code = " . $request->org_code . " ";
		
		if ($all_emp[0]->count_no > 0) {
			$items = DB::select("
			SELECT
				a.org_id ,a.org_name ,a.org_code ,a.org_abbr ,a.is_active ,b.org_name parent_org_name,
				a.parent_org_code ,a.level_id ,c.appraisal_level_name,
				CASE WHEN a.longitude = 0 THEN '' ELSE a.longitude END longitude,
				CASE WHEN a.latitude = 0 THEN '' ELSE a.latitude END latitude,
				a.province_code ,d.province_name 
			FROM
				org a
				LEFT OUTER JOIN org b ON b.org_code = a.parent_org_code
				LEFT OUTER JOIN appraisal_level c ON a.level_id = c.level_id
				LEFT OUTER JOIN province d ON a.province_code = d.province_code 
			WHERE
				1 = 1 " . $level . $org . " 
				AND a.is_active = 1 
			ORDER BY
			a.org_name ASC
			");
		} else {

			$re_emp = array();
			
			$emp_list = array();
			
			$emps = DB::select("
				select distinct org_code
				from org
				where parent_org_code = ?
			", array($co->org_code));
			
			foreach ($emps as $e) {
				$emp_list[] = $e->org_code;
				$re_emp[] = $e->org_code;
			}
		
			$emp_list = array_unique($emp_list);
			
			// Get array keys
			$arrayKeys = array_keys($emp_list);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach($emp_list as $k => $v) {
				if($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}	
				
			do {				
				empty($in_emp) ? $in_emp = "null" : null;

				$emp_list = array();			

				$emp_items = DB::select("
					select distinct org_code
					from org
					where parent_org_code in ({$in_emp})
					and parent_org_code != org_code
					and is_active = 1			
				");
				
				foreach ($emp_items as $e) {
					$emp_list[] = $e->org_code;
					$re_emp[] = $e->org_code;
				}			
				
				$emp_list = array_unique($emp_list);
				
				// Get array keys
				$arrayKeys = array_keys($emp_list);
				// Fetch last array key
				$lastArrayKey = array_pop($arrayKeys);
				//iterate array
				$in_emp = '';
				foreach($emp_list as $k => $v) {
					if($k == $lastArrayKey) {
						//during array iteration this condition states the last element.
						$in_emp .= "'" . $v . "'";
					} else {
						$in_emp .= "'" . $v . "'" . ',';
					}
				}		
			} while (!empty($emp_list));		
			
			$re_emp[] = $co->org_code;

			//# สิทธิ์ โดยเมื่อ User Login เข้ามาแล้วส่วนของหน่วยงานที่แสดงใน Parameter ให้เช็ค org_id ที่ table emp_multi_org_mapping เพิ่ม
			$muti_org =  DB::select("
				select o.org_code
				from emp_org e
				inner join org o on e.org_id = o.org_id
				where emp_id = ?
				", array($emp->emp_id));
			
			foreach ($muti_org as $e) {
				$re_emp[] = $e->org_code;
			}

			$re_emp = array_unique($re_emp);
			
			// Get array keys
			$arrayKeys = array_keys($re_emp);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach($re_emp as $k => $v) {
				if($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}				
			
			empty($in_emp) ? $in_emp = "null" : null;

			if ($emp->is_show_corporate == 1) {
				$items = DB::select("
				SELECT
					a.org_id ,a.org_name ,a.org_code ,a.org_abbr ,a.is_active ,b.org_name parent_org_name,
					a.parent_org_code ,a.level_id ,c.appraisal_level_name ,a.longitude,
					a.latitude ,a.province_code ,d.province_name 
				FROM
					org a
					LEFT OUTER JOIN org b ON b.org_code = a.parent_org_code
					LEFT OUTER JOIN appraisal_level c ON a.level_id = c.level_id
					LEFT OUTER JOIN province d ON a.province_code = d.province_code 
				WHERE
					a.org_code IN ({$in_emp}, '50000001') and a.is_active = 1 ".$level."
				ORDER BY
					c.level_id ASC
				");
			} else {
				$items = DB::select("
				SELECT
					a.org_id ,a.org_name ,a.org_code ,a.org_abbr ,a.is_active ,b.org_name parent_org_name,
					a.parent_org_code ,a.level_id ,c.appraisal_level_name ,a.longitude,
					a.latitude ,a.province_code ,d.province_name 
				FROM
					org a
					LEFT OUTER JOIN org b ON b.org_code = a.parent_org_code
					LEFT OUTER JOIN appraisal_level c ON a.level_id = c.level_id
					LEFT OUTER JOIN province d ON a.province_code = d.province_code 
				WHERE
					a.org_code IN ({$in_emp}) ".$level." 
					AND a.is_active = 1 
				ORDER BY
					c.level_id ASC
				");
			}
			//echo $in_emp;
			
		}
		return response()->json($items);
	}
}
