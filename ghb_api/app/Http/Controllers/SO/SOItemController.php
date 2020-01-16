<?php

namespace App\Http\Controllers\SO;

use App\UOM;
use App\Model\ValueType;
use App\So;
use App\AppraisalItem;
use App\SoKpi;

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
        $DB = So::select('id','name')
                ->where('is_active',1)
                ->orderBy('name')
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
        $DB = So::select('id','name')
                ->where('is_active',1)
                ->orderBy('name')
                ->get();

        return response()->json(['status' => 200, 'data' => $DB]);
    }

    public function getDropdownSmartKPI(Request $request){
        $DB = AppraisalItem::select('item_id','item_name')
                    ->join('appraisal_structure','appraisal_structure.structure_id','=','appraisal_item.structure_id')
                    ->where('appraisal_structure.form_id',1)
                    ->where('appraisal_item.is_active',1)
                    ->where('appraisal_structure.is_active',1)
                    ->orderBy('item_name')
                    ->get();

        return response()->json(['status' => 200, 'data' => $DB]);
    }

    public function getDropdownValueType(Request $request){
        $DB = ValueType::select('value_type_id','value_type_name')
                            ->orderBy('value_type_id')
                            ->get();

        return response()->json(['status' => 200, 'data' => $DB]);
    }


    public function show(Request $request){
        $SO = !empty($request->so_id)?$request->so_id:'%';
        $item_name = !empty($request->item_name)?$request->item_name:'';
        $DB = SoKpi::select('so_kpis.id','so_kpis.so_id','so_kpis.name','so_kpis.item_id','so_kpis.uom_id',
                                'so_kpis.value_type_id','so_kpis.function_type','appraisal_item.item_name',
                                'so_kpis.is_active','sos.name as so_name')
                    ->join('appraisal_item','so_kpis.item_id','=','appraisal_item.item_id')
                    ->join('appraisal_structure','appraisal_structure.structure_id','=','appraisal_item.structure_id')
                    ->join('sos','sos.id','=','so_kpis.so_id')
                    ->where('appraisal_item.item_name','like',"%".$item_name."%")
                    ->where('sos.id','like',$SO)         
                    ->where('appraisal_structure.form_id',1)
                    ->where('appraisal_item.is_active',1)
                    ->where('appraisal_structure.is_active',1)
                    ->where('sos.is_active',1)
                    ->orderBy('so_kpis.name')
                    ->get();
        
        $DB_so_id_zero = SoKpi::select('so_kpis.id','so_kpis.name','so_kpis.item_id','so_kpis.uom_id',
                                    'so_kpis.value_type_id','so_kpis.function_type',
                                    'so_kpis.is_active','sos.name as so_name','so_kpis.so_id')
                                ->join('sos','sos.id','=','so_kpis.so_id')
                                ->where('sos.id','like',$SO)  
                                ->where('so_kpis.item_id',0)
                                ->where('so_kpis.is_active',1)
                                ->get();
                           

        $data=[];
        foreach($DB as $item){
            $t_data = array(
                "so_item_id" => $item->id,
                "so_item_name" => $item->name,
                "item_id" => $item->item_id,
                "uom_id" =>  $item->uom_id,
                "value_type_id" => $item->value_type_id,
                "function_type" => $item->function_type,
                "is_active" => $item->is_active,
                "so_name" => $item->so_name,
                "so_id" => $item->so_id,
                "item_name" => $item->item_name,
            );
            array_push($data,$t_data);
        }

        foreach($DB_so_id_zero as $item){
            $t_data = array(
                "so_item_id" => $item->id,
                "so_item_name" => $item->name,
                "item_id" => $item->item_id,
                "uom_id" =>  $item->uom_id,
                "value_type_id" => $item->value_type_id,
                "function_type" => $item->function_type,
                "is_active" => $item->is_active,
                "so_name" => $item->so_name,
                "so_id" => $item->so_id,
                "item_name" =>" ",
            );
            array_push($data,$t_data);
        }
        
        return response()->json(['status' => 200, 'data' => $data]);   
    }

    public function autocomplete(Request $request){
        $DB = AppraisalItem::select('item_name')
                    ->join('appraisal_structure','appraisal_structure.structure_id','=','appraisal_item.structure_id')  
                    ->where('appraisal_structure.form_id',1)
                    ->where('appraisal_item.is_active',1)
                    ->groupBy('appraisal_item.item_name')
                    ->get();

        return response()->json(['status' => 200, 'data' => $DB]);   
    }

    public function store(Request $request){

        $validator = Validator::make($request->all(), [	
            'name' => 'required|unique:so_kpis,name',
            'item_id' => 'required|integer',
            'uom_id' => 'required|integer',
            'value_type_id' => 'required|integer',
            'function_type' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400, 'data' => $validator->errors()]);
        } else{

                $item = new SoKpi;
				$item->fill($request->all());
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
				$item->save();
        }
        return response()->json(['status' => 200, 'data' => $item]);

    }

    public function update(Request $request,$id){
        try {
			$item = SoKpi::findOrFail($id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'SO Item not found.']);
		}

        $validator = Validator::make($request->all(), [	
            'name' => 'required|unique:so_kpis,name,'.$id . ',id',
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
			$item = SoKpi::findOrFail($id);
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
