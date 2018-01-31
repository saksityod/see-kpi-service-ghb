<?php

namespace App\Http\Controllers;

use App\EmpThreshold;

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

class EmpThresholdController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function index(Request $request)
	{		
		$items = DB::select("
			select emp_threshold_id, begin_threshold, end_threshold, color_code
			from emp_threshold
			where result_type = ?
			order by begin_threshold asc
		", array($request->result_type));
		return response()->json($items);
	}
	
	public function store(Request $request)
	{
		$empt = $request->emp_thresholds;
		foreach ($empt as $e) {
			$e['result_type'] = $request->result_type;
			$item = new EmpThreshold;
			$item->fill($e);
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		//	$successes[] = ['rule_id' => $c['rule_id']];	
		}
		return response()->json(['status' => 200]);
	}
	
	
	public function update(Request $request)
	{
		$empt = $request->emp_thresholds;
		foreach ($empt as $e) {
			$item = EmpThreshold::find($e['emp_threshold_id']);
			$e['result_type'] = $request->result_type;
			$item->fill($e);
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		//	$successes[] = ['rule_id' => $c['rule_id']];	
		}
		return response()->json(['status' => 200]);
				
	}
	
	public function destroy(Request $request)
	{
		$empt = $request->emp_thresholds;
		foreach ($empt as $e) {
			$item = EmpThreshold::find($e['emp_threshold_id']);
			$item->delete();
		//	$successes[] = ['rule_id' => $c['rule_id']];	
		}
		return response()->json(['status' => 200]);
		
	}	
}
