<?php

namespace App\Http\Controllers;

use App\AppraisalItemResult;
use App\AppraisalFrequency;
use App\AppraisalPeriod;
use App\EmpResult;
use App\EmpResultStage;
use App\WorkflowStage;
use App\Employee;
use App\ResultThresholdGroup;
use App\ThresholdGroup;
use App\Org;
use App\SystemConfiguration;

use Auth;
use DB;
use File;
use Validator;
use Excel;
use Mail;
use Config;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AppraisalAssignmentController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function appraisal_type_list()
	{
		$items = DB::select("
			Select appraisal_type_id, appraisal_type_name
			From appraisal_type
			Order by appraisal_type_id
		");
		return response()->json($items);
	}
	
	
	public function new_assign_to(Request $request)
	{
		$items = DB::select("
			SELECT a.stage_id, a.to_appraisal_level_id, b.appraisal_level_name
			FROM workflow_stage a
			left outer join appraisal_level b
			on a.to_appraisal_level_id = b.appraisal_level_id
			where a.stage_id = 1
			union
			SELECT a.stage_id, a.to_appraisal_level_id, b.appraisal_level_name
			FROM workflow_stage a
			left outer join appraisal_level b
			on a.to_appraisal_level_id = b.appraisal_level_id
			where a.from_stage_id = 1
			and a.to_appraisal_level_id = (
				select parent_id
				from appraisal_level
				where appraisal_level_id = ?
			)
		", array($request->appraisal_level_id));
		
		return response()->json($items);
	}
	
	public function new_action_to()
	{
		$items = DB::select("	
			select stage_id, to_action
			from appraisal_stage
			where stage_id in (1)
		"); 
		
		return response()->json($items);			
	}
	
	public function edit_assign_to(Request $request)
	{
	
		$al = DB::select("
			select b.appraisal_level_id, b.is_hr
			from emp_level a
			left outer join appraisal_level b
			on a.appraisal_level_id = b.appraisal_level_id
			where a.emp_code = ?
		", array(Auth::id()));
		
		if (empty($al)) {
			$is_hr = null;
			$al_id = null;
		} else {
			$is_hr = $al[0]->is_hr;
			$al_id = $al[0]->appraisal_level_id;
		}		
	
		$items = DB::select("
			select distinct a.to_appraisal_level_id, b.appraisal_level_name
			from workflow_stage a
			left outer join appraisal_level b
			on a.to_appraisal_level_id = b.appraisal_level_id
			where from_stage_id = ?		
			and from_appraisal_level_id = ?
			and stage_id < 17
		", array($request->stage_id, $al_id));
		
		if (empty($items)) {
			$workflow = WorkflowStage::find($request->stage_id);
			empty($workflow->to_stage_id) ? $to_stage_id = "null" : $to_stage_id = $workflow->to_stage_id;
			$items = DB::select("
				select distinct a.to_appraisal_level_id, b.appraisal_level_name
				from workflow_stage a
				left outer join appraisal_level b
				on a.to_appraisal_level_id = b.appraisal_level_id
				where stage_id in ({$to_stage_id})
				and from_appraisal_level_id = ?
				and stage_id < 17
			", array($al_id));
		}
		
		return response()->json($items);	
	}
	
	public function edit_action_to(Request $request)
	{
		$emp = DB::select("
			select is_hr
			from employee a
			left outer join appraisal_level b
			on a.level_id = b.level_id
			where emp_code = ?
		", array(Auth::id()));
		
		$is_hr = $emp[0]->is_hr;
		
		if ($is_hr == 1) {
			$hr_query = " and hr_see = 1 ";
		} else { 
			$hr_query = "";
		}
		
		$items = DB::select("
			select stage_id, to_action
			from appraisal_stage 
			where from_stage_id = ?		
			and assignment_flag = 1
		" . $hr_query, array($request->stage_id));
		
		if (empty($items)) {
			$workflow = WorkflowStage::find($request->stage_id);
			empty($workflow->to_stage_id) ? $to_stage_id = "null" : $to_stage_id = $workflow->to_stage_id;
			$items = DB::select("	
				select stage_id, to_action
				from appraisal_stage a
				where stage_id in ({$to_stage_id})
				and assignment_flag = 1
			" . $hr_query);
		}
		
		return response()->json($items);	
	}
	
	public function auto_position_name(Request $request)
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
				Select distinct b.position_id, b.position_name
				From employee a left outer join position b
				on a.position_id = b.position_id
				Where position_name like ? 
				and a.is_active = 1
				and b.is_active = 1
				Order by position_name			
				limit 10
			",array('%'.$request->position_name.'%'));
		} else {
			$items = DB::select("
				Select distinct b.position_id, b.position_name
				From employee a left outer join position b
				on a.position_id = b.position_id
				Where a.chief_emp_code = ?
				and position_name like ? 				
				and a.is_active = 1
				and b.is_active = 1
				Order by position_name		
				limit 10
			", array($emp->emp_code,'%'.$request->position_name.'%'));
		}
		return response()->json($items);
	}	
	
    // public function al_list()
    // {
		// $all_emp = DB::select("
			// SELECT count(is_all_employee) count_no
			// FROM emp_level a
			// left outer join appraisal_level b
			// on a.appraisal_level_id = b.appraisal_level_id
			// where emp_code = ?
			// and is_all_employee = 1		
		// ", array(Auth::id()));
		
		// if ($all_emp[0]->count_no > 0) {
			// $items = DB::select("
				// Select appraisal_level_id, appraisal_level_name
				// From appraisal_level 
				// Where is_active = 1 
				// Order by appraisal_level_name			
			// ");
		// } else {
				// // select al.appraisal_level_id, al.appraisal_level_name
				// // from emp_level el, appraisal_level al
				// // where el.appraisal_level_id = al.appraisal_level_id
				// // and el.emp_code = 1
				// // union
			// $items = DB::select("
				// select distinct el.appraisal_level_id, al.appraisal_level_name
				// from employee e, emp_level el, appraisal_level al
				// where e.emp_code = el.emp_code
				// and el.appraisal_level_id = al.appraisal_level_id
				// and e.chief_emp_code = ?
				// and e.is_active = 1			
			// ", array(Auth::id()));
			
			// $chief_list = array();
			
			// $chief_items = DB::select("
				// select distinct e.emp_code
				// from employee e, emp_level el, appraisal_level al
				// where e.emp_code = el.emp_code
				// and el.appraisal_level_id = al.appraisal_level_id
				// and e.chief_emp_code = ?
				// and e.is_active = 1			
			// ", array(Auth::id()));
			
			// foreach ($chief_items as $i) {
				// $chief_list[] = $i->emp_code;
			// }
		
			// $chief_list = array_unique($chief_list);
			
			// // Get array keys
			// $arrayKeys = array_keys($chief_list);
			// // Fetch last array key
			// $lastArrayKey = array_pop($arrayKeys);
			// //iterate array
			// $in_chief = '';
			// foreach($chief_list as $k => $v) {
				// if($k == $lastArrayKey) {
					// //during array iteration this condition states the last element.
					// $in_chief .= $v;
				// } else {
					// $in_chief .= $v . ',';
				// }
			// }					
			
			
			// do {				
				// empty($in_chief) ? $in_chief = "null" : null;
				// $ritems = DB::select("
					// select distinct el.appraisal_level_id, al.appraisal_level_name
					// from employee e, emp_level el, appraisal_level al
					// where e.emp_code = el.emp_code
					// and el.appraisal_level_id = al.appraisal_level_id
					// and e.is_active = 1		
					// and e.chief_emp_code in ({$in_chief})
				// ");

				// $chief_list = array();			
				
				// foreach ($ritems as $r) {
					// $items[] = $r;
				// }
				
				// $chief_items = DB::select("
					// select distinct e.emp_code
					// from employee e, emp_level el, appraisal_level al
					// where e.emp_code = el.emp_code
					// and el.appraisal_level_id = al.appraisal_level_id
					// and e.chief_emp_code in ({$in_chief})
					// and e.is_active = 1			
				// ");
				
				// foreach ($chief_items as $i) {
					// $chief_list[] = $i->emp_code;
				// }			
				
				// $chief_list = array_unique($chief_list);
				
				// // Get array keys
				// $arrayKeys = array_keys($chief_list);
				// // Fetch last array key
				// $lastArrayKey = array_pop($arrayKeys);
				// //iterate array
				// $in_chief = '';
				// foreach($chief_list as $k => $v) {
					// if($k == $lastArrayKey) {
						// //during array iteration this condition states the last element.
						// $in_chief .= $v;
					// } else {
						// $in_chief .= $v . ',';
					// }
				// }		
			// } while (!empty($chief_list));
	
		// }			
		
		// $items = array_unique($items,SORT_REGULAR);
		
		// return response()->json($items);
    // }
	
    public function al_list()
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
				Order by level_id			
			");
		} else {
			$items = DB::select("
				select distinct al.level_id, al.appraisal_level_name
				from employee e, appraisal_level al
				where e.level_id = al.level_id
				and e.chief_emp_code = ?
				and e.is_active = 1			
				and al.is_hr = 0
				Order by level_id	
			", array($emp->emp_code));
		}
		
		return response()->json($items);
    }
		
	public function frequency_list()
	{
		$items = DB::select("
			Select frequency_id, frequency_name, frequency_month_value
			From  appraisal_frequency
			Order by frequency_month_value asc
		");
		return response()->json($items);
	}
	
	public function period_list (Request $request)
	{
		// if ($request->assignment_frequency == 1) {
			// $items = DB::select("
				// select period_id, appraisal_period_desc 
				// From appraisal_period
				// Where appraisal_year = (select current_appraisal_year from system_config)		
				// order by appraisal_period_desc
			// ");
		// } else {
			// $items = DB::select("
				// select period_id, appraisal_period_desc 
				// From appraisal_period
				// Where appraisal_year = (select current_appraisal_year from system_config)
				// And appraisal_frequency_id = ?
				// order by appraisal_period_desc
			// ", array($request->frequency_id));
		// }
		$items = DB::select("
			select period_id, appraisal_period_desc 
			From appraisal_period
			Where appraisal_year = ?
			And appraisal_frequency_id = ?
			order by start_date asc
		", array($request->appraisal_year, $request->frequency_id));		
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
		
		if ($all_emp[0]->count_no > 0) {
			$items = DB::select("
				Select emp_code, emp_name
				From employee 
				Where emp_name like ? 
				and is_active = 1
				Order by emp_name			
			", array('%'.$request->emp_name.'%'));
		} else {
			$items = DB::select("
				Select emp_code, emp_name
				From employee 
				Where chief_emp_code = ?
				And emp_name like ?
				and is_active = 1
				Order by emp_name	
			", array($emp->emp_code,'%'.$request->emp_name.'%'));
		}
		return response()->json($items);
		
	}
	
	public function index(Request $request)
	{
		$all_emp = DB::select("
			SELECT sum(b.is_all_employee) count_no
			from employee a
			left outer join appraisal_level b
			on a.level_id = b.level_id
			where emp_code = ?
		", array(Auth::id()));

		$qinput = array();
		if ($all_emp[0]->count_no > 0) {

			if ($request->appraisal_type_id == 2) {
				$query_unassign = "
					Select distinct null as emp_result_id,  'Unassigned' as status, emp_id, emp_code, emp_name, o.org_id, o.org_code, o.org_name, p.position_name, 'Individual' as appraisal_type_name, 2 appraisal_type_id, 0 period_id, 'Unassigned' appraisal_period_desc
					From employee e
					left outer join org o
					on e.org_id = o.org_id
					left outer join position p
					on e.position_id = p.position_id
					Where e.is_active = 1
				";
				empty($request->position_id) ?: ($query_unassign .= " and e.position_id = ? " AND $qinput[] = $request->position_id);
				empty($request->org_id) ?: ($query_unassign .= " and e.org_id = ? " AND $qinput[] = $request->org_id);
				empty($request->emp_code) ?: ($query_unassign .= " and emp_code = ? " AND $qinput[] = $request->emp_code);
				empty($request->appraisal_level_id) ?: ($query_unassign .= " And e.level_id = ? " AND $qinput[] = $request->appraisal_level_id);			
				
				$query_unassign .= "
					and emp_code not in 
					(SELECT emp_code
						FROM   (SELECT e.emp_code,
									   p.appraisal_year,
									   p.appraisal_frequency_id,
									   er.appraisal_type_id,
									   Count(1) assigned_total,
									   z.period_total
								FROM   emp_result er,
									   employee e,
									   appraisal_period p,
									   (SELECT appraisal_year,
											   appraisal_frequency_id,
											   Count(1) period_total
										FROM   appraisal_period
					";
				empty($request->period_id) ?: ($query_unassign .= " where period_id = ? " AND $qinput[] = $request->period_id);
				$query_unassign .= "
										GROUP  BY appraisal_year,
												  appraisal_frequency_id) z
								WHERE  er.emp_id = e.emp_id
									   AND er.period_id = p.period_id
									   AND p.appraisal_year = z.appraisal_year
									   AND p.appraisal_frequency_id = z.appraisal_frequency_id
				";
				empty($request->position_id) ?: ($query_unassign .= " and er.position_id = ? " AND $qinput[] = $request->position_id);
				empty($request->org_id) ?: ($query_unassign .= " and er.org_id = ? " AND $qinput[] = $request->org_id);
				empty($request->emp_code) ?: ($query_unassign .= " and e.emp_code = ? " AND $qinput[] = $request->emp_code);	
				empty($request->appraisal_level_id) ?: ($query_unassign .= " and e.level_id = ? " AND $qinput[] = $request->appraisal_level_id);
				empty($request->appraisal_type_id) ?: ($query_unassign .= " and er.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
				empty($request->appraisal_year) ?: ($query_unassign .= " and p.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
				empty($request->frequency_id) ?: ($query_unassign .= " and p.appraisal_frequency_id = ? " AND $qinput[] = $request->frequency_id);
				empty($request->period_id) ?: ($query_unassign .= " and p.period_id = ? " AND $qinput[] = $request->period_id);
				
				$query_unassign .= " GROUP  BY e.emp_code,
										  p.appraisal_year,
										  p.appraisal_frequency_id, er.appraisal_type_id) assigned
						WHERE  assigned_total >= period_total  ) union all ";
				
				$query_unassign .= "
					select distinct er.emp_result_id, er.status, e.emp_id, e.emp_code, e.emp_name, o.org_id, o.org_code, o.org_name, po.position_name, t.appraisal_type_name, t.appraisal_type_id, p.period_id, concat(p.appraisal_period_desc,' Start Date: ',p.start_date,' End Date: ',p.end_date) appraisal_period_desc
					From emp_result er, employee e, appraisal_type t, appraisal_item_result ir, appraisal_item I, appraisal_period p, org o, position po
					Where er.emp_id = e.emp_id and er.appraisal_type_id = t.appraisal_type_id
					And er.emp_result_id = ir.emp_result_id 
					and ir.item_id = I.item_id		
					and er.period_id = p.period_id
					and e.org_id = o.org_id
					and e.position_id = po.position_id
				";
				empty($request->position_id) ?: ($query_unassign .= " and er.position_id = ? " AND $qinput[] = $request->position_id);
				empty($request->org_id) ?: ($query_unassign .= " and er.org_id = ? " AND $qinput[] = $request->org_id);
				empty($request->emp_code) ?: ($query_unassign .= " and e.emp_code = ? " AND $qinput[] = $request->emp_code);	
				empty($request->appraisal_level_id) ?: ($query_unassign .= " And e.level_id = ? " AND $qinput[] = $request->appraisal_level_id);
				empty($request->appraisal_type_id) ?: ($query_unassign .= " and er.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);	
				empty($request->appraisal_year) ?: ($query_unassign .= " and p.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
				empty($request->frequency_id) ?: ($query_unassign .= " and p.appraisal_frequency_id = ? " AND $qinput[] = $request->frequency_id);
				empty($request->period_id) ?: ($query_unassign .= " and p.period_id = ? " AND $qinput[] = $request->period_id);		
			
			} else {
			
				$query_unassign = "
					Select distinct null as emp_result_id,  'Unassigned' as status, null emp_id, null emp_code, null emp_name, o.org_id, o.org_code, o.org_name, null position_name, 'Organization' as appraisal_type_name, 1 appraisal_type_id, 0 period_id, 'Unassigned' appraisal_period_desc
					From org o
					Where o.is_active = 1
				";
				//empty($request->position_id) ?: ($query_unassign .= " and e.position_id = ? " AND $qinput[] = $request->position_id);
				empty($request->org_id) ?: ($query_unassign .= " and o.org_id = ? " AND $qinput[] = $request->org_id);
				//empty($request->emp_code) ?: ($query_unassign .= " and emp_code = ? " AND $qinput[] = $request->emp_code);
				empty($request->appraisal_level_id) ?: ($query_unassign .= " And o.level_id = ? " AND $qinput[] = $request->appraisal_level_id);			
				
				$query_unassign .= "
					and org_id not in 
					(SELECT org_id
						FROM   (SELECT o.org_id,
									   p.appraisal_year,
									   p.appraisal_frequency_id,
									   er.appraisal_type_id,
									   Count(1) assigned_total,
									   z.period_total
								FROM   emp_result er,
									   org o,
									   appraisal_period p,
									   (SELECT appraisal_year,
											   appraisal_frequency_id,
											   Count(1) period_total
										FROM   appraisal_period
					";
				empty($request->period_id) ?: ($query_unassign .= " where period_id = ? " AND $qinput[] = $request->period_id);
				$query_unassign .= "
										GROUP  BY appraisal_year,
												  appraisal_frequency_id) z
								WHERE  er.org_id = o.org_id
									   AND er.period_id = p.period_id
									   AND p.appraisal_year = z.appraisal_year
									   AND p.appraisal_frequency_id = z.appraisal_frequency_id
				";
				//empty($request->position_id) ?: ($query_unassign .= " and er.position_id = ? " AND $qinput[] = $request->position_id);
				empty($request->org_id) ?: ($query_unassign .= " and er.org_id = ? " AND $qinput[] = $request->org_id);
				//empty($request->emp_code) ?: ($query_unassign .= " and e.emp_code = ? " AND $qinput[] = $request->emp_code);	
				empty($request->appraisal_level_id) ?: ($query_unassign .= " and o.level_id = ? " AND $qinput[] = $request->appraisal_level_id);
				empty($request->appraisal_type_id) ?: ($query_unassign .= " and er.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
				empty($request->appraisal_year) ?: ($query_unassign .= " and p.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
				empty($request->frequency_id) ?: ($query_unassign .= " and p.appraisal_frequency_id = ? " AND $qinput[] = $request->frequency_id);
				empty($request->period_id) ?: ($query_unassign .= " and p.period_id = ? " AND $qinput[] = $request->period_id);
				
				$query_unassign .= " GROUP  BY o.org_id,
										  p.appraisal_year,
										  p.appraisal_frequency_id, er.appraisal_type_id) assigned
						WHERE  assigned_total >= period_total  ) union all ";
				
				$query_unassign .= "
					select distinct er.emp_result_id, er.status, null emp_id, null emp_code,  null emp_name, o.org_id, o.org_code, o.org_name, null position_name, t.appraisal_type_name, t.appraisal_type_id, p.period_id, concat(p.appraisal_period_desc,' Start Date: ',p.start_date,' End Date: ',p.end_date) appraisal_period_desc
					From emp_result er, org o, appraisal_type t, appraisal_item_result ir, appraisal_item I, appraisal_period p
					Where er.org_id = o.org_id and er.appraisal_type_id = t.appraisal_type_id
					And er.emp_result_id = ir.emp_result_id 
					and ir.item_id = I.item_id		
					and er.period_id = p.period_id
				";
				//empty($request->position_id) ?: ($query_unassign .= " and er.position_id = ? " AND $qinput[] = $request->position_id);
				empty($request->org_id) ?: ($query_unassign .= " and er.org_id = ? " AND $qinput[] = $request->org_id);
				//empty($request->emp_code) ?: ($query_unassign .= " and e.emp_code = ? " AND $qinput[] = $request->emp_code);	
				empty($request->appraisal_level_id) ?: ($query_unassign .= " And er.level_id = ? " AND $qinput[] = $request->appraisal_level_id);
				empty($request->appraisal_type_id) ?: ($query_unassign .= " and er.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);	
				empty($request->appraisal_year) ?: ($query_unassign .= " and p.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
				empty($request->frequency_id) ?: ($query_unassign .= " and p.appraisal_frequency_id = ? " AND $qinput[] = $request->frequency_id);
				empty($request->period_id) ?: ($query_unassign .= " and p.period_id = ? " AND $qinput[] = $request->period_id);		
			
			}
			
		} else {
		
			if ($request->appraisal_type_id == 2) {
				$query_unassign = "
					Select distinct null as emp_result_id,  'Unassigned' as status, e.emp_id, emp_code, emp_name, o.org_id, o.org_code, o.org_name, p.position_name, 'Individual' as appraisal_type_name, 2 appraisal_type_id, 0 period_id, 'Unassigned' appraisal_period_desc
					From employee e left outer join	org o 
					on e.org_id = o.org_id
					left outer join position p
					on e.position_id = p.position_id
					Where e.is_active = 1
					and chief_emp_code = ?
				";
				$qinput[] = Auth::id();
				empty($request->position_id) ?: ($query_unassign .= " and e.position_id = ? " AND $qinput[] = $request->position_id);
				empty($request->org_id) ?: ($query_unassign .= " and e.org_id = ? " AND $qinput[] = $request->org_id);
				empty($request->emp_code) ?: ($query_unassign .= " and emp_code = ? " AND $qinput[] = $request->emp_code);
				empty($request->appraisal_level_id) ?: ($query_unassign .= " and e.level_id = ? " AND $qinput[] = $request->appraisal_level_id);			
				
				$query_unassign .= "
					and emp_code not in 
					(SELECT emp_code
						FROM   (SELECT e.emp_code,
									   p.appraisal_year,
									   p.appraisal_frequency_id,
									   er.appraisal_type_id,
									   Count(1) assigned_total,
									   z.period_total
								FROM   emp_result er,
									   employee e,
									   appraisal_period p,
									   (SELECT appraisal_year,
											   appraisal_frequency_id,
											   Count(1) period_total
										FROM   appraisal_period
				";
				empty($request->period_id) ?: ($query_unassign .= " where period_id = ? " AND $qinput[] = $request->period_id);
				$query_unassign .= "
										GROUP  BY appraisal_year,
												  appraisal_frequency_id) z
								WHERE  er.emp_id = e.emp_id
									   AND er.period_id = p.period_id
									   AND p.appraisal_year = z.appraisal_year
									   AND p.appraisal_frequency_id = z.appraisal_frequency_id
									   AND e.chief_emp_code = ?
				";
				$qinput[] = Auth::id();
				empty($request->position_id) ?: ($query_unassign .= " and er.position_id = ? " AND $qinput[] = $request->position_id);
				empty($request->org_id) ?: ($query_unassign .= " and er.org_id = ? " AND $qinput[] = $request->org_id);
				empty($request->emp_code) ?: ($query_unassign .= " and e.emp_code = ? " AND $qinput[] = $request->emp_code);	
				empty($request->appraisal_level_id) ?: ($query_unassign .= " and e.level_id = ? " AND $qinput[] = $request->appraisal_level_id);
				empty($request->appraisal_type_id) ?: ($query_unassign .= " and er.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);	
				//empty($request->period_id) ?: ($query_unassign .= " and er.period_id = ? " AND $qinput[] = $request->period_id);
				empty($request->appraisal_year) ?: ($query_unassign .= " and p.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
				empty($request->frequency_id) ?: ($query_unassign .= " and p.appraisal_frequency_id = ? " AND $qinput[] = $request->frequency_id);
				empty($request->period_id) ?: ($query_unassign .= " and p.period_id = ? " AND $qinput[] = $request->period_id);			
				
				$query_unassign .= " GROUP  BY e.emp_code,
										  p.appraisal_year,
										  p.appraisal_frequency_id,er.appraisal_type_id) assigned
						WHERE  assigned_total = period_total ) union all ";
				
				$query_unassign .= "
					select distinct er.emp_result_id, er.status, e.emp_id, e.emp_code, e.emp_name, o.org_id, o.org_code, o.org_name, po.position_name, t.appraisal_type_name, t.appraisal_type_id, p.period_id, concat(p.appraisal_period_desc,' Start Date: ',p.start_date,' End Date: ',p.end_date) appraisal_period_desc
					From emp_result er, employee e, appraisal_type t, appraisal_item_result ir, appraisal_item I, appraisal_period p, org o, position po
					Where er.emp_id = e.emp_id and er.appraisal_type_id = t.appraisal_type_id
					And er.emp_result_id = ir.emp_result_id 
					and ir.item_id = I.item_id	
					and er.period_id = p.period_id
					and e.org_id = o.org_id
					and e.position_id = po.position_id
					and e.chief_emp_code = ? 
				";
				$qinput[] = Auth::id();
				
				empty($request->position_id) ?: ($query_unassign .= " and er.position_id = ? " AND $qinput[] = $request->position_id);
				empty($request->org_id) ?: ($query_unassign .= " and er.org_id = ? " AND $qinput[] = $request->org_id);
				empty($request->emp_code) ?: ($query_unassign .= " and e.emp_code = ? " AND $qinput[] = $request->emp_code);	
				empty($request->appraisal_level_id) ?: ($query_unassign .= " and e.level_id = ? " AND $qinput[] = $request->appraisal_level_id);
				empty($request->appraisal_type_id) ?: ($query_unassign .= " and er.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);	
				//empty($request->period_id) ?: ($query_unassign .= " and er.period_id = ? " AND $qinput[] = $request->period_id);	
				empty($request->appraisal_year) ?: ($query_unassign .= " and p.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
				empty($request->frequency_id) ?: ($query_unassign .= " and p.appraisal_frequency_id = ? " AND $qinput[] = $request->frequency_id);
				empty($request->period_id) ?: ($query_unassign .= " and p.period_id = ? " AND $qinput[] = $request->period_id);	
				
			} else {
			
				$query_unassign = "
					Select distinct null as emp_result_id,  'Unassigned' as status, null emp_id, null emp_code, null emp_name, o.org_id, o.org_code, o.org_name, null position_name, 'Organization' as appraisal_type_name, 1 appraisal_type_id, 0 period_id, 'Unassigned' appraisal_period_desc
					From org o
					Where o.is_active = 1
				";
				//empty($request->position_id) ?: ($query_unassign .= " and e.position_id = ? " AND $qinput[] = $request->position_id);
				empty($request->org_id) ?: ($query_unassign .= " and o.org_id = ? " AND $qinput[] = $request->org_id);
				//empty($request->emp_code) ?: ($query_unassign .= " and emp_code = ? " AND $qinput[] = $request->emp_code);
				empty($request->appraisal_level_id) ?: ($query_unassign .= " And o.level_id = ? " AND $qinput[] = $request->appraisal_level_id);			
				
				$query_unassign .= "
					and org_id not in 
					(SELECT org_id
						FROM   (SELECT o.org_id,
									   p.appraisal_year,
									   p.appraisal_frequency_id,
									   er.appraisal_type_id,
									   Count(1) assigned_total,
									   z.period_total
								FROM   emp_result er,
									   org o,
									   appraisal_period p,
									   (SELECT appraisal_year,
											   appraisal_frequency_id,
											   Count(1) period_total
										FROM   appraisal_period
					";
				empty($request->period_id) ?: ($query_unassign .= " where period_id = ? " AND $qinput[] = $request->period_id);
				$query_unassign .= "
										GROUP  BY appraisal_year,
												  appraisal_frequency_id) z
								WHERE  er.org_id = o.org_id
									   AND er.period_id = p.period_id
									   AND p.appraisal_year = z.appraisal_year
									   AND p.appraisal_frequency_id = z.appraisal_frequency_id
				";
				//empty($request->position_id) ?: ($query_unassign .= " and er.position_id = ? " AND $qinput[] = $request->position_id);
				empty($request->org_id) ?: ($query_unassign .= " and er.org_id = ? " AND $qinput[] = $request->org_id);
				//empty($request->emp_code) ?: ($query_unassign .= " and e.emp_code = ? " AND $qinput[] = $request->emp_code);	
				empty($request->appraisal_level_id) ?: ($query_unassign .= " and o.level_id = ? " AND $qinput[] = $request->appraisal_level_id);
				empty($request->appraisal_type_id) ?: ($query_unassign .= " and er.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
				empty($request->appraisal_year) ?: ($query_unassign .= " and p.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
				empty($request->frequency_id) ?: ($query_unassign .= " and p.appraisal_frequency_id = ? " AND $qinput[] = $request->frequency_id);
				empty($request->period_id) ?: ($query_unassign .= " and p.period_id = ? " AND $qinput[] = $request->period_id);
				
				$query_unassign .= " GROUP  BY o.org_id,
										  p.appraisal_year,
										  p.appraisal_frequency_id, er.appraisal_type_id) assigned
						WHERE  assigned_total >= period_total  ) union all ";
				
				$query_unassign .= "
					select distinct er.emp_result_id, er.status, null emp_id, null emp_code,  null emp_name, o.org_id, o.org_code, o.org_name, null position_name, t.appraisal_type_name, t.appraisal_type_id, p.period_id, concat(p.appraisal_period_desc,' Start Date: ',p.start_date,' End Date: ',p.end_date) appraisal_period_desc
					From emp_result er, org o, appraisal_type t, appraisal_item_result ir, appraisal_item I, appraisal_period p
					Where er.org_id = o.org_id and er.appraisal_type_id = t.appraisal_type_id
					And er.emp_result_id = ir.emp_result_id 
					and ir.item_id = I.item_id		
					and er.period_id = p.period_id
				";
				//empty($request->position_id) ?: ($query_unassign .= " and er.position_id = ? " AND $qinput[] = $request->position_id);
				empty($request->org_id) ?: ($query_unassign .= " and er.org_id = ? " AND $qinput[] = $request->org_id);
				//empty($request->emp_code) ?: ($query_unassign .= " and e.emp_code = ? " AND $qinput[] = $request->emp_code);	
				empty($request->appraisal_level_id) ?: ($query_unassign .= " And er.level_id = ? " AND $qinput[] = $request->appraisal_level_id);
				empty($request->appraisal_type_id) ?: ($query_unassign .= " and er.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);	
				empty($request->appraisal_year) ?: ($query_unassign .= " and p.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
				empty($request->frequency_id) ?: ($query_unassign .= " and p.appraisal_frequency_id = ? " AND $qinput[] = $request->frequency_id);
				empty($request->period_id) ?: ($query_unassign .= " and p.period_id = ? " AND $qinput[] = $request->period_id);			
			
			}
		}	
		
		$items = DB::select($query_unassign . " order by period_id,emp_code,org_code asc ", $qinput);
		
		// Get the current page from the url if it's not set default to 1
		empty($request->page) ? $page = 1 : $page = $request->page;
		
		// Number of items per page
		empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;
		
		$offSet = ($page * $perPage) - $perPage; // Start displaying items from this number

		// Get only the items you need using array_slice (only get 10 items since that's what you need)
		$itemsForCurrentPage = array_slice($items, $offSet, $perPage, false);
		
		// Return the paginator with only 10 items but with the count of all items and set the it on the correct page
		$result = new LengthAwarePaginator($itemsForCurrentPage, count($items), $perPage, $page);			

		$groups = array();
		foreach ($itemsForCurrentPage as $item) {
			$key = "p".$item->period_id;
			if (!isset($groups[$key])) {
				$groups[$key] = array(
					'items' => array($item),
					'appraisal_period_desc' => $item->appraisal_period_desc,
					'count' => 1,
				);
			} else {
				$groups[$key]['items'][] = $item;
				$groups[$key]['count'] += 1;
			}
		}		
		$resultT = $result->toArray();
		$resultT['group'] = $groups;
		return response()->json($resultT);			

	}
	
	public function assign_template(Request $request)
	{	
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}	
		$qinput = array();
		$query = "
			select a.item_id, a.item_name, a.structure_id, b.structure_name, b.nof_target_score, f.form_id, f.form_name, f.app_url, 
			if(ar.structure_weight_percent is null,c.weight_percent,ar.structure_weight_percent) weight_percent, 
			a.max_value, a.unit_deduct_score, e.no_weight, a.kpi_type_id, ar.structure_weight_percent
			from appraisal_item a
			left outer join appraisal_structure b
			on a.structure_id = b.structure_id
			left outer join form_type f
			on b.form_id = f.form_id
			left outer join appraisal_criteria c
			on b.structure_id = c.structure_id
			left outer join appraisal_item_level d
			on a.item_id = d.item_id
			left outer join appraisal_level e
			on d.level_id = e.level_id
			left outer join appraisal_item_result ar
			on a.item_id = ar.item_id
			and ar.emp_result_id = ?
			where a.is_active = 1
			and e.is_active = 1
		";
		$qinput[] = $request->emp_result_id;
		empty($request->appraisal_level_id) ?: ($query .= " and d.level_id = ? " AND $qinput[] = $request->appraisal_level_id);	
		empty($request->appraisal_level_id) ?: ($query .= " and c.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);	
		
		$qfooter = " order by b.seq_no, a.item_id, ar.structure_weight_percent desc ";
		
		$items = DB::select($query . $qfooter, $qinput);
		
		$groups = array();
		foreach ($items as $item) {
			$key = $item->structure_name;
			if (!isset($groups[$key])) {
				if ($item->form_name == 'Quantity') {
					$columns = [
						[
							'column_display' => 'Appraisal Item Name',
							'column_name' => 'item_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Appraisal Level',
							'column_name' => 'appraisal_level_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Structure',
							'column_name' => 'structure_name',
							'data_type' => 'text',
						],						
						[
							'column_display' => 'Perspective',
							'column_name' => 'perspective_name',
							'data_type' => 'text',
						],						
						[
							'column_display' => 'UOM',
							'column_name' => 'uom_name',
							'data_type' => 'text',
						],					
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],						
					];
				} elseif ($item->form_name == 'Quality') {
					$columns = [
						[
							'column_display' => 'Appraisal Item Name',
							'column_name' => 'item_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Appraisal Level',
							'column_name' => 'appraisal_level_name',
							'data_type' => 'text',
						],				
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],									
					];
				} elseif ($item->form_name == 'Deduct Score') {
					$columns = [
						[
							'column_display' => 'Appraisal Item Name',
							'column_name' => 'item_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Appraisal Level',
							'column_name' => 'appraisal_level_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Max Value',
							'column_name' => 'max_value',
							'data_type' => 'number',
						],						
						[
							'column_display' => 'Deduct Score/Unit',
							'column_name' => 'unit_deduct_score',
							'data_type' => 'number',
						],									
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],									
					];
				}
				
				$tc = DB::select("
					SELECT b.target_score, b.color_code
					FROM threshold_group a
					inner join threshold b on a.threshold_group_id = b.threshold_group_id
					where a.is_active = 1
					and b.structure_id = ?
					order by b.target_score asc				
				",array($item->structure_id));
				
				foreach (range(0,4,1) as $i) {
					if (array_key_exists($i,$tc)) {
					} else {
						$place_holder = ["target_score" => $i + 1, "color_code" => "DDDDDD"];
						$tc[] = $place_holder;
					}
				}
				
				// $check = DB::select("
					// select ifnull(max(a.end_threshold),0) max_no
					// from result_threshold a left outer join result_threshold_group b
					// on a.result_threshold_group_id = b.result_threshold_group_id
					// where b.is_active = 1
					// and b.result_type = 2
				// ");
				
				// if ($check[0]->max_no == 0) {
					// $total_weight = $item->weight_percent;
				// } else {
					// $total_weight = ($check[0]->max_no * $item->weight_percent) / 100;
				// }
				
				$total_weight = $item->weight_percent;
				
				$groups[$key] = array(
					'items' => array($item),
					'count' => 1,
					'columns' => $columns,
					'structure_id' => $item->structure_id,
					'form_id' => $item->form_id,
					'form_url' => $item->app_url,
					'nof_target_score' => $item->nof_target_score,
					'total_weight' => $total_weight,
					'no_weight' => $item->no_weight,
					'threshold' => $config->threshold,
					'threshold_color' => $tc
				);
			} else {
				$groups[$key]['items'][] = $item;
				$groups[$key]['count'] += 1;
			}
		}		
	//	$resultT = $items->toArray();
	//	$items['group'] = $groups
		return response()->json(['data' => $items, 'group' => $groups, 'result_type' => $config->result_type]);	

	}	
	
	public function store(Request $request)
	{
		$errors = array();
		$semp_code = array();
		
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}			
		
		// Config::set('mail.driver',$config->mail_driver);
		// Config::set('mail.host',$config->mail_host);
		// Config::set('mail.port',$config->mail_port);
		// Config::set('mail.encryption',$config->mail_encryption);
		// Config::set('mail.username',$config->mail_username);
		// Config::set('mail.password',$config->mail_password);		
		
		// hr cannot assign
		// $al = DB::select("
			// select b.appraisal_level_id, b.is_hr
			// from emp_level a
			// left outer join appraisal_level b
			// on a.appraisal_level_id = b.appraisal_level_id
			// where a.emp_code = ?
		// ", array(Auth::id()));
		
		// if (empty($al)) {
			// $is_hr = null;
			// $al_id = null;
		// } else {
			// $is_hr = $al[0]->is_hr;
			// $al_id = $al[0]->appraisal_level_id;
		// }			
		// if (empty($is_hr)) {
			// return response()->json(['status' => 400, 'data' => ['Invalid action for HR.']]);
		// }
		
		
		// if ($request->head_params['action_to'] > 16) {
			// return response()->json(['status' => 400, 'data' => ['Invalid action.']]);
			// // if ($request->head_params['action_to'] == 17 || $request->head_params['action_to'] == 25 || $request->head_params['action_to'] == 29) {
			// // } else {
				// // return response()->json(['status' => 400, 'data' => ['Invalid action.']]);
			// // }
		// }
		
		$validator = Validator::make($request->head_params, [
			'appraisal_type_id' => 'required',
			'appraisal_year' => 'required',
			'frequency_id' => 'required',			
			'action_to' => 'required'
		]);

		if ($validator->fails()) {
			$errors[] = ['item_id' => '', 'item_name' => '', 'data' => $validator->errors()];
		}			
		
		$frequency = AppraisalFrequency::find($request->head_params['frequency_id']);
		
		if (empty($frequency)) {
			return response()->json(['status' => 400, 'data' => ['Frequency not found.']]);
		}
		
		//$period_count = 12 / $frequency->frequency_month_value;
		
		$period_errors = array();
		
		if (empty($request->head_params['period_id'])) {
			// foreach (range(1,$period_count,1) as $p) {
				// $appraisal_period = AppraisalPeriod::where('appraisal_year',$request->head_params['appraisal_year'])->where('period_no',$p)->where('appraisal_frequency_id',$request->head_params['frequency_id']);
				// if ($appraisal_period->count() == 0) {
					// $period_errors[] = 'Appraisal Period not found for Appraisal Year: ' . $request->head_params['appraisal_year'] . ' Period Number: ' . $p . ' Appraisal Frequency ID: ' . $request->head_params['frequency_id'];
				// }			
			// }
			
			// if (!empty($period_errors)) {
				// return response()->json(['status' => 400, 'data' => $period_errors]);			
			//}			
			
			$period_check = DB::select("
				select period_id
				from appraisal_period
				where appraisal_year = ?
				and appraisal_frequency_id = ?
			", array($request->head_params['appraisal_year'], $request->head_params['frequency_id']));
			
			if (empty($period_check)) {
				return response()->json(['status' => 400, 'data' => ['Appraisal Period not found for Appraisal Year: ' . $request->head_params['appraisal_year'] . ' Appraisal Frequency ID: ' . $request->head_params['frequency_id']]]);
			}
			

			
		} else {
			$appraisal_period = AppraisalPeriod::where('appraisal_year',$request->head_params['appraisal_year'])->where('period_id',$request->head_params['period_id'])->where('appraisal_frequency_id',$request->head_params['frequency_id']);
			if ($appraisal_period->count() == 0) {
				$period_errors[] = 'Appraisal Period not found for Appraisal Year: ' . $request->head_params['appraisal_year'] . ' Period ID: ' . $request->head_params['period_id'] . ' Appraisal Frequency ID: ' . $request->head_params['frequency_id'];
				return response()->json(['status' => 400, 'data' => $period_errors]);
			}
			
		}
		

		
		foreach ($request->appraisal_items as $i) {
			if (array_key_exists ( 'form_id' , $i ) == false) {
				$i['form_id'] = 0;
			}
			
			if ($i['form_id'] == 1) {
				if (array_key_exists ( 'nof_target_score' , $i ) == false) {
					$i['nof_target_score'] = 0;
				}				
				if ($i['nof_target_score'] == 1) {
					$validator = Validator::make($i, [
						'item_id' => 'required|integer',
						'target_value' => 'required|numeric',
					//	'weight_percent' => 'required|numeric',
					]);
					if ($validator->fails()) {
						$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => $validator->errors()];
					}						
					
				} elseif ($i['nof_target_score'] == 2) {
					$validator = Validator::make($i, [
						'item_id' => 'required|integer',
						'target_value' => 'required|numeric',					
					//	'weight_percent' => 'required|numeric',
					]);			
					if ($validator->fails()) {
						$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => $validator->errors()];
					}						
					
				} elseif ($i['nof_target_score'] == 3) {
					$validator = Validator::make($i, [
						'item_id' => 'required|integer',
						'target_value' => 'required|numeric',						
					//	'weight_percent' => 'required|numeric',
					]);			
					if ($validator->fails()) {
						$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => $validator->errors()];
					}						
					
				} elseif ($i['nof_target_score'] == 4) {
					$validator = Validator::make($i, [
						'item_id' => 'required|integer',
						'target_value' => 'required|numeric',						
					//	'weight_percent' => 'required|numeric',
					]);			
					if ($validator->fails()) {
						$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => $validator->errors()];
					}						
					
				} elseif ($i['nof_target_score'] == 5) {
					$validator = Validator::make($i, [
						'item_id' => 'required|integer',
						'target_value' => 'required|numeric',							
					//	'weight_percent' => 'required|numeric',
					]);			
					if ($validator->fails()) {
						$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => $validator->errors()];
					}						
				} 
				else {
				//	$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => 'Invalid Number of Target Score.'];	
					$validator = Validator::make($i, [
						'item_id' => 'required|integer',
						'target_value' => 'required|numeric',							
					//	'weight_percent' => 'required|numeric',
					]);			
					if ($validator->fails()) {
						$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => $validator->errors()];
					}								
				}

			} elseif ($i['form_id'] == 2) {
			
				$validator = Validator::make($i, [
					'item_id' => 'required|integer',
					'target_value' => 'required|numeric',
				//	'weight_percent' => 'required|numeric',
				]);

				if ($validator->fails()) {
					$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => $validator->errors()];
				}			
			
			} elseif ($i['form_id'] == 3) {
			
				$validator = Validator::make($i, [
					'item_id' => 'required|integer',
					'max_value' => 'required|numeric',
					'deduct_score_unit' => 'required|numeric',
				]);

				if ($validator->fails()) {
					$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => $validator->errors()];
				}				
			
			} else {
				$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => 'Invalid Form.'];
			}
		}
		
		if (count($errors) > 0) {
			return response()->json(['status' => 400, 'data' => $errors]);
		}
		
		if (empty($request->employees)) {
			return response()->json(['status' => 200, 'data' => []]);
		}
		
		$already_assigned = array();
		
		foreach ($request->employees as $e) {
			// $check_unassign = DB::select("
				// select emp_code
				// from emp_result
				// where emp_code = ?
			// ", array($e['emp_code']));
			


			if (empty($request->head_params['period_id'])) {
				foreach ($period_check as $p) {
					// $appraisal_period = AppraisalPeriod::where('appraisal_year',$request->head_params['appraisal_year'])->where('period_no',$p)->where('appraisal_frequency_id',$request->head_params['frequency_id']);
					$period_id = $p->period_id;
					$qinput = array();
					
					if ($request->head_params['appraisal_type_id'] == 2) {
					
						$query_unassign = "
							 select emp_id 
							 from emp_result
							 where emp_id = ?
							 and period_id = ?
							 and appraisal_type_id = 2
						";
						$qinput[] = $e['emp_id'];
					
					} else {
						$query_unassign = "
							 select org_id 
							 from emp_result
							 where org_id = ?
							 and period_id = ?
							 and appraisal_type_id = 1
						";
						$qinput[] = $e['org_id'];					
					}
					
					$qinput[] = $period_id;
				//	empty($request->position_code) ?: ($query_unassign .= " and e.position_code = ? " AND $qinput[] = $request->position_code);
				//	empty($request->emp_code) ?: ($query_unassign .= " and e.emp_code = ? " AND $qinput[] = $request->emp_code);	
				//	empty($request->appraisal_level_id) ?: ($query_unassign .= " and I.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
					//empty($request->head_params['appraisal_type_id']) ?: ($query_unassign .= " and appraisal_type_id = ? " AND $qinput[] = $request->head_params['appraisal_type_id']);	
					
					$check_unassign = DB::select($query_unassign,$qinput);	
					$rtg_id = ResultThresholdGroup::where('is_active',1)->first();
					empty($rtg_id) ? $rtg_id = null : $rtg_id = $rtg_id->result_threshold_group_id; 
					if (empty($check_unassign)) {
						$stage = WorkflowStage::find($request->head_params['action_to']);
						
						if ($request->head_params['appraisal_type_id'] == 2) {
							$employee = Employee::find($e['emp_code']);
							if (empty($employee)) {
								$chief_emp_code = null;
								$chief_emp_id = null;
								$level_id = null;
								$org_id = null;
								$position_id = null;
								$emp_id = null;
							} else {
								$chief_emp_code = $employee->chief_emp_code;
								$chief_emp_id = Employee::where('emp_code',$chief_emp_code)->first();
								empty($chief_emp_id) ? $chief_emp_id = null : $chief_emp_id = $chief_emp_id->emp_id;
								$level_id = $employee->level_id;
								$org_id = $employee->org_id;
								$position_id = $employee->position_id;
								$emp_id = $e['emp_id'];
							}
						} else {
							$org = Org::find($e['org_id']);
							$chief_emp_code = null;
							$chief_emp_id = null;
							$level_id = $org->level_id;
							$org_id = $e['org_id'];
							$position_id = null;	
							$emp_id = null;
						}
						$emp_result = new EmpResult;
						$emp_result->appraisal_type_id = $request->head_params['appraisal_type_id'];
						$emp_result->period_id = $period_id;
						$emp_result->emp_id = $emp_id;
						$emp_result->level_id = $level_id;
						$emp_result->org_id = $org_id;
						$emp_result->position_id = $position_id;
						$emp_result->chief_emp_id = $chief_emp_id;
						$emp_result->result_threshold_group_id = $rtg_id;
						$emp_result->result_score = 0;
						$emp_result->b_rate = 0;
						$emp_result->b_amount = 0;
						$emp_result->grade = null;
						$emp_result->raise_amount = 0;
						$emp_result->new_s_amount = 0;
						$emp_result->status = $stage->status;
						$emp_result->stage_id = $stage->stage_id;
						$emp_result->created_by = Auth::id();
						$emp_result->updated_by = Auth::id();
						$emp_result->save();
						
						$emp_stage = new EmpResultStage;
						$emp_stage->emp_result_id = $emp_result->emp_result_id;
						$emp_stage->stage_id = $stage->stage_id;
						$emp_stage->remark = $request->head_params['remark'];;
						$emp_stage->created_by = Auth::id();
						$emp_stage->updated_by = Auth::id();
						$emp_stage->save();
						
						$mail_error = [];
						
						// if ($request->head_params['appraisal_type_id'] == 1) {
							// try {
								// $chief_emp = Employee::where('emp_code',$employee->chief_emp_code)->first();
								
								// $data = ["chief_emp_name" => $chief_emp->emp_name, "emp_name" => $employee->emp_name, "status" => $stage->status];
								// $to = [$employee->email, $chief_emp->email];
								
								// $from = $config->mail_username;
								
								// Mail::send('emails.status', $data, function($message) use ($from, $to)
								// {
									// $message->from($from, 'SEE-KPI System');
									// $message->to($to)->subject('ระบบได้ทำการประเมิน');
								// });			
							// } catch (Exception $e) {
								// $mail_error[] = $e->getMessage();
							// //	$mail_error = 'has error';
							// }		
						// }
						
						$semp_code[] = ['emp_id' => $e['emp_id'], 'org_id' => $org_id, 'period_id' => $period_id, 'mail_error' => $mail_error];
						
						$tg_id = ThresholdGroup::where('is_active',1)->first();
						empty($tg_id) ? $tg_id = null : $tg_id = $tg_id->threshold_group_id;
						
						foreach ($request->appraisal_items as $i) {
							if ($i['form_id'] == 1) {		
								$aitem = new AppraisalItemResult;
								$aitem->emp_result_id = $emp_result->emp_result_id;
								$aitem->kpi_type_id = $i['kpi_type_id'];
								$aitem->period_id = $period_id;
								$aitem->emp_id = $emp_id;
								$aitem->chief_emp_id = $chief_emp_id;
								$aitem->level_id = $level_id;
								$aitem->org_id = $org_id;
								$aitem->position_id = $position_id;
								$aitem->item_id = $i['item_id'];
								$aitem->item_name = $i['item_name'];
								$aitem->target_value = $i['target_value'];
								$aitem->weight_percent = $i['weight_percent'];
								array_key_exists('score0', $i) ? $aitem->score0 = $i['score0'] : null;
								array_key_exists('score1', $i) ? $aitem->score1 = $i['score1'] : null;
								array_key_exists('score2', $i) ? $aitem->score2 = $i['score2'] : null;
								array_key_exists('score3', $i) ? $aitem->score3 = $i['score3'] : null;
								array_key_exists('score4', $i) ? $aitem->score4 = $i['score4'] : null;
								array_key_exists('score5', $i) ? $aitem->score5 = $i['score5'] : null;
								array_key_exists('forecast_value', $i) ? $aitem->forecast_value = $i['forecast_value'] : null;
								$aitem->over_value = 0;
								$aitem->weigh_score = 0;
								$aitem->structure_weight_percent = $i['total_weight'];
								$aitem->threshold_group_id = $tg_id;
								$aitem->created_by = Auth::id();
								$aitem->updated_by = Auth::id();
								$aitem->save();					

							} elseif ($i['form_id'] == 2) {

								$aitem = new AppraisalItemResult;
								$aitem->emp_result_id = $emp_result->emp_result_id;
								$aitem->period_id = $period_id;
								$aitem->emp_id = $emp_id;
								$aitem->chief_emp_id = $chief_emp_id;
								$aitem->level_id = $level_id;
								$aitem->org_id = $org_id;
								$aitem->position_id = $position_id;
								$aitem->item_id = $i['item_id'];
								$aitem->item_name = $i['item_name'];
								$aitem->target_value = $i['target_value'];
								$aitem->weight_percent = $i['weight_percent'];
								$aitem->over_value = 0;
								$aitem->weigh_score = 0;
								$aitem->structure_weight_percent = $i['total_weight'];
								$aitem->threshold_group_id = $tg_id;
								$aitem->created_by = Auth::id();
								$aitem->updated_by = Auth::id();
								$aitem->save();
								
							} elseif ($i['form_id'] == 3) {
						
								$aitem = new AppraisalItemResult;
								$aitem->emp_result_id = $emp_result->emp_result_id;
								$aitem->period_id = $period_id;
								$aitem->emp_id = $emp_id;
								$aitem->chief_emp_id = $chief_emp_id;
								$aitem->level_id = $level_id;
								$aitem->org_id = $org_id;
								$aitem->position_id = $position_id;
								$aitem->item_id = $i['item_id'];
								$aitem->item_name = $i['item_name'];
								$aitem->max_value = $i['max_value'];
								$aitem->deduct_score_unit = $i['deduct_score_unit'];
								$aitem->weight_percent = 0;
								$aitem->over_value = 0;
								$aitem->weigh_score = 0;
								$aitem->structure_weight_percent = $i['total_weight'];
								$aitem->threshold_group_id = $tg_id;
								$aitem->created_by = Auth::id();
								$aitem->updated_by = Auth::id();
								$aitem->save();
							
							} 	
						}						
					} else {
						$already_assigned = ['emp_id' => $e['emp_id'], 'org_id' => $e['org_id'], 'period_id' => $period_id];
					}					
				} 
			} else {
				$appraisal_period = AppraisalPeriod::where('appraisal_year',$request->head_params['appraisal_year'])->where('period_id',$request->head_params['period_id'])->where('appraisal_frequency_id',$request->head_params['frequency_id']);	
				$period_id = $appraisal_period->first()->period_id;
				$qinput = array();
				
				if ($request->head_params['appraisal_type_id'] == 2) {
					$query_unassign = "
						 select emp_id 
						 from emp_result
						 Where emp_id = ?
						 and period_id = ?
						 and appraisal_type_id = 2
					";
					$qinput[] = $e['emp_id'];
				} else {
					$query_unassign = "
						 select org_id 
						 from emp_result
						 Where org_id = ?
						 and period_id = ?
						 and appraisal_type_id = 1
					";
					$qinput[] = $e['org_id'];				
				}
				
				$qinput[] = $period_id;
				//empty($request->position_code) ?: ($query_unassign .= " and e.position_code = ? " AND $qinput[] = $request->position_code);
				//empty($request->emp_code) ?: ($query_unassign .= " and e.emp_code = ? " AND $qinput[] = $request->emp_code);	
				//empty($request->appraisal_level_id) ?: ($query_unassign .= " and I.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
				//empty($request->head_params['appraisal_type_id']) ?: ($query_unassign .= " and appraisal_type_id = ? " AND $qinput[] =  $request->head_params['appraisal_type_id']);	
				
				$check_unassign = DB::select($query_unassign,$qinput);	
				$rtg_id = ResultThresholdGroup::where('is_active',1)->first();
				empty($rtg_id) ? $rtg_id = null : $rtg_id = $rtg_id->result_threshold_group_id; 				
				if (empty($check_unassign)) {
					$stage = WorkflowStage::find($request->head_params['action_to']);
					
					if ($request->head_params['appraisal_type_id'] == 2) {
						$employee = Employee::find($e['emp_code']);
						if (empty($employee)) {
							$chief_emp_code = null;
							$chief_emp_id = null;
							$level_id = null;
							$org_id = null;
							$position_id = null;
							$emp_id = null;
						} else {
							$chief_emp_code = $employee->chief_emp_code;
							$chief_emp_id = Employee::where('emp_code',$chief_emp_code)->first();
							empty($chief_emp_id) ? $chief_emp_id = null : $chief_emp_id = $chief_emp_id->emp_id;
							$level_id = $employee->level_id;
							$org_id = $employee->org_id;
							$position_id = $employee->position_id;
							$emp_id = $e['emp_id'];
						}
					} else {
						$org = Org::find($e['org_id']);
						$chief_emp_code = null;
						$chief_emp_id = null;
						$level_id = $org->level_id;
						$org_id = $e['org_id'];
						$position_id = null;	
						$emp_id = null;
					}
						
					$emp_result = new EmpResult;
					// $emp_result->appraisal_type_id = $request->head_params['appraisal_type_id'];
					// $emp_result->period_id = $period_id;
					// $emp_result->emp_code = $e['emp_code'];
					// $emp_result->department_code = $employee->department_code;
					// $emp_result->department_name = $employee->department_name;
					// $emp_result->section_code = $employee->section_code;
					// $emp_result->section_name = $employee->section_name;
					// $emp_result->position_code = $employee->position_code;
					// $emp_result->position_name = $employee->position_name;					
					// $emp_result->chief_emp_code = $chief_emp_code;
					// $emp_result->result_score = 0;
					// $emp_result->b_rate = 0;
					// $emp_result->b_amount = 0;
					// $emp_result->grade = null;
					// $emp_result->raise_amount = 0;
					// $emp_result->new_s_amount = 0;
					// $emp_result->status = $stage->status;
					// $emp_result->stage_id = $stage->stage_id;
					// $emp_result->created_by = Auth::id();
					// $emp_result->updated_by = Auth::id();
					// $emp_result->save();
					
					$emp_result->appraisal_type_id = $request->head_params['appraisal_type_id'];
					$emp_result->period_id = $period_id;
					$emp_result->emp_id = $emp_id;
					$emp_result->level_id = $level_id;
					$emp_result->org_id = $org_id;
					$emp_result->position_id = $position_id;
					$emp_result->chief_emp_id = $chief_emp_id;
					$emp_result->result_threshold_group_id = $rtg_id;
					$emp_result->result_score = 0;
					$emp_result->b_rate = 0;
					$emp_result->b_amount = 0;
					$emp_result->grade = null;
					$emp_result->raise_amount = 0;
					$emp_result->new_s_amount = 0;
					$emp_result->status = $stage->status;
					$emp_result->stage_id = $stage->stage_id;
					$emp_result->created_by = Auth::id();
					$emp_result->updated_by = Auth::id();
					$emp_result->save();					
					
					$emp_stage = new EmpResultStage;
					$emp_stage->emp_result_id = $emp_result->emp_result_id;
					$emp_stage->stage_id = $stage->stage_id;
					$emp_stage->remark = $request->head_params['remark'];
					$emp_stage->created_by = Auth::id();
					$emp_stage->updated_by = Auth::id();
					$emp_stage->save();
					
					$mail_error = [];
					
					// if ($request->head_params['appraisal_type_id'] == 1) {
						// try {
							// $chief_emp = Employee::where('emp_code',$employee->chief_emp_code)->first();
							
							// $data = ["chief_emp_name" => $chief_emp->emp_name, "emp_name" => $employee->emp_name, "status" => $stage->status];
							// $to = [$employee->email, $chief_emp->email];
							
							// $from = $config->mail_username;
							
							// Mail::send('emails.status', $data, function($message) use ($from, $to)
							// {
								// $message->from($from, 'SEE-KPI System');
								// $message->to($to)->subject('ระบบได้ทำการประเมิน');
							// });			
						// } catch (Exception $e) {
							// $mail_error[] = $e->getMessage();
						// //	$mail_error = 'has error';
						// }		
					// }					
					$semp_code[] = ['emp_id' => $e['emp_id'], 'org_id' => $org_id, 'period_id' => $period_id, 'mail_error' => $mail_error];
					
					$tg_id = ThresholdGroup::where('is_active',1)->first();
					empty($tg_id) ? $tg_id = null : $tg_id = $tg_id->threshold_group_id;	
					
					foreach ($request->appraisal_items as $i) {
						if ($i['form_id'] == 1) {		

							$aitem = new AppraisalItemResult;
							$aitem->emp_result_id = $emp_result->emp_result_id;
							$aitem->kpi_type_id = $i['kpi_type_id'];
							$aitem->period_id = $period_id;
							$aitem->emp_id = $emp_id;
							$aitem->chief_emp_id = $chief_emp_id;
							$aitem->org_id = $org_id;
							$aitem->position_id = $position_id;
							$aitem->level_id = $level_id;
							$aitem->item_id = $i['item_id'];
							$aitem->item_name = $i['item_name'];
							$aitem->target_value = $i['target_value'];
							$aitem->weight_percent = $i['weight_percent'];
							array_key_exists('score0', $i) ? $aitem->score0 = $i['score0'] : null;
							array_key_exists('score1', $i) ? $aitem->score1 = $i['score1'] : null;
							array_key_exists('score2', $i) ? $aitem->score2 = $i['score2'] : null;
							array_key_exists('score3', $i) ? $aitem->score3 = $i['score3'] : null;
							array_key_exists('score4', $i) ? $aitem->score4 = $i['score4'] : null;
							array_key_exists('score5', $i) ? $aitem->score5 = $i['score5'] : null;
							array_key_exists('forecast_value', $i) ? $aitem->forecast_value = $i['forecast_value'] : null;
							$aitem->over_value = 0;
							$aitem->weigh_score = 0;
							$aitem->threshold_group_id = $tg_id;
							$aitem->structure_weight_percent = $i['total_weight'];
							$aitem->created_by = Auth::id();
							$aitem->updated_by = Auth::id();
							$aitem->save();										
			
						} elseif ($i['form_id'] == 2) {

							$aitem = new AppraisalItemResult;
							$aitem->emp_result_id = $emp_result->emp_result_id;
							$aitem->period_id = $period_id;
							$aitem->emp_id = $emp_id;
							$aitem->chief_emp_id = $chief_emp_id;
							$aitem->org_id = $org_id;
							$aitem->position_id = $position_id;
							$aitem->level_id = $level_id;
							$aitem->item_id = $i['item_id'];
							$aitem->item_name = $i['item_name'];
							$aitem->target_value = $i['target_value'];
							$aitem->weight_percent = $i['weight_percent'];
							$aitem->over_value = 0;
							$aitem->weigh_score = 0;
							$aitem->threshold_group_id = $tg_id;
							$aitem->structure_weight_percent = $i['total_weight'];
							$aitem->created_by = Auth::id();
							$aitem->updated_by = Auth::id();
							$aitem->save();
							
						} elseif ($i['form_id'] == 3) {
					
							$aitem = new AppraisalItemResult;
							$aitem->emp_result_id = $emp_result->emp_result_id;
							$aitem->period_id = $period_id;
							$aitem->emp_id = $emp_id;
							$aitem->chief_emp_id = $chief_emp_id;
							$aitem->org_id = $org_id;
							$aitem->position_id = $position_id;
							$aitem->level_id = $level_id;
							$aitem->item_id = $i['item_id'];
							$aitem->item_name = $i['item_name'];
							$aitem->max_value = $i['max_value'];
							$aitem->deduct_score_unit = $i['deduct_score_unit'];
							$aitem->weight_percent = 0;
							$aitem->over_value = 0;
							$aitem->weigh_score = 0;
							$aitem->threshold_group_id = $tg_id;
							$aitem->structure_weight_percent = $i['total_weight'];
							$aitem->created_by = Auth::id();
							$aitem->updated_by = Auth::id();
							$aitem->save();
						
						} 	
					}	
			
				} else {
					$already_assigned[] = ['emp_id' => $e['emp_id'], 'org_id' => $e['org_id'], 'period_id' => $period_id];
				}
			
			}
		}
		
		return response()->json(['status' => 200, 'data' => $semp_code, 'already_assigned' => $already_assigned]);
	}	

	public function show(Request $request, $emp_result_id)
	{
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}				
		$head = DB::select("
			SELECT b.emp_id, b.emp_code, b.emp_name, b.working_start_date, h.position_name, g.org_name, g.org_code, pg.org_name parent_org_name, b.chief_emp_code, e.emp_name chief_emp_name, c.appraisal_period_desc, d.appraisal_type_name, a.stage_id, f.status, f.edit_flag
			FROM emp_result a
			left outer join employee b
			on a.emp_id = b.emp_id
			left outer join appraisal_period c
			on c.period_id = a.period_id
			left outer join appraisal_type d
			on a.appraisal_type_id = d.appraisal_type_id
			left outer join employee e
			on b.chief_emp_code = e.emp_code
			left outer join appraisal_stage f
			on a.stage_id = f.stage_id
			left outer join org g
			on a.org_id = g.org_id
			left outer join org pg
			on g.parent_org_code = pg.org_code
			left outer join position h
			on a.position_id = h.position_id
			where a.emp_result_id = ?
		", array($emp_result_id));
		
		$items = DB::select("
			select b.item_name, b.structure_id, a.*
			from appraisal_item_result a
			left outer join appraisal_item b
			on a.item_id = b.item_id		
			where a.emp_result_id = ?
		", array($emp_result_id));
		
		$stage = DB::select("
			SELECT a.created_by, a.created_dttm, b.from_action, b.to_action, a.remark
			FROM emp_result_stage a
			left outer join appraisal_stage b
			on a.stage_id = b.stage_id
			where a.emp_result_id = ?
			order by a.created_dttm asc		
		", array($emp_result_id));
		
		return response()->json(['head' => $head, 'data' => $items, 'stage' => $stage, 'threshold' => $config->threshold]);		
	}
	
	public function update(Request $request, $emp_result_id)
	{
		$errors = array();
		
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}			
		
		// Config::set('mail.driver',$config->mail_driver);
		// Config::set('mail.host',$config->mail_host);
		// Config::set('mail.port',$config->mail_port);
		// Config::set('mail.encryption',$config->mail_encryption);
		// Config::set('mail.username',$config->mail_username);
		// Config::set('mail.password',$config->mail_password);	
		
		// if ($request->head_params['action_to'] > 16) {
			// if ($request->head_params['action_to'] == 17 || $request->head_params['action_to'] == 25 || $request->head_params['action_to'] == 29) {
			// } else {
				// return response()->json(['status' => 400, 'data' => 'Invalid action.']);
			// }
		// }
		
		$validator = Validator::make($request->head_params, [
			'appraisal_type_id' => 'required',
			'period_id' => 'required',		
			'action_to' => 'required'
		]);
		
		if ($validator->fails()) {
			$errors[] = ['item_id' => '', 'item_name' => '', 'data' => $validator->errors()];
		}			
		
		foreach ($request->appraisal_items as $i) {
			
			if ($i['select_flag'] == 1) {
				if (array_key_exists ( 'form_id' , $i ) == false) {
					$i['form_id'] = 0;
				}
				
				if ($i['form_id'] == 1) {
					if (array_key_exists ( 'nof_target_score' , $i ) == false) {
						$i['nof_target_score'] = 0;
					}				
					if ($i['nof_target_score'] == 1) {
						$validator = Validator::make($i, [
							'item_id' => 'required|integer',
							'target_value' => 'required|numeric',
						//	'weight_percent' => 'required|numeric',
						]);
						if ($validator->fails()) {
							$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => $validator->errors()];
						}						
						
					} elseif ($i['nof_target_score'] == 2) {
						$validator = Validator::make($i, [
							'item_id' => 'required|integer',
							'target_value' => 'required|numeric',					
						//	'weight_percent' => 'required|numeric',
						]);			
						if ($validator->fails()) {
							$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => $validator->errors()];
						}						
						
					} elseif ($i['nof_target_score'] == 3) {
						$validator = Validator::make($i, [
							'item_id' => 'required|integer',
							'target_value' => 'required|numeric',					
						//	'weight_percent' => 'required|numeric',
						]);			
						if ($validator->fails()) {
							$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => $validator->errors()];
						}						
						
					} elseif ($i['nof_target_score'] == 4) {
						$validator = Validator::make($i, [
							'item_id' => 'required|integer',
							'target_value' => 'required|numeric',					
						//	'weight_percent' => 'required|numeric',
						]);			
						if ($validator->fails()) {
							$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => $validator->errors()];
						}						
						
					} elseif ($i['nof_target_score'] == 5) {
						$validator = Validator::make($i, [
							'item_id' => 'required|integer',
							'target_value' => 'required|numeric',							
						//	'weight_percent' => 'required|numeric',
						]);			
						if ($validator->fails()) {
							$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => $validator->errors()];
						}					
					}  else {
						// $errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => 'Invalid Number of Target Score.'];				
						$validator = Validator::make($i, [
							'item_id' => 'required|integer',
							'target_value' => 'required|numeric',							
						//	'weight_percent' => 'required|numeric',
						]);			
						if ($validator->fails()) {
							$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => $validator->errors()];
						}									
					}

				} elseif ($i['form_id'] == 2) {
				
					$validator = Validator::make($i, [
						'item_id' => 'required|integer',
						'target_value' => 'required|numeric',
					//	'weight_percent' => 'required|numeric',
					]);

					if ($validator->fails()) {
						$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => $validator->errors()];
					}			
				
				} elseif ($i['form_id'] == 3) {
				
					$validator = Validator::make($i, [
						'item_id' => 'required|integer',
						'max_value' => 'required|numeric',
						'deduct_score_unit' => 'required|numeric',
					]);

					if ($validator->fails()) {
						$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => $validator->errors()];
					}				
				
				} else {
					$errors[] = ['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'data' => 'Invalid Form.'];
				}
			} else {
				// select flag false
			}
		}
		
		if (count($errors) > 0) {
			return response()->json(['status' => 400, 'data' => $errors]);
		}
		
		$stage = WorkflowStage::find($request->head_params['action_to']);
		$emp_result = EmpResult::find($emp_result_id);
		$emp_result->status = $stage->status;
		$emp_result->stage_id = $stage->stage_id;
		$emp_result->updated_by = Auth::id();
		$emp_result->save();
		
		$emp_stage = new EmpResultStage;
		$emp_stage->emp_result_id = $emp_result->emp_result_id;
		$emp_stage->stage_id = $stage->stage_id;
		$emp_stage->remark = $request->head_params['remark'];
		$emp_stage->created_by = Auth::id();
		$emp_stage->updated_by = Auth::id();
		$emp_stage->save();
		
		$mail_error = '';
		
		if ($emp_result->appraisal_type_id == 2) {
			$employee = Employee::where('emp_id',$emp_result->emp_id)->first();
			if (empty($employee)) {
				$chief_emp_code = null;
				$chief_emp_id = null;
				$position_id = null;
				$level_id = null;
				$org_id = null;
			} else {
				$chief_emp_code = $employee->chief_emp_code;
				$chief_emp_id = Employee::where('emp_code',$chief_emp_code)->first();
				empty($chief_emp_id) ? $chief_emp_id = null : $chief_emp_id = $chief_emp_id->emp_id;
				$position_id = $employee->position_id;
				$level_id = $employee->level_id;
				$org_id = $employee->org_id;
				
				
				// try {
					// $chief_emp = Employee::where('emp_code',$employee->chief_emp_code)->first();
					
					// $data = ["chief_emp_name" => $chief_emp->emp_name, "emp_name" => $employee->emp_name, "status" => $stage->status];
					// $to = [$employee->email, $chief_emp->email];
					
					// $from = $config->mail_username;
					
					// Mail::send('emails.status', $data, function($message) use ($from, $to)
					// {
						// $message->from($from, 'SEE-KPI System');
						// $message->to($to)->subject('ระบบได้ทำการประเมิน');
					// });			
				// } catch (Exception $e) {
					// $mail_error = $e->getMessage();

				// }		
										
			}		
		} else {
				$org = Org::find($emp_result->org_id);
				$chief_emp_code = null;
				$chief_emp_id = null;
				$position_id = null;
				$level_id = $org->level_id;
				$org_id = $emp_result->org_id;		
		}
		
		$tg_id = ThresholdGroup::where('is_active',1)->first();
		empty($tg_id) ? $tg_id = null : $tg_id = $tg_id->threshold_group_id;
		
		foreach ($request->appraisal_items as $i) {
			if ($i['select_flag'] == 1) {
				if ($i['form_id'] == 1) {		
					$aitem = AppraisalItemResult::find($i['item_result_id']);
					if (empty($aitem)) {
						$aitem = new AppraisalItemResult;
						$aitem->org_id = $org_id;
						$aitem->position_id = $position_id;
						$aitem->level_id = $level_id;
						$aitem->chief_emp_id = $chief_emp_id;
						$aitem->kpi_type_id = $i['kpi_type_id'];
						$aitem->structure_weight_percent = $i['total_weight'];
						$aitem->created_by = Auth::id();
					}
					$aitem->emp_result_id = $emp_result->emp_result_id;
					$aitem->period_id = $request->head_params['period_id'];
					$aitem->emp_id = $emp_result->emp_id;
					$aitem->item_id = $i['item_id'];
					$aitem->item_name = $i['item_name'];
					$aitem->target_value = $i['target_value'];
					$aitem->weight_percent = $i['weight_percent'];
					array_key_exists('score0', $i) ? $aitem->score0 = $i['score0'] : null;
					array_key_exists('score1', $i) ? $aitem->score1 = $i['score1'] : null;
					array_key_exists('score2', $i) ? $aitem->score2 = $i['score2'] : null;
					array_key_exists('score3', $i) ? $aitem->score3 = $i['score3'] : null;
					array_key_exists('score4', $i) ? $aitem->score4 = $i['score4'] : null;
					array_key_exists('score5', $i) ? $aitem->score5 = $i['score5'] : null;
					array_key_exists('forecast_value', $i) ? $aitem->forecast_value = $i['forecast_value'] : null;
					$aitem->over_value = 0;
					$aitem->weigh_score = 0;
					$aitem->threshold_group_id = $tg_id;
					$aitem->updated_by = Auth::id();
					$aitem->save();					

				} elseif ($i['form_id'] == 2) {

					$aitem = AppraisalItemResult::find($i['item_result_id']);
					if (empty($aitem)) {
						$aitem = new AppraisalItemResult;
						$aitem->org_id = $org_id;
						$aitem->position_id = $position_id;
						$aitem->level_id = $level_id;
						$aitem->chief_emp_id = $chief_emp_id;	
						$aitem->structure_weight_percent = $i['total_weight'];
						$aitem->created_by = Auth::id();
					}
					$aitem->emp_result_id = $emp_result->emp_result_id;
					$aitem->period_id = $request->head_params['period_id'];
					$aitem->emp_id = $emp_result->emp_id;
					$aitem->item_id = $i['item_id'];
					$aitem->item_name = $i['item_name'];
					$aitem->target_value = $i['target_value'];
					$aitem->weight_percent = $i['weight_percent'];
					$aitem->over_value = 0;
					$aitem->weigh_score = 0;
					$aitem->threshold_group_id = $tg_id;
					$aitem->updated_by = Auth::id();
					$aitem->save();
					
				} elseif ($i['form_id'] == 3) {
			
					$aitem = AppraisalItemResult::find($i['item_result_id']);
					if (empty($aitem)) {
						$aitem = new AppraisalItemResult;
						$aitem->org_id = $org_id;
						$aitem->position_id = $position_id;
						$aitem->level_id = $level_id;
						$aitem->chief_emp_id = $chief_emp_id;
						$aitem->structure_weight_percent = $i['total_weight'];
						$aitem->created_by = Auth::id();
					}
					$aitem->emp_result_id = $emp_result->emp_result_id;
					$aitem->period_id = $request->head_params['period_id'];
					$aitem->emp_id = $emp_result->emp_id;
					$aitem->item_id = $i['item_id'];
					$aitem->item_name = $i['item_name'];
					$aitem->max_value = $i['max_value'];
					$aitem->deduct_score_unit = $i['deduct_score_unit'];
					$aitem->weight_percent = 0;
					$aitem->over_value = 0;
					$aitem->weigh_score = 0;
					$aitem->threshold_group_id = $tg_id;
					$aitem->updated_by = Auth::id();
					$aitem->save();
				
				} 
			} else {
				// select flag false
				$aitem = AppraisalItemResult::find($i['item_result_id']);
				if (!empty($aitem)) {
					$aitem->delete();
				}
			}
		}		
		
		return response()->json(['status' => 200, 'mail_error' => $mail_error]);
		
	}
	
	public function destroy($emp_result_id)
	{
	
		try {
			$item = EmpResult::findOrFail($emp_result_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 400, 'data' => 'Appraisal Assignment not found.']);
		}	

		try {
			if ($item->status == 'Assigned' || $item->status == 'Reject' || $item->status == 'Draft') {
				EmpResultStage::where('emp_result_id',$item->emp_result_id)->delete();
				AppraisalItemResult::where('emp_result_id',$item->emp_result_id)->delete();			
				$item->delete();
			} else {
				return response()->json(['status' => 400, 'data' => 'Cannot delete Appraisal Assignment at this stage.']);
			}
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Appraisal Assignment is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);	
		
	}
	
}
