<?php

namespace App\Http\Controllers;
use App\Model\SOItemModel;
use App\UOM;
use App\Model\ValueTypeModel;
use App\Model\StrategicObjectiveModel;

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

class SOItemController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function getDropdownSOItemName(Request $request){
        $DB = StrategicObjectiveModel::select('so_id','so_name')
                        ->where('is_active',1)
                        ->orderBy('so_name')
                        ->get();
        
        return response()->json(['status' => 200, 'data' => $DB]);
    }

    public function getDropdownUOM(Request $request){
        $DB = UOM::select('uom_id','uom_name')
                    ->where('is_active',1)
                    ->orderBy('uom_name')
                    ->get();
                    
        return response()->json(['status' => 200, 'data' => $DB]);
    }

    public function getDropdownSO(Request $request){
        $DB = StrategicObjectiveModel::select('so_id','so_name')
                                        ->where('is_active',1)
                                        ->orderBy('so_name')
                                        ->get();

        return response()->json(['status' => 200, 'data' => $DB]);
    }

    public function getDropdownSmartKPI(Request $request){
        $DB = DB::table('appraisal_item')->select('item_id','item_name')
                    ->join('appraisal_structure','appraisal_structure.structure_id','=','appraisal_item.structure_id')
                    ->where('appraisal_structure.form_id',1)
                    ->where('appraisal_item.is_active',1)
                    ->where('appraisal_structure.is_active',1)
                    ->orderBy('item_name')
                    ->get();

        return response()->json(['status' => 200, 'data' => $DB]);
    }

    public function getDropdownValueType(Request $request){
        $DB = ValueTypeModel::select('value_type_id','value_type_name')
                            ->orderBy('value_type_id')
                            ->get();

        return response()->json(['status' => 200, 'data' => $DB]);
    }


    public function show(Request $request){
        $SO = !empty($request->so_id)?$request->so_id:'%';
        $item_name = !empty($request->item_name)?$request->item_name:'';
        $DB = DB::table('so_item')->select('so_item_id','so_item.so_id','so_item_name','so_item.item_id','so_item.uom_id',
                                    'so_item.value_type_id','so_item.function_type','appraisal_item.item_name',
                                    'so_item.is_active','strategic_objective.so_name')
                    ->join('appraisal_item','so_item.item_id','=','appraisal_item.item_id')
                    ->join('appraisal_structure','appraisal_structure.structure_id','=','appraisal_item.structure_id')
                    ->join('strategic_objective','strategic_objective.so_id','=','so_item.so_id')
                    ->where('appraisal_item.item_name','like',"%".$item_name."%")
                    ->where('strategic_objective.so_id','like',$SO)         
                    ->where('appraisal_structure.form_id',1)
                    ->where('appraisal_item.is_active',1)
                    ->where('appraisal_structure.is_active',1)
                    ->where('so_item.is_active',1)
                    ->where('strategic_objective.is_active',1)
                    ->orderBy('so_item_name')
                    ->get();

        return response()->json(['status' => 200, 'data' => $DB]);   
    }

    public function autocomplete(Request $request){
        $DB = DB::table('appraisal_item')->select('item_name')
                    ->join('appraisal_structure','appraisal_structure.structure_id','=','appraisal_item.structure_id')  
                    ->where('appraisal_structure.form_id',1)
                    ->where('appraisal_item.is_active',1)
                    ->groupBy('appraisal_item.item_name')
                    ->get();

        return response()->json(['status' => 200, 'data' => $DB]);   
    }

    public function store(Request $request){

        $validator = Validator::make($request->all(), [	
            'so_id' => 'required|integer',
            'so_item_name' => 'required|unique:so_item,so_item_name',
            'item_id' => 'required|integer',
            'uom_id' => 'required|integer',
            'value_type_id' => 'required|integer',
            'function_type' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400, 'data' => $validator->errors()]);
        } else{

                $item = new SOItemModel;
				$item->fill($request->all());
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
				$item->save();
        }
        return response()->json(['status' => 200, 'data' => $item]);

    }

    public function update(Request $request,$id){
        try {
			$item = SOItemModel::findOrFail($id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'SO Item not found.']);
		}

        $validator = Validator::make($request->all(), [	
            'so_id' => 'required|integer',
            'so_item_name' => 'required|unique:so_item,so_item_name,'.$id . ',so_item_id',
            'item_id' => 'required|integer',
            'uom_id' => 'required|integer',
            'value_type_id' => 'required|integer',
            'function_type' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400, 'data' => $validator->errors()]);
        } else{
				$item->fill($request->all());
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
				$item->save();
        }
        return response()->json(['status' => 200, 'data' => $item]);

    }

    public function destroy(Request $request,$id){
        try {
			$item = SOItemModel::findOrFail($id);
		}catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'SO Item not found.']);
        }
        
        try {
			$item->delete();
		}catch (Exception $e) {
				return response()->json($e->errorInfo);
		}
		
		return response()->json(['status' => 200]);
    }

}
