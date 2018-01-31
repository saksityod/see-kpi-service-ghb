<?php

namespace App\Http\Controllers;

use App\EmpResult;
use App\WorkflowStage;
use App\AppraisalItemResult;
use App\AppraisalLevel;
use App\EmpResultStage;
use App\ActionPlan;
use App\Reason;
use App\AttachFile;
use App\SystemConfiguration;

use Auth;
use DB;
use File;
use Validator;
use Excel;
use Config;
use Mail;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AppraisalController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function year_list()
	{
		$items = DB::select("
			Select distinct appraisal_year appraisal_year_id, appraisal_year
			from appraisal_period 
			order by appraisal_year desc
		");
		return response()->json($items);
	}
	
	public function period_list(Request $request)
	{
		$items = DB::select("
			Select period_id, period_no, appraisal_period_desc
			from appraisal_period
			where appraisal_year = ?
			order by period_id asc
		", array($request->appraisal_year));
		return response()->json($items);
	}	
	
    public function al_list()
    {
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
			
			//echo $in_emp;
			$items = DB::select("
				select distinct al.level_id, al.appraisal_level_name
				from employee el, appraisal_level al
				where el.level_id = al.level_id
				and el.emp_code in ({$in_emp})
				and al.is_hr = 0
				order by al.level_id asc
			");
		}
		
		return response()->json($items);
    }
		
	public function auto_org_name(Request $request)
	{	
	
		$all_emp = DB::select("
			SELECT sum(b.is_all_employee) count_no
			from employee a
			left outer join appraisal_level b
			on a.level_id = b.level_id
			where emp_code = ?
		", array(Auth::id()));
		
		if ($all_emp[0]->count_no > 0) {
			$qinput = array();
			$query = "
				Select distinct org_id, org_code, org_name
				From org
				Where org_name like ?
			";
			
			$qfooter = " Order by org_name limit 10";
			$qinput[] = '%'.$request->org_name.'%';
			
			$items = DB::select($query.$qfooter,$qinput);
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
					and is_active = 1			
					and chief_emp_code != emp_code
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
			
			$qinput = array();
			$query = "
				Select distinct b.org_id, b.org_code, b.org_name
				From employee a left outer join org b on a.org_id = b.org_id
				Where b.org_name like ?
				and a.emp_code in ({$in_emp})
				
			";
			
			$qfooter = " Order by b.org_name limit 10";
			$qinput[] = '%'.$request->org_name.'%';
			
			$items = DB::select($query.$qfooter,$qinput);			
			
		}
		
		return response()->json($items);		
	}		
	
	public function auto_position_name(Request $request)
	{	
	
		$all_emp = DB::select("
			SELECT sum(b.is_all_employee) count_no
			from employee a
			left outer join appraisal_level b
			on a.level_id = b.level_id
			where emp_code = ?
		", array(Auth::id()));
		
		if ($all_emp[0]->count_no > 0) {
			$qinput = array();
			$query = "
				Select distinct position_id, position_code, position_name
				From position
				Where position_name like ?
			";
			
			$qfooter = " Order by position_name limit 10";
			$qinput[] = '%'.$request->position_name.'%';
			
			$items = DB::select($query.$qfooter,$qinput);
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
					and is_active = 1			
					and chief_emp_code != emp_code
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
			
			$qinput = array();
			$query = "
				Select distinct b.position_id, b.position_code, b.position_name
				From employee a left outer join position b on a.position_id = b.position_id
				Where b.position_name like ?
				and a.emp_code in ({$in_emp})
				
			";
			
			$qfooter = " Order by b.position_name limit 10";
			$qinput[] = '%'.$request->position_name.'%';
			
			$items = DB::select($query.$qfooter,$qinput);			
			
		}
		
		return response()->json($items);		
	}
	
	public function auto_employee_name(Request $request)
	{
	
		$all_emp = DB::select("
			SELECT sum(b.is_all_employee) count_no
			from employee a
			left outer join appraisal_level b
			on a.level_id = b.level_id
			where emp_code = ?
		", array(Auth::id()));
		
		if ($all_emp[0]->count_no > 0) {
			$qinput = array();
			$query = "
				Select emp_id, emp_code, emp_name
				From employee
				Where emp_name like ?
			";
			
			$qfooter = " Order by emp_name limit 10 ";
			$qinput[] = '%'.$request->emp_name.'%';
			empty($request->org_id) ?: ($query .= " and org_id = ? " AND $qinput[] = $request->section_code);
			empty($request->position_id) ?: ($query .= " and position_id = ? " AND $qinput[] = $request->position_code);
			
			$items = DB::select($query.$qfooter,$qinput);
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
			$qinput = array();
			$query = "
				Select emp_id, emp_code, emp_name
				From employee
				Where emp_name like ?
				and emp_code in ({$in_emp})
			";
			
			$qfooter = " Order by emp_name limit 10 ";
			$qinput[] = '%'.$request->emp_name.'%';
			empty($request->org_id) ?: ($query .= " and org_id = ? " AND $qinput[] = $request->org_id);
			empty($request->position_id) ?: ($query .= " and position_id = ? " AND $qinput[] = $request->position_id);
			
			$items = DB::select($query.$qfooter,$qinput);			
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
			$query = "
				select a.emp_result_id, a.emp_id, b.emp_code, b.emp_name, d.appraisal_level_name, e.appraisal_type_id, e.appraisal_type_name, p.position_name, o.org_code, o.org_name, po.org_name parent_org_name, f.to_action, a.stage_id, g.period_id, concat(g.appraisal_period_desc,' Start Date: ',g.start_date,' End Date: ',g.end_date) appraisal_period_desc
				from emp_result a
				left outer join employee b
				on a.emp_id = b.emp_id
				left outer join appraisal_level d
				on a.level_id = d.level_id
				left outer join appraisal_type e
				on a.appraisal_type_id = e.appraisal_type_id
				left outer join appraisal_stage f
				on a.stage_id = f.stage_id
				left outer join appraisal_period g
				on a.period_id = g.period_id
				left outer join position p
				on a.position_id = p.position_id
				left outer join org o
				on a.org_id = o.org_id
				left outer join org po
				on o.parent_org_code = po.org_code
				where d.is_hr = 0
			";		
				
			empty($request->appraisal_year) ?: ($query .= " and g.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
			empty($request->period_no) ?: ($query .= " and g.period_id = ? " AND $qinput[] = $request->period_no);
			empty($request->level_id) ?: ($query .= " and a.level_id = ? " AND $qinput[] = $request->level_id);
			empty($request->appraisal_type_id) ?: ($query .= " and a.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
			empty($request->org_id) ?: ($query .= " and a.org_id = ? " AND $qinput[] = $request->org_id);
			empty($request->position_id) ?: ($query .= " and a.position_id = ? " AND $qinput[] = $request->position_id);
			empty($request->emp_id) ?: ($query .= " And a.emp_id = ? " AND $qinput[] = $request->emp_id);
			/*
			echo $query. " order by period_id,emp_code,org_code  asc ";
			print_r($qinput);
			*/
			$items = DB::select($query. " order by period_id,emp_code,org_code  asc ", $qinput);
			
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
					and is_active = 1			
					and chief_emp_code != emp_code
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
			
			if ($request->appraisal_type_id == 2) {
				$query = "
					select a.emp_result_id, b.emp_code, b.emp_name, d.appraisal_level_name, e.appraisal_type_id, e.appraisal_type_name, p.position_name, o.org_code, o.org_name, po.org_name parent_org_name, f.to_action, a.stage_id, g.period_id, concat(g.appraisal_period_desc,' Start Date: ',g.start_date,' End Date: ',g.end_date) appraisal_period_desc
					from emp_result a
					left outer join employee b
					on a.emp_id = b.emp_id
					left outer join appraisal_level d
					on a.level_id = d.level_id
					left outer join appraisal_type e
					on a.appraisal_type_id = e.appraisal_type_id
					left outer join appraisal_stage f
					on a.stage_id = f.stage_id
					left outer join appraisal_period g
					on a.period_id = g.period_id
					left outer join position p
					on a.position_id = p.position_id
					left outer join org o
					on a.org_id = o.org_id
					left outer join org po
					on o.parent_org_code = po.org_code
					where d.is_hr = 0
					and b.emp_code in ({$in_emp})
				";		
					
				empty($request->appraisal_year) ?: ($query .= " and g.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
				empty($request->period_no) ?: ($query .= " and g.period_id = ? " AND $qinput[] = $request->period_no);
				empty($request->level_id) ?: ($query .= " and a.level_id = ? " AND $qinput[] = $request->level_id);
				empty($request->appraisal_type_id) ?: ($query .= " and a.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
				empty($request->org_id) ?: ($query .= " and a.org_id = ? " AND $qinput[] = $request->org_id);
				empty($request->position_id) ?: ($query .= " and a.position_id = ? " AND $qinput[] = $request->position_id);
				empty($request->emp_id) ?: ($query .= " And a.emp_id = ? " AND $qinput[] = $request->emp_id);
				
				/*
				echo $query. " order by period_id,emp_code,org_code  asc ";
				echo "<br>";
				print_r($qinput);
				*/
				
				$items = DB::select($query. " order by period_id,emp_code,org_code  asc ", $qinput);	
				
			} else {
			
				$query = "
					select a.emp_result_id, b.emp_code, b.emp_name, d.appraisal_level_name, e.appraisal_type_id, e.appraisal_type_name, p.position_name, o.org_code, o.org_name, po.org_name parent_org_name, f.to_action, a.stage_id, g.period_id, concat(g.appraisal_period_desc,' Start Date: ',g.start_date,' End Date: ',g.end_date) appraisal_period_desc
					from emp_result a
					left outer join employee b
					on a.emp_id = b.emp_id
					left outer join appraisal_level d
					on a.level_id = d.level_id
					left outer join appraisal_type e
					on a.appraisal_type_id = e.appraisal_type_id
					left outer join appraisal_stage f
					on a.stage_id = f.stage_id
					left outer join appraisal_period g
					on a.period_id = g.period_id
					left outer join position p
					on a.position_id = p.position_id
					left outer join org o
					on a.org_id = o.org_id
					left outer join org po
					on o.parent_org_code = po.org_code
					where d.is_hr = 0
				";		
					
				empty($request->appraisal_year) ?: ($query .= " and g.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
				empty($request->period_no) ?: ($query .= " and g.period_id = ? " AND $qinput[] = $request->period_no);
				empty($request->level_id) ?: ($query .= " and a.level_id = ? " AND $qinput[] = $request->level_id);
				empty($request->appraisal_type_id) ?: ($query .= " and a.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
				empty($request->org_id) ?: ($query .= " and a.org_id = ? " AND $qinput[] = $request->org_id);
				empty($request->position_id) ?: ($query .= " and a.position_id = ? " AND $qinput[] = $request->position_id);
				empty($request->emp_id) ?: ($query .= " And a.emp_id = ? " AND $qinput[] = $request->emp_id);
				
				$items = DB::select($query. " order by period_id,emp_code,org_code  asc ", $qinput);			
			
			}
	
		}
		
		
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
	
	public function show(Request $request, $emp_result_id)
	{
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		
		$head = DB::select("
			SELECT b.emp_code, b.emp_name, b.working_start_date, p.position_name, o.org_code, o.org_name, po.org_name parent_org_name, b.chief_emp_code, e.emp_name chief_emp_name, c.appraisal_period_desc, a.appraisal_type_id, d.appraisal_type_name, a.stage_id, f.status, a.result_score, f.edit_flag, al.no_weight
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
			left outer join position p
			on b.position_id = p.position_id
			left outer join org o
			on a.org_id = o.org_id
			left outer join org po
			on o.parent_org_code = po.org_code
			left outer join appraisal_level al
			on a.level_id = al.level_id
			where a.emp_result_id = ?
		", array($emp_result_id));
		
		$items = DB::select("
			select DISTINCT b.item_name,uom.uom_name, b.structure_id, c.structure_name, d.form_id, d.app_url, c.nof_target_score, a.*, e.perspective_name, a.weigh_score, f.weigh_score total_weigh_score, a.weight_percent, g.weight_percent total_weight_percent, al.no_weight,
			if(ifnull(a.target_value,0) = 0,0,(ifnull(a.actual_value,0)/a.target_value)*100) achievement, a.percent_achievement, h.result_threshold_group_id
			from appraisal_item_result a
			left outer join appraisal_item b
			on a.item_id = b.item_id	
			left outer join appraisal_structure c
			on b.structure_id = c.structure_id
			left outer join form_type d
			on c.form_id = d.form_id
			left outer join perspective e
			on b.perspective_id = e.perspective_id
			left outer join structure_result f
			on a.emp_result_id = f.emp_result_id
			and c.structure_id = f.structure_id
			left outer join appraisal_criteria g
			on c.structure_id = g.structure_id
			and a.level_id = g.appraisal_level_id	
			left outer join appraisal_level al
			on a.level_id = al.level_id
			left outer join emp_result h
			on a.emp_result_id = h.emp_result_id
			left join uom on  b.uom_id= uom.uom_id
			where a.emp_result_id = ?
			order by b.item_id
		", array($emp_result_id));
		
		$groups = array();
		foreach ($items as $item) {
			$key = $item->structure_name;
			$color = DB::select("
				select color_code
				from result_threshold
				where ? between begin_threshold and end_threshold
				and result_threshold_group_id = ?
			", array($item->percent_achievement, $item->result_threshold_group_id));
			
			if (empty($color)) {
				$minmax = DB::select("
					select min(begin_threshold) min_threshold, max(end_threshold) max_threshold
					from result_threshold
					where result_threshold_group_id = ?		
				",array($item->result_threshold_group_id));
				
				if (empty($minmax)) {
					$item->color = 0;
				} else {
					if ($item->percent_achievement < $minmax[0]->min_threshold) {
						$get_color = DB::select("
							select color_code
							from result_threshold
							where result_threshold_group_id = ?
							and begin_threshold = ?
						", array($item->result_threshold_group_id, $minmax[0]->min_threshold));
						$item->color = $get_color[0]->color_code;
					} elseif ($item->percent_achievement > $minmax[0]->max_threshold) {
						$get_color = DB::select("
							select color_code
							from result_threshold
							where result_threshold_group_id = ?
							and end_threshold = ?
						", array($item->result_threshold_group_id, $minmax[0]->max_threshold));
						$item->color = $get_color[0]->color_code;					
					} else {
						$item->color = 0;
					}				
				}
			} else {
				$item->color = $color[0]->color_code;
			}
			
			$hint = array();
			if ($item->form_id == 2) {
				$hint = DB::select("
					select concat(a.target_score,' = ',a.threshold_name) hint
					from threshold a
					left outer join threshold_group b
					on a.threshold_group_id = b.threshold_group_id
					where b.is_active = 1
					and a.structure_id=?
					order by target_score asc				
				", array($item->structure_id));
			}
			
			/*
			$check = DB::select("
				select ifnull(max(a.end_threshold),0) max_no
				from result_threshold a left outer join result_threshold_group b
				on a.result_threshold_group_id = b.result_threshold_group_id
				where b.result_threshold_group_id = ?
				and b.result_type = 2
			", array($item->result_threshold_group_id));

			if ($check[0]->max_no == 0) {
				$total_weight = $item->structure_weight_percent;
			} else {
				$total_weight = ($check[0]->max_no * $item->structure_weight_percent) / 100;
			}	

			*/

			$check = DB::select("
				SELECT nof_target_score as max_no FROM 
			appraisal_structure
			where  structure_id=?
			", array($item->structure_id));

			$total_weight = ($check[0]->max_no * $item->structure_weight_percent) / 100;

					
			
			if (!isset($groups[$key])) {
				$groups[$key] = array(
					'items' => array($item),
					'count' => 1,
					'form_id' => $item->form_id,
					'form_url' => $item->app_url,
					'nof_target_score' => $item->nof_target_score,
					'total_weight' => $total_weight,
					'hint' => $hint,
					'total_weigh_score' => $item->total_weigh_score,
					'no_weight' => $item->no_weight,
					'threshold' => $config->threshold,
					'result_type' => $config->result_type
				);
			} else {
				$groups[$key]['items'][] = $item;
			//	$groups[$key]['total_weight'] += $item->weight_percent;
				$groups[$key]['count'] += 1;
			}
		}		
	//	$resultT = $items->toArray();
	//	$items['group'] = $groups;
	
		$stage = DB::select("
			SELECT a.created_by, a.created_dttm, b.from_action, b.to_action, a.remark
			FROM emp_result_stage a
			left outer join appraisal_stage b
			on a.stage_id = b.stage_id
			where a.emp_result_id = ?
			order by a.created_dttm asc		
		", array($emp_result_id));
		
		return response()->json(['head' => $head, 'data' => $items, 'group' => $groups, 'stage' => $stage]);		
			
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
			and stage_id > 16
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
				and stage_id > 16
			", array($al_id));
		}
		
		return response()->json($items);	
	}
	
	public function edit_action_to(Request $request)
	{
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
		
		// $items = DB::select("
			// select stage_id, to_action
			// from workflow_stage 
			// where from_stage_id = ?		
			// and to_appraisal_level_id = ?
			// and from_appraisal_level_id = ?
			// and stage_id > 16
		// ", array($request->stage_id, $request->to_appraisal_level_id, $al_id));
		
		// if (empty($items)) {
			// $workflow = WorkflowStage::find($request->stage_id);
			// empty($workflow->to_stage_id) ? $to_stage_id = "null" : $to_stage_id = $workflow->to_stage_id;
			// $items = DB::select("	
				// select a.stage_id, a.to_action
				// from workflow_stage a
				// left outer join appraisal_level b
				// on a.to_appraisal_level_id = b.appraisal_level_id
				// where stage_id in ({$to_stage_id})
				// and to_appraisal_level_id = ?
				// and from_appraisal_level_id = ?
				// and stage_id > 16
			// ", array($request->to_appraisal_level_id, $al_id));
		// }
		
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
			and appraisal_flag = 1
		" . $hr_query, array($request->stage_id));
		
		if (empty($items)) {
			$workflow = WorkflowStage::find($request->stage_id);
			empty($workflow->to_stage_id) ? $to_stage_id = "null" : $to_stage_id = $workflow->to_stage_id;
			$items = DB::select("	
				select stage_id, to_action
				from appraisal_stage a
				where stage_id in ({$to_stage_id})
				and appraisal_flag = 1
			" . $hr_query);
		}
		
		return response()->json($items);	
		
	}
	
	public function update(Request $request, $emp_result_id)
	{
		// if ($request->stage_id < 14) {
			// return response()->json(['status' => 400, 'data' => 'Invalid action.']);
		// }
		
		// $checklevel = DB::select("
			// select appraisal_level_id
			// from emp_level
			// where emp_code = ?
		// ", array(Auth::id()));
		
		// if (empty($checklevel)) {
			// return response()->json(['status' => 400, 'data' => 'Permission Denied.']);
		// } else {
			// $alevel = AppraisalLevel::find($checklevel[0]->appraisal_level_id);
			// if ($alevel->is_hr == 1) {
				// return response()->json(['status' => 400, 'data' => 'Permission Denied for HR user.']);
			// }
			
			// $checkop = DB::select("
				// select appraisal_level_id
				// from appraisal_level
				// where parent_id = ?
			// ", array($alevel->appraisal_level_id));
			
			// if (empty($checkop)) {
				// return response()->json(['status' => 400, 'data' => 'Permission Denied for Operation Level user.']);
			// }
			
		// }
		
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
		
		if (!empty($request->appraisal)) {
			foreach ($request->appraisal as $a) {
				$aresult = AppraisalItemResult::find($a['item_result_id']);
				if (empty($aresult)) {
				} else {
					array_key_exists('score', $a) ? $aresult->score = $a['score'] : null;
					array_key_exists('forecast_value', $a) ? $aresult->forecast_value = $a['forecast_value'] : null;
					array_key_exists('actual_value', $a) ? $aresult->actual_value = $a['actual_value'] : null;
					$aresult->updated_by = Auth::id();
					$aresult->save();
				}
			}
		}
		
		$stage = WorkflowStage::find($request->stage_id);
		$emp = EmpResult::find($emp_result_id);
		$emp->stage_id = $request->stage_id;
		$emp->status = $stage->status;
		$emp->updated_by = Auth::id();
		$emp->save();
		
		$emp_stage = new EmpResultStage;
		$emp_stage->emp_result_id = $emp_result_id;
		$emp_stage->stage_id = $request->stage_id;
		$emp_stage->remark = $request->remark;
		$emp_stage->created_by = Auth::id();
		$emp_stage->updated_by = Auth::id();
		$emp_stage->save();	
		
		$mail_error = '';
		// if ($emp->appraisal_type_id == 1) {
			
			// try {
				// $employee = Employee::where('emp_id',$emp->emp_id)->first();
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
		// }
		
		//if ($request->stage_id == 22 || $request->stage_id == 27 || $request->stage_id == 29) {
		// if ($request->stage_id == 19 || $request->stage_id == 25 || $request->stage_id == 29) {
			// $items = DB::select("
				// select a.appraisal_item_result_id, ifnull(a.score,0) score, a.weight_percent
				// from appraisal_item_result a
				// left outer join emp_result b
				// on a.emp_result_id = b.emp_result_id
				// left outer join appraisal_item c
				// on a.appraisal_item_id = c.appraisal_item_id
				// left outer join appraisal_structure d
				// on c.structure_id = d.structure_id
				// where d.form_id = 2
				// and b.emp_result_id = ?
			// ", array($emp_result_id));
			
			// foreach ($items as $i) {
				// $uitem = AppraisalItemResult::find($i->appraisal_item_result_id);
				// $uitem->weigh_score = $i->score * $i->weight_percent;
				// $uitem->updated_by = Auth::id();
				// $uitem->save();
			// }	
		// }
		
		return response()->json(['status' => 200, 'mail_error' => $mail_error]);
	}
	
	public function calculate_weight(Request $request)
	{
		$items = DB::select("
			select a.appraisal_item_result_id, ifnull(a.score,0) score, a.weight_percent
			from appraisal_item_result a
			left outer join emp_result b
			on a.emp_result_id = b.emp_result_id
			left outer join appraisal_item c
			on a.appraisal_item_id = c.appraisal_item_id
			left outer join appraisal_structure d
			on c.structure_id = d.structure_id
			where d.form_id = 2
			and b.appraisal_type_id = ?
			and a.period_id = ?
			and a.emp_code = ?
			and a.appraisal_item_id = ?
		", array($request->appraisal_type_id, $request->period_id, $request->emp_code, $request->appraisal_item_id));
		
		foreach ($items as $i) {
			$uitem = AppraisalItemResult::find($i->appraisal_item_result_id);
			$uitem->weigh_score = $i->score * $i->weight_percent;
			$uitem->updated_by = Auth::id();
			$uitem->save();
		}
		
		return response()->json(['status' => 200]);
	
	}
	
	public function phase_list(Request $request)
	{
		$items = DB::select("
			select phase_id, phase_name
			from phase
			where is_active = 1
			and item_result_id=?
			order by phase_id asc
		", array($request->item_result_id));

		return response()->json($items);
	}
	
	public function add_action(Request $request, $item_result_id)
	{
		try {
			$item_result = AppraisalItemResult::findOrFail($item_result_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Item Result not found.']);
		}		
		
		$actions = $request->actions;
		
		if (empty($actions)) {
			return response()->json(['status' => 400, 'data' => "Require at least 1 Action"]);		
		}
		
		$errors = array();
		$successes = array();
		
		foreach ($actions as $a) {
		
			$validator = Validator::make($a, [
				//'phase_id' => 'required|integer',
				'action_plan_name' => 'required|max:255',
				'plan_start_date' => 'date|date_format:Y-m-d',
				'plan_end_date' => 'date|date_format:Y-m-d',
				'actual_start_date' => 'date|date_format:Y-m-d',
				'actual_end_date' => 'date|date_format:Y-m-d',
				'completed_percent' => 'numeric'
			]);
			if ($validator->fails()) {
				$errors[] = ['action_plan_name' => $a['action_plan_name'], 'error' => $validator->errors()];
			} else {
				$item = new ActionPlan;
				$item->fill($a);
				$item->item_result_id = $item_result_id;
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
				$item->save();
				$successes[] = ['action_plan_name' => $a['action_plan_name']];
			}			
		}
		return response()->json(['status' => 200, 'data' => ["success" => $successes, "error" => $errors]]);
			
	}
	
	public function update_action(Request $request, $item_result_id)
	{
		$errors = array();
		$successes = array();
		
		$actions = $request->actions;
		
		
		if (empty($actions)) {
			return response()->json(['status' => 200, 'data' => ["success" => [], "error" => []]]);
		}
		
		foreach ($actions as $a) {
			$item = ActionPlan::find($a["action_plan_id"]);
			if (empty($item)) {
				$errors[] = ["action_plan_id" => $a["action_plan_id"]];
			} else {
				$validator = Validator::make($a, [
					'phase_id' => 'required|integer',
					'action_plan_name' => 'required|max:255',
					'plan_start_date' => 'date|date_format:Y-m-d',
					'plan_end_date' => 'date|date_format:Y-m-d',
					'actual_start_date' => 'date|date_format:Y-m-d',
					'actual_end_date' => 'date|date_format:Y-m-d',
					'completed_percent' => 'numeric'
				]);

				if ($validator->fails()) {
					$errors[] = ["action_plan_id" => $a["action_plan_id"], "error" => $validator->errors()];
				} else {
					$item->fill($a);
					$item->updated_by = Auth::id();
					$item->save();
					$sitem = ["action_plan_id" => $item->action_plan_id];
					$successes[] = $sitem;					
				}			

			}
		}
		
		return response()->json(['status' => 200, 'data' => ["success" => $successes, "error" => $errors]]);		
	}
	
	public function show_action($item_result_id)
	{
		$header = DB::select("
			select a.item_result_id, a.threshold_group_id, b.item_name, c.emp_id, c.emp_code, c.emp_name, d.org_id, d.org_code, d.org_name, a.target_value, a.actual_value, a.forecast_value, e.appraisal_type_id,
			if(ifnull(a.forecast_value,0) = 0,0,(ifnull(a.actual_value,0)/a.forecast_value)*100) actual_vs_forecast,
			if(ifnull(a.target_value,0) = 0,0,(ifnull(a.actual_value,0)/a.target_value)*100) actual_vs_target
			from appraisal_item_result a
			left outer join appraisal_item b
			on a.item_id = b.item_id
			left outer join employee c
			on a.emp_id = c.emp_id
			left outer join org d
			on a.org_id = d.org_id
			left outer join emp_result e
			on a.emp_result_id = e.emp_result_id
			where item_result_id = ?
		",array($item_result_id));
		
		$threshold_color = DB::select("
			select color_code
			from threshold
			where threshold_group_id = ?
			order by target_score asc
		",array($header[0]->threshold_group_id));
		
		$result_threshold_color = DB::select("
			select begin_threshold, end_threshold, color_code
			from appraisal_item_result a
			left outer join emp_result b
			on a.emp_result_id = b.emp_result_id
			left outer join result_threshold c
			on b.result_threshold_group_id = c.result_threshold_group_id
			where a.item_result_id = ?
			order by begin_threshold desc		
		", array($item_result_id));
		
		$header[0]->threshold_color = $threshold_color;
		$header[0]->result_threshold_color = $result_threshold_color;
		
		$actions = DB::select("
			select a.*, b.emp_name responsible, c.phase_name
			from action_plan a
			left outer join employee b
			on a.emp_id = b.emp_id
			left outer join phase c
			on a.phase_id = c.phase_id
			where a.item_result_id = ?
			order by a.action_plan_id asc
		", array($item_result_id));

		return response()->json(['header' => $header[0], 'actions' => $actions]);
	}
	
	public function delete_action(Request $request)
	{
		$errors = array();
		$successes = array();
		
		$actions = $request->actions;
		
		
		if (empty($actions)) {
			return response()->json(['status' => 200, 'data' => ["success" => [], "error" => []]]);
		}
		
		foreach ($actions as $a) {
			$item = ActionPlan::find($a["action_plan_id"]);
			if (empty($item)) {
				$errors[] = ["action_plan_id" => "Action Plan ID " . $a["action_plan_id"] . " not found."];
			} else {
				$item->delete();
				$successes[] = $a["action_plan_id"];
			}
		}
		
		return response()->json(['status' => 200, 'data' => ["success" => $successes, "error" => $errors]]);
	}
	
	public function add_reason(Request $request, $item_result_id)
	{
		try {
			$item_result = AppraisalItemResult::findOrFail($item_result_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Item Result not found.']);
		}		
		
		$validator = Validator::make($request->all(), [
			'reason_name' => 'required|max:255'
		]);
		
		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new Reason;
			$item->reason_name = $request->reason_name;
			$item->item_result_id = $item_result_id;
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}			
		
		return response()->json(['status' => 200, 'data' => $item]);
			
	}	
	
	public function show_reason($item_result_id,$reason_id)
	{
		try {
			$item = Reason::findOrFail($reason_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Reason not found.']);
		}
		return response()->json($item);
			
	}
	
	public function list_reason($item_result_id)
	{
		$items = DB::select("
			SELECT @rownum := @rownum + 1 AS rank, a.reason_id, a.reason_name, a.created_dttm
			FROM reason a, (SELECT @rownum := 0) b
			where a.item_result_id = ?
			order by a.created_dttm asc
		", array($item_result_id));
		return response()->json($items);
	}
	
	public function update_reason(Request $request, $item_result_id)
	{
		try {
			$item = Reason::findOrFail($request->reason_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Reason not found.']);
		}
		
		$validator = Validator::make($request->all(), [
			'reason_name' => 'required|max:255'
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item->reason_name = $request->reason_name;
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);
				
	}
	
	public function delete_reason(Request $request, $item_result_id)
	{
		try {
			$item = Reason::findOrFail($request->reason_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Reason not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Reason is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
	
	public function auto_action_employee_name(Request $request)
	{
		$qinput = array();
		$query = "
			Select emp_id, emp_code, emp_name
			From employee
			Where emp_name like ?
		";
		
		$qfooter = " Order by emp_name limit 10 ";
		$qinput[] = '%'.$request->emp_name.'%';

		
		$items = DB::select($query.$qfooter,$qinput);	
		
		return response()->json($items);
		
	}


	public function appraisal_upload_files(Request $request,$item_result_id )
	{
		
		
		
		$result = array();	
			
			$path = $_SERVER['DOCUMENT_ROOT'] . '/see_api/public/attach_files/' . $item_result_id . '/';
			foreach ($request->file() as $f) {
				$filename = iconv('UTF-8','windows-874',$f->getClientOriginalName());
				//$f->move($path,$filename);
				$f->move($path,$f->getClientOriginalName());
				//echo $filename;
				
				$item = AttachFile::firstOrNew(array('doc_path' => 'attach_files/' . $item_result_id . '/' . $f->getClientOriginalName()));
				
				$item->item_result_id = $item_result_id;
				$item->created_by = Auth::id();
				
				//print_r($item);
				$item->save();
				$result[] = $item;
				//echo "hello".$f->getClientOriginalName();

			}
		
		return response()->json(['status' => 200, 'data' => $result]);
	}

	public function upload_files_list(Request $request)
	{
		$items = DB::select("
			SELECT result_doc_id,doc_path 
			FROM appraisal_item_result_doc
			where  item_result_id=?
			order by result_doc_id;
		", array($request->item_result_id));

		return response()->json($items);
	}


	public function delete_file(Request $request){

		try {
			$item = AttachFile::findOrFail($request->result_doc_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'File not found.']);
		}
		           //$_SERVER['DOCUMENT_ROOT'] . '/see_api/public/attach_files/' . $item_result_id . '/';
		File::Delete($_SERVER['DOCUMENT_ROOT'] . '/see_api/public/'.$item->doc_path);	
		$item->delete();

		return response()->json(['status' => 200]);

	}
	
}


