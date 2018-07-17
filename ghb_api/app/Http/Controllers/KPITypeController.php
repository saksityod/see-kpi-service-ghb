<?php

namespace App\Http\Controllers;

use App\KPIType;

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

class KPITypeController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function index(Request $request)
	{		
		$items = DB::select("
			select kpi_type_id, kpi_type_name, is_active
			from kpi_type
			where is_active = 1
			order by kpi_type_id asc
		");
		return response()->json($items);
	}
	
	
	public function store(Request $request)
	{
	
		$validator = Validator::make($request->all(), [
			'kpi_type_name' => 'required|max:255|unique:kpi_type',
			'is_active' => 'required|integer',
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new KPIType;
			$item->fill($request->all());
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($kpi_type_id)
	{
		try {
			$item = KPIType::findOrFail($kpi_type_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'KPI Type not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $kpi_type_id)
	{
		try {
			$item = KPIType::findOrFail($kpi_type_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'KPI Type not found.']);
		}
		
		$validator = Validator::make($request->all(), [
			'kpi_type_name' => 'required|max:255|unique:kpi_type,kpi_type_name,' . $kpi_type_id . ',kpi_type_id',
			'is_active' => 'required|integer'
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
	
	public function destroy($kpi_type_id)
	{
		try {
			$item = KPIType::findOrFail($kpi_type_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'KPI Type not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this KPI Type is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
