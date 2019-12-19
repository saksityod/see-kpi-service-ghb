<?php

namespace App\Http\Controllers\SO;

use App\Model\Project;
use App\Model\SOItemModel;
use App\Org;
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

class ProjectController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function search_project(Request $request){
		$project_id = $request->project_id;
		$emp_code = Auth::id();

		if(strcmp($emp_code, 'admin') == 0){
			if(empty($project_id))
				$items = DB::table('project')->select('project.project_id', 'project.project_name', 'org_name', 'project.org_id', 'emp_name', 'project.emp_id','project.project_value', 'project.project_start_date', 'project.project_end_date', 'project.project_value', 'project.project_risk', 'project.is_active', 'project.objective', 'so_item_id')->
							join('org', 'project.org_id', '=', 'org.org_id')->
							join('employee', 'project.emp_id', '=', 'employee.emp_id')->
							join('so_item_project_mapping', 'project.project_id', '=', 'so_item_project_mapping.project_id')->
							get();
			else $items = DB::table('project')->select('project.project_id', 'project.project_name', 'org_name', 'project.org_id', 'emp_name', 'project.emp_id','project.project_value', 'project.project_start_date', 'project.project_end_date', 'project.project_value', 'project.project_risk', 'project.is_active', 'project.objective', 'so_item_id')->
							join('org', 'project.org_id', '=', 'org.org_id')->
							join('employee', 'project.emp_id', '=', 'employee.emp_id')->
							join('so_item_project_mapping', 'project.project_id', '=', 'so_item_project_mapping.project_id')->
							where('project.project_id', $project_id)->
							get();
		}else{
			if(empty($project_id))
				$items = DB::table('project')->select('project.project_id', 'project.project_name', 'org_name', 'project.org_id', 'emp_name', 'project.emp_id','project.project_value', 'project.project_start_date', 'project.project_end_date', 'project.project_value', 'project.project_risk', 'project.is_active', 'project.objective', 'so_item_id')->
							join('org', 'project.org_id', '=', 'org.org_id')->
							join('employee', 'project.emp_id', '=', 'employee.emp_id')->
							join('so_item_project_mapping', 'project.project_id', '=', 'so_item_project_mapping.project_id')->
							where('emp_code', $emp_code)->
							get();
			else $items = DB::table('project')->select('project.project_id', 'project.project_name', 'org_name', 'project.org_id', 'emp_name', 'project.emp_id','project.project_value', 'project.project_start_date', 'project.project_end_date', 'project.project_value', 'project.project_risk', 'project.is_active', 'project.objective', 'so_item_id')->
							join('org', 'project.org_id', '=', 'org.org_id')->
							join('employee', 'project.emp_id', '=', 'employee.emp_id')->
							join('so_item_project_mapping', 'project.project_id', '=', 'so_item_project_mapping.project_id')->
							where('emp_code', $emp_code)->
							where('project.project_id', $project_id)->
							get();
		}
		return response()->json($items);
	}
	
	public function destroy($project_id){
		try {
			$item = Project::findOrFail($project_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Project not found.']);
		}	
		$item->delete();
		return response()->json(['status' => 200]);
	}

	public function dropDownSoItem(){
		$items = SOItemModel::select('so_item_id', 'so_item_name')->get();
		return response()->json($items);
	}

	public function dropDownOwner(){
		$items = Org::select('org_id', 'org_name')->get();
		return response()->json($items);
	}

	public function dropDownResponsible(){
		$items = Employee::select('emp_id', 'emp_name')->get();
		return response()->json($items);
	}

	public function update(Request $request, $project_id){

		try {
			$item = Project::findOrFail($project_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Project not found.']);
		}
		
		$validator = Validator::make($request->all(), [
			'project_name' => 'required',
			'project_start_date'  => 'required|date|date_format:Y-m-d',
			'project_end_date'  => 'required|date|date_format:Y-m-d',
			'is_active' => 'required|numeric'
		]);

		$id_name = Project::select('project_id')->
				where('project_name', $request->project_name)->
				get();
		$id = null;
		foreach ($id_name  as $id_name) {
				$id = $id_name->project_id;
		}

		if ($validator->fails() || (isset($id) && $id != $project_id)) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item->fill($request->all());
			$item->updated_by = Auth::id();
			$item->save();


			DB::table('so_item_project_mapping')->
				where('so_item_project_mapping.project_id', $project_id)->
				update(array('so_item_id' => $request->so_item_id));
		}
		return response()->json(['status' => 200]);
	}

	public function store(Request $request){
		$validator = Validator::make($request->all(), [
			'project_name' => 'required|unique:project',
			'project_start_date'  => 'required|date|date_format:Y-m-d',
			'project_end_date'  => 'required|date|date_format:Y-m-d',
			'is_active' => 'required|numeric'
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item1 = new Project;
			$item1->fill($request->all());
			$item1->created_by = Auth::id();
			$item1->updated_by = Auth::id();
			$item1->save();

			$id = Project::max('project_id');
			DB::table('so_item_project_mapping')->insert(array(
				'so_item_id' => $request->so_item_id,
				'project_id' => $id,
				'created_by' => Auth::id(),
				'created_dttm' => date("Y-m-d H:i:s")
			));
		}
		return response()->json(['status' => 200]);
		
	}



}
