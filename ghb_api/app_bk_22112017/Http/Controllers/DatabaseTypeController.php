<?php

namespace App\Http\Controllers;

use App\DatabaseType;
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

class DatabaseTypeController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
 
	
	public function index(Request $request)
	{		
	
		
			$query = "
				select database_type_id,database_type
				from database_type
				order by database_type_id;
			";		
		
				

		
		$items = DB::select($query);
		
		
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
			
			'database_type' => 'required|max:255'
	
			
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new DatabaseType;
			$item->fill($request->all());
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($database_type_id)
	{
		try {
			$item = DatabaseType::findOrFail($database_type_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Database type not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $database_type_id)
	{
		try {
			$item = DatabaseType::findOrFail($database_type_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Database type not found.']);
		}
		
		$validator = Validator::make($request->all(), [
				'database_type' => 'required|max:255'
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item->fill($request->all());
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);
				
				
	}
	
	public function destroy($database_type_id)
	{
		try {
			$item = DatabaseType::findOrFail($database_type_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Database type not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Database type is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
