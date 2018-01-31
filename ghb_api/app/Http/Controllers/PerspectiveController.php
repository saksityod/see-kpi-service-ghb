<?php

namespace App\Http\Controllers;

use App\Perspective;

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

class PerspectiveController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function index(Request $request)
	{		
		$items = DB::select("
			select perspective_id, perspective_name, perspective_abbr, color_code, is_active
			from perspective
			order by perspective_id asc
		");
		return response()->json($items);
	}
	
	public function store(Request $request)
	{
	
		$validator = Validator::make($request->all(), [
			'perspective_name' => 'required|max:100|unique:perspective',
			'perspective_abbr' => 'max:255',
			'color_code' => 'max:15',
			'is_active' => 'required|boolean',
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new Perspective;
			$item->fill($request->all());
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($perspective_id)
	{
		try {
			$item = Perspective::findOrFail($perspective_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Perspective not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $perspective_id)
	{
		try {
			$item = Perspective::findOrFail($perspective_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Perspective not found.']);
		}
		
		$validator = Validator::make($request->all(), [
			'perspective_name' => 'required|max:100|unique:perspective,perspective_name,' . $perspective_id . ',perspective_id',
			'perspective_abbr' => 'max:255',
			'color_code' => 'max:15',
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
	
	public function destroy($perspective_id)
	{
		try {
			$item = Perspective::findOrFail($perspective_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Perspective not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Perspective is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
