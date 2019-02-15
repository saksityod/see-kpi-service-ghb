<?php

namespace App\Http\Controllers;

use App\Org;
use App\AppraisalLevel;
use App\Employee;

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

class OrgController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function index(Request $request)
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
		
		empty($request->level_id) ? $level = "" : $level = " and a.level_id = " . $request->level_id . " ";
		empty($request->org_code) ? $org = "" : $org = " and a.org_code = " . $request->org_code . " ";
		
		if ($all_emp[0]->count_no > 0) {
			$items = DB::select("
				select a.org_id,
						a.org_name,
						a.org_code,
						a.org_abbr,
						a.is_active,
						b.org_name parent_org_name,
						a.parent_org_code,
						a.level_id,
						c.appraisal_level_name,
						case when a.longitude = 0 then '' else a.longitude end  longitude,
						case when a.latitude = 0 then '' else a.latitude end  latitude,
						a.province_code,
						d.province_name
				from org a left outer join
				org b on b.org_code = a.parent_org_code
				left outer join appraisal_level c
				on a.level_id = c.level_id 
				left outer join province d on a.province_code = d.province_code
				where 1=1 " . $level . $org . " and a.is_active = 1
				order by a.org_code asc
			");
		} else {
			// $items = DB::select("
			// 	select a.org_id, a.org_name, a.org_code, a.org_abbr, a.is_active, b.org_name parent_org_name, a.parent_org_code, a.level_id, c.appraisal_level_name, a.longitude, a.latitude, a.province_code, d.province_name
			// 	from org a left outer join
			// 	org b on b.org_code = a.parent_org_code
			// 	left outer join appraisal_level c
			// 	on a.level_id = c.level_id 
			// 	left outer join province d on a.province_code = d.province_code
			// 	where 1=1 " . $level . "
			// 	and (a.org_code = {$co->org_code} or a.parent_org_code = {$co->org_code})
			// 	order by a.org_code asc
			// ");

			// $items = DB::select("
			// 	select *
			// 	from (
			// 		select a.*, b.org_name parent_org_name, c.appraisal_level_name, d.province_name
			// 		from (
			// 			select *
			// 			from (
			// 				select org_id, 
			// 				org_name, 
			// 				org_code, 
			// 				org_abbr, 
			// 				is_active, 
			// 				parent_org_code, 
			// 				level_id, 
			// 				longitude, 
			// 				latitude, 
			// 				province_code
			// 				from org
			// 				order by org_id
			// 			) products_sorted,
			// 			( select @pv := {$co->org_code} COLLATE latin1_general_ci as pv ) initialisation
			// 			where   find_in_set(parent_org_code, @pv)
			// 			and     length(@pv := concat(@pv, ',', org_code))
			// 		) a
			// 		left outer join org b on b.org_code = a.parent_org_code
			// 		left outer join appraisal_level c on a.level_id = c.level_id 
			// 		left outer join province d on a.province_code = d.province_code
			// 		where 1=1
			// 		" .$level. "
			// 		union all
			// 		select a.*, b.org_name parent_org_name, c.appraisal_level_name, d.province_name
			// 		from (
			// 			select *
			// 			from (
			// 				select a.org_id, 
			// 				a.org_name, 
			// 				a.org_code, 
			// 				a.org_abbr, 
			// 				a.is_active, 
			// 				a.parent_org_code, 
			// 				a.level_id, 
			// 				a.longitude, 
			// 				a.latitude, 
			// 				a.province_code,
			// 				'' as pv
			// 				from org a
			// 				where a.org_code = {$co->org_code}
			// 				" .$level. "
			// 			) org
			// 		) a
			// 		left outer join org b on b.org_code = a.parent_org_code
			// 		left outer join appraisal_level c on a.level_id = c.level_id 
			// 		left outer join province d on a.province_code = d.province_code
			// 	) d1
			// 	order by d1.org_id asc
			// ");

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
				select a.org_id,
				a.org_name,
				a.org_code,
				a.org_abbr,
				a.is_active,
				b.org_name parent_org_name,
				a.parent_org_code,
				a.level_id,
				c.appraisal_level_name,
				a.longitude, a.latitude,
				a.province_code,
				d.province_name
				from org a
				left outer join org b on b.org_code = a.parent_org_code
				left outer join appraisal_level c on a.level_id = c.level_id 
				left outer join province d on a.province_code = d.province_code
				where a.org_code in ({$in_emp})
				".$level." and a.is_active = 1
				order by a.org_id asc
			");
		}
		return response()->json($items);
	}

	public function org_master(Request $request)
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
		
		empty($request->level_id) ? $level = "" : $level = " and a.level_id = " . $request->level_id . " ";
		empty($request->org_code) ? $org = "" : $org = " and a.org_code = " . $request->org_code . " ";
		
		if ($all_emp[0]->count_no > 0) {
			$items = DB::select("
				select a.org_id,
						a.org_name,
						a.org_code,
						a.org_abbr,
						a.is_active,
						b.org_name parent_org_name,
						a.parent_org_code,
						a.level_id,
						c.appraisal_level_name,
						case when a.longitude = 0 then '' else a.longitude end  longitude,
						case when a.latitude = 0 then '' else a.latitude end  latitude,
						a.province_code,
						d.province_name
				from org a left outer join
				org b on b.org_code = a.parent_org_code
				left outer join appraisal_level c
				on a.level_id = c.level_id 
				left outer join province d on a.province_code = d.province_code
				where 1=1 " . $level . $org . " 
				order by a.org_code asc
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
				select a.org_id,
				a.org_name,
				a.org_code,
				a.org_abbr,
				a.is_active,
				b.org_name parent_org_name,
				a.parent_org_code,
				a.level_id,
				c.appraisal_level_name,
				a.longitude, a.latitude,
				a.province_code,
				d.province_name
				from org a
				left outer join org b on b.org_code = a.parent_org_code
				left outer join appraisal_level c on a.level_id = c.level_id 
				left outer join province d on a.province_code = d.province_code
				where a.org_code in ({$in_emp})
				".$level."
				order by a.org_id asc
			");
		}
		return response()->json($items);
	}

	public function list_organization(Request $request)
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

		$all_org = DB::select("
			SELECT sum(is_show_corporate) count_no
			from employee
			where emp_code = ?
		", array(Auth::id()));
		
		empty($request->level_id) ? $level = "" : $level = " and a.level_id = " . $request->level_id . " ";
		empty($request->org_code) ? $org = "" : $org = " and a.org_code = " . $request->org_code . " ";
		
		if ($all_emp[0]->count_no > 0) {
			$items = DB::select("
				select a.org_id,
						a.org_name,
						a.org_code,
						a.org_abbr,
						a.is_active,
						b.org_name parent_org_name,
						a.parent_org_code,
						a.level_id,
						c.appraisal_level_name,
						case when a.longitude = 0 then '' else a.longitude end  longitude,
						case when a.latitude = 0 then '' else a.latitude end  latitude,
						a.province_code,
						d.province_name
				from org a left outer join
				org b on b.org_code = a.parent_org_code
				left outer join appraisal_level c
				on a.level_id = c.level_id 
				left outer join province d on a.province_code = d.province_code
				where 1=1 " . $level . $org . " and a.is_active = 1
				order by a.org_code asc
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

			if($all_org[0]->count_no > 0) {
				if(empty($request->level_id)) {
					$items = DB::select("
						select *
						from (
						select a.org_id, a.org_name, a.org_code, a.org_abbr, a.is_active, b.org_name parent_org_name, a.parent_org_code, a.level_id, c.appraisal_level_name, a.longitude, a.latitude, a.province_code, d.province_name
						from org a left outer join
						org b on b.org_code = a.parent_org_code
						left outer join appraisal_level c
						on a.level_id = c.level_id 
						left outer join province d on a.province_code = d.province_code
						where 1=1 " . $level . " and a.is_active = 1
						#and (a.org_code = {$co->org_code} or a.parent_org_code = {$co->org_code})
						and a.org_code in ({$in_emp})
						UNION
						select a.org_id, a.org_name, a.org_code, a.org_abbr, a.is_active, b.org_name parent_org_name, a.parent_org_code, a.level_id, c.appraisal_level_name, a.longitude, a.latitude, a.province_code, d.province_name
						from org a left outer join
						org b on b.org_code = a.parent_org_code
						left outer join appraisal_level c
						on a.level_id = c.level_id 
						left outer join province d on a.province_code = d.province_code
						where 1=1 and a.is_active = 1
						and a.level_id = 2
						)d1
						order by org_code asc
					");
				} else if($request->level_id==2) {
					$items = DB::select("
						select a.org_id, a.org_name, a.org_code, a.org_abbr, a.is_active, b.org_name parent_org_name, a.parent_org_code, a.level_id, c.appraisal_level_name, a.longitude, a.latitude, a.province_code, d.province_name
						from org a left outer join
						org b on b.org_code = a.parent_org_code
						left outer join appraisal_level c
						on a.level_id = c.level_id 
						left outer join province d on a.province_code = d.province_code
						where 1=1 " . $level . " and a.is_active = 1
						order by a.org_code asc
					");
				} else {
					$items = DB::select("
						select a.org_id, a.org_name, a.org_code, a.org_abbr, a.is_active, b.org_name parent_org_name, a.parent_org_code, a.level_id, c.appraisal_level_name, a.longitude, a.latitude, a.province_code, d.province_name
						from org a left outer join
						org b on b.org_code = a.parent_org_code
						left outer join appraisal_level c
						on a.level_id = c.level_id 
						left outer join province d on a.province_code = d.province_code
						where 1=1 " . $level . " and a.is_active = 1
						#and (a.org_code = {$co->org_code} or a.parent_org_code = {$co->org_code})
						and a.org_code in ({$in_emp})
						order by a.org_code asc
					");
				}
			} else {
				$items = DB::select("
					select a.org_id, a.org_name, a.org_code, a.org_abbr, a.is_active, b.org_name parent_org_name, a.parent_org_code, a.level_id, c.appraisal_level_name, a.longitude, a.latitude, a.province_code, d.province_name
					from org a left outer join
					org b on b.org_code = a.parent_org_code
					left outer join appraisal_level c
					on a.level_id = c.level_id 
					left outer join province d on a.province_code = d.province_code
					where 1=1 " . $level . " and a.is_active = 1
					#and (a.org_code = {$co->org_code} or a.parent_org_code = {$co->org_code})
					and a.org_code in ({$in_emp})
					order by a.org_code asc
				");			
			}
		}
		return response()->json($items);
	}
	
	public function auto_org_name(Request $request)
	{
		empty($request->level_id) ? $level = "" : $level = " and a.level_id = " . $request->level_id . " ";
		$items = DB::select("
			select a.org_id, a.org_code, a.org_name, a.org_abbr
			from org a 
			where org_name like ? " . $level . "
			order by a.org_code asc
			limit 10
		", array('%'.$request->org_name.'%'));
		return response()->json($items);		
	}
	
	public function province_list()
	{
		$items = DB::select("
			select province_code, province_name
			from province
			order by province_name asc
		");
		return response()->json($items);	
	}
	
	public function al_list()
	{
		$items = DB::select("
			select level_id, appraisal_level_name, parent_id
			from appraisal_level
			where is_active = 1
			order by level_id asc
		");
		foreach ($items as $i) {
			$parent_org = DB::select("
				select org_code, org_name, org_abbr
				from org
				where level_id = ?
				order by org_code asc
			", array($i->parent_id));
			
			$org = DB::select("
				select org_code, org_name, org_abbr
				from org
				where level_id = ?
				order by org_code asc
			", array($i->level_id));
			
			$i->parent_org = $parent_org;
			$i->org = $org;
		}
		return response()->json($items);
	}
	
	public function parent_list()
	{
		$items = DB::select("
			select a.org_code, a.org_name, a.org_abbr
			from org a 
			order by a.org_code asc
		");
		return response()->json($items);		
	}	
	
	public function import(Request $request)
	{
		$errors = array();
		foreach ($request->file() as $f) {
			$items = Excel::load($f, function($reader){})->get();	
			foreach ($items as $i) {
				
				$validator = Validator::make($i->toArray(), [
					'org_code' => 'required|max:15',
					'org_name' => 'required|max:255',
					'org_abbr' => 'max:255',
					'parent_org_code' => 'max:15'
				]);

				if ($validator->fails()) {
					$errors[] = ['org_code' => $i->org_code, 'errors' => $validator->errors()];
				} else {
					$org = DB::select("
						select org_id
						from org
						where org_code = ?
					",array($i->org_code));
					if (empty($org)) {
						$org = new Org;		
						$org->org_code = $i->org_code;
						$org->org_name = $i->org_name;
						$org->org_abbr = $i->org_abbr;
						$org->parent_org_code = $i->parent_org_code;
						$org->is_active = 1;
						$org->latitude = $i->latitude;
						$org->longitude = $i->longitude;
						$org->org_email = $i->org_email;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						try {
							$org->save();
						} catch (Exception $e) {
							$errors[] = ['org_code' => $i->org_code, 'errors' => substr($e,0,254)];
						}
					} else {
 						Org::where('org_code',$i->org_code)->update(['org_name' => $i->org_name,'org_abbr' => $i->org_abbr,'parent_org_code' => $i->parent_org_code,'latitude' => $i->latitude,'longitude' => $i->longitude]);
					}
				}					
			}
		}
		return response()->json(['status' => 200, 'errors' => $errors]);
	}	
	
	public function store(Request $request)
	{
	
		$validator = Validator::make($request->all(), [
			'org_code' => 'required|max:15|unique:org',
			'org_name' => 'required|max:255|unique:org',
			'org_abbr' => 'max:255',
			'parent_org_code' => 'max:15',
			'level_id' => 'integer',
			'latitude' => 'numeric',
			'longitude' => 'numeric',
			'province_code' => 'integer',			
			'is_active' => 'required|integer',
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new Org;
			$item->fill($request->all());
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($org_id)
	{
		try {
			$item = Org::findOrFail($org_id);

			($item->longitude == 0) ? $item->longitude = "" : $item->longitude;
			($item->latitude == 0) ? $item->latitude = "" : $item->latitude;
			
			$parent = DB::select("
				select org_name
				from org
				where org_code = ?
			", array($item->parent_org_code));
			
			if (!empty($parent)) {
				$item->parent_org_name = $parent[0]->org_name;
			}
			
			$al = AppraisalLevel::find($item->level_id);
			
			empty($al) ? $item->level_name = null : $item->level_name = $al->appraisal_level_name;
			
			
			
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Organization not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $org_id)
	{
		try {
			$item = Org::findOrFail($org_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Organization not found.']);
		}
		
		$validator = Validator::make($request->all(), [
			'org_code' => 'required|max:15|unique:org,org_name,' . $org_id . ',org_id',
			'org_name' => 'required|max:255|unique:org,org_name,' . $org_id . ',org_id',
			'org_abbr' => 'max:255',
			'parent_org_code' => 'max:15',
			'level_id' => 'integer',
			'latitude' => 'numeric',
			'longitude' => 'numeric',
			'province_code' => 'integer',
			'is_active' => 'required|integer',
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
	
	public function batch_role(Request $request)
	{
		if (empty($request->orgs)) {
		} else {
			foreach ($request->orgs as $o) {
				$org = Org::find($o);
				if (empty($request->roles)) {
				} else {
					foreach ($request->roles as $r) {
						$org->level_id = $r;
						$org->updated_by = Auth::id();
						$org->save();
					}				
				}
			}
		}
		return response()->json(['status' => 200]);
	}	
	
	public function destroy($org_id)
	{
		try {
			$item = Org::findOrFail($org_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Organization not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Organization is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
