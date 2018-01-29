<?php

namespace App\Http\Controllers;

use App\ResultThreshold;
use App\ResultThresholdGroup;
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

class ResultThresholdController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function group_list() 
	{
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		

		$items = DB::select("
			select result_threshold_group_id, result_threshold_group_name, is_active
			from result_threshold_group
			where result_type = ?
		", array($config->result_type));
		
		return response()->json($items);
	}
	
	public function add_group(Request $request)
	{
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		
		
		$validator = Validator::make($request->all(), [
			'result_threshold_group_name' => 'required|max:255',
			'is_active' => 'required|boolean'
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new ResultThresholdGroup;
			$item->result_threshold_group_name = $request->result_threshold_group_name;
			if ($request->is_active == 0) {
				$item->is_active = 0;
			} else {
				DB::table('result_threshold_group')->update(['is_active' => 0]);
				$item->is_active = 1;
			}
			$item->result_type = $config->result_type;
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);		
	}
	
	public function show_group($result_threshold_group_id)
	{
		try {
			$item = ResultThresholdGroup::findOrFail($result_threshold_group_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Result Threshold Group not found.']);
		}
		return response()->json($item);	
	}
	
	public function delete_group($result_threshold_group_id)
	{
		try {
			$item = ResultThresholdGroup::findOrFail($result_threshold_group_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Result Threshold Group not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Result Threshold Group is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);	
	}
	
	public function edit_group(Request $request, $result_threshold_group_id)
	{
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		
		
		try {
			$item = ResultThresholdGroup::findOrFail($result_threshold_group_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Result Threshold Group not found.']);
		}
		
		$validator = Validator::make($request->all(), [	
			'result_threshold_group_name' => 'required|max:255',
			'is_active' => 'required|boolean',
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item->result_threshold_group_name = $request->result_threshold_group_name;
			if ($request->is_active == 0) {
				$item->is_active = 0;
			} else {
				//DB::table('result_threshold_group')->update(['is_active' => 0]);
				ResultThresholdGroup::where('result_threshold_group_id','!=',$result_threshold_group_id)->update(['is_active' => 0]);
				$item->is_active = 1;
				$item->save();
			}
			$item->result_type = $config->result_type;
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);		
	}
	
	public function index(Request $request)
	{		
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		
		
		$qinput = array();
		$query = "
			select result_threshold_id, begin_threshold, end_threshold, color_code, a.result_threshold_group_id, b.result_threshold_group_name
			from result_threshold a
			left outer join result_threshold_group b
			on a.result_threshold_group_id = b.result_threshold_group_id
			where a.result_type = ?		
		";
		
		$qinput[] = $config->result_type;
		$qfooter = " order by result_threshold_group_name asc, begin_threshold asc ";
		empty($request->result_threshold_group_id) ?: ($query .= " and a.result_threshold_group_id = ? " AND $qinput[] = $request->result_threshold_group_id);
		$items = DB::select($query.$qfooter,$qinput);
		return response()->json($items);
	}
	
	public function store(Request $request)
	{
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		
		
		if ($config->result_type == 1) {
			$validator = Validator::make($request->all(), [
				'begin_threshold' => 'required|numeric',
				'end_threshold' => 'required|numeric',
				'color_code' => 'required|max:15',
				'result_threshold_group_id' => 'required|integer'
			]);
		} else {
			$validator = Validator::make($request->all(), [
				/*
				'begin_threshold' => 'required|max:5|numeric',
				'end_threshold' => 'required|max:5|numeric',
				'color_code' => 'required|max:15',
				'result_threshold_group_id' => 'required|integer'
				*/
				'begin_threshold' => 'required|numeric',
				'end_threshold' => 'required|numeric',
				'color_code' => 'required|max:15',
				'result_threshold_group_id' => 'required|integer'
			]);		
		}

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
		
			if ($request->begin_threshold > $request->end_threshold) {
				return response()->json(['status' => 400, 'data' => ['overlap' => 'Begin result threshold cannot be greater than end result threshold.']]);			
			}
			
			$check = DB::select("
				SELECT 1
				FROM result_threshold
				where (? between begin_threshold and end_threshold
				or ? between begin_threshold and end_threshold
				or begin_threshold between ? and ?
				or end_threshold between ? and ?)
				and result_threshold_group_id = ?
			",array($request->begin_threshold,$request->end_threshold,$request->begin_threshold,$request->end_threshold,$request->begin_threshold,$request->end_threshold,$request->result_threshold_group_id));
			
			if (!empty($check)) {
				return response()->json(['status' => 400, 'data' => ['overlap' => 'The begin score and end score is overlapped with another result threshold within the same group.']]);
			}			
			
			
			$item = new ResultThreshold;
			$item->begin_threshold = $request->begin_threshold;
			$item->end_threshold = $request->end_threshold;
			$item->color_code = $request->color_code;
			$item->result_threshold_group_id = $request->result_threshold_group_id;
			$item->result_type = $config->result_type;
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($result_threshold_id)
	{
		try {
			$item = ResultThreshold::findOrFail($result_threshold_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Result Threshold not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $result_threshold_id)
	{
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		
		
		try {
			$item = ResultThreshold::findOrFail($result_threshold_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Result Threshold not found.']);
		}		
		/*
		if ($config->result_type == 1) {
			$validator = Validator::make($request->all(), [
				'begin_threshold' => 'required|numeric',
				'end_threshold' => 'required|numeric',
				'color_code' => 'required|max:15',
				'result_threshold_group_id' => 'required|integer'
			]);
		} else {
			$validator = Validator::make($request->all(), [
				'begin_threshold' => 'required|max:5|numeric',
				'end_threshold' => 'required|max:5|numeric',
				'color_code' => 'required|max:15',
				'result_threshold_group_id' => 'required|integer'
			]);		
		}
		*/
		$validator = Validator::make($request->all(), [
				'begin_threshold' => 'required|numeric',
				'end_threshold' => 'required|numeric',
				'color_code' => 'required|max:15',
				'result_threshold_group_id' => 'required|integer'
			]);
		

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
		
			if ($request->begin_threshold > $request->end_threshold) {
				return response()->json(['status' => 400, 'data' => ['overlap' => 'Begin result threshold cannot be greater than end result threshold.']]);			
			}
			
			$check = DB::select("
				SELECT 1
				FROM result_threshold
				where (? between begin_threshold and end_threshold
				or ? between begin_threshold and end_threshold
				or begin_threshold between ? and ?
				or end_threshold between ? and ?)
				and result_threshold_group_id = ?
				and result_threshold_id != ?
			",array($request->begin_threshold,$request->end_threshold,$request->begin_threshold,$request->end_threshold,$request->begin_threshold,$request->end_threshold,$request->result_threshold_group_id,$result_threshold_id));
			
			if (!empty($check)) {
				return response()->json(['status' => 400, 'data' => ['overlap' => 'The begin score and end score is overlapped with another result threshold within the same group.']]);
			}			
			
			$item->begin_threshold = $request->begin_threshold;
			$item->end_threshold = $request->end_threshold;
			$item->color_code = $request->color_code;
			$item->result_threshold_group_id = $request->result_threshold_group_id;
			$item->result_type = $config->result_type;
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);		
	}
	
	public function destroy($result_threshold_id)
	{
		try {
			$item = ResultThreshold::findOrFail($result_threshold_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Result Threshold not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Result Threshold is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
