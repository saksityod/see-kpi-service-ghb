<?php

namespace App\Http\Controllers;

use App\Threshold;
use App\ThresholdGroup;

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

class ThresholdController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function group_list()
	{
		$items = DB::select("
			select threshold_group_id, threshold_group_name, is_active
			from threshold_group
			order by threshold_group_id asc
		");
		
		return response()->json($items);
	}
	
	public function add_group(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'threshold_group_name' => 'required|max:255|unique:threshold_group',
			'is_active' => 'required|boolean'
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new ThresholdGroup;
			$item->fill($request->all());
			if ($request->is_active == 0) {
				$item->is_active = 0;
			} else {
				DB::table('threshold_group')->update(['is_active' => 0]);
				$item->is_active = 1;
			}
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);		
	}
	
	public function show_group($threshold_group_id)
	{
		try {
			$item = ThresholdGroup::findOrFail($threshold_group_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Threshold group not found.']);
		}
		return response()->json($item);
	}
		
	
	public function edit_group(Request $request, $threshold_group_id)
	{
		try {
			$item = ThresholdGroup::findOrFail($threshold_group_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Threshold Group not found.']);
		}
		
		$validator = Validator::make($request->all(), [	
			'threshold_group_name' => 'required|max:255|unique:threshold_group,threshold_group_name,'. $threshold_group_id .',threshold_group_id',
			'is_active' => 'required|boolean',
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item->fill($request->all());
			if ($request->is_active == 0) {
				$item->is_active = 0;
			} else {
				//DB::table('threshold_group')->update(['is_active' => 0]);
				ThresholdGroup::where('threshold_group_id','!=',$threshold_group_id)->update(['is_active' => 0]);
				$item->is_active = 1;
			}			
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function delete_group($threshold_group_id)
	{
		try {
			$item = ThresholdGroup::findOrFail($threshold_group_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'ThresholdGroup not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Threshold Group is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
	
	public function index(Request $request)
	{		
		$qinput = array();
		$query = "
			select a.threshold_id, b.structure_name, a.target_score, a.threshold_name, a.color_code, d.threshold_group_id, d.threshold_group_name
			from threshold a
			left outer join appraisal_structure b
			on a.structure_id = b.structure_id
			left outer join form_type c
			on b.form_id = c.form_id
			left outer join threshold_group d
			on a.threshold_group_id = d.threshold_group_id
			where c.form_name in ('Quality','Quantity')
			and b.is_active = 1		
		";
		
		empty($request->structure_id) ?: ($query .= " and a.structure_id = ? " AND $qinput[] = $request->structure_id);
		
		empty($request->threshold_group_id) ?: ($query .= " and a.threshold_group_id = ? " AND $qinput[] = $request->threshold_group_id);
		
		$qfooter = " order by b.structure_id asc, target_score asc ";
		
		$items = DB::select($query.$qfooter, $qinput);

		return response()->json($items);
	}
	
	public function structure_list()
	{
		$items = DB::select("
			select a.structure_id, a.structure_name, a.nof_target_score
			from appraisal_structure a
			left outer join form_type b
			on a.form_id = b.form_id
			where b.form_name in ('Quality','Quantity')
			and a.is_active = 1
		");
		
		foreach ($items as $i) {
			$target_score = [];
			foreach (range(0,$i->nof_target_score,1) as $r) {
				$target_score[] = $r;
			}
			$i->score_list = $target_score;
		}
		
		return response()->json($items);
	}
	
	public function store(Request $request)
	{

		$validator = Validator::make($request->all(), [
			'structure_id' => 'required|integer|unique:threshold,structure_id,null,threshold_id,target_score,' . $request->target_score . ',threshold_name,' . $request->threshold_name,
			'target_score' => 'required|numeric|unique:threshold,target_score,null,structure_id,structure_id,' . $request->structure_id,
			'threshold_name' => 'required|max:50|unique:threshold,threshold_name,null,structure_id,structure_id,' . $request->structure_id,
			'threshold_group_id' => 'required|integer',
			'color_code' => 'max:15'
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new Threshold;
			$item->fill($request->all());
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($threshold_id)
	{
		try {
			$item = Threshold::findOrFail($threshold_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Threshold not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $threshold_id)
	{
		try {
			$item = Threshold::findOrFail($threshold_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Threshold not found.']);
		}
		
		$validator = Validator::make($request->all(), [	
			'structure_id' => 'required|integer|unique:threshold,structure_id,'. $threshold_id .',threshold_id,target_score,' . $request->target_score . ',threshold_name,' . $request->threshold_name,
			'target_score' => 'required|numeric|unique:threshold,target_score,' . $threshold_id . ',threshold_id,structure_id,' . $request->structure_id,
			'threshold_name' => 'required|max:50|unique:threshold,threshold_name,' . $threshold_id . ',threshold_id,structure_id,' . $request->structure_id,
			'threshold_group_id' => 'required|integer',
			'color_code' => 'max:15',
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
	
	public function destroy($threshold_id)
	{
		try {
			$item = Threshold::findOrFail($threshold_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Threshold not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Threshold is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
