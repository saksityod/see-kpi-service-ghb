<?php

namespace App\Http\Controllers\SO;

use App\So;

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

class SOController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	public function objective_length(){
		$count = So::count();
		return response()->json($count);
	}	

	public function objective_list(){
		$items = So::all();
		return response()->json($items);
	}

	public function destroy($id){
		try {
			$item = So::findOrFail($id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Strategic objective not found.']);
		}	
		$item->delete();
		return response()->json(['status' => 200]);
	}

	public function update(Request $request, $id){

		try {
			$item = So::findOrFail($id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'SO not found.']);
		}

        $validator = Validator::make($request->all(), [	

			'name' => 'required|unique:sos,name,'.$id.'id',
			'abbr' => 'required|unique:sos,abbr,'.$id.'id',
			'is_active' => 'required|numeric',
			'color_code' => 'required'

        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400, 'data' => $validator->errors()]);
        } else{
				$item->fill($request->all());
				$item->updated_by = Auth::id();
				$item->save();
        }
        return response()->json(['status' => 200, 'data' => $item]);
		
	}

	public function store(Request $request){
		$validator = Validator::make($request->all(), [
				'name' => 'required|unique:sos',
				'abbr' => 'required|unique:sos',
				'is_active' => 'required|numeric',
				'color_code' => 'required'
			
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new SO;
			$item->fill($request->all());
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
		return response()->json(['status' => 200]);
	}

}
