<?php

namespace App\Http\Controllers;

use App\AppraisalStage;
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

class AppraisalStageController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
    public function appraisal_type_list()
    {
		$items = DB::select("
			SELECT appraisal_type_id,appraisal_type_name 
			FROM appraisal_type;
		");
		return response()->json($items);
    }
	
	public function index(Request $request)
	{		
	$qinput = array();
		
			$query = "
				select stage_id,appt.appraisal_type_name,from_stage_id,to_stage_id,from_action,to_action,status,
				edit_flag,hr_see,assignment_flag,appraisal_flag 
				from appraisal_stage apps
				inner join appraisal_type appt 
				on apps.appraisal_type_id=appt.appraisal_type_id
				
				
			";		
		
				
		empty($request->appraisal_type_id) ?: ($query .= " where appt.appraisal_type_id=? " AND $qinput[] = $request->appraisal_type_id);
		
		$qfooter = " order by appt.appraisal_type_id,apps.stage_id ";
		
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
	
	public function store(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'appraisal_type_id' => 'required|numeric',
			'from_action' => 'required|max:255',
			'to_action' => 'required|max:255',
			'status' => 'required|max:255',
			'edit_flag' => 'required|numeric',
			'hr_see' => 'required|numeric',
			'assignment_flag' => 'required|numeric',
			'appraisal_flag' => 'required|numeric'
			
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new AppraisalStage;
			$item->fill($request->all());
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($stage_id)
	{
		try {
			$item = AppraisalStage::findOrFail($stage_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal stage not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $stage_id)
	{
		try {
			$item = AppraisalStage::findOrFail($stage_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal stage not found.']);
		}
		
		$validator = Validator::make($request->all(), [
				'appraisal_type_id' => 'required|numeric',
				'from_action' => 'required|max:255',
				'to_action' => 'required|max:255',
				'status' => 'required|max:255',
				'edit_flag' => 'required|numeric',
				'hr_see' => 'required|numeric',
				'assignment_flag' => 'required|numeric',
				'appraisal_flag' => 'required|numeric'
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item->fill($request->all());
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);
				
				
	}
	
	public function destroy($stage_id)
	{
		try {
			$item = AppraisalStage::findOrFail($stage_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal stage not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Appraisal stage is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
