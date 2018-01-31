<?php

namespace App\Http\Controllers;

use App\AxisMapping;
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

class AxisMappingController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
    public function axis_type_list()
    {
		$items = DB::select("
			select DISTINCT axis_type_id,if(axis_type_id=1,'แกน X','แกน Y') as 'axis_type' 
			from axis_mapping
			order by axis_type_id
		");
		return response()->json($items);
    }
	
	public function index(Request $request)
	{		
	$qinput = array();
		
			$query = "
				select axis_mapping_id,if(axis_type_id=1,'แกน X','แกน Y') as 'axis_type_id',axis_value_name,axis_value,axis_value_start,axis_value_end 
				from axis_mapping


			";		
		
				
		empty($request->axis_type_id) ?: ($query .= " where axis_type_id = ? " AND $qinput[] = $request->axis_type_id);
		
		$qfooter = " order by axis_type_id ";
		
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
			'axis_type_id' => 'required|numeric',
			'axis_value_name' => 'required|max:255',
			'axis_value' => 'required|numeric',
			
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new AxisMapping;
			$item->fill($request->all());
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($axis_mapping_id)
	{
		try {
			$item = AxisMapping::findOrFail($axis_mapping_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Axis Mapping not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $axis_mapping_id)
	{
		try {
			$item = AxisMapping::findOrFail($axis_mapping_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Axis Mapping not found.']);
		}
		
		$validator = Validator::make($request->all(), [
				'axis_type_id' => 'required|numeric',
				'axis_value_name' => 'required|max:255',
				'axis_value' => 'required|numeric',
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item->fill($request->all());
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);
				
				
	}
	
	public function destroy($axis_mapping_id)
	{
		try {
			$item = AxisMapping::findOrFail($axis_mapping_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Axis Mapping not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Axis Mapping is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
