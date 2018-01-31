<?php

namespace App\Http\Controllers;

use App\Org;
use App\AppraisalItem;
use App\Perspective;
use App\AppraisalPeriod;
use App\AppraisalFrequency;
use App\Employee;
use App\UOM;
use App\SystemConfiguration;

use Auth;
use DB;
use File;
use Validator;
use Excel;
use DateTime;
use DateInterval;
use DatePeriod;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DashboardController extends Controller
{

	public function __construct()
	{

	//   $this->middleware('jwt.auth');
	}



	public function year_list()
	{
		$items = DB::select("
			SELECT appraisal_year FROM appraisal_period
			GROUP BY appraisal_year ORDER BY appraisal_year
		");
		return response()->json($items);
	}

	public function period_list(Request $request){
		$items = DB::select("
			SELECT period_id, appraisal_period_desc
			FROM appraisal_period
			WHERE appraisal_year = ?
		", array($request->appraisal_year));
		return response()->json($items);
	}
	
	public function region_list() 
	{
		$items = DB::select("
			select distinct a.org_code, a.org_name, a.longitude, a.latitude
			from org a,
			(
			select parent_org_code
			from org a
			left outer join appraisal_level b
			on a.level_id = b.level_id
			where b.district_flag = 1
			) b
			where a.org_code = b.parent_org_code		
		");
		return response()->json($items);
	}
	
	public function district_list(Request $request)
	{
		$items = DB::select("
			select a.org_code, a.org_name, a.longitude, a.latitude
			from org a
			left outer join appraisal_level b
			on a.level_id = b.level_id
			where b.district_flag = 1		
			and a.parent_org_code = ?
		", array($request->org_code));
		return response()->json($items);
	}

	public function appraisal_level(){
		// $items = DB::select("
			// SELECT level_id, appraisal_level_name FROM appraisal_level ORDER BY level_id
		// ");
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
				Order by appraisal_level_name			
			");
		} else {
			
			$re_emp = array();
			
			$emp_list = array();
			
			$emps = DB::select("
				select distinct emp_code
				from employee
				where chief_emp_code = ?
			", array(Auth::id()));
			
			foreach ($emps as $e) {
				$emp_list[] = $e->emp_code;
				$re_emp[] = $e->emp_code;
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
					select distinct emp_code
					from employee
					where chief_emp_code in ({$in_emp})
					and chief_emp_code != emp_code
					and is_active = 1			
				");
				
				foreach ($emp_items as $e) {
					$emp_list[] = $e->emp_code;
					$re_emp[] = $e->emp_code;
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
			
			$re_emp[] = Auth::id();
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
			

			$items = DB::select("
				select distinct al.level_id, al.appraisal_level_name
				from employee el, appraisal_level al
				where el.level_id = al.level_id
				and el.emp_code in ({$in_emp})
				and al.is_hr = 0
				order by al.appraisal_level_name
			");
		}		
		return response()->json($items);
	}

	public function org_list(Request $request){
		$items = DB::select("
			SELECT org_id, org_name
			FROM org
			WHERE is_active = 1
			AND level_id = ?
			ORDER BY org_id
		", array($request->appraisal_level));
		return response()->json($items);
	}
	
	public function kpi_map_list(Request $request)
	{
		$org_input = array();
		$org_query = "
			select distinct e.item_id, e.item_name
			from appraisal_item_result a
			left outer join emp_result b
			on a.emp_result_id = b.emp_result_id
			left outer join org c
			on a.org_id = c.org_id
			left outer join appraisal_level d
			on c.level_id = d.level_id
			inner join (
				select org_id, org_name, org_code
				from org x left outer join appraisal_level y
				on x.level_id = y.level_id
				where y.district_flag = 1
				
		";
		
		empty($request->region_code) ?: ($org_query .= " and parent_org_code = ? " AND $org_input[] = $request->region_code);	
		
		$org_query .= "
			) e on c.parent_org_code = e.org_code
			left outer join appraisal_item e
			on a.item_id = e.item_id
			where appraisal_type_id = 1
		";

		empty($request->district_code) ?: ($org_query .= " and c.parent_org_code = ? " AND $org_input[] = $request->district_code);	
		
		$org_list = DB::select($org_query, $org_input);	
		
		return response()->json($org_list);
		
	}

	public function kpi_list(Request $request){
		$qinput = array();
		/*
		$query = "
			SELECT item_id, MAX(item_name) item_name
			FROM appraisal_item_result
			WHERE 1=1
		";
		*/
		$query = "
		SELECT distinct air.item_id, air.item_name
			FROM appraisal_item_result air
			inner join appraisal_item ai on air.item_id=ai.item_id
			inner join appraisal_structure aps on ai.structure_id = aps.structure_id
		
			WHERE 1=1
			and aps.form_id=1
			";
		
		$qfooter = " GROUP BY air.item_id
			ORDER BY air.item_id ";
		
		if ($request->appraisal_type_id == 1) {
			empty($request->appraisal_level) ?: ($query .= " and level_id = ? " AND $qinput[] = $request->appraisal_level);
			empty($request->org_id) ?: ($query .= " and org_id = ? " AND $qinput[] = $request->org_id);
		} else {
			empty($request->emp_id) ?: ($query .= " and emp_id = ? " AND $qinput[] = $request->emp_id);
		}
			
		$items = DB::select($query.$qfooter,$qinput);
		return response()->json($items);
	}

	public function dashboard_content(Request $request){
		$RespData = [];

		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}	


		// Get accordion data //
		$groupQry = DB::select("
			SELECT air.org_id,
				org.org_name,
				p.perspective_name,
				ai.item_name,
				air.target_value,
				air.forecast_value,
				air.actual_value,
				air.item_result_id
			FROM appraisal_item_result air
			INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
			INNER JOIN org ON org.org_id = air.org_id
			INNER JOIN appraisal_item ai ON ai.item_id = air.item_id
			INNER JOIN perspective p ON p.perspective_id = ai.perspective_id
			WHERE ap.appraisal_year = ?
			AND air.period_id = ?
			AND air.level_id = ?
			AND air.org_id = ?
			AND air.item_id = ?
			UNION ALL
			SELECT air.org_id,
				org.org_name,
				p.perspective_name,
				ai.item_name,
				air.target_value,
				air.forecast_value,
				air.actual_value,
				air.item_result_id
			FROM appraisal_item_result air
			INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
			INNER JOIN org ON org.org_id = air.org_id
			INNER JOIN appraisal_item ai ON ai.item_id = air.item_id
			INNER JOIN perspective p ON p.perspective_id = ai.perspective_id
			WHERE ap.appraisal_year = ?
			AND air.period_id = ?
			AND org.parent_org_code = (
				SELECT org_code FROM org WHERE org_id = ?
			)
			AND air.item_id = ?
		", array($request->year_id, $request->period_id, $request->level_id, $request->org_id, $request->item_id,
			$request->year_id, $request->period_id, $request->org_id, $request->item_id));
		$loopCnt = 1;
		foreach ($groupQry as $groupObj) {
			$responseArr["group".$loopCnt] = array(
				"org_id" => $groupObj->org_id,
				"org_name" => $groupObj->org_name,
				"perspective_name" => $groupObj->perspective_name,
				"item_name" => $groupObj->item_name
			);

			// Dual chart data builder //
			$responseArr["group".$loopCnt]["dual_chart"]["data"] = array(
				"target" => $groupObj->target_value,
				"forecast" => $groupObj->forecast_value,
				"actual_value" => $groupObj->actual_value
			);

			// Dual chart color range builder //


			//echo $config->threshold;



			$dualColorQry = DB::select("
				SELECT begin_threshold, end_threshold, color_code, result_type
				FROM result_threshold
				WHERE result_threshold_group_id = (
					SELECT MAX(er.result_threshold_group_id) result_threshold_group_id
					FROM appraisal_item_result air
					INNER JOIN emp_result er ON er.emp_result_id = air.emp_result_id
					INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
					WHERE air.item_result_id = ?
				)
				ORDER BY begin_threshold
			", array($groupObj->item_result_id));
			if ($dualColorQry == []) {
				$responseArr["group".$loopCnt]["dual_chart"]["color_range"] = array();
			} else {
				foreach ($dualColorQry as $dualColorObj) {
					$responseArr["group".$loopCnt]["dual_chart"]["color_range"][] = array(
						"min_val" => $dualColorObj->begin_threshold,
						"max_val" => $dualColorObj->end_threshold,
						"color" => $dualColorObj->color_code
					);
				};
			}

			// Bar chart target data builder //
			$valueBarQry = DB::select("
				SELECT q1.appraisal_month_no,
					q1.appraisal_month_name,
					q1.actual_value,
					SUM(q2.actual_value) actual_value_ytd
				FROM monthly_appraisal_item_result q1
				INNER JOIN monthly_appraisal_item_result q2
					ON q1.year = q2.year
					AND q1.period_id = q2.period_id
					AND q1.level_id = q2.level_id
					AND q1.org_id = q2.org_id
					AND q1.item_id = q2.item_id
				WHERE q1.appraisal_month_no >= q2.appraisal_month_no
				AND q1.year = ?
				AND q1.period_id = ?
				AND q1.level_id = ?
				AND q1.org_id = ?
				AND q1.item_id = ?
				GROUP BY q1.appraisal_month_no, q1.appraisal_month_name, q1.actual_value
				ORDER BY q1.appraisal_month_no
			", array($request->year_id, $request->period_id, $request->level_id, $request->org_id, $request->item_id));
			if ($valueBarQry == []) {
				$responseArr["group".$loopCnt]["bar_chart"]["data"]["actual"] = array();
			} else {
				foreach ($valueBarQry as $valueBarObj) {
					$responseArr["group".$loopCnt]["bar_chart"]["data"]["actual"][] = array(
						"month" => $valueBarObj->appraisal_month_name,
						"value" => $valueBarObj->actual_value_ytd
					);
				};
			}

			$responseArr["group".$loopCnt]["bar_chart"]["data"]["target"] = $groupObj->target_value;

			$responseArr["group".$loopCnt]["bar_chart"]["data"]["forecast"] = $groupObj->forecast_value;

			$RespData = $RespData+$responseArr; //array_push($RespData, $responseArr);
			$loopCnt++;
		};

		return response()->json($RespData);
	}


	public function all_dashboard_content(Request $request){
	
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}	
		
		$RespData = [];


		
		if ($request->appraisal_type_id == 2) { //emp

		$emp = Employee::where('emp_id',$request->emp_id)->first();
		//Start find item_id on emp_code
			$re_emp = array();
			$emp_list = array();
			$emps = DB::select("
				SELECT distinct  ai.item_id
				FROM appraisal_item ai 
				left join appraisal_item_result air on ai.item_id=air.item_id
				left join employee e on air.emp_id=e.emp_id
				where e.emp_code=?
			", array($emp->emp_code));
			foreach ($emps as $e) {
				$emp_list[] = $e->item_id;
				$re_emp[] = $e->item_id;
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
		//End find item_id on emp_code
			//echo $in_emp;
			empty($in_emp) ? $in_emp = "null" : null;
			
			$chartQry = DB::select("
				#SELECT DISTINCT p.perspective_id, p.perspective_name, air.item_id, air.item_name, u.uom_name
				SELECT p.perspective_id, p.perspective_name, air.item_id, air.item_name, u.uom_name, air.etl_dttm, max(air.item_result_id) item_result_id
				FROM appraisal_item_result air
				INNER JOIN appraisal_item ai ON ai.item_id = air.item_id
				INNER JOIN perspective p ON p.perspective_id = ai.perspective_id
				INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
				LEFT OUTER JOIN uom u ON u.uom_id = ai.uom_id
				LEFT OUTER JOIN org o ON o.org_id = air.org_id
				LEFT OUTER JOIN org ON org.org_id = air.org_id
				LEFT OUTER JOIN emp_result er on air.emp_result_id = er.emp_result_id
				LEFT OUTER JOIN employee e on air.emp_id = e.emp_id
				WHERE er.appraisal_type_id = ?
				AND ap.appraisal_year = ?
				AND air.period_id = ?
				AND (e.emp_code = ? or e.chief_emp_code = ?)
				AND ai.item_id in({$in_emp})
				GROUP BY p.perspective_id, air.item_id
				ORDER BY p.perspective_name, air.item_name, air.item_result_id
			", array($request->appraisal_type_id, $request->year_id, $request->period_id, $emp->emp_code, $emp->emp_code));	
			
			
			
			$OrgListQry = DB::select("
				SELECT DISTINCT air.emp_id org_id
				FROM appraisal_item_result air
				INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
				LEFT OUTER JOIN org ON org.org_id = air.org_id
				LEFT OUTER JOIN emp_result er on air.emp_result_id = er.emp_result_id
				left outer join employee e on air.emp_id = e.emp_id
				left join appraisal_item ai on ai.item_id=air.item_id
				WHERE er.appraisal_type_id = ?
				AND ap.appraisal_year = ?
				AND air.period_id = ?
				AND (e.emp_code = ? or e.chief_emp_code = ?)
				AND ai.item_id in({$in_emp})
				ORDER BY e.emp_code
			", array($request->appraisal_type_id, $request->year_id, $request->period_id, $emp->emp_code, $emp->emp_code));	
			
		} else { //org
			$org = Org::find($request->org_id);

			//Start find item_id on emp_code
			
			$re_org = array();
			$org_list = array();
			$orgs = DB::select("
				SELECT distinct  ai.item_id
				FROM appraisal_item ai 
				left join appraisal_item_result air on ai.item_id=air.item_id
				left join org o on air.org_id=o.org_id
				where o.org_code=?
			", array($org->org_code));
			foreach ($orgs as $e) {
				$org_list[] = $e->item_id;
				$re_org[] = $e->item_id;
			}
			$org_list = array_unique($org_list);
			
			// Get array keys
			$arrayKeys = array_keys($org_list);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_item = '';
			foreach($org_list as $k => $v) {
				if($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_item .= "'" . $v . "'";
				} else {
					$in_item .= "'" . $v . "'" . ',';
				}
			}		
			
			//End find item_id on emp_code
			empty($in_item) ? $in_item = "null" : null;

			$chartQry = DB::select("
				#SELECT DISTINCT p.perspective_id, p.perspective_name, air.item_id, air.item_name, u.uom_name
				SELECT p.perspective_id, p.perspective_name, air.item_id, air.item_name, u.uom_name, air.etl_dttm, max(air.item_result_id) item_result_id
				FROM appraisal_item_result air
				INNER JOIN appraisal_item ai ON ai.item_id = air.item_id
				INNER JOIN perspective p ON p.perspective_id = ai.perspective_id
				INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
				LEFT OUTER JOIN uom u ON u.uom_id = ai.uom_id
				LEFT OUTER JOIN org o ON o.org_id = air.org_id
				LEFT OUTER JOIN org ON org.org_id = air.org_id
				LEFT OUTER JOIN emp_result er on air.emp_result_id = er.emp_result_id
				WHERE er.appraisal_type_id = ?
				AND ap.appraisal_year = ?
				AND air.period_id = ?
				AND (org.parent_org_code = ? or org.org_code = ?)
				AND ai.item_id in({$in_item})
				GROUP BY p.perspective_id, air.item_id
				ORDER BY p.perspective_name, air.item_name, air.item_result_id
			", array($request->appraisal_type_id, $request->year_id, $request->period_id, $org->org_code, $org->org_code));
			
			$OrgListQry = DB::select("
				SELECT DISTINCT air.org_id
				FROM appraisal_item_result air
				INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
				LEFT OUTER JOIN org ON org.org_id = air.org_id
				LEFT OUTER JOIN emp_result er on air.emp_result_id = er.emp_result_id
				left join appraisal_item ai on ai.item_id=air.item_id
				WHERE er.appraisal_type_id = ?
				AND ap.appraisal_year = ?
				AND air.period_id = ?
				AND (org.parent_org_code = ? or org.org_code = ?)
				AND ai.item_id in({$in_item})
				ORDER BY org.org_code
			", array($request->appraisal_type_id, $request->year_id, $request->period_id, $org->org_code, $org->org_code));			
		}




		$loopCnt = 0;
		foreach ($chartQry as $chartObj) {
			if ($loopCnt == 0) {
				$previousItem = 0;
				$previousPerspec = 0;
			}
			$RespData[$loopCnt] = array(
				"perspective"=> $chartObj->perspective_name,
				"item"=> $chartObj->item_name,
				"uom"=> $chartObj->uom_name,
				"item_id" => $chartObj->item_id,
				"etl_dttm" => $chartObj->etl_dttm
			);

			// chart color range builder //
			$colors = array();
			$valueRanges = array();
			$ColorRangeQry = DB::select("
				SELECT begin_threshold, end_threshold, color_code, result_type
				FROM result_threshold
				WHERE result_threshold_group_id = (
					SELECT MAX(er.result_threshold_group_id) result_threshold_group_id
					FROM appraisal_item_result air
					INNER JOIN emp_result er ON er.emp_result_id = air.emp_result_id
					INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
					WHERE air.item_result_id = ?
				)
				ORDER BY begin_threshold DESC
			", array($chartObj->item_result_id));
			/*if ($ColorRangeQry == []) {
				$RespData[$loopCnt]["color"] = array();
			} else {
				foreach ($ColorRangeQry as $colorRangeObj) {
					$colorCode = $retVal = (strpos($colorRangeObj->color_code, '#') == true) ? $colorRangeObj->color_code : "#".$colorRangeObj->color_code ;
					$RespData[$loopCnt]["color"][] = array(
						"minValue" => $colorRangeObj->begin_threshold,
						"maxValue" => $colorRangeObj->end_threshold,
						"code" => $colorCode
					);
				};
			}*/
			if ($ColorRangeQry == []) {
				$RespData[$loopCnt]["color"] = array();
			} else {
				foreach ($ColorRangeQry as $colorRangeObj) {
					$colorCode = $retVal = (strpos($colorRangeObj->color_code, '#') == true) ? $colorRangeObj->color_code : "#".$colorRangeObj->color_code ;
					array_push($colors, $colorCode);
					array_push($valueRanges, $colorRangeObj->end_threshold);
				}
				$RespData[$loopCnt]["rangeColor"] = $colors;
			}

			// push all org //
			foreach ($OrgListQry as $OrgListObj) {
				$RespData[$loopCnt]["org"]["id_".$OrgListObj->org_id] = array();
			}

			// push data to org //
			
			if ($request->appraisal_type_id == 2) { //emp
				$dataListQry = DB::select("
					SELECT air.item_result_id, p.perspective_id, p.perspective_name, air.item_id, air.item_name, u.uom_name, e.emp_id org_id, e.emp_code org_code, e.emp_name org_name, air.etl_dttm,
						air.target_value, air.forecast_value, ifnull(air.actual_value, 0) actual_value,
						ifnull(if(air.target_value = 0, 0, (air.actual_value/air.target_value)*100), 0) percent_target,
						ifnull(if(air.forecast_value = 0, 0, (air.actual_value/air.forecast_value)*100), 0) percent_forecast
					FROM appraisal_item_result air
					INNER JOIN appraisal_item ai ON ai.item_id = air.item_id
					INNER JOIN perspective p ON p.perspective_id = ai.perspective_id
					INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
					LEFT OUTER JOIN uom u ON u.uom_id = ai.uom_id
					LEFT OUTER JOIN org o ON o.org_id = air.org_id
					LEFT OUTER JOIN org ON org.org_id = air.org_id
					left outer join emp_result er on air.emp_result_id = er.emp_result_id
					left outer join employee e on air.emp_id = e.emp_id
					WHERE er.appraisal_type_id = ?
					and ap.appraisal_year = ?
					AND air.period_id = ?
					AND (e.emp_code = ? or e.chief_emp_code = ?)
					AND air.item_id = ?
					ORDER BY p.perspective_name, air.item_name, air.item_result_id, e.emp_code
				", array($request->appraisal_type_id, $request->year_id, $request->period_id, $emp->emp_code, $emp->emp_code, $chartObj->item_id));			
			} else { //org
				$dataListQry = DB::select("
					SELECT air.item_result_id, p.perspective_id, p.perspective_name, air.item_id, air.item_name, u.uom_name, air.org_id, org.org_code, o.org_name, air.etl_dttm,
						air.target_value, air.forecast_value, ifnull(air.actual_value, 0) actual_value,
						ifnull(if(air.target_value = 0, 0, (air.actual_value/air.target_value)*100), 0) percent_target,
						ifnull(if(air.forecast_value = 0, 0, (air.actual_value/air.forecast_value)*100), 0) percent_forecast
					FROM appraisal_item_result air
					INNER JOIN appraisal_item ai ON ai.item_id = air.item_id
					INNER JOIN perspective p ON p.perspective_id = ai.perspective_id
					INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
					LEFT OUTER JOIN uom u ON u.uom_id = ai.uom_id
					LEFT OUTER JOIN org o ON o.org_id = air.org_id
					LEFT OUTER JOIN org ON org.org_id = air.org_id
					left outer join emp_result er on air.emp_result_id = er.emp_result_id
					WHERE er.appraisal_type_id = ?
					and ap.appraisal_year = ?
					AND air.period_id = ?
					AND (org.parent_org_code = ? or org.org_code = ?)
					AND air.item_id = ?
					GROUP BY p.perspective_id, air.item_id, air.org_id
					ORDER BY p.perspective_name, air.item_name, air.item_result_id, org.org_code
				", array($request->appraisal_type_id, $request->year_id, $request->period_id, $org->org_code, $org->org_code, $chartObj->item_id));
			}

			foreach ($dataListQry as $dataListObj) {
				
				$target_ranges = $valueRanges;
				$forecast_ranges = $valueRanges;
				
				if ($dataListObj->percent_target > $valueRanges[0]) {
					$target_ranges[0] = floor($dataListObj->percent_target) + 1;
				}
				
				if ($dataListObj->percent_forecast > $valueRanges[0]) {
					$forecast_ranges[0] = floor($dataListObj->percent_forecast) + 1;
				}				
				
				$orgDetail = array(
					"org" => $dataListObj->org_name,
					"org_id" => $dataListObj->org_id,
					"org_code" => $dataListObj->org_code,
					"target"=> $dataListObj->target_value,
					"forecast" => $dataListObj->forecast_value,
					"actual" => $dataListObj->actual_value,
					"etl_dttm" => $dataListObj->etl_dttm,
					"percent_target" => $dataListObj->percent_target,
					"percent_forecast" => $dataListObj->percent_forecast,

					// For Spackline JS //
					"percent_target_str" => "100".",".$dataListObj->percent_target.",".implode($target_ranges, ","),
					"percent_forecast_str" => "100".",".$dataListObj->percent_forecast.",".implode($forecast_ranges, ",")
				);

				$RespData[$loopCnt]["org"]["id_".$dataListObj->org_id] = $orgDetail;
			}


			// Set loop index //
			$loopCnt++;
		}

		return response()->json($RespData);
	}
	
	public function kpi_overall_pie(Request $request)
	{

		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}

		


		$qinput = [];

		

		if ($request->appraisal_type_id == 2) {


			
			if($config->result_type==2 and $config->threshold==1){// (CSP)
				$query = "
				select a.emp_result_id, b.emp_name name, b.emp_name full_name, sum(d.weigh_score) total_weigh_score, 
				round((f.nof_target_score*sum(d.weight_percent)/100),2) total_weight_percent, 
				 round((sum(d.weigh_score)/sum(d.weight_percent)) * 100,2) result_score,
				(
					select rt.color_code
					from result_threshold rt
					where rt.result_threshold_group_id = a.result_threshold_group_id
					and (sum(d.weigh_score)/sum(d.weight_percent)) * 100 between begin_threshold and end_threshold
				) color_code,
				h.no_weight,
				f.nof_target_score

				from emp_result a
				left outer join employee b
				on a.emp_id = b.emp_id
				left outer join org c
				on a.org_id = c.org_id
				left outer join appraisal_item_result d
				on a.emp_result_id = d.emp_result_id
				left outer join appraisal_item e
				on d.item_id = e.item_id
				left outer join appraisal_structure f
				on e.structure_id = f.structure_id
				left outer join form_type g
				on f.form_id = g.form_id
				left outer join appraisal_level h 
				on a.level_id=h.level_id
				where a.appraisal_type_id = ?
				and a.period_id = ?
				and g.form_name = 'Quantity'
			";
			
			$qfooter = " group by a.emp_result_id, b.emp_name , b.emp_name order by color_code ";
			
			$qinput[] = $request->appraisal_type_id;
			$qinput[] = $request->period_id;	
						
			empty($request->emp_id) ?: ($query .= " and a.emp_id = ? " AND $qinput[] = $request->emp_id);
		
			

			}else if($config->result_type==1 and $config->threshold==1){// (TFG)

				$query = "
				select a.emp_result_id, b.emp_name name, b.emp_name full_name, sum(d.weigh_score) total_weigh_score, 
				round((sum(d.weight_percent)/f.nof_target_score),2) total_weight_percent, 
				 round((sum(d.weigh_score)/sum(d.weight_percent)) * 100,2) result_score,
				(
					select rt.color_code
					from result_threshold rt
					where rt.result_threshold_group_id = a.result_threshold_group_id
					and (sum(d.weigh_score)/sum(d.weight_percent)) * 100 between begin_threshold and end_threshold
				) color_code,
				h.no_weight,
				f.nof_target_score

				from emp_result a
				left outer join employee b
				on a.emp_id = b.emp_id
				left outer join org c
				on a.org_id = c.org_id
				left outer join appraisal_item_result d
				on a.emp_result_id = d.emp_result_id
				left outer join appraisal_item e
				on d.item_id = e.item_id
				left outer join appraisal_structure f
				on e.structure_id = f.structure_id
				left outer join form_type g
				on f.form_id = g.form_id
				left outer join appraisal_level h 
				on a.level_id=h.level_id
				where a.appraisal_type_id = ?
				and a.period_id = ?
				and g.form_name = 'Quantity'
			";
			
			$qfooter = " group by a.emp_result_id, b.emp_name , b.emp_name order by color_code ";
			
			$qinput[] = $request->appraisal_type_id;
			$qinput[] = $request->period_id;	
						
			empty($request->emp_id) ?: ($query .= " and a.emp_id = ? " AND $qinput[] = $request->emp_id);
		

			}else{ // (GHB)

			$query = "
				select a.emp_result_id, b.emp_name name, b.emp_name full_name, sum(d.weigh_score) total_weigh_score, sum(d.weight_percent) total_weight_percent, round((sum(d.weigh_score)/sum(d.weight_percent)) * 100,2) result_score,
				(
					select rt.color_code
					from result_threshold rt
					where rt.result_threshold_group_id = a.result_threshold_group_id
					and (sum(d.weigh_score)/sum(d.weight_percent)) * 100 between begin_threshold and end_threshold
				) color_code,
				h.no_weight
				from emp_result a
				left outer join employee b
				on a.emp_id = b.emp_id
				left outer join org c
				on a.org_id = c.org_id
				left outer join appraisal_item_result d
				on a.emp_result_id = d.emp_result_id
				left outer join appraisal_item e
				on d.item_id = e.item_id
				left outer join appraisal_structure f
				on e.structure_id = f.structure_id
				left outer join form_type g
				on f.form_id = g.form_id
				left outer join appraisal_level h 
				on a.level_id=h.level_id
				where a.appraisal_type_id = ?
				and a.period_id = ?
				and g.form_name = 'Quantity'
			";
			
			$qfooter = " group by a.emp_result_id, b.emp_name , b.emp_name order by color_code ";
			
			$qinput[] = $request->appraisal_type_id;
			$qinput[] = $request->period_id;	
						
			empty($request->emp_id) ?: ($query .= " and a.emp_id = ? " AND $qinput[] = $request->emp_id);

			}

			
		
		} else {
			//ORG
			if($config->result_type==2 and $config->threshold==1){// (CSP)
				/*
				$query = "
				select a.emp_result_id, b.emp_name name, b.emp_name full_name, 
				round(sum(d.weigh_score),2) total_weigh_score, 
				round((f.nof_target_score*sum(d.weight_percent)/100),2) total_weight_percent, 
				round((sum(d.weigh_score)/sum(d.weight_percent)) * 100,2) result_score,
				(
					select rt.color_code
					from result_threshold rt
					where rt.result_threshold_group_id = a.result_threshold_group_id
					and (sum(d.weigh_score)/sum(d.weight_percent)) * 100 between begin_threshold and end_threshold
				) color_code,
				h.no_weight,
				f.nof_target_score

				from emp_result a
				left outer join employee b
				on a.emp_id = b.emp_id
				left outer join org c
				on a.org_id = c.org_id
				left outer join appraisal_item_result d
				on a.emp_result_id = d.emp_result_id
				left outer join appraisal_item e
				on d.item_id = e.item_id
				left outer join appraisal_structure f
				on e.structure_id = f.structure_id
				left outer join form_type g
				on f.form_id = g.form_id
				left outer join appraisal_level h 
				on a.level_id=h.level_id
				where a.appraisal_type_id = ?
				and a.period_id = ?
				and g.form_name = 'Quantity'

			";
			*/

			$query = "
				select a.emp_result_id, c.org_abbr name, c.org_name full_name, 
					

					round(sum(d.weigh_score),2) total_weigh_score, 
					round((f.nof_target_score*sum(d.weight_percent)/100),2) total_weight_percent, 
					round((sum(d.weigh_score)/sum(d.weight_percent)) * 100,2) result_score,
				(
					select rt.color_code
					from result_threshold rt
					where rt.result_threshold_group_id = a.result_threshold_group_id
					and (sum(d.weigh_score)/sum(d.weight_percent)) * 100 between begin_threshold and end_threshold
				) color_code,
				h.no_weight
				from emp_result a
				left outer join employee b
				on a.emp_id = b.emp_id
				left outer join org c
				on a.org_id = c.org_id
				left outer join appraisal_item_result d
				on a.emp_result_id = d.emp_result_id
				left outer join appraisal_item e
				on d.item_id = e.item_id
				left outer join appraisal_structure f
				on e.structure_id = f.structure_id
				left outer join form_type g
				on f.form_id = g.form_id
				left outer join appraisal_level h 
				on a.level_id=h.level_id
				where a.appraisal_type_id = ?
				and a.period_id = ?
				and g.form_name = 'Quantity'


			";

			
			$qfooter = " group by a.emp_result_id, b.emp_name , b.emp_name order by color_code ";
			
			$qinput[] = $request->appraisal_type_id;
			$qinput[] = $request->period_id;	
						
			//empty($request->emp_id) ?: ($query .= " and a.emp_id = ? " AND $qinput[] = $request->emp_id);
			empty($request->org_id) ?: ($query .= " and a.org_id = ? " AND $qinput[] = $request->org_id);
			

			}else if($config->result_type==1 and $config->threshold==1){// (TFG)

				/*
				$query = "
				select a.emp_result_id, b.emp_name name, b.emp_name full_name, 
				round(sum(d.weigh_score),2) total_weigh_score, 
				round((sum(d.weight_percent)/f.nof_target_score),2) total_weight_percent, 
				round((sum(d.weigh_score)/sum(d.weight_percent)) * 100,2) result_score,
				(
					select rt.color_code
					from result_threshold rt
					where rt.result_threshold_group_id = a.result_threshold_group_id
					and (sum(d.weigh_score)/sum(d.weight_percent)) * 100 between begin_threshold and end_threshold
				) color_code,
				h.no_weight,
				f.nof_target_score

				from emp_result a
				left outer join employee b
				on a.emp_id = b.emp_id
				left outer join org c
				on a.org_id = c.org_id
				left outer join appraisal_item_result d
				on a.emp_result_id = d.emp_result_id
				left outer join appraisal_item e
				on d.item_id = e.item_id
				left outer join appraisal_structure f
				on e.structure_id = f.structure_id
				left outer join form_type g
				on f.form_id = g.form_id
				left outer join appraisal_level h 
				on a.level_id=h.level_id
				where a.appraisal_type_id = ?
				and a.period_id = ?
				and g.form_name = 'Quantity'
			";
			*/
			$query = "
				select a.emp_result_id, c.org_abbr name, c.org_name full_name, 
					

					round(sum(d.weigh_score),2) total_weigh_score, 
					round((sum(d.weight_percent)/f.nof_target_score),2) total_weight_percent, 
					round((sum(d.weigh_score)/sum(d.weight_percent)) * 100,2) result_score,

				(
					select rt.color_code
					from result_threshold rt
					where rt.result_threshold_group_id = a.result_threshold_group_id
					and (sum(d.weigh_score)/sum(d.weight_percent)) * 100 between begin_threshold and end_threshold
				) color_code,
				h.no_weight
				from emp_result a
				left outer join employee b
				on a.emp_id = b.emp_id
				left outer join org c
				on a.org_id = c.org_id
				left outer join appraisal_item_result d
				on a.emp_result_id = d.emp_result_id
				left outer join appraisal_item e
				on d.item_id = e.item_id
				left outer join appraisal_structure f
				on e.structure_id = f.structure_id
				left outer join form_type g
				on f.form_id = g.form_id
				left outer join appraisal_level h 
				on a.level_id=h.level_id
				where a.appraisal_type_id = ?
				and a.period_id = ?
				and g.form_name = 'Quantity'


			";
			
			$qfooter = " group by a.emp_result_id, b.emp_name , b.emp_name order by color_code ";
			
			$qinput[] = $request->appraisal_type_id;
			$qinput[] = $request->period_id;	
						
			//empty($request->emp_id) ?: ($query .= " and a.emp_id = ? " AND $qinput[] = $request->emp_id);
			empty($request->org_id) ?: ($query .= " and a.org_id = ? " AND $qinput[] = $request->org_id);

			}else{ // (GHB)

			$query = "
				select a.emp_result_id, c.org_abbr name, c.org_name full_name, sum(d.weigh_score) total_weigh_score, sum(d.weight_percent) total_weight_percent, round((sum(d.weigh_score)/sum(d.weight_percent)) * 100,2) result_score,
				(
					select rt.color_code
					from result_threshold rt
					where rt.result_threshold_group_id = a.result_threshold_group_id
					and (sum(d.weigh_score)/sum(d.weight_percent)) * 100 between begin_threshold and end_threshold
				) color_code,
				h.no_weight
				from emp_result a
				left outer join employee b
				on a.emp_id = b.emp_id
				left outer join org c
				on a.org_id = c.org_id
				left outer join appraisal_item_result d
				on a.emp_result_id = d.emp_result_id
				left outer join appraisal_item e
				on d.item_id = e.item_id
				left outer join appraisal_structure f
				on e.structure_id = f.structure_id
				left outer join form_type g
				on f.form_id = g.form_id
				left outer join appraisal_level h 
				on a.level_id=h.level_id
				where a.appraisal_type_id = ?
				and a.period_id = ?
				and g.form_name = 'Quantity'
			";
			
			$qfooter = " group by a.emp_result_id, c.org_abbr, c.org_name order by color_code ";
			
			$qinput[] = $request->appraisal_type_id;
			$qinput[] = $request->period_id;	
						
			empty($request->org_id) ?: ($query .= " and a.org_id = ? " AND $qinput[] = $request->org_id);	

			}


				
		}
		// echo $query.$qfooter; 
		// echo "<br>";
		// print_r($qinput);



		$emp_result = DB::select($query.$qfooter,$qinput);	
		
		$category = [];
		$cat1 = [];
		$header = '';
		foreach ($emp_result as $e) {
			
			

			if($config->result_type==2 and $config->threshold==1){// (CSP)

				$perspective = DB::select("
				select b.perspective_id, c.perspective_abbr, c.perspective_name, c.color_code,
				#sum(a.weight_percent) perspective_weight, 
				round((f.nof_target_score*sum(a.weight_percent)/100),2) perspective_weight, 
				round(sum(a.weigh_score),2) total_score
				from appraisal_item_result a
				left outer join appraisal_item b
				on a.item_id = b.item_id
				left outer join perspective c
				on b.perspective_id = c.perspective_id
				left outer join appraisal_structure d
				on b.structure_id = d.structure_id
				left outer join form_type e
				on d.form_id = e.form_id
				left outer join appraisal_structure f
				on b.structure_id = f.structure_id
				where emp_result_id = ?
				and b.perspective_id is not null
				and e.form_name = 'Quantity'
				group by b.perspective_id, c.perspective_name, c.color_code, a.structure_weight_percent			
			",array($e->emp_result_id));

			}else if($config->result_type==1 and $config->threshold==1){// (TFG)

				$perspective = DB::select("
				select b.perspective_id, c.perspective_abbr, c.perspective_name, c.color_code,
				round((sum(a.weight_percent)/f.nof_target_score),2) perspective_weight, 
				round(sum(a.weigh_score),2) total_score
				from appraisal_item_result a
				left outer join appraisal_item b
				on a.item_id = b.item_id
				left outer join perspective c
				on b.perspective_id = c.perspective_id
				left outer join appraisal_structure d
				on b.structure_id = d.structure_id
				left outer join form_type e
				on d.form_id = e.form_id
				left outer join appraisal_structure f
				on b.structure_id = f.structure_id
				where emp_result_id = ?
				and b.perspective_id is not null
				and e.form_name = 'Quantity'
				group by b.perspective_id, c.perspective_name, c.color_code, a.structure_weight_percent			
			",array($e->emp_result_id));

			}else{ // (GHB)

				$perspective = DB::select("
				select b.perspective_id, c.perspective_abbr, c.perspective_name, c.color_code, 
				round(sum(a.weight_percent),2) perspective_weight, 
				round(sum(a.weigh_score),2) total_score
				from appraisal_item_result a
				left outer join appraisal_item b
				on a.item_id = b.item_id
				left outer join perspective c
				on b.perspective_id = c.perspective_id
				left outer join appraisal_structure d
				on b.structure_id = d.structure_id
				left outer join form_type e
				on d.form_id = e.form_id
				where emp_result_id = ?
				and b.perspective_id is not null
				and e.form_name = 'Quantity'
				group by b.perspective_id, c.perspective_name, c.color_code, a.structure_weight_percent			
			",array($e->emp_result_id));

			}
			

			//echo $e->emp_result_id;
			
			$cat2 = [];
			foreach ($perspective as $p) {
				$cat2[] = ['label' => $p->perspective_abbr.' '.$p->total_score.'%', 'color' => $p->color_code, 'value' => $p->total_score, 'perspective_id' => $p->perspective_id,
				'tooltext' => "<div id='nameDiv'>" . $p->perspective_name . "</div>{br}" . $p->total_score."% of " . $p->perspective_weight . "%"];
			}
			$cat1[] = ['label' => $e->name . ' ' . $e->total_weigh_score .'%', 'color' => $e->color_code, 'value' => $e->result_score, 'category' => $cat2,
			'tooltext' => "<div id='nameDiv'>" . $e->full_name . "</div>{br}" . $e->total_weigh_score."% of " . $e->total_weight_percent . "%"];
			$header = $e->name;
		}
		
		$category['category'] = $cat1;
		$category['header'] = $header . " Performance by Perspective" ;
		$category['name'] = $header;
		
		return response()->json($category);
	}
	
	public function kpi_overall_bubble(Request $request)
	{	
		$max_y = 0;
		$max_x = 0;
		$qinput = [];
		$query = "
			select c.org_id, if(c.appraisal_type_id=1,e.emp_name,d.org_name) name, a.item_id, b.item_name, ifnull(a.target_value,0) target_value, ifnull(a.actual_value,0) actual_value, a.weight_percent,
				(
				SELECT axis_value_name FROM axis_mapping
				where axis_type_id=2
				and a.weight_percent between axis_value_start and axis_value_end 

				)axis_value_name,
			 a.etl_dttm, c.result_threshold_group_id,
			a.percent_achievement achievement,
			#round(if(ifnull(a.target_value,0) = 0,0,(ifnull(a.actual_value,0)/a.target_value)*100),2) achievement,
			100 - a.percent_achievement urgency,
			(
			select rt.color_code
			from result_threshold rt
			where rt.result_threshold_group_id = c.result_threshold_group_id
			and a.percent_achievement between begin_threshold and end_threshold
			) color_code,
			(
			select rt.begin_threshold
			from result_threshold rt
			where rt.result_threshold_group_id = c.result_threshold_group_id
			and a.percent_achievement between begin_threshold and end_threshold
			) begin_threshold,
			(
			select rt.end_threshold
			from result_threshold rt
			where rt.result_threshold_group_id = c.result_threshold_group_id
			and a.percent_achievement between begin_threshold and end_threshold
			) end_threshold

			from appraisal_item_result a
			left outer join appraisal_item b
			on a.item_id = b.item_id
			left outer join appraisal_structure s
			on b.structure_id = s.structure_id
			left outer join form_type f
			on s.form_id = f.form_id
			left outer join emp_result c
			on a.emp_result_id = c.emp_result_id
			left outer join org d
			on c.org_id = d.org_id
			left outer join employee e
			on c.emp_id = e.emp_id
			where c.appraisal_type_id = ?
			and c.period_id = ?
			and b.perspective_id is not null
			and f.form_name = 'Quantity'
		";
		
		$qinput[] = $request->appraisal_type_id;
		$qinput[] = $request->period_id;
		
		empty($request->perspective_id) ?: ($query .= " and b.perspective_id = ? " AND $qinput[] = $request->perspective_id);
		
		if ($request->appraisal_type_id == 2) {
			empty($request->emp_id) ?: ($query .= " and c.emp_id = ? " AND $qinput[] = $request->emp_id);
		} else {
			empty($request->org_id) ?: ($query .= " and c.org_id = ? " AND $qinput[] = $request->org_id);
		}
		
		$qfooter = " order by begin_threshold is null asc,begin_threshold asc ";

		//echo $query.$qfooter;
		//print_r($qinput);

		$items = DB::select($query.$qfooter,$qinput);
		
		$dataset = [];
		$set = [];
		$color = '';
		$data = [];
		$groups = array();
		foreach ($items as $i) {
			if ($i->color_code == null) {
				$minmax = DB::select("
					select min(begin_threshold) min_threshold, max(end_threshold) max_threshold
					from result_threshold
					where result_threshold_group_id = ?		
				",array($i->result_threshold_group_id));
				
				if (empty($minmax)) {
					$key = 0;
					$begin_threshold=0;
					$end_threshold=0;
				} else {
					if ($i->achievement < $minmax[0]->min_threshold) {
						$get_color = DB::select("
							select color_code,begin_threshold,end_threshold
							from result_threshold
							where result_threshold_group_id = ?
							and begin_threshold = ?
						", array($i->result_threshold_group_id, $minmax[0]->min_threshold));
						$key = $get_color[0]->color_code;
						$begin_threshold=$get_color[0]->begin_threshold;
						$end_threshold=$get_color[0]->end_threshold;

					} elseif ($i->achievement > $minmax[0]->max_threshold) {
						$get_color = DB::select("
							select color_code,begin_threshold,end_threshold
							from result_threshold
							where result_threshold_group_id = ?
							and end_threshold = ?
						", array($i->result_threshold_group_id, $minmax[0]->max_threshold));
						$key = $get_color[0]->color_code;	
						$begin_threshold=$get_color[0]->begin_threshold;
						$end_threshold=$get_color[0]->end_threshold;

					} else {
						$key = 0;
					}				
				}
				
			} else {
				$key = $i->color_code;
				$begin_threshold = $i->begin_threshold;
				$end_threshold = $i->end_threshold;
			}
			
			if ($i->weight_percent > $max_y) {
				$max_y = $i->weight_percent;
			}
			
			if ($i->urgency > $max_x) {
				$max_x = $i->urgency;
			}			
			
			if (!isset($groups[$key])) {
				$groups[$key] = array(
					'items' => array('color' => $key, 'seriesName'=>$begin_threshold.'-'.$end_threshold,'bubbleHoverColor' => $key, 'data' => array(['x' => $i->urgency, 'y' => $i->weight_percent, 'z' => $i->achievement, 'name' => $i->item_name, 'item_id' => $i->item_id,
					"tooltext" => "<div id='nameDiv'>" .$i->item_name. "</div>{br}ทำได้ : <b>" . $i->achievement . "%</b>{br}ห่างเป้า : <b>" . $i->urgency . "%</b>{br}ความสำคัญ : <b>" . $i->axis_value_name ."</b>{br}As of: <b>" . $i->etl_dttm . "<b>"]))
				);
			} else {
				$groups[$key]['items']['data'][] = ['x' => $i->urgency, 'y' => $i->weight_percent, 'z' => $i->achievement, 'name' => $i->item_name, 'item_id' => $i->item_id,
				"tooltext" => "<div id='nameDiv'>" .$i->item_name. "</div>{br}ทำได้ : <b>" . $i->achievement . "%</b>{br}ห่างเป้า : <b>" . $i->urgency . "%</b>{br}ความสำคัญ : <b>" . $i->axis_value_name ."</b>{br}As of : <b>" . $i->etl_dttm . "<b>"];
			}

		}
		
		foreach ($groups as $g) {
			$dataset[] = $g['items'];
		}
		
		$x_axis = DB::select("
			SELECT axis_type_name, axis_value_name, axis_value
			FROM axis_type a
			left outer join axis_mapping b
			on a.axis_type_id = b.axis_type_id
			where a.graph_id = 1
			and a.axis_type = 1
			order by axis_value asc		
		");
		
		$y_axis = DB::select("
			SELECT axis_type_name, axis_value_name, axis_value
			FROM axis_type a
			left outer join axis_mapping b
			on a.axis_type_id = b.axis_type_id
			where a.graph_id = 1
			and a.axis_type = 2
			order by axis_value asc		
		");		
		
		$category = array();
		
		foreach ($x_axis as $x) {
			$category[] = ['label' => $x->axis_value_name, 'x' => $x->axis_value, 'showverticalline' => 1];
		}
		
		$cat = ['category' => $category];
		
		$line = array();
		
		foreach ($y_axis as $y) {
			$line[] = ['startValue' => $y->axis_value, 'displayvalue' => $y->axis_value_name, 'dashed' => 1, 'dashLen' => 1, 'dashGap' => 1];
		}
		
		$trendlines = [
			'line' => $line
		];
		

		$perspective = Perspective::find($request->perspective_id);
		
		if ($request->appraisal_type_id == 2) {
			$emp = Employee::where('emp_id',$request->emp_id)->first();
			$name = $emp->emp_name;
		} else {
			$org = Org::find($request->org_id);
			$name = $org->org_name;
		}
		
		empty($perspective) ? $header =  $name . ' Performance by KPI' : $header = $perspective->perspective_name . ' Performance by KPI';
		return response()->json(['dataset' => $dataset, 'header' => $header, 'max_x' => $max_x, 'max_y' => $max_y, 'categories' => array($cat), 'trendlines' => array($trendlines)]);	
	}
	
	public function kpi_overall(Request $request)
	{
		//$begin_threshold;
		//$end_threshold;
		$apitem = AppraisalItem::find($request->item_id);
		$perspective = Perspective::find($apitem->perspective_id);
		$uom = UOM::find($apitem->uom_id);
		$header = $perspective->perspective_name . ' - ' . $apitem->item_name . ' (หน่วย: ' . $uom->uom_name . ')';
		
		
		
		if ($request->appraisal_type_id == 2) {
			$emp = Employee::where('emp_id',$request->emp_id)->first();
			$qinput = [];
			$query = "
				select c.org_id, a.level_id, e.emp_name name, a.item_id, b.item_name, ifnull(a.target_value,0) target_value, ifnull(a.actual_value,0) actual_value, a.etl_dttm, c.result_threshold_group_id,
				a.percent_achievement pct,
				#round(if(ifnull(a.target_value,0) = 0,0,ifnull(a.actual_value,0) / a.target_value) * 100,2) pct,
				(
				select rt.color_code
				from result_threshold rt
				where rt.result_threshold_group_id = c.result_threshold_group_id
				and a.percent_achievement between begin_threshold and end_threshold
				) color_code,

				(
			select rt.begin_threshold
			from result_threshold rt
			where rt.result_threshold_group_id = c.result_threshold_group_id
			and a.percent_achievement between begin_threshold and end_threshold
			) begin_threshold,
			(
			select rt.end_threshold
			from result_threshold rt
			where rt.result_threshold_group_id = c.result_threshold_group_id
			and a.percent_achievement between begin_threshold and end_threshold
			) end_threshold

				from appraisal_item_result a
				left outer join appraisal_item b
				on a.item_id = b.item_id
				left outer join emp_result c
				on a.emp_result_id = c.emp_result_id
				left outer join org d
				on c.org_id = d.org_id
				left outer join employee e
				on c.emp_id = e.emp_id
				where a.item_id = ?
				and c.appraisal_type_id = ?
				and (e.emp_code = ? or e.chief_emp_code = ?)
				and c.period_id = ?
			";
			
			$qinput[] = $request->item_id;
			$qinput[] = $request->appraisal_type_id;
			$qinput[] = $emp->emp_code;
			$qinput[] = $emp->emp_code;
			$qinput[] = $request->period_id;
			
			//empty($request->emp_id) ?: ($query .= " and c.emp_id = ? " AND $qinput[] = $request->emp_id);
			//empty($request->position_id) ?: ($query .= " and c.position_id = ? " AND $qinput[] = $request->position_id);		
		} else {
			$org = Org::find($request->org_id);
			
			$qinput = [];
			$query = "
				select c.org_id, a.level_id, d.org_abbr name, a.item_id, b.item_name, ifnull(a.target_value,0) target_value, ifnull(a.actual_value,0) actual_value, etl_dttm, c.result_threshold_group_id,
				a.percent_achievement pct,
				#round(if(ifnull(a.target_value,0) = 0,0,ifnull(a.actual_value,0) / a.target_value) * 100,2) pct,
				(
				select rt.color_code
				from result_threshold rt
				where rt.result_threshold_group_id = c.result_threshold_group_id
				and a.percent_achievement between begin_threshold and end_threshold
				) color_code,

				(
			select rt.begin_threshold
			from result_threshold rt
			where rt.result_threshold_group_id = c.result_threshold_group_id
			and a.percent_achievement between begin_threshold and end_threshold
			) begin_threshold,
			(
			select rt.end_threshold
			from result_threshold rt
			where rt.result_threshold_group_id = c.result_threshold_group_id
			and a.percent_achievement between begin_threshold and end_threshold
			) end_threshold

				from appraisal_item_result a
				left outer join appraisal_item b
				on a.item_id = b.item_id
				left outer join emp_result c
				on a.emp_result_id = c.emp_result_id
				left outer join org d
				on c.org_id = d.org_id
				left outer join employee e
				on c.emp_id = e.emp_id
				where a.item_id = ?
				and c.appraisal_type_id = ?
				and (d.org_code = ? or d.parent_org_code = ?)
				and c.period_id = ?
			";
			$qinput[] = $request->item_id;
			$qinput[] = $request->appraisal_type_id;
			$qinput[] = $org->org_code;
			$qinput[] = $org->org_code;
			$qinput[] = $request->period_id;
			
			
		}

		$qfooter = " order by begin_threshold is null asc,begin_threshold asc ";		
		$items = DB::select($query.$qfooter,$qinput);
		
		$dataset = [];
		$set = [];
		$color = '';
		$data = [];
		$groups = array();
		$max_y = 0;
		$max_x = 0;
		foreach ($items as $i) {
			if ($i->actual_value > $max_y) {
				$max_y = $i->actual_value;
			}
			
			if ($i->target_value > $max_x) {
				$max_x = $i->target_value;
			}			
			
			if ($i->color_code == null) {
				$minmax = DB::select("
					select min(begin_threshold) min_threshold, max(end_threshold) max_threshold
					from result_threshold
					where result_threshold_group_id = ?		
				",array($i->result_threshold_group_id));
				
				if (empty($minmax)) {
					$key = 0;
					$begin_threshold=0;
					$end_threshold=0;
				} else {
					if ($i->pct < $minmax[0]->min_threshold) {
						$get_color = DB::select("
							select color_code,begin_threshold,end_threshold
							from result_threshold
							where result_threshold_group_id = ?
							and begin_threshold = ?
						", array($i->result_threshold_group_id, $minmax[0]->min_threshold));
						$key = $get_color[0]->color_code;
						$begin_threshold=$get_color[0]->begin_threshold;
						$end_threshold=$get_color[0]->end_threshold;

					} elseif ($i->pct > $minmax[0]->max_threshold) {
						$get_color = DB::select("
							select color_code,begin_threshold,end_threshold
							from result_threshold
							where result_threshold_group_id = ?
							and end_threshold = ?
						", array($i->result_threshold_group_id, $minmax[0]->max_threshold));
						$key = $get_color[0]->color_code;
						$begin_threshold=$get_color[0]->begin_threshold;
						$end_threshold=$get_color[0]->end_threshold;

					} else {
						$key = 0;
					}				
				}
				
			} else {
				$key = $i->color_code;
				$begin_threshold=$i->begin_threshold;
				$end_threshold=$i->end_threshold;
			}
			
			if (!isset($groups[$key])) {
				$groups[$key] = array(
					'items' => array('color' => $key,'seriesName'=>$begin_threshold.'-'.$end_threshold, 'bubbleHoverColor' => $key,'data' => array(['x' => $i->target_value, 'y' => $i->actual_value, 'z' => $i->pct, 'name' => $i->name, 'org_id' => $i->org_id, 'level_id' => $i->level_id,
					"tooltext" => "<div id='nameDiv'>" .$i->name. "</div>{br}Achievement : <b>" . $i->pct . "%</b>{br}Target : <b>" . $i->target_value . "</b>{br}Actual : <b>" . $i->actual_value ."</b>{br}As of: <b>" . $i->etl_dttm . "<b>"]))
				);
			} else {
				$groups[$key]['items']['data'][] = ['x' => $i->target_value, 'y' => $i->actual_value, 'z' => $i->pct, 'name' => $i->name, 'org_id' => $i->org_id, 'level_id' => $i->level_id,
				"tooltext" => "<div id='nameDiv'>" .$i->name. "</div>{br}Achievement : <b>" . $i->pct . "%</b>{br}Target : <b>" . $i->target_value . "</b>{br}Actual : <b>" . $i->actual_value ."</b>{br}As of: <b>" . $i->etl_dttm . "<b>"];
			}

		}
		

		foreach ($groups as $g) {
			$dataset[] = $g['items'];
		}
		

		
		return response()->json(['dataset' => $dataset, 'header' => $header, 'max_x' => $max_x, 'max_y' => $max_y]);
	}
	
	public function gantt(Request $request)
	{
		$categories = array();
		$items = DB::select("
			select action_plan_id, action_plan_name, plan_start_date, plan_end_date, actual_start_date, actual_end_date, completed_percent,
			datediff(actual_end_date, plan_end_date) delay, b.emp_name
			from action_plan a left outer join employee b
			on a.emp_id = b.emp_id
			where item_result_id = ?
			order by plan_start_date asc		
		", array($request->item_result_id));
		
		$date = DB::select("
			select date_format(min(plan_start_date),'%Y-%m-01') start_date, last_day(max(actual_end_date)) end_date
			from action_plan
			where item_result_id = ?
			order by plan_start_date asc	
		", array($request->item_result_id));
		
		$header = DB::select("
			select b.org_name, c.item_name, d.appraisal_period_desc, e.emp_name
			from appraisal_item_result a
			left outer join org b
			on a.org_id = b.org_id
			left outer join appraisal_item c
			on a.item_id = c.item_id
			left outer join appraisal_period d
			on a.period_id = d.period_id
			left outer join employee e
			on a.emp_id = e.emp_id
			where a.item_result_id = ?
		", array($request->item_result_id));
		
		$begin = new DateTime($date[0]->start_date);
		$end = new DateTime($date[0]->end_date);

		$interval = DateInterval::createFromDateString('1 month');
		$week_interval = DateInterval::createFromDateString('1 week');
		$period = new DatePeriod($begin, $interval, $end);
		$weekly = new DatePeriod($begin, $week_interval, $end);		
		
		$result = array();
		$month_list = array();
		$week_list = array();
		
		$categories[] = [
			 "bgcolor" => "#999999",
			 "category" => array([
				"start" => $date[0]->start_date,
				"end" => $date[0]->end_date,
				"label" => "Months",
				"align" => "middle",
				"fontcolor" => "#ffffff",
				"fontsize" => "12"				
			 ])
		];
		
		foreach ($period as $p) {
			$month_list[] = ['start' => $p->format("Y-m-01"), 'end' => $p->format("Y-m-t"), 'label' => $p->format("M")];
		}
		
		$week_counter = 1;
		
		foreach ($weekly as $w) {
			$week_list[] = ['start' => $w->format("Y-m-d"), 'end' => $w->modify("+6 day")->format("Y-m-d"), 'label' => "W ".$week_counter];
			$week_counter++;
		}		
		
		$categories[] = [
			"bgcolor" => "#999999",
			"align" => "middle",
            "fontcolor" => "#ffffff",
            "fontsize" => "12",			 
			"category" => $month_list
		];
		
		$categories[] = [
            "bgcolor" => "#ffffff",
            "fontcolor" => "#333333",
            "fontsize" => "11",
            "align" => "center",		 
			"category" => $week_list
		];		
		
		$process = array();
		$actual_start_date = array();
		$actual_end_date = array();
		$completed_percent = array();
		$responsible = array();
		$task = array();
		
		foreach ($items as $i) {
			$process[] = ['label' => $i->action_plan_name, 'id' => (string)$i->action_plan_id];
			$actual_start_date[] = ['label' => $i->actual_start_date];
			$actual_end_date[] = ['label' => $i->actual_end_date];
			$responsible[] = ['label' => $i->emp_name];
			$completed_percent[] = ['label' => $i->completed_percent];
			$task[] = [
                "label" => "Planned",
                "processid" => (string)$i->action_plan_id,
                "start" => $i->plan_start_date,
                "end" => $i->plan_end_date,
                "id" => $i->action_plan_id."-1",
                "color" => "#008ee4",
                "height" => "32%",
                "toppadding" => "12%"			
			];
			$task[] = [
                "label" => "Actual",
                "processid" => (string)$i->action_plan_id,
                "start" => $i->actual_start_date,
                "end" => $i->actual_end_date,
                "id" => (string)$i->action_plan_id,
                "color" => "#6baa01",
                "toppadding" => "56%",
                "height" => "32%"		
			];
			if ($i->delay > 0) {
				$task[] = [			
					"label" => "Delay",
					"processid" => (string)$i->action_plan_id,
					"start" => $i->plan_end_date,
					"end" => $i->actual_end_date,
					"id" => $i->action_plan_id."-2",
					"color" => "#e44a00",
					"toppadding" => "56%",
					"height" => "32%",
					"tooltext" => "Delayed by ".$i->delay." days."			
				];
			}
		}
		$tasks = ['task' => $task];
		$processes = [
			"headertext" => "Task",
			"fontcolor" => "#000000",
			"fontsize" => "11",
			"isanimated" => "1",
			"bgcolor" => "#6baa01",
			"headervalign" => "bottom",
			"headeralign" => "left",
			"headerbgcolor" => "#999999",
			"headerfontcolor" => "#ffffff",
			"headerfontsize" => "12",
			"align" => "left",
			"isbold" => "1",
			"bgalpha" => "25",		
			'process' => $process
		];
		
		$datacolumn = array();
		/*
		$datacolumn[] = [
			"headertext" => "Actual{br}Start{br}Date",
			"text" => $actual_start_date
		];
		
		$datacolumn[] = [
			"headertext" => "Actual{br}End{br}Date",
			"text" => $actual_end_date
		];
		*/
		$datacolumn[] = [
			"headertext" => "Responsible",
			"text" => $responsible
		];					

		$datacolumn[] = [
			"headertext" => "%Complete",
			"text" => $completed_percent
		];			
		
		$datatable = [
			"showprocessname" => "1",
			"namealign" => "left",
			"fontcolor" => "#000000",
			"fontsize" => "10",
			"valign" => "right",
			"align" => "center",
			"headervalign" => "bottom",
			"headeralign" => "center",
			"headerbgcolor" => "#999999",
			"headerfontcolor" => "#ffffff",
			"headerfontsize" => "12",		
			'datacolumn' => $datacolumn
		];
		
		empty($header) ? $header = [] : $header = $header[0];
		
		return response()->json(['header' => $header,'categories' => $categories, 'processes' => $processes, 'datatable' => $datatable, 'tasks' => $tasks]);
		
	}
	
	public function performance_trend(Request $request) 
	{
		$period = AppraisalPeriod::find($request->period_id);
		$frequency = AppraisalFrequency::find($period->appraisal_frequency_id);
		$result = array();
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}	

		

		$counter = 1;
		if ($frequency->frequency_month_value > 1) {

			$org = Org::find($request->org_id);
			
			if ($request->appraisal_type_id == 2) {
				$emp = Employee::where('emp_id',$request->emp_id)->first();
				$org_list = DB::select("
					SELECT a.org_id, a.emp_id, g.emp_name org_name, e.item_name, f.perspective_name, u.uom_name, c.result_threshold_group_id, b.item_result_id, b.threshold_group_id, e.is_show_variance, max(a.etl_dttm) etl_dttm
					FROM monthly_appraisal_item_result a
					left outer join appraisal_item_result b
					on a.item_id = b.item_id
					and a.emp_result_id = b.emp_result_id
					left outer join emp_result c
					on a.emp_result_id = c.emp_result_id
					left outer join org d
					on a.org_id = d.org_id
					left outer join appraisal_item e
					on a.item_id = e.item_id
					left outer join perspective f
					on e.perspective_id = f.perspective_id
					left outer join employee g
					on b.emp_id = g.emp_id
					left outer join uom u
					on e.uom_id = u.uom_id
					where a.item_id = ?
					and c.appraisal_type_id = ?
					and (g.emp_code = ? or g.chief_emp_code = ?)
					and c.period_id = ?
					group by a.org_id, a.emp_id, g.emp_name ,org_name, e.item_name, f.perspective_name, u.uom_name, c.result_threshold_group_id, b.item_result_id, e.is_show_variance
					order by a.org_id asc, a.appraisal_month_no asc			
				", array($request->item_id,$request->appraisal_type_id,$emp->emp_code, $emp->emp_code, $request->period_id));
			} else {
				$org_list = DB::select("
					SELECT a.org_id, a.emp_id, d.org_name, e.item_name, f.perspective_name, u.uom_name, c.result_threshold_group_id, b.item_result_id, b.threshold_group_id, e.is_show_variance, max(a.etl_dttm) etl_dttm
					FROM monthly_appraisal_item_result a
					left outer join appraisal_item_result b
					on a.item_id = b.item_id
					and a.emp_result_id = b.emp_result_id
					left outer join emp_result c
					on a.emp_result_id = c.emp_result_id
					left outer join org d
					on a.org_id = d.org_id
					left outer join appraisal_item e
					on a.item_id = e.item_id
					left outer join perspective f
					on e.perspective_id = f.perspective_id
					left outer join uom u
					on e.uom_id = u.uom_id
					where a.item_id = ?
					and c.appraisal_type_id = ?
					and (d.org_code = ? or d.parent_org_code = ?)
					and c.period_id = ?
					group by a.org_id, a.emp_id, d.org_name, e.item_name, f.perspective_name, u.uom_name, c.result_threshold_group_id, b.item_result_id, e.is_show_variance
					order by a.org_id asc, a.appraisal_month_no asc			
				", array($request->item_id,$request->appraisal_type_id,$org->org_code, $org->org_code, $request->period_id));			
			}
			
			foreach ($org_list as $o) {
				$qinput = [];
				
				if ($request->appraisal_type_id == 2) {
					$query = "
						SELECT a.org_id, e.emp_name org_name, a.appraisal_month_name, a.appraisal_month_no, a.target_value monthly_target, b.target_value yearly_target, b.forecast_value, b.actual_value,b.percent_achievement, a.actual_value sum_actual_value
						#(
						# select sum(actual_value)
						# from monthly_appraisal_item_result
						# where period_id = a.period_id
						# and item_id = a.item_id
						# and emp_id = a.emp_id
						# and appraisal_month_no <= a.appraisal_month_no
						#) sum_actual_value
						FROM monthly_appraisal_item_result a
						left outer join appraisal_item_result b
						on a.item_id = b.item_id
						and a.emp_result_id = b.emp_result_id
						left outer join emp_result c
						on a.emp_result_id = c.emp_result_id
						left outer join org d
						on a.org_id = d.org_id
						left outer join employee e
						on b.emp_id = e.emp_id
						where a.item_id = ?
						and c.appraisal_type_id = ?
						and b.emp_id = ?
						and c.period_id = ?
					";
					$qinput[] = $request->item_id;
					$qinput[] = $request->appraisal_type_id;
					$qinput[] = $o->emp_id;
					$qinput[] = $request->period_id;	
					
				} else {
					$query = "
						SELECT a.org_id, d.org_name, a.appraisal_month_name, a.appraisal_month_no, a.target_value monthly_target, b.target_value yearly_target, b.forecast_value, b.actual_value,b.percent_achievement, a.actual_value sum_actual_value
						#(
						# select sum(actual_value)
						# from monthly_appraisal_item_result
						# where period_id = a.period_id
						# and item_id = a.item_id
						# and org_id = a.org_id
						# and appraisal_month_no <= a.appraisal_month_no
						#) sum_actual_value
						FROM monthly_appraisal_item_result a
						left outer join appraisal_item_result b
						on a.item_id = b.item_id
						and a.emp_result_id = b.emp_result_id
						left outer join emp_result c
						on a.emp_result_id = c.emp_result_id
						left outer join org d
						on a.org_id = d.org_id
						where a.item_id = ?
						and c.appraisal_type_id = ?
						and a.org_id = ?
						and c.period_id = ?
					";
					$qinput[] = $request->item_id;
					$qinput[] = $request->appraisal_type_id;
					$qinput[] = $o->org_id;
					$qinput[] = $request->period_id;
				
				}
			
				$qfooter = " order by a.org_id asc, a.appraisal_month_no asc ";

				$items = DB::select($query.$qfooter,$qinput);	
				

				//check threshold start
				/*
				if($config->threshold==1){
				
				$color = DB::select("
					select target_score as min_val, target_score as max_val, color_code as color 
					from threshold
					where structure_id=1
					and threshold_group_id=?
					order by target_score asc				
				", array($o->threshold_group_id));

				}else{

				$color = DB::select("
					SELECT begin_threshold min_val, end_threshold max_val, color_code color
					FROM result_threshold
					where result_threshold_group_id = ?
					order by begin_threshold asc				
				", array($o->result_threshold_group_id));

				}
				*/
				//check threshold end
				//not check
				
				$color = DB::select("
					SELECT begin_threshold min_val, end_threshold max_val, color_code color
					FROM result_threshold
					where result_threshold_group_id = ?
					order by begin_threshold asc				
				", array($o->result_threshold_group_id));
				
				
				$o->dual_chart = [
					'data' => [
					"target" => $items[0]->yearly_target,
					"percent_achievement" => $items[0]->percent_achievement,
					"forecast" => $items[0]->forecast_value,
					"actual_value" => $items[0]->actual_value				
					],
					'color_range' => $color
				];
				$actual = array();
				$target = array();
				$forecast = array();
				$category = array();
				$variance = array();
				$growth = array();
				$max_value = 0;
				$current_month = 0;
				$previous_month = 0;
				$v_counter = 0;
				foreach ($items as $i) {
					$actual[] = ['value' => $i->sum_actual_value];
					$forecast[] = ['value' => $i->forecast_value];
					$category[] = ['label' => $i->appraisal_month_name];
					if ($i->sum_actual_value > $max_value || $i->yearly_target > $max_value) {
						if ($i->sum_actual_value > $i->yearly_target) {
							$max_value = $i->sum_actual_value;
						} else {
							$max_value = $i->yearly_target;
						}
					}
					if ($o->is_show_variance == 1) {
						$target[] = ['value' => $i->monthly_target];
						if ($v_counter == 0) {
							$variance[] = ['value' => 0];
							$previous_month = $i->sum_actual_value;
						} else {
							$current_month = $i->sum_actual_value;
							$variance[]= ['value' => $current_month - $previous_month];
							$previous_month = $i->sum_actual_value;
						}
						$v_counter++;
					} else {
						$target[] = ['value' => $i->yearly_target];
						if ($v_counter == 0) {
							$growth[]= ['value' => 0];
							$previous_month = $i->sum_actual_value;
						} else {
							$current_month = $i->sum_actual_value;
							if($previous_month!=0){
								$growthFormat=number_format((float)(($current_month - $previous_month)/$previous_month)*100, 2, '.', '');
								$growth[]= ['value' => $growthFormat];
							}else{
								$growth[]= ['value' => 0];
							}
							$previous_month = $i->sum_actual_value;
						}
						$v_counter++;
					}
				}
				$dataset = [
						[
							'seriesName' => 'Actual',
							'data' => $actual
						],
						// [
							// 'seriesName' => 'Target',
							// "renderAs" => "line",
							// "showValues" => "0",							
							// 'data' => $target	
						// ],
						// [
							// 'seriesName' => 'Forecast',
							// "renderAs" => "line",
							// "showValues" => "0",							
							// 'data' => $forecast						
						// ],
				];
				
				if (!empty($variance)) {
					$dataset[] = [
						"seriesName" => "Diff",
						"parentYAxis" => "S",
						"renderAs" => "line",
						"showValues" => "0",
						"data" => $variance					
					];
					
				}else if(!empty($growth)){
					$dataset[] = [
						"seriesName" => "Growth",
						"parentYAxis" => "S",
						"renderAs" => "line",
						"showValues" => "0",
						"data" => $growth					
					];

				}
				
				if ($o->is_show_variance == 0) {
					empty($items) ? $trendlines_target = 0 : $trendlines_target = $items[0]->monthly_target;
				} else {
					empty($items) ? $trendlines_target = 0 : $trendlines_target = $items[0]->yearly_target;
				}
				
				//is_show_variance == 1 ไม่ต้องเช็ค
				 // empty($items) ? $trendlines_target = 0 : $trendlines_target = $items[0]->yearly_target;			

				
				$o->bar_chart = [
					// 'data' => [
						// 'actual' => $actual,
						// 'target' => $items[0]->yearly_target,
						// 'forecast' => $items[0]->forecast_value
					// ],
					'max_value' => $max_value,
					
					'categories' => [
						[
							"category" => $category
						]
					],
					
					'dataset' => $dataset,
					
					'trendlines' => [
						[
							"line" => [
								[
									"startvalue" => $trendlines_target,
									"color" => "#1aaf5d",
									"valueOnRight" => "1",
									"displayvalue" => "Target{br}".$trendlines_target."",
									 "thickness"=> "3"				
								],
								[
									 "startvalue" => $items[0]->forecast_value,
									 "color" => "#DC143C",
									 "valueOnRight" => "1",
									 "displayvalue" => "Forecast{br}".$items[0]->forecast_value."",
									  "thickness"=> "3"							
								]
							]
						]
					]						
					
					
					
				];
				
				$action_plans = DB::select("
					select distinct date_format(created_dttm,'%b') month_name, date_format(created_dttm,'%c') - 1 month_no
					from action_plan
					where item_result_id = ?
					order by created_dttm asc
				", array($o->item_result_id));
				
				$action_groups = array();
				foreach ($action_plans as $a) {
					$action_item = [
						[
						   "id" => $o->item_result_id.'-'.$a->month_name."-Base",
						   "type" => "rectangle",
						   "radius" => "2",
						   "alpha" => "90",
						   "fillColor" => "#7FC31C",
						   "link" => "javascript:void(0)",
						   "x" => '$dataset.0.set.'.$a->month_no.'.x-15',
						   "y" => '$dataset.0.set.'.$a->month_no.'.starty-15',
						   "tox" => '$dataset.0.set.'.$a->month_no.'.x+15',
						   "toy" => '$dataset.0.set.'.$a->month_no.'.starty-30'
						],
						[
						   "id" => $o->item_result_id.'-'.$a->month_name."Triangle",
						   "type" => "polygon",
						   "sides" => "3",
						   "startangle" => "270",
						   "alpha" => "90",
						   "fillColor" => "#7FC31C",
						   "link" => "javascript:void(0)",
						   "x" => '$dataset.0.set.'.$a->month_no.'.x',
						   "y" => '$dataset.0.set.'.$a->month_no.'.starty-18',
						   "radius" => "11",
						],		
						[
						   "id" => $o->item_result_id.'-'.$a->month_name."-Label",
						   "type" => "Text",
						   "fontSize" => "10",
						   "link" => "javascript:void(0)",
						   "bold" => "1",
						   "fillcolor" => "#ffffff",
						   "text" => "SIP",
						   "x" => '$dataset.0.set.'.$a->month_no.'.x-',
						   "y" => '$dataset.0.set.'.$a->month_no.'.starty - 23'
						]
					];
					
					$action_groups[] = [
						'id' => $o->item_result_id.'-'.$a->month_name,
						'items' => $action_item
					];	
				}		
				
				$o->annotations = [
					"drawImmediately" => "1",
                    "showbelow" => "1",
					"groups" => $action_groups
				];
				
				$o->chart_type = 'yearly';
				
				$result['group'.$counter] = $o;
				$counter++;
			}
		} else {
		
			if ($request->appraisal_type_id == 2) {
				$emp = Employee::where('emp_id',$request->emp_id)->first();
				$period = AppraisalPeriod::find($request->period_id);
				$org_list = DB::select("
					select e.org_id, d.emp_id, i.emp_name org_name, d.item_result_id, f.result_threshold_group_id, g.item_name, h.perspective_name,u.uom_name, max(a.etl_dttm) etl_dttm
					from monthly_appraisal_item_result a
					left outer join appraisal_period b
					on a.period_id = b.period_id
					left outer join appraisal_frequency c
					on b.appraisal_frequency_id = c.frequency_id
					left outer join appraisal_item_result d
					on a.period_id = d.period_id
					and a.item_id = d.item_id
					left outer join org e
					on a.org_id = e.org_id
					left outer join emp_result f
					on d.emp_result_id = f.emp_result_id
					left outer join appraisal_item g
					on a.item_id = g.item_id
					left outer join perspective h
					on g.perspective_id = h.perspective_id
					left outer join employee i
					on d.emp_id = i.emp_id
					left outer join uom u
					on g.uom_id = u.uom_id
					where c.frequency_month_value = 1
					and b.period_no <= ?
					and a.item_id = ?
					and b.appraisal_year = ?		
					and f.appraisal_type_id = ?
					and (i.emp_code = ? or i.chief_emp_code = ?)
					group by e.org_id, d.emp_id, i.emp_name, d.item_result_id, f.result_threshold_group_id, g.item_name, h.perspective_name,u.uom_name
				",array($period->period_no, $request->item_id, $request->year_id, $request->appraisal_type_id, $emp->emp_code, $emp->emp_code));
			
			} else {

				$org = Org::find($request->org_id);
				$period = AppraisalPeriod::find($request->period_id);
				$org_list = DB::select("
					select e.org_id, d.emp_id, e.org_name, d.item_result_id, f.result_threshold_group_id, g.item_name, h.perspective_name,u.uom_name, max(a.etl_dttm) etl_dttm
					from monthly_appraisal_item_result a
					left outer join appraisal_period b
					on a.period_id = b.period_id
					left outer join appraisal_frequency c
					on b.appraisal_frequency_id = c.frequency_id
					left outer join appraisal_item_result d
					on a.period_id = d.period_id
					and a.item_id = d.item_id
					left outer join org e
					on a.org_id = e.org_id
					left outer join emp_result f
					on d.emp_result_id = f.emp_result_id
					left outer join appraisal_item g
					on a.item_id = g.item_id
					left outer join perspective h
					on g.perspective_id = h.perspective_id
					left outer join uom u
					on g.uom_id = u.uom_id
					where c.frequency_month_value = 1
					and b.period_no <= ?
					and a.item_id = ?
					and b.appraisal_year = ?		
					and f.appraisal_type_id = ?
					and (e.org_code = ? or e.parent_org_code = ?)
					group by e.org_id, d.emp_id, e.org_name, d.item_result_id, f.result_threshold_group_id, g.item_name, h.perspective_name,u.uom_name
				",array($period->period_no, $request->item_id, $request->year_id, $request->appraisal_type_id, $org->org_code, $org->org_code));
			}
			
			foreach ($org_list as $o) {
				$qinput = [];
				
				if ($request->appraisal_type_id == 2) {
					$query = "
						select a.emp_id, a.org_id, a.item_id, a.appraisal_month_no, a.appraisal_month_name, a.actual_value, d.forecast_value ,a.target_value, a.period_id, d.item_result_id
						from monthly_appraisal_item_result a
						left outer join appraisal_period b
						on a.period_id = b.period_id
						left outer join appraisal_frequency c
						on b.appraisal_frequency_id = c.frequency_id
						left outer join appraisal_item_result d
						on a.period_id = d.period_id
						and a.item_id = d.item_id
						left outer join emp_result e
						on d.emp_result_id = e.emp_result_id
						where c.frequency_month_value = 1
						and b.period_no <= ?
						and a.item_id = ?
						and b.appraisal_year = ?		
						and e.appraisal_type_id = ?
						and a.emp_id = ?
					";
					$qinput[] = $period->period_no;
					$qinput[] = $request->item_id;
					$qinput[] = $request->year_id;
					$qinput[] = $request->appraisal_type_id;
					$qinput[] = $o->emp_id;				
					
				$dual_chart = DB::select("
					select a.target_value, a.actual_value, a.forecast_value
					from appraisal_item_result a
					left outer join emp_result b
					on a.emp_result_id = b.emp_result_id
					where a.emp_id = ?
					and b.appraisal_type_id = ?
					and a.period_id = ?
					and a.item_id = ?
				", array($request->emp_id, $request->appraisal_type_id, $request->period_id, $request->item_id));
				
				} else {
			
					$query = "
						select a.org_id, a.item_id, a.appraisal_month_no, a.appraisal_month_name, a.actual_value, d.forecast_value ,a.target_value, a.period_id, d.item_result_id
						from monthly_appraisal_item_result a
						left outer join appraisal_period b
						on a.period_id = b.period_id
						left outer join appraisal_frequency c
						on b.appraisal_frequency_id = c.frequency_id
						left outer join appraisal_item_result d
						on a.period_id = d.period_id
						and a.item_id = d.item_id
						left outer join emp_result e
						on d.emp_result_id = e.emp_result_id
						where c.frequency_month_value = 1
						and b.period_no <= ?
						and a.item_id = ?
						and b.appraisal_year = ?		
						and e.appraisal_type_id = ?
						and a.org_id = ?
					";
					$qinput[] = $period->period_no;
					$qinput[] = $request->item_id;
					$qinput[] = $request->year_id;
					$qinput[] = $request->appraisal_type_id;
					$qinput[] = $o->org_id;
					
					$dual_chart = DB::select("
						select a.target_value, a.actual_value, a.forecast_value
						from appraisal_item_result a
						left outer join emp_result b
						on a.emp_result_id = b.emp_result_id
						where a.org_id = ?
						and b.appraisal_type_id = ?
						and a.period_id = ?
						and a.item_id = ?
					", array($request->org_id, $request->appraisal_type_id, $request->period_id, $request->item_id));					
				}
				

				
				$qfooter = " order by a.org_id asc, a.appraisal_month_no asc ";

				$items = DB::select($query.$qfooter,$qinput);		

				$color = DB::select("
					SELECT begin_threshold min_val, end_threshold max_val, color_code color
					FROM result_threshold
					where result_threshold_group_id = ?
					order by begin_threshold asc				
				", array($o->result_threshold_group_id));
				
				//print_r($o);

				//print_r($dual_chart);

		/*		
    	if(empty($dual_chart)){
    		$o->dual_chart = [
					'data' => [
					"target" => 0,
					"forecast" => 0,
					"actual_value" => null				
					],
					'color_range' => $color
				];			
				

    	}else{

    		$o->dual_chart = [
					'data' => [
					"target" => $dual_chart[0]->target_value,
					"forecast" => $dual_chart[0]->forecast_value,
					"actual_value" => $dual_chart[0]->actual_value				
					],
					'color_range' => $color
				];			
				

    	}
    	*/


				$o->dual_chart = [
					'data' => [
					"target" => $dual_chart[0]->target_value,
					"forecast" => $dual_chart[0]->forecast_value,
					"actual_value" => $dual_chart[0]->actual_value				
					],
					'color_range' => $color
				];		


				
				$actual_data = array();
				$forecast_data = array();
				$target_data = array();
				$category = array();
				$action_groups = array();
				
				$action_order = 0;
				
				foreach ($items as $i) {
					$actual_data[] = ['value' => $i->actual_value];
					$forecast_data[] = ['value' => $i->forecast_value];
					$target_data[] = ['value' => $i->target_value];
					$category[] = ['label' => $i->appraisal_month_name];
					
					$action_plans = DB::select("
						select 1
						from action_plan a
						left outer join appraisal_item_result b
						on a.item_result_id = b.item_result_id
						where b.item_result_id = ?
					", array($i->item_result_id));
					
					if (!empty($action_plans)) {
						$action_item = [
							[
							   "id" => $i->item_result_id.'-'.$i->appraisal_month_name."-Base",
							   "type" => "rectangle",
							   "radius" => "2",
							   "alpha" => "90",
							   "fillColor" => "#7FC31C",
							   "link" => "javascript:void(0)",
							   "x" => '$dataset.0.set.'.$action_order.'.x-15',
							   "y" => '$dataset.0.set.'.$action_order.'.starty-15',
							   "tox" => '$dataset.0.set.'.$action_order.'.x+15',
							   "toy" => '$dataset.0.set.'.$action_order.'.starty-30'
							],
							[
							   "id" => $i->item_result_id.'-'.$i->appraisal_month_name."-Triangle",
							   "type" => "polygon",
							   "sides" => "3",
							   "startangle" => "270",
							   "alpha" => "90",
							   "fillColor" => "#7FC31C",
							   "link" => "javascript:void(0)",
							   "x" => '$dataset.0.set.'.$action_order.'.x',
							   "y" => '$dataset.0.set.'.$action_order.'.starty-18',
							   "radius" => "11",
							],		
							[
							   "id" => $i->item_result_id.'-'.$i->appraisal_month_name."-Label",
							   "type" => "Text",
							   "fontSize" => "11",
							   "link" => "javascript:void(0)",
							   "bold" => "1",
							   "fillcolor" => "#ffffff",
							   "text" => "SIP",
							   "x" => '$dataset.0.set.'.$action_order.'.x-',
							   "y" => '$dataset.0.set.'.$action_order.'.starty - 23'
							]
						];
						
						$action_groups[] = [
							'id' => $i->item_result_id.'-'.$i->appraisal_month_name,
							'items' => $action_item
						];	
					}
					$action_order++;

				}
				
				$o->categories = [['category' => $category]];
				$o->dataset = [
					[
						"seriesName" => "Actual",
						"showValues" => "0",
						"data" => $actual_data				
					],
					[
						"seriesName" => "Forecast",
						"renderAs" => "line",
						"data" => $forecast_data			
					],
					[
						"seriesName" => "Target",
						"renderAs" => "area",
						"anchorRadius"=> "0",
						"anchorBorderThickness"=> "0",
						"alpha" => "30",
						"data" => $target_data		
					]
				];

				
				$o->annotations = [
					"drawImmediately" => "1",
                    "showbelow" => "1",
					"groups" => $action_groups
				];				
				
				$o->chart_type = 'monthly';
				$result['group'.$counter] = $o;
				$counter++;
			}
		}
		
		return response()->json($result);
		
	}
	
	public function branch_performance(Request $request)
	{
		$vector = array();
		$org_list = array();
		$longitude = 0;
		$latitude = 0;
		
		if (empty($request->region_code)) {
			if (!empty($request->district_code)) {
				$location = DB::select("
					select longitude, latitude		
					from org
					where org_code = ?
				", array($request->district_code));
				$longitude = $location[0]->longitude;
				$latitude = $location[0]->latitude;				
			}
		} else {
			$location = DB::select("
				select longitude, latitude		
				from org
				where org_code = ?
			", array($request->region_code));	
			$longitude = $location[0]->longitude;
			$latitude = $location[0]->latitude;			
		}
		
		if (empty($request->region_code)) {
			if (empty($request->item_id)) {
				$vector = DB::select("
					select province_code, avg(b.result_score) average
					#(select if(instr(x.color_code,'#') > 0,x.color_code,concat('#',x.color_code)) color_code
					#from result_threshold x
					#left outer join result_threshold_group y on x.result_threshold_group_id = y.result_threshold_group_id
					#where y.is_active = 1
					#and avg(b.result_score) between x.begin_threshold and x.end_threshold
					#) color_code
					from emp_result b
					left outer join org c
					on b.org_id = c.org_id
					where b.appraisal_type_id = 1	
					and b.period_id = ?
					and exists (
						select 1
						from org x
						left outer join appraisal_level y
						on x.level_id = y.level_id
						where y.district_flag = 1
						and x.org_code = c.parent_org_code
					)				
					group by province_code
				", array($request->period_id));
				
				foreach ($vector as $i) {
					$color = DB::select("
						select if(instr(x.color_code,'#') > 0,x.color_code,concat('#',x.color_code)) color_code
						from result_threshold x
						left outer join result_threshold_group y on x.result_threshold_group_id = y.result_threshold_group_id
						where y.is_active = 1
						and ? between x.begin_threshold and x.end_threshold
					", array($i->average));
					if (empty($color)) {
						$minmax = DB::select("
							select min(begin_threshold) min_threshold, max(end_threshold) max_threshold
							from result_threshold a left outer join result_threshold_group b
							on a.result_threshold_group_id = b.result_threshold_group_id
							where b.is_active = 1		
						");
						
						if (empty($minmax)) {
							$i->color_code = 0;
						} else {
							if ($i->average < $minmax[0]->min_threshold) {
								$get_color = DB::select("
									select if(instr(x.color_code,'#') > 0,x.color_code,concat('#',x.color_code)) color_code
									from result_threshold x left outer join result_threshold_group y
									on x.result_threshold_group_id = y.result_threshold_group_id
									where y.is_active = 1
									and x.begin_threshold = ?
								", array($minmax[0]->min_threshold));
								$i->color_code = $get_color[0]->color_code;
							} elseif ($i->average > $minmax[0]->max_threshold) {
								$get_color = DB::select("
									select if(instr(x.color_code,'#') > 0,x.color_code,concat('#',x.color_code)) color_code
									from result_threshold x left outer join result_threshold_group y
									on x.result_threshold_group_id = y.result_threshold_group_id
									where y.is_active = 1
									and x.end_threshold = ?
								", array($minmax[0]->max_threshold));
								$i->color_code = $get_color[0]->color_code;					
							} else {
								$i->color_code = 0;
							}				
						}
						
					} else {
						$i->color_code = $color[0]->color_code;
					}				
				}
			} else {
				$vector = DB::select("
					select province_code, avg(a.percent_achievement) average
					#(select if(instr(x.color_code,'#') > 0,x.color_code,concat('#',x.color_code)) color_code
					#from result_threshold x
					#left outer join result_threshold_group y on x.result_threshold_group_id = y.result_threshold_group_id
					#where y.is_active = 1
					#and avg(a.percent_achievement) between x.begin_threshold and x.end_threshold
					#) color_code
					from appraisal_item_result a
					left outer join emp_result b
					on a.emp_result_id = b.emp_result_id
					left outer join org c
					on a.org_id = c.org_id
					where b.appraisal_type_id = 1	
					and a.period_id = ?
					and a.item_id = ?
					and exists (
						select 1
						from org x
						left outer join appraisal_level y
						on x.level_id = y.level_id
						where y.district_flag = 1
						and x.org_code = c.parent_org_code
					)							
					group by province_code
				", array($request->period_id, $request->item_id));	

				foreach ($vector as $i) {
					$color = DB::select("
						select if(instr(x.color_code,'#') > 0,x.color_code,concat('#',x.color_code)) color_code
						from result_threshold x
						left outer join result_threshold_group y on x.result_threshold_group_id = y.result_threshold_group_id
						where y.is_active = 1
						and ? between x.begin_threshold and x.end_threshold
					", array($i->average));
					if (empty($color)) {
						$minmax = DB::select("
							select min(begin_threshold) min_threshold, max(end_threshold) max_threshold
							from result_threshold a left outer join result_threshold_group b
							on a.result_threshold_group_id = b.result_threshold_group_id
							where b.is_active = 1		
						");
						
						if (empty($minmax)) {
							$i->color_code = 0;
						} else {
							if ($i->average < $minmax[0]->min_threshold) {
								$get_color = DB::select("
									select if(instr(x.color_code,'#') > 0,x.color_code,concat('#',x.color_code)) color_code
									from result_threshold x left outer join result_threshold_group y
									on x.result_threshold_group_id = y.result_threshold_group_id
									where y.is_active = 1
									and x.begin_threshold = ?
								", array($minmax[0]->min_threshold));
								$i->color_code = $get_color[0]->color_code;
							} elseif ($i->average > $minmax[0]->max_threshold) {
								$get_color = DB::select("
									select if(instr(x.color_code,'#') > 0,x.color_code,concat('#',x.color_code)) color_code
									from result_threshold x left outer join result_threshold_group y
									on x.result_threshold_group_id = y.result_threshold_group_id
									where y.is_active = 1
									and x.end_threshold = ?
								", array($minmax[0]->max_threshold));
								$i->color_code = $get_color[0]->color_code;					
							} else {
								$i->color_code = 0;
							}				
						}
						
					} else {
						$i->color_code = $color[0]->color_code;
					}				
				}				
			}
		} else {
			$org_input = array();
			
			if (empty($request->item_id)) {
				$org_query = "
					select distinct b.org_id, c.org_name, c.longitude, c.latitude, b.result_threshold_group_id, b.result_score pct
					from emp_result b
					left outer join org c
					on b.org_id = c.org_id
					left outer join appraisal_level d
					on c.level_id = d.level_id
					inner join (
						select org_id, org_name, org_code
						from org x left outer join appraisal_level y
						on x.level_id = y.level_id
						where parent_org_code = ?
						and y.district_flag = 1
					) e on c.parent_org_code = e.org_code
					where appraisal_type_id = 1
					and b.period_id = ?
				";
			} else {
				$org_query = "
					select distinct a.org_id, c.org_name, c.longitude, c.latitude, b.result_threshold_group_id, a.percent_achievement pct
					from appraisal_item_result a
					left outer join emp_result b
					on a.emp_result_id = b.emp_result_id
					left outer join org c
					on a.org_id = c.org_id
					left outer join appraisal_level d
					on c.level_id = d.level_id
					inner join (
						select org_id, org_name, org_code
						from org x left outer join appraisal_level y
						on x.level_id = y.level_id
						where parent_org_code = ?
						and y.district_flag = 1
					) e on c.parent_org_code = e.org_code
					where appraisal_type_id = 1
					and a.period_id = ?
				";			
			}
			
			$org_input[] = $request->region_code;
			$org_input[] = $request->period_id;
			
			empty($request->item_id) ?: ($org_query .= " and a.item_id = ? " AND $org_input[] = $request->item_id);
			empty($request->district_code) ?: ($org_query .= " and c.parent_org_code = ? " AND $org_input[] = $request->district_code);	
			
			$org_list = DB::select($org_query, $org_input);
			
			foreach ($org_list as $o) {
				$qinput = array();
				$query = "
					SELECT air.item_result_id, p.perspective_id, p.perspective_name, air.item_id, air.item_name, u.uom_name, air.org_id, org.org_code, o.org_name, er.result_threshold_group_id, air.etl_dttm,
						air.target_value, air.forecast_value, ifnull(air.actual_value, 0) actual_value,
						#ifnull(if(air.target_value = 0, 0, (air.actual_value/air.target_value)*100), 0) percent_target,
						air.percent_achievement percent_target,
						ifnull(if(air.forecast_value = 0, 0, (air.actual_value/air.forecast_value)*100), 0) percent_forecast
					FROM appraisal_item_result air
					INNER JOIN appraisal_item ai ON ai.item_id = air.item_id
					INNER JOIN perspective p ON p.perspective_id = ai.perspective_id
					INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
					INNER JOIN emp_result er on air.emp_result_id = er.emp_result_id
					LEFT OUTER JOIN uom u ON u.uom_id = ai.uom_id
					LEFT OUTER JOIN org o ON o.org_id = air.org_id
					LEFT OUTER JOIN org ON org.org_id = air.org_id
					WHERE air.period_id = ?
					AND air.org_id = ? 
				";
				
				$qinput[] = $request->period_id;
				$qinput[] = $o->org_id;		
				
				$qfooter = " ORDER BY p.perspective_name, air.item_name, air.item_result_id, org.org_code ";
							

				
				empty($request->item_id) ?: ($query .= " AND air.item_id = ? " AND $qinput[] = $request->item_id);
				$items = DB::select($query.$qfooter,$qinput);
				
				$orgDetail = array();
				
				$branch_details = array();
				foreach ($items as $i) {
					$colorRanges = DB::select("
						select begin_threshold, end_threshold, if(instr(color_code,'#') > 0,color_code,concat('#',color_code)) color_code
						from result_threshold
						where result_threshold_group_id = ?
						order by end_threshold desc
					", array($i->result_threshold_group_id));

					$colors = array();
					$ranges = array();
					
					foreach ($colorRanges as $c) {
						$colors[] = $c->color_code;
						$ranges[] = $c->end_threshold;
					}
					
					$target_ranges = $ranges;
					$forecast_ranges = $ranges;
					
					
					if ($i->percent_target > $ranges[0]) {
						$target_ranges[0] = floor($i->percent_target) + 1;
					}
					
					if ($i->percent_forecast > $ranges[0]) {
						$forecast_ranges[0] = floor($i->percent_forecast) + 1;
					}
					
					$orgDetail = array(
						"perspective_name" => $i->perspective_name,
						"item_name" => $i->item_name,
						"uom_name" => $i->uom_name,
						"rangeColor" => $colors,
						"target"=> $i->target_value,
						"forecast" => $i->forecast_value,
						"actual" => $i->actual_value,
						"percent_target" => $i->percent_target,
						"percent_forecast" => $i->percent_forecast,
						"etl_dttm" => $i->etl_dttm,

						// For Spackline JS //
						"percent_target_str" => "100".",".$i->percent_target.",".implode($target_ranges, ","),
						"percent_forecast_str" => "100".",".$i->percent_forecast.",".implode($forecast_ranges, ",")
					);
					$branch_details[] = $orgDetail;
				}
				
				$branch_color = DB::select("
					select if(instr(color_code,'#') > 0,color_code,concat('#',color_code)) color_code
					from result_threshold
					where result_threshold_group_id = ?
					and ? between begin_threshold and end_threshold
				", array($o->result_threshold_group_id, $o->pct));
								
				//empty($branch_color) ? $color_code = '#9169FF' : $color_code = $branch_color[0]->color_code;
				
				if (empty($branch_color)) {
					$minmax = DB::select("
						select min(begin_threshold) min_threshold, max(end_threshold) max_threshold
						from result_threshold
						where result_threshold_group_id = ?		
					",array($o->result_threshold_group_id));
					
					if (empty($minmax)) {
						$color_code = '#9169FF';
					} else {
						if ($o->pct < $minmax[0]->min_threshold) {
							$get_color = DB::select("
								select if(instr(color_code,'#') > 0,color_code,concat('#',color_code)) color_code
								from result_threshold
								where result_threshold_group_id = ?
								and begin_threshold = ?
							", array($o->result_threshold_group_id, $minmax[0]->min_threshold));
							$color_code = $get_color[0]->color_code;
						} elseif ($o->pct > $minmax[0]->max_threshold) {
							$get_color = DB::select("
								select if(instr(color_code,'#') > 0,color_code,concat('#',color_code)) color_code
								from result_threshold
								where result_threshold_group_id = ?
								and end_threshold = ?
							", array($o->result_threshold_group_id, $minmax[0]->max_threshold));
							$color_code = $get_color[0]->color_code;					
						} else {
							$color_code = '#9169FF';
						}				
					}
					
				} else {
					$color_code = $branch_color[0]->color_code;
				}				
				
				$o->color_code = $color_code;	
				
				$o->branch_details = $branch_details;
				
			}		
			
		}
		
		
		return response()->json(['vector_map' => $vector, 'google_map' => $org_list, 'longitude' => $longitude, 'latitude' => $latitude]);
	}

	public function branch_details(Request $request)
	{
		if (empty($request->item_id)) {
			$org_list = DB::select("
				select distinct b.org_id, c.org_name, c.org_code, b.result_threshold_group_id, b.result_score pct
				from emp_result b
				left outer join org c
				on b.org_id = c.org_id
				left outer join appraisal_level d
				on c.level_id = d.level_id
				where b.appraisal_type_id = 1
				and c.province_code = ?
				and b.period_id = ?
				and exists (
					select 1
					from org x
					left outer join appraisal_level y
					on x.level_id = y.level_id
					where y.district_flag = 1
					and x.org_code = c.parent_org_code
				)
			", array($request->province_code, $request->period_id));
		} else {
			$org_list = DB::select("
				select distinct a.org_id, c.org_name, c.org_code, b.result_threshold_group_id, a.percent_achievement pct
				from appraisal_item_result a
				left outer join emp_result b
				on a.emp_result_id = b.emp_result_id
				left outer join org c
				on a.org_id = c.org_id
				left outer join appraisal_level d
				on c.level_id = d.level_id
				where appraisal_type_id = 1
				and c.province_code = ?
				and a.period_id = ?
				and a.item_id = ?
				and exists (
					select 1
					from org x
					left outer join appraisal_level y
					on x.level_id = y.level_id
					where y.district_flag = 1
					and x.org_code = c.parent_org_code
				)
			", array($request->province_code, $request->period_id, $request->item_id));
		
		}
		foreach ($org_list as $o) {
			$qinput = array();
			$query = "
				SELECT air.item_result_id, p.perspective_id, p.perspective_name, air.item_id, air.item_name, u.uom_name, air.org_id, org.org_code, o.org_name, er.result_threshold_group_id, air.etl_dttm,
					air.target_value, air.forecast_value, ifnull(air.actual_value, 0) actual_value,
					#ifnull(if(air.target_value = 0, 0, (air.actual_value/air.target_value)*100), 0) percent_target,
					air.percent_achievement percent_target,
					ifnull(if(air.forecast_value = 0, 0, (air.actual_value/air.forecast_value)*100), 0) percent_forecast
				FROM appraisal_item_result air
				INNER JOIN appraisal_item ai ON ai.item_id = air.item_id
				INNER JOIN perspective p ON p.perspective_id = ai.perspective_id
				INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
				INNER JOIN emp_result er on air.emp_result_id = er.emp_result_id
				LEFT OUTER JOIN uom u ON u.uom_id = ai.uom_id
				LEFT OUTER JOIN org o ON o.org_id = air.org_id
				LEFT OUTER JOIN org ON org.org_id = air.org_id
				WHERE air.period_id = ?
				AND o.org_id = ? 
			";
			
			$qinput[] = $request->period_id;
			$qinput[] = $o->org_id;		
			
			$qfooter = " ORDER BY p.perspective_name, air.item_name, air.item_result_id, org.org_code ";
						

			
			empty($request->item_id) ?: ($query .= " AND air.item_id = ? " AND $qinput[] = $request->item_id);
			$items = DB::select($query.$qfooter,$qinput);
			
			$orgDetail = array();
			
			$branch_details = array();
			foreach ($items as $i) {
				$colorRanges = DB::select("
					select begin_threshold, end_threshold, if(instr(color_code,'#') > 0,color_code,concat('#',color_code)) color_code
					from result_threshold
					where result_threshold_group_id = ?
					order by end_threshold desc
				", array($i->result_threshold_group_id));

				$colors = array();
				$ranges = array();
				
				foreach ($colorRanges as $c) {
					$colors[] = $c->color_code;
					$ranges[] = $c->end_threshold;
				}
				
				$orgDetail = array(
					"perspective_name" => $i->perspective_name,
					"item_name" => $i->item_name,
					"uom_name" => $i->uom_name,
					"rangeColor" => $colors,
					"target"=> $i->target_value,
					"forecast" => $i->forecast_value,
					"actual" => $i->actual_value,
					"percent_target" => $i->percent_target,
					"percent_forecast" => $i->percent_forecast,
					"etl_dttm" => $i->etl_dttm,

					// For Spackline JS //
					"percent_target_str" => "100".",".$i->percent_target.",".implode($ranges, ","),
					"percent_forecast_str" => "100".",".$i->percent_forecast.",".implode($ranges, ",")
				);
				$branch_details[] = $orgDetail;
			}
			//$o->pct = 80.50;
			
			$branch_color = DB::select("
				select if(instr(color_code,'#') > 0,color_code,concat('#',color_code)) color_code
				from result_threshold
				where result_threshold_group_id = ?
				and ? between begin_threshold and end_threshold
			", array($o->result_threshold_group_id, $o->pct));
			
			//empty($branch_color) ? $color_code = '#9169FF' : $color_code = $branch_color[0]->color_code;
 			
			if (empty($branch_color)) {
				$minmax = DB::select("
					select min(begin_threshold) min_threshold, max(end_threshold) max_threshold
					from result_threshold
					where result_threshold_group_id = ?		
				",array($o->result_threshold_group_id));
				
				if (empty($minmax)) {
					$color_code = '#9169FF';
				} else {
					if ($o->pct < $minmax[0]->min_threshold) {
						$get_color = DB::select("
							select if(instr(color_code,'#') > 0,color_code,concat('#',color_code)) color_code
							from result_threshold
							where result_threshold_group_id = ?
							and begin_threshold = ?
						", array($o->result_threshold_group_id, $minmax[0]->min_threshold));
						$color_code = $get_color[0]->color_code;
					} elseif ($o->pct > $minmax[0]->max_threshold) {
						$get_color = DB::select("
							select if(instr(color_code,'#') > 0,color_code,concat('#',color_code)) color_code
							from result_threshold
							where result_threshold_group_id = ?
							and end_threshold = ?
						", array($o->result_threshold_group_id, $minmax[0]->max_threshold));
						$color_code = $get_color[0]->color_code;					
					} else {
						$color_code = '#9169FF';
					}				
				}
				
			} else {
				$color_code = $branch_color[0]->color_code;
			}							
			
			$o->color_code = $color_code;			
			$o->branch_details = $branch_details;
			
		}
		
		return response()->json($org_list);
	}





	public function perspective_details(Request $request)
	{
		
		//foreach ($org_list as $o) {
			$qinput = array();

			if ($request->appraisal_type_id == 2) {//emp

					$query = "
							SELECT air.item_result_id, p.perspective_id, p.perspective_name, air.item_id, air.item_name, u.uom_name, air.org_id, org.org_code, o.org_name, er.result_threshold_group_id, air.etl_dttm,
								air.target_value, air.forecast_value, ifnull(air.actual_value, 0) actual_value,
								#ifnull(if(air.target_value = 0, 0, (air.actual_value/air.target_value)*100), 0) percent_target,
								air.percent_achievement percent_target,
								ifnull(if(air.forecast_value = 0, 0, (air.actual_value/air.forecast_value)*100), 0) percent_forecast
							FROM appraisal_item_result air
							INNER JOIN appraisal_item ai ON ai.item_id = air.item_id
							INNER JOIN perspective p ON p.perspective_id = ai.perspective_id
							INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
							INNER JOIN emp_result er on air.emp_result_id = er.emp_result_id
							LEFT OUTER JOIN uom u ON u.uom_id = ai.uom_id
							LEFT OUTER JOIN org o ON o.org_id = air.org_id
							LEFT OUTER JOIN org ON org.org_id = air.org_id
							WHERE air.period_id = ?
							#AND o.org_id = ? 
							AND er.emp_id=?
							and er.appraisal_type_id = ?
						";
						
						$qinput[] = $request->period_id;
						#$qinput[] = $request->org_id;	
						$qinput[] = $request->emp_id;		
						$qinput[] = $request->appraisal_type_id;	
						
						$qfooter = " ORDER BY p.perspective_name, air.item_name, air.item_result_id, er.emp_id ";
						empty($request->perspective_id) ?: ($query .= " AND p.perspective_id = ? " AND $qinput[] = $request->perspective_id);

			}else{//org


			$query = "
				SELECT air.item_result_id, p.perspective_id, p.perspective_name, air.item_id, air.item_name, u.uom_name, air.org_id, org.org_code, o.org_name, er.result_threshold_group_id, air.etl_dttm,
					air.target_value, air.forecast_value, ifnull(air.actual_value, 0) actual_value,
					#ifnull(if(air.target_value = 0, 0, (air.actual_value/air.target_value)*100), 0) percent_target,
					air.percent_achievement percent_target,
					ifnull(if(air.forecast_value = 0, 0, (air.actual_value/air.forecast_value)*100), 0) percent_forecast
				FROM appraisal_item_result air
				INNER JOIN appraisal_item ai ON ai.item_id = air.item_id
				INNER JOIN perspective p ON p.perspective_id = ai.perspective_id
				INNER JOIN appraisal_period ap ON ap.period_id = air.period_id
				INNER JOIN emp_result er on air.emp_result_id = er.emp_result_id
				LEFT OUTER JOIN uom u ON u.uom_id = ai.uom_id
				LEFT OUTER JOIN org o ON o.org_id = air.org_id
				LEFT OUTER JOIN org ON org.org_id = air.org_id
				WHERE air.period_id = ?
				AND o.org_id = ? 
				and er.appraisal_type_id = ?
			";
			
			$qinput[] = $request->period_id;
			$qinput[] = $request->org_id;	
			$qinput[] = $request->appraisal_type_id;		
			
			$qfooter = " ORDER BY p.perspective_name, air.item_name, air.item_result_id, org.org_code ";
			empty($request->perspective_id) ?: ($query .= " AND p.perspective_id = ? " AND $qinput[] = $request->perspective_id);
			

			}
			// echo $query.$qfooter;
			// echo "<br>";
			// print_r($qinput);

			$items = DB::select($query.$qfooter,$qinput);
			$orgDetail = array();
			
			$per_details = array();
			foreach ($items as $i) {
				$colorRanges = DB::select("
					select begin_threshold, end_threshold, if(instr(color_code,'#') > 0,color_code,concat('#',color_code)) color_code
					from result_threshold
					where result_threshold_group_id = ?
					order by end_threshold desc
				", array($i->result_threshold_group_id));

				$colors = array();
				$ranges = array();
				
				foreach ($colorRanges as $c) {
					$colors[] = $c->color_code;
					$ranges[] = $c->end_threshold;
				}
				
				$orgDetail = array(
					"perspective_name" => $i->perspective_name,
					"item_name" => $i->item_name,
					"uom_name" => $i->uom_name,
					"rangeColor" => $colors,
					"target"=> $i->target_value,
					"forecast" => $i->forecast_value,
					"actual" => $i->actual_value,
					"percent_target" => $i->percent_target,
					"percent_forecast" => $i->percent_forecast,
					"etl_dttm" => $i->etl_dttm,

					// For Spackline JS //
					"percent_target_str" => "100".",".$i->percent_target.",".implode($ranges, ","),
					"percent_forecast_str" => "100".",".$i->percent_forecast.",".implode($ranges, ",")
				);
				$per_details[] = $orgDetail;
			}
			return response()->json($per_details);
			
	}







/*
	public function year_list()
	{
		$items = DB::select("
			SELECT appraisal_year FROM appraisal_period GROUP BY appraisal_year ORDER BY appraisal_year
		");
		return response()->json($items);
	}

	public function month_list(Request $request)
	{
		$items = DB::select("
			select period_id, substr(monthname(start_date),1,3) as monthname
                from appraisal_period
                where appraisal_year = ?
	        and appraisal_frequency_id = 1
                order by period_id desc
		", array($request->appraisal_year));
		return response()->json($items);

	}

	public function balance_scorecard(Request $request)
	{
		$items = DB::select("
			select p.perspective_name, r.appraisal_item_id, i.appraisal_item_name, r.target_value, r.actual_value
				from appraisal_item_result r, employee e, appraisal_item i, perspective p, appraisal_structure s, form_type f
				where r.emp_code = e.emp_code
				and r.appraisal_item_id = i.appraisal_item_id
				and i.perspective_id = p.perspective_id
				and i.structure_id = s.structure_id
				and s.form_id = f.form_id
				and f.form_name = 'Quantity'
				and e.is_coporate_kpi = 1
				and r.period_id = ?
				order by r.appraisal_item_id
		", array($request->period_id));
		return response()->json($items);

	}

	public function monthly_variance(Request $request)
	{
		$items = DB::select("
			select p.period_no, substr(monthname(p.start_date),1,3), r.target_value, r.actual_value, r.target_value - r.actual_value as variance_value
from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
where r.emp_code = e.emp_code
and r.appraisal_item_id = i.appraisal_item_id
and i.structure_id = s.structure_id
and s.form_id = f.form_id
and r.period_id = p.period_id
and f.form_name = 'Quantity'
and e.is_coporate_kpi = 1
and p.appraisal_frequency_id = 1
and p.appraisal_year = ?
and r.appraisal_item_id = ?
order by p.period_no
		", array($request->appraisal_year,$request->appraisal_item_id));
		return response()->json($items);

	}

	public function monthly_growth(Request $request)
	{
		$items = DB::select("
			select period_no, period_desc, sum(previous_year) as pyear, sum(current_year) as cyear,
((sum(current_year) - sum(previous_year))/sum(previous_year)) * 100 as growth_percent
from
(select p.period_no, substr(monthname(p.start_date),1,3) as period_desc, r.actual_value as previous_year, 0 as current_year
from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
where r.emp_code = e.emp_code
and r.appraisal_item_id = i.appraisal_item_id
and i.structure_id = s.structure_id
and s.form_id = f.form_id
and r.period_id = p.period_id
and f.form_name = 'Quantity'
and e.is_coporate_kpi = 1
and p.appraisal_frequency_id = 1
and p.appraisal_year = ? - 1
and r.appraisal_item_id = ?
union
select p.period_no, substr(monthname(p.start_date),1,3) as period_desc, 0 as previous_year, r.actual_value as current_year
from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
where r.emp_code = e.emp_code
and r.appraisal_item_id = i.appraisal_item_id
and i.structure_id = s.structure_id
and s.form_id = f.form_id
and r.period_id = p.period_id
and f.form_name = 'Quantity'
and e.is_coporate_kpi = 1
and p.appraisal_year = ?
and r.appraisal_item_id = ?) as growth
group by period_no, period_desc
order by period_no
		", array($request->appraisal_year,$request->appraisal_item_id,$request->appraisal_year,$request->appraisal_item_id));
		return response()->json($items);

	}

	public function ytd_monthly_variance(Request $request)
	{
		$items = DB::select("
			select a.period_no as a
,month_name
,(select sum(target_value)
 from(
   select p.period_no
    , r.target_value
   from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
   where r.emp_code = e.emp_code
   and r.appraisal_item_id = i.appraisal_item_id
   and i.structure_id = s.structure_id
   and s.form_id = f.form_id
   and r.period_id = p.period_id
   and f.form_name = 'Quantity'
   and e.is_coporate_kpi = 1
   and p.appraisal_frequency_id = 1
   and p.appraisal_year = ?
   and r.appraisal_item_id = ?
 )b
 where period_no <= a
) as target_value
,(select sum(actual_value)
 from(
   select p.period_no
   , r.actual_value
   from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
   where r.emp_code = e.emp_code
   and r.appraisal_item_id = i.appraisal_item_id
   and i.structure_id = s.structure_id
   and s.form_id = f.form_id
   and r.period_id = p.period_id
   and f.form_name = 'Quantity'
   and e.is_coporate_kpi = 1
   and p.appraisal_frequency_id = 1
   and p.appraisal_year = ?
   and r.appraisal_item_id = ?
 )b
 where period_no <= a
) as actual_value
,(select sum(variance_value)
 from(
   select p.period_no
   , r.target_value - r.actual_value as variance_value
   from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
   where r.emp_code = e.emp_code
   and r.appraisal_item_id = i.appraisal_item_id
   and i.structure_id = s.structure_id
   and s.form_id = f.form_id
   and r.period_id = p.period_id
   and f.form_name = 'Quantity'
   and e.is_coporate_kpi = 1
   and p.appraisal_frequency_id = 1
   and p.appraisal_year = ?
   and r.appraisal_item_id = ?
 )b
 where period_no <= a
) as variance_value
from(
  select p.period_no
   , substr(monthname(p.start_date),1,3) as month_name
  from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
  where r.emp_code = e.emp_code
  and r.appraisal_item_id = i.appraisal_item_id
  and i.structure_id = s.structure_id
  and s.form_id = f.form_id
  and r.period_id = p.period_id
  and f.form_name = 'Quantity'
  and e.is_coporate_kpi = 1
  and p.appraisal_frequency_id = 1
  and p.appraisal_year = ?
  and r.appraisal_item_id = ?
)a
order by a.period_no
		", array($request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id));
		return response()->json($items);

	}


	public function ytd_monthly_growth(Request $request)
	{
		$items = DB::select("



SELECT period_no
,period_desc
, sum(previous_year) as pyear
, sum(current_year) as cyear
, ((sum(current_year) - sum(previous_year))/sum(previous_year)) * 100 as growth_percent
from(
select main_previous_year.period_no as period_no
,period_desc
,(select sum(previous_year)
 from(
	select p.period_no
	, r.actual_value as previous_year
	from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
	where r.emp_code = e.emp_code
	and r.appraisal_item_id = i.appraisal_item_id
	and i.structure_id = s.structure_id
	and s.form_id = f.form_id
	and r.period_id = p.period_id
	and f.form_name = 'Quantity'
	and e.is_coporate_kpi = 1
	and p.appraisal_frequency_id = 1
	and p.appraisal_year = ? - 1
	and r.appraisal_item_id = ?
 )sub
 where sub.period_no <= main_previous_year.period_no
) as previous_year
, 0 as current_year
from(
select p.period_no
, substr(monthname(p.start_date),1,3) as period_desc
from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
where r.emp_code = e.emp_code
and r.appraisal_item_id = i.appraisal_item_id
and i.structure_id = s.structure_id
and s.form_id = f.form_id
and r.period_id = p.period_id
and f.form_name = 'Quantity'
and e.is_coporate_kpi = 1
and p.appraisal_frequency_id = 1
and p.appraisal_year = ? - 1
and r.appraisal_item_id = ?
)main_previous_year

union

select main_current_year.period_no as period_no
,period_desc
,0 as previous_year
,(select sum(current_year)
 from(
		select p.period_no
		, r.actual_value as current_year
		from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
		where r.emp_code = e.emp_code
		and r.appraisal_item_id = i.appraisal_item_id
		and i.structure_id = s.structure_id
		and s.form_id = f.form_id
		and r.period_id = p.period_id
		and f.form_name = 'Quantity'
		and e.is_coporate_kpi = 1
		and p.appraisal_year = ?
		and r.appraisal_item_id = ?
 )sub
 where sub.period_no <= main_current_year.period_no
) as current_year
from(
select p.period_no
, substr(monthname(p.start_date),1,3) as period_desc
from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
where r.emp_code = e.emp_code
and r.appraisal_item_id = i.appraisal_item_id
and i.structure_id = s.structure_id
and s.form_id = f.form_id
and r.period_id = p.period_id
and f.form_name = 'Quantity'
and e.is_coporate_kpi = 1
and p.appraisal_year = ?
and r.appraisal_item_id = ?
)main_current_year

) as growth
group by period_no
,period_desc


		", array($request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id));
		return response()->json($items);

	}
	*/
}
