<?php

namespace App\Http\Controllers;

use App\AppraisalStructure;
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

class AppraisalStructureController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function form_list(Request $request)
	{
		$items = DB::select("
			select *
			from form_type
		");
		
		return response()->json($items);
	}
	
	public function index(Request $request)
	{		
		$items = DB::select("
			SELECT structure_id, seq_no, structure_name, nof_target_score, a.form_id, b.form_name, a.is_active
			FROM appraisal_structure a
			left outer join form_type b
			on a.form_id = b.form_id
			order by seq_no asc
		");
		return response()->json($items);
	}
	
	public function store(Request $request)
	{
	
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}			
	
		$validator = Validator::make($request->all(), [
			'seq_no' => 'required|integer|unique:appraisal_structure', 
			'structure_name' => 'required|max:100|unique:appraisal_structure',
			'nof_target_score' => 'required|integer|min:0|max:5',
			'form_id' => 'required|integer',
			'is_active' => 'required|boolean',					
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			
			if ($config->result_type == 2) {
				$check = DB::select("
					select max(a.end_threshold) max_no
					from result_threshold a left outer join result_threshold_group b
					on a.result_threshold_group_id = b.result_threshold_group_id
					where b.is_active = 1
					and b.result_type = 2
				");
				
				if (empty($check)) {
					return response()->json(['status' => 400, 'data' => ['is_active' => 'Please set Result Type to Score in System Configuration and set a Result Threshold Group of type Score to active.']]);
				}
				
				if ($request->nof_target_score != $check[0]->max_no && ($request->form_id == 1 || $request->form_id == 2)) {
					return response()->json(['status' => 400, 'data' => ['nof_target_score' => 'Number of Target Score must be equal to max Result Threshold Score']]);
				}
				
			}
			
			$item = new AppraisalStructure;
			$item->fill($request->all());
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($structure_id)
	{
		try {
			$item = AppraisalStructure::findOrFail($structure_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Structure not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $structure_id)
	{
	
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}			
		try {
			$item = AppraisalStructure::findOrFail($structure_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Structure not found.']);
		}
		
		$validator = Validator::make($request->all(), [
			'seq_no' => 'required|integer|unique:appraisal_structure,seq_no,' . $structure_id . ',structure_id', 
			'structure_name' => 'required|max:100|unique:appraisal_structure,structure_name,' . $structure_id . ',structure_id', 
			'nof_target_score' => 'required|integer|min:0|max:5',
			'form_id' => 'required|integer',
			'is_active' => 'required|boolean',		
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
		
			if ($config->result_type == 2) {
				$check = DB::select("
					select max(a.end_threshold) max_no
					from result_threshold a left outer join result_threshold_group b
					on a.result_threshold_group_id = b.result_threshold_group_id
					where b.is_active = 1
					and b.result_type = 2
				");
				
				if (empty($check)) {
					return response()->json(['status' => 400, 'data' => ['is_active' => 'Please set Result Type to Score in System Configuration and set a Result Threshold Group of type Score to active.']]);
				}
				
				if ($request->nof_target_score != $check[0]->max_no  && ($request->form_id == 1 || $request->form_id == 2)) {
					return response()->json(['status' => 400, 'data' => ['nof_target_score' => 'Number of Target Score must be equal to max Result Threshold Score']]);
				}
				
			}
			
			$item->fill($request->all());
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);
				
	}
	
	public function destroy($perspective_id)
	{
		try {
			$item = AppraisalStructure::findOrFail($perspective_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Structure not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Appraisal Structure is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
