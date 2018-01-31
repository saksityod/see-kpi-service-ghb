<?php

namespace App\Http\Controllers;

use App\EmpLevel;
use App\Employee;
use App\Position;
use App\Org;

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

class ImportEmployeeController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function import(Request $request)
	{
		$errors = array();
		foreach ($request->file() as $f) {
			$items = Excel::load($f, function($reader){})->get();	
			foreach ($items as $i) {
						
				$validator = Validator::make($i->toArray(), [
					'employee_code' => 'required|max:255',
					'employee_name' => 'required|max:255',
					'working_start_date_yyyy_mm_dd' => 'date|date_format:Y-m-d',
					'probation_end_date_yyyy_mm_dd' => 'date|date_format:Y-m-d',
					'acting_end_date_yyyy_mm_dd' => 'date|date_format:Y-m-d',
					'organization_code' => 'required',
					'position_code' => 'required',
					'chief_employee_code' => 'max:255',
					//'salary_amount' => 'numeric|digits_between:1,10',
					'email' => 'required|email|max:100',	
					'employee_type' => 'max:50',
				]);

				$org = Org::where('org_code',$i->organization_code)->first();
				$position = Position::where('position_code',$i->position_code)->first();
				
				empty($org) ? $org_id = null : $org_id = $org->org_id;
				empty($position) ? $position_id = null : $position_id = $position->position_id;
				
				if ($validator->fails()) {
					$errors[] = ['employee_code' => $i->employee_code, 'errors' => $validator->errors()];
				} else {
					$emp = Employee::where('emp_code',$i->employee_code)->first();
					if (empty($emp)) {
						$emp = new Employee;
						$emp->emp_code = $i->employee_code;
						$emp->emp_name = $i->employee_name;
						$emp->working_start_date = $i->working_start_date_yyyy_mm_dd;
						$emp->probation_end_date = $i->probation_end_date_yyyy_mm_dd;
						$emp->acting_end_date = $i->acting_end_date_yyyy_mm_dd;
						$emp->org_id = $org_id;
						$emp->position_id = $position_id;
						$emp->chief_emp_code = $i->chief_employee_code;
						$emp->s_amount = $i->salary_amount;
						$emp->email = $i->email;
						$emp->emp_type = $i->employee_type;
						$emp->is_active = 1;					
						$emp->created_by = Auth::id();
						$emp->updated_by = Auth::id();
						try {
							$emp->save();
						} catch (Exception $e) {
							$errors[] = ['employee_code' => $i->employee_code, 'errors' => substr($e,0,254)];
						}
					} else {
						$emp->emp_name = $i->employee_name;
						$emp->working_start_date = $i->working_start_date_yyyy_mm_dd;
						$emp->probation_end_date = $i->probation_end_date_yyyy_mm_dd;
						$emp->acting_end_date = $i->acting_end_date_yyyy_mm_dd;
						$emp->org_id = $org_id;
						$emp->position_id = $position_id;
						$emp->chief_emp_code = $i->chief_employee_code;
						$emp->s_amount = $i->salary_amount;
						$emp->email = $i->email;
						$emp->emp_type = $i->employee_type;
						$emp->is_active = 1;					
						$emp->updated_by = Auth::id();
						try {
							$emp->save();
						} catch (Exception $e) {
							$errors[] = ['employee_code' => $i->employee_code, 'errors' => substr($e,0,254)];
						}
					}
				}					
			}
		}
		return response()->json(['status' => 200, 'errors' => $errors]);
	}
	
	public function index(Request $request)
	{	
		$qinput = array();
		$query = "
			select a.emp_id, a.emp_code, a.emp_name, c.org_name, d.appraisal_level_name, b.position_name, a.chief_emp_code, a.emp_type
			From employee a left outer join position b
			on a.position_id = b.position_id
			left outer join org c
			on a.org_id = c.org_id
			left outer join appraisal_level d
			on a.level_id = d.level_id
			Where 1=1
		";
				
		empty($request->org_id) ?: ($query .= " AND a.org_id = ? " AND $qinput[] = $request->org_id);
		empty($request->position_id) ?: ($query .= " And a.position_id = ? " AND $qinput[] = $request->position_id);
		empty($request->emp_code) ?: ($query .= " And a.emp_code = ? " AND $qinput[] = $request->emp_code);
		
		$qfooter = " Order by a.emp_code ";
		
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
	
    public function role_list()
    {
		$items = DB::select("
			select appraisal_level_id, appraisal_level_name
			from appraisal_level
			where is_active = 1
			order by appraisal_level_name
		");
		return response()->json($items);
    }
	
	public function dep_list()
	{
		$items = DB::select("
			Select distinct department_code, department_name
			From employee
			Order by department_name	
		");
		return response()->json($items);
	}
   
    public function sec_list(Request $request)
    {

		$qinput = array();
		$query = "
			Select distinct section_code, section_name
			From employee
			Where 1=1
		";
		
		$qfooter = " Order by section_name ";

		empty($request->department_code) ?: ($query .= " and department_code = ? " AND $qinput[] = $request->department_code);
		
		$items = DB::select($query.$qfooter,$qinput);
		return response()->json($items);				
    }
	
	public function auto_position_name(Request $request)
	{	
		$qinput = array();
		$query = "
			Select distinct position_code, position_name
			From employee
			Where position_name like ?
		";
		
		$qfooter = " Order by position_name limit 10";
		$qinput[] = '%'.$request->position_name.'%';
		empty($request->section_code) ?: ($query .= " and section_code = ? " AND $qinput[] = $request->section_code);
		empty($request->department_code) ?: ($query .= " and department_code = ? " AND $qinput[] = $request->department_code);
		
		$items = DB::select($query.$qfooter,$qinput);
		return response()->json($items);		
	}
	
	public function auto_employee_name(Request $request)
	{
		$qinput = array();
		$query = "
			Select emp_id, emp_code, emp_name
			From employee
			Where emp_name like ?
		";
		
		$qfooter = " Order by emp_name limit 10 ";
		$qinput[] = '%'.$request->emp_name.'%';
		empty($request->org_id) ?: ($query .= " and org_id = ? " AND $qinput[] = $request->org_id);
		empty($request->position_id) ?: ($query .= " and position_id = ? " AND $qinput[] = $request->position_id);
		
		$items = DB::select($query.$qfooter,$qinput);
		return response()->json($items);			
	}
	
	
	public function show($emp_id)
	{
		try {
			$item = Employee::findOrFail($emp_id);
			$position= Position::find($item->position_id);
			empty($position) ? $position_name = null : $position_name = $position->position_name;
			$item->position_name = $position_name;
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Employee not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $emp_id)
	{
		try {
			$item = Employee::findOrFail($emp_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Employee not found.']);
		}	
		
        $validator = Validator::make($request->all(), [
			'emp_code' => 'required|max:255|unique:employee,emp_code,'. $emp_id . ',emp_code',
			'emp_name' => 'required|max:255',
			'working_start_date' => 'date|date_format:Y-m-d',
			'probation_end_date' => 'date|date_format:Y-m-d',
			'acting_end_date' => 'date|date_format:Y-m-d',
			'org_id' => 'integer',
			'position_id' => 'integer',
			'chief_emp_code' => 'max:255',
			'level_id' => 'integer',
			//'s_amount' => 'required|numeric|digits_between:1,10',
			'email' => 'required|email|max:100',	
			'emp_type' => 'max:50',
			'is_active' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400, 'data' => $validator->errors()]);
        } else {
			$item->emp_code = $request->emp_code;
			$item->emp_name = $request->emp_name;
			$item->working_start_date = $request->working_start_date;
			$item->probation_end_date = $request->probation_end_date;
			$item->acting_end_date = $request->acting_end_date;
			$item->org_id = $request->org_id;
			$item->position_id = $request->position_id;
			$item->chief_emp_code = $request->chief_emp_code;
			$item->level_id = $request->level_id;
			$item->s_amount = $request->s_amount;
			$item->email = $request->email;
			$item->emp_type = $request->emp_type;
			$item->is_active = $request->is_active;					
			$item->updated_by = Auth::id();
			$item->save();
		}		
		
		return response()->json(['status' => 200, 'data' => $item]);
	}
	
	public function show_role($emp_code)
	{
		$items = DB::select("
			SELECT a.appraisal_level_id, a.appraisal_level_name, if(b.emp_code is null,0,1) role_active
			FROM appraisal_level a
			left outer join emp_level b
			on a.appraisal_level_id = b.appraisal_level_id
			and b.emp_code = ?
			order by a.appraisal_level_name		
		", array($emp_code));
		return response()->json($items);
	}
	
	public function assign_role(Request $request, $emp_code)
	{
		DB::table('emp_level')->where('emp_code',$emp_code)->delete();
		
		if (empty($request->roles)) {
		} else {
			foreach ($request->roles as $r) {
				$item = new EmpLevel;
				$item->appraisal_level_id = $r;
				$item->emp_code = $emp_code;
				$item->created_by = Auth::id();
				$item->save();
			}		
		}
		
		return response()->json(['status' => 200]);
	}
	
	public function batch_role(Request $request)
	{
		if (empty($request->employees)) {
		} else {
			foreach ($request->employees as $e) {
				$emp = Employee::find($e);
				if (empty($request->roles)) {
				} else {
					foreach ($request->roles as $r) {
						$emp->level_id = $r;
						$emp->updated_by = Auth::id();
						$emp->save();
					}				
				}
			}
		}
		return response()->json(['status' => 200]);
	}
	
	public function destroy($emp_id)
	{
		try {
			$item = Employee::findOrFail($emp_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Employee not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Employee is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
