<?php

namespace App\Http\Controllers;

use App\SystemConfiguration;
use App\Employee;

use PDO;
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

class ReportController extends Controller
{

	public function __construct()
	{
		//$this->middleware('jwt.auth');
	}

	public function del_sql() {

		// $appraisal_item_result = DB::select("
		// 	SELECT air.item_result_id
		// 	FROM appraisal_item_result air
		// 	LEFT JOIN employee er ON er.emp_id = air.emp_id and er.level_id = air.level_id and er.position_id = air.position_id and er.org_id = air.org_id
		// 	WHERE er.emp_id IS NULL;
		// ");

		// foreach ($appraisal_item_result as $key => $value) {
		// 	DB::table('appraisal_item_result')->where('item_result_id', '=', $value->item_result_id)->delete();
		// }

		$emp_result = DB::select("
			SELECT air.emp_result_id
			FROM emp_result er
			LEFT JOIN appraisal_item_result air ON er.emp_result_id = air.emp_result_id
			WHERE air.emp_result_id IS NULL;
		");

		foreach ($emp_result as $key => $value) {
			DB::table('emp_result')->where('emp_result_id', '=', $value->emp_result_id)->delete();
		}

		$emp_result_stage = DB::select("
			SELECT er.emp_result_stage_id
			FROM emp_result_stage er
			LEFT JOIN appraisal_item_result air ON er.emp_result_id = air.emp_result_id
			WHERE air.emp_result_id IS NULL;
		");

		foreach ($emp_result_stage as $key => $value) {
			DB::table('emp_result_stage')->where('emp_result_stage_id', '=', $value->emp_result_stage_id)->delete();
		}

		$structure_result = DB::select("
			SELECT er.structure_result_id
			FROM structure_result er
			LEFT JOIN appraisal_item_result air ON er.emp_result_id = air.emp_result_id
			WHERE air.emp_result_id IS NULL;
		");

		foreach ($structure_result as $key => $value) {
			DB::table('structure_result')->where('structure_result_id', '=', $value->structure_result_id)->delete();
		}

		$monthly_appraisal_item_result = DB::select("
			SELECT er.item_result_id
			FROM monthly_appraisal_item_result er
			LEFT JOIN appraisal_item_result air ON er.emp_result_id = air.emp_result_id
			WHERE er.emp_result_id IS NULL;
		");

		foreach ($monthly_appraisal_item_result as $key => $value) {
			DB::table('monthly_appraisal_item_result')->where('item_result_id', '=', $value->item_result_id)->delete();
		}

		$cds_result_doc = DB::select("
			SELECT cds_result_doc_id
			FROM cds_result_doc er
			LEFT JOIN cds_result air ON er.cds_result_id = air.cds_result_id
			WHERE air.cds_result_id IS NULL;
		");

		foreach ($cds_result_doc as $key => $value) {
			DB::table('cds_result_doc')->where('cds_result_doc_id', '=', $value->cds_result_doc_id)->delete();
		}

		$appraisal_item_result_doc = DB::select("
			SELECT result_doc_id
			FROM appraisal_item_result_doc er
			LEFT JOIN appraisal_item_result air ON er.item_result_id = air.item_result_id
			WHERE air.item_result_id IS NULL
		");

		foreach ($appraisal_item_result_doc as $key => $value) {
			DB::table('appraisal_item_result_doc')->where('result_doc_id', '=', $value->result_doc_id)->delete();
		}

		// $cds_result = DB::select("
		// 	SELECT cds_result_id
		// 	FROM cds_result
		// 	WHERE cds_result_id NOT IN(
		// 		SELECT cds.cds_result_id FROM cds_result cds 
		// 		INNER JOIN appraisal_item_result air 
		// 			ON air.org_id = cds.org_id
		// 			AND air.level_id = cds.level_id
		// 		WHERE EXISTS(
		// 			SELECT 1 FROM kpi_cds_mapping 
		// 			WHERE item_id = air.item_id
		// 			AND cds_id = cds.cds_id
		// 		)
		// 	)
		// ");

		// foreach ($cds_result as $key => $value) {
		// 	DB::table('cds_result')->where('cds_result_id', '=', $value->cds_result_id)->delete();
		// }

	}
	
    public function al_list()
    {
		$items = DB::select("
			Select level_id, appraisal_level_name
			From appraisal_level 
			Where is_active = 1 
			order by appraisal_level_name
		");
		return response()->json($items);
    }	
	
	public function auto_employee_name(Request $request)
	{
		$emp = Employee::find(Auth::id());
		$all_emp = DB::select("
			SELECT sum(b.is_all_employee) count_no
			from employee a
			left outer join appraisal_level b
			on a.level_id = b.level_id
			where emp_code = ?
		", array(Auth::id()));

		$level_id = empty($request->level_id) ? "" : "and level_id = {$request->level_id}";
		$org_id = empty($request->org_id) ? "" : "and org_id = {$request->org_id}";
		
		if ($all_emp[0]->count_no > 0) {
			$items = DB::select("
				Select e.emp_code, e.emp_name, p.position_id, p.position_name
				From employee e
				left join position p
				on e.position_id = p.position_id
				Where e.emp_name like ?
				and e.is_active = 1
				Order by e.emp_code asc
			", array('%'.$request->emp_name.'%'));
		} else {
			$items = DB::select("
				Select e.emp_code, e.emp_name, p.position_id, p.position_name
				From employee e
				left join position p
				on e.position_id = p.position_id
				Where 1=1
				And e.emp_name like ?
				".$level_id."
				".$org_id."
				and e.is_active = 1
				Order by e.emp_code asc
			", array('%'.$request->emp_name.'%'));
		}
		return response()->json($items);
		
	}

	public function usage_log(Request $request) 
	{

		// Get the current page from the url if it's not set default to 1
		empty($request->page) ? $page = 1 : $page = $request->page;
		
		// Number of items per page
		empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;

		// Start displaying items from this number;
		$offset = ($page * $perPage) - $perPage; // Start displaying items from this number		
			
		$limit = " limit " . $perPage . " offset " . $offset;
		
		$query ="			
			select SQL_CALC_FOUND_ROWS a.created_dttm, b.emp_code, b.emp_name, d.org_name, e.appraisal_level_name, c.friendlyURL url
			from usage_log a, employee b, lportal.Layout c, org d, appraisal_level e
			where a.emp_code = b.emp_code
			and a.plid = c.plid
			and b.org_id = d.org_id
			and b.level_id = e.level_id
		";			
			
		$qfooter = " order by e.appraisal_level_name asc, a.created_dttm desc, a.emp_code asc, url asc " . $limit;		
		$qinput = array();
		
		// empty($request->branch_code) ?: ($query .= " and b.branch_code = ? " AND $qinput[] =  $request->branch_code);
		// empty($request->personnel_name) ?: ($query .= " and b.thai_full_name like ? " AND  $qinput[] = '%' . $request->personnel_name . '%');
		if (!empty($request->usage_start_date) and empty($request->usage_end_date)) {
			$query .= " and date(a.created_dttm) >= date(?) ";
			$qinput[] = $request->usage_start_date;		
		} elseif (empty($request->usage_start_date) and empty($request->usage_end_date)) {
		} else {
			$query .= " and date(a.created_dttm) between date(?) and date(?) ";
			$qinput[] = $request->usage_start_date;
			$qinput[] = $request->usage_end_date;				
		}
		empty($request->emp_id) ?: ($query .= " and b.emp_code = ? " AND $qinput[] = $request->emp_id);
		empty($request->position_id) ?: ($query .= " and b.position_id = ? " AND $qinput[] = $request->position_id);

		if($request->appraisal_type==1) {
			empty($request->level_id) ?: ($query .= " and d.level_id = ? " AND $qinput[] = $request->level_id);
			empty($request->org_id) ?: ($query .= " and d.org_id = ? " AND $qinput[] = $request->org_id);
		} else {
			empty($request->level_id) ?: ($query .= " and b.level_id = ? " AND $qinput[] = $request->level_id);
			empty($request->org_id) ?: ($query .= " and b.org_id = ? " AND $qinput[] = $request->org_id);
		}

		// empty($request->level_id) ?: ($query .= " and b.level_id = ? " AND $qinput[] = $request->level_id);
		// empty($request->org_id) ?: ($query .= " and b.org_id = ? " AND $qinput[] = $request->org_id);
		
	
		$items = DB::select($query . $qfooter, $qinput);
		$count = DB::select("select found_rows() as total_count");

	
		$groups = array();
		foreach ($items as $item) {
			$key = ($request->appraisal_type==1) ? $item->org_name : $item->appraisal_level_name;
			if (!isset($groups[$key])) {
				$groups[$key] = array(
					'items' => array($item),
					'count' => 1,
				);
			} else {
				$groups[$key]['items'][] = $item;
				$groups[$key]['count'] += 1;
			}
		}		
		
		empty($items) ? $totalPage = 0 : $totalPage = $count[0]->total_count;
		
		$result = [
			"total" => $totalPage, 
			"current_page" => $page,
			"last_page" => ceil($totalPage / $perPage),
			"data" => $groups
		];
		
		return response()->json($result);	
	}

	//add by toto 2018-05-31
	public function list_kpi_type(Request $request) {
		$qinput = array();
		$query = "
		SELECT distinct ai.kpi_type_id, kt.kpi_type_name
		FROM appraisal_item_result air
		inner join appraisal_item ai on air.item_id=ai.item_id
		inner join appraisal_structure aps on ai.structure_id = aps.structure_id
		inner join kpi_type kt on kt.kpi_type_id = ai.kpi_type_id
		
			WHERE 1=1
			and aps.form_id=1
			";
		
		$qfooter = " GROUP BY air.item_id
			ORDER BY kt.kpi_type_name asc ";
		
		if ($request->appraisal_type_id == 1) {
			empty($request->appraisal_level) ?: ($query .= " and level_id = ? " AND $qinput[] = $request->appraisal_level);
			empty($request->org_id) ?: ($query .= " and org_id = ? " AND $qinput[] = $request->org_id);
			empty($request->period) ?: ($query .= " and period_id = ? " AND $qinput[] = $request->period);
		} else {
			empty($request->emp_id) ?: ($query .= " and emp_id = ? " AND $qinput[] = $request->emp_id);
		}
			
		$items = DB::select($query.$qfooter,$qinput);
		return response()->json($items);
	}
}
