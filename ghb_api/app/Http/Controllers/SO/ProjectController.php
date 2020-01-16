<?php

namespace App\Http\Controllers\SO;

use App\Model\Project;
use App\SoKpi;
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
				$items = Project::select('projects.id', 'projects.name', 'org_name', 'projects.org_id', 'emp_name', 'projects.emp_id',
										'projects.value', 'projects.date_start', 'projects.date_end', 
										'projects.risk', 'projects.is_active', 'projects.objective','project_so_kpi.so_kpi_id')
								->join('org', 'projects.org_id', '=', 'org.org_id')
								->join('employee', 'projects.emp_id', '=', 'employee.emp_id')
								->join('project_so_kpi', 'projects.id', '=', 'project_so_kpi.project_id')
								->get();
							
			else $items = Project::select('projects.id', 'projects.name', 'org_name', 'projects.org_id', 'emp_name', 'projects.emp_id','projects.value',
										'projects.date_start', 'projects.date_end',
										'projects.risk', 'projects.is_active', 'projects.objective','project_so_kpi.so_kpi_id')
								->join('org', 'projects.org_id', '=', 'org.org_id')
								->join('employee', 'projects.emp_id', '=', 'employee.emp_id')
								->join('project_so_kpi', 'projects.id', '=', 'project_so_kpi.project_id')
								->where('projects.id', $project_id)
								->get();
		}else{
			if(empty($project_id))
				$items = Project::select('projects.id', 'projects.name', 'org_name', 'projects.org_id', 'emp_name', 'projects.emp_id','projects.value',
										'projects.date_start', 'projects.date_end', 'projects.value', 'projects.risk', 'projects.is_active',
										'projects.objective','project_so_kpi.so_kpi_id')
								->join('org', 'projects.org_id', '=', 'org.org_id')
								->join('employee', 'projects.emp_id', '=', 'employee.emp_id')
								->join('project_so_kpi', 'projects.id', '=', 'project_so_kpi.project_id')
								->where('emp_code', $emp_code)
								->get();

			else $items = DB::table('projects')->select('projects.id', 'projects.name', 'org_name', 'projects.org_id', 'emp_name', 'projects.emp_id','projects.value',
														'projects.date_start', 'projects.date_end', 'projects.value', 'projects.risk', 'projects.is_active', 
														'projects.objective','project_so_kpi.so_kpi_id')
												->join('org', 'project.org_id', '=', 'org.org_id')
												->join('employee', 'projects.emp_id', '=', 'employee.emp_id')
												->join('project_so_kpi', 'projects.id', '=', 'project_so_kpi.project_id')
												->where('emp_code', $emp_code)
												->where('projects.id', $project_id)
												->get();
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
		DB::table('project_so_kpi')->where('project_id', $project_id)->delete();
		return response()->json(['status' => 200]);
	}

	public function dropDownSoItem(){
		$items = SoKpi::select('id', 'name')->get();
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

			'name' => 'required|unique:projects,name,'.$project_id.'id',
			'date_start' => 'required|date|date_format:Y-m-d',
			'is_active' => 'required|numeric',
			'date_end' => 'required|date|date_format:Y-m-d'

        ]);

		$id_name = Project::select('id')->
				where('name', $request->project_name)->
				get();
		$id = null;
		foreach ($id_name  as $id_name) {
				$id = $id_name->project_id;
		}

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item->fill($request->all());
			$item->updated_by = Auth::id();
			$item->save();


			DB::table('project_so_kpi')->
				where('project_so_kpi.project_id', $project_id)->
				update(array('so_kpi_id' => $request->so_kpi_id,
							'updated_at'=> date("Y-m-d H:i:s") ));
		}
		return response()->json(['status' => 200]);
	}

	public function store(Request $request){
		$validator = Validator::make($request->all(), [
			'name' => 'required|unique:projects',
			'date_start'  => 'required|date|date_format:Y-m-d',
			'date_end'  => 'required|date|date_format:Y-m-d',
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

			$id = Project::max('id');
			DB::table('project_so_kpi')->insert(array(
				'so_kpi_id' => $request->so_kpi_id,
				'project_id' => $id,
				'created_by' => Auth::id(),
				'created_at' => date("Y-m-d H:i:s"),
				'updated_at' => date("Y-m-d H:i:s")
			));
		}
		return response()->json(['status' => 200]);
		
	}



}
