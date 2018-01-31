<?php

namespace App\Http\Controllers;

use App\UOM;

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

class UOMController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function index(Request $request)
	{		
		$items = DB::select("
			select uom_id, uom_name, is_active
			from uom
			order by uom_id asc
		");
		return response()->json($items);
	}
	
	public function store(Request $request)
	{
	
		$validator = Validator::make($request->all(), [
			'uom_name' => 'required|max:100|unique:uom',
			'is_active' => 'required|boolean',
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new UOM;
			$item->fill($request->all());
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($uom_id)
	{
		try {
			$item = UOM::findOrFail($uom_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'UOM not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $uom_id)
	{
		try {
			$item = UOM::findOrFail($uom_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'UOM not found.']);
		}
		
		$validator = Validator::make($request->all(), [
			'uom_name' => 'required|max:100|unique:uom,uom_name,' . $uom_id . ',uom_id',
			'is_active' => 'required|boolean',
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
	
	public function destroy($uom_id)
	{
		try {
			$item = UOM::findOrFail($uom_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'UOM not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this UOM is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
