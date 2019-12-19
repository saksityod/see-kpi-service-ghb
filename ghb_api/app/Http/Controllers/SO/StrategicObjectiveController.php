<?php

namespace App\Http\Controllers\SO;

use App\Model\StrategicObjective;

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

class StrategicObjectiveController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	public function objective_length(){
		$count = StrategicObjective::count();
		return response()->json($count);
	}	

	public function objective_list(){
		$items = StrategicObjective::all();
		return response()->json($items);
	}

	public function destroy($so_id){
		try {
			$item = StrategicObjective::findOrFail($so_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Strategic objective not found.']);
		}	
		$item->delete();
		return response()->json(['status' => 200]);
	}

	public function update(Request $request, $so_id){

		try {
			$item = StrategicObjective::findOrFail($so_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Strategic objective not found.']);
		}	
		
		$validator = Validator::make($request->all(), [
				'so_name' => 'required',
				'so_abbr' => 'required',
				'is_active' => 'required|numeric',
				'color_code' => 'required'
				
		]);

		$id_name = StrategicObjective::select('so_id')->
				where('so_name', $request->so_name)->
				get();

		$id_abbr = StrategicObjective::select('so_id')->
				where('so_abbr', $request->so_abbr)->
				get();
		$id1 = null;
		$id2 = null;
		foreach ($id_name  as $id_name) {
			$id1 = $id_name->so_id;
		}
		foreach ($id_abbr  as $id_abbr) {
			$id2 = $id_abbr->so_id;
		}
		
		if(($id1 == $so_id && empty($id2)) || ($id2 == $so_id) && empty($id1)){
				$item->fill($request->all());
				$item->updated_by = Auth::id();
				$item->save();
		}else if($validator->fails() || (isset($id_name) && $id1 != $so_id) || (isset($id_abbr) && $id2 != $so_id)){
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		}else{
				$item->fill($request->all());
				$item->updated_by = Auth::id();
				$item->save();
		} 
		return response()->json(['status' => 200]);
		return response()->json($name);
	}

	public function store(Request $request){
		$validator = Validator::make($request->all(), [
				'so_name' => 'required|unique:strategic_objective',
				'so_abbr' => 'required|unique:strategic_objective',
				'is_active' => 'required|numeric',
				'color_code' => 'required'
			
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new StrategicObjective;
			$item->fill($request->all());
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
		return response()->json(['status' => 200]);
	}

}
