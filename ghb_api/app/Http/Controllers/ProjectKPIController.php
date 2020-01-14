<?php

namespace App\Http\Controllers;
use App\Model\ProjectItem;
use App\UOM;
use App\Model\Project;
use App\Model\StrategicObjective;

use Auth;
use DB;
use File;
use Validator;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProjectKPIController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
    public function dropdownProjectKPIName(Request $request){
        $DB = ProjectItem::select('project_item_id','project_item_name')
                ->orderBy('project_item_name')
                ->get();
        
        return response()->json(['status' => 200, 'data' => $DB]); 
    }

    public function dropdownProjectName(Request $request){
        $project_item_id= !empty($request->project_item_id)?$request->project_item_id:'%';
        $DB = DB::table('project_by_project_item_mapping')->select('project.project_name','project.project_id')
                    ->join('project','project_by_project_item_mapping.project_id'
                            ,'=','project.project_id')
                    ->join('project_item','project_by_project_item_mapping.project_item_id'
                            ,'=','project_item.project_item_id')
                    ->where('project.is_active',1)
                    ->where('project_item.is_active',1)
                    ->where('project_item.project_item_id','like',$project_item_id)
                    ->groupBy('project.project_name')
                    ->get();
        
        return response()->json(['status' => 200, 'data' => $DB]); 
    }

    public function show(Request $request){
        $emp_code = Auth::id();
        $project_item_id = !empty($request->project_item_id)?$request->project_item_id:'%';
        $project_id = !empty($request->project_id)?$request->project_id:'%';
        if(strcmp($emp_code, 'admin') == 0){

        $DB = DB::table('project_by_project_item_mapping')->select('project_item.project_item_id')
                    ->join('project','project_by_project_item_mapping.project_id'
                        ,'=','project.project_id')
                    ->join('project_item','project_by_project_item_mapping.project_item_id',
                        '=','project_item.project_item_id')
                    ->where('project.project_id','like',$project_id)
                    ->where('project_item.project_item_id','like',$project_item_id)
                    ->where('project.is_active',1)
                    ->groupBy('project_item.project_item_id')
                    ->get();
        
        $data = [];
        $tempdata ;

        foreach($DB as $key){
            $DB_data=DB::table('project_by_project_item_mapping')->select('project_item.project_item_id',
                                'project_item.project_item_name','project_item.is_active','project.emp_id' ,'project_item.uom_id'
                                ,'project_item.value_type_id','project_item.function_type' )
                            ->join('project','project_by_project_item_mapping.project_id'
                                ,'=','project.project_id')
                            ->join('project_item','project_by_project_item_mapping.project_item_id',
                                '=','project_item.project_item_id')
                            ->where('project.project_id','like',$project_id)
                            ->where('project_item.project_item_id','like',$key->project_item_id)
                            ->where('project.is_active',1)
                            ->get();

            $DB_data_project=DB::table('project_by_project_item_mapping')->select('project.project_id','project.project_name' )
                                    ->join('project','project_by_project_item_mapping.project_id'
                                        ,'=','project.project_id')
                                    ->join('project_item','project_by_project_item_mapping.project_item_id',
                                        '=','project_item.project_item_id')
                                    ->where('project.project_id','like',$project_id)
                                    ->where('project_item.project_item_id','like',$key->project_item_id)
                                    ->where('project.is_active',1)
                                    ->orderBy('project_item.project_item_id')
                                    ->get();
            $str_project_name="";
            $project_id_result =[];
            $emp_id =[];
            foreach($DB_data_project as $item){
                $str_project_name = $str_project_name . $item->project_name . " ";
                array_push($project_id_result,(int)$item->project_id);
            }


            foreach($DB_data as $item){
                $project_item_id=$item->project_item_id;
                $project_item_name=$item->project_item_name;
                $is_active=$item->is_active;
                $uom_id = $item->uom_id;
                $value_type_id = $item->value_type_id;
                $function_type = $item->function_type;
                array_push($emp_id,(int)$item->emp_id);
            }

            $tempdata = array(
                "project_id" => $project_id_result,
                "project_name" => $str_project_name,
                "project_item_id" => $project_item_id,
                "project_item_name" => $project_item_name,
                "is_active" => $is_active,
                "emp_id" => $emp_id,
                "uom_id" => $uom_id,
                "value_type_id"=> $value_type_id,
                "function_type" => $function_type

            );
            array_push($data,$tempdata);
        }

                    
        
        }else{
            $DB = DB::table('project_by_project_item_mapping')->select('project.project_id','project_item.project_item_id','project.project_name'
                        ,'project_item.project_item_name','project_item.is_active')
                    ->join('project','project_by_project_item_mapping.project_id'
                        ,'=','project.project_id')
                    ->join('project_item','project_by_project_item_mapping.project_item_id'
                        ,'=','project_item.project_item_id')
                    ->join('employee','project.emp_id','=','employee.emp_id')
                    ->where('emp_code',$emp_code)
                    ->where('project.is_active',1)
                    ->where('project.project_id','like',$project_id)
                    ->where('project_item.project_item_id','like',$project_item_id)
                    ->groupBy('project_item.project_item_id')
                    ->get();
                    $data = [];
                    $tempdata ;
            
            foreach($DB as $key){
                $DB_data=DB::table('project_by_project_item_mapping')->select('project_item.project_item_id'
                                    ,'project_item.project_item_name','project_item.is_active','project.emp_id','project_item.uom_id'
                                    ,'project_item.value_type_id','project_item.function_type' )
                                ->join('project','project_by_project_item_mapping.project_id'
                                    ,'=','project.project_id')
                                ->join('project_item','project_by_project_item_mapping.project_item_id'
                                    ,'=','project_item.project_item_id')
                                ->join('employee','project.emp_id','=','employee.emp_id')
                                ->where('emp_code',$emp_code)
                                ->where('project.project_id','like',$project_id)
                                ->where('project_item.project_item_id','like',$key->project_item_id)
                                ->where('project.is_active',1)
                                ->get();
            
                $DB_data_project=DB::table('project_by_project_item_mapping')->select('project.project_id','project.project_name' )
                                        ->join('project','project_by_project_item_mapping.project_id'
                                            ,'=','project.project_id')
                                        ->join('project_item','project_by_project_item_mapping.project_item_id',
                                            '=','project_item.project_item_id')
                                        ->join('employee','project.emp_id','=','employee.emp_id')
                                        ->where('emp_code',$emp_code)
                                        ->where('project.project_id','like',$project_id)
                                        ->where('project_item.project_item_id','like',$key->project_item_id)
                                        ->where('project.is_active',1)
                                        ->orderBy('project_item.project_item_id')
                                        ->get();

                        $str_project_name="";
                        $project_id_result =[];
                        $emp_id =[];
                        foreach($DB_data_project as $item){
                            $str_project_name = $str_project_name . $item->project_name . " ";
                            array_push($project_id_result,(int)$item->project_id);
                        }
            
            
                        foreach($DB_data as $item){
                            $project_item_id=$item->project_item_id;
                            $project_item_name=$item->project_item_name;
                            $is_active=$item->is_active;
                            $uom_id = $item->uom_id;
                            $value_type_id = $item->value_type_id;
                            $function_type = $item->function_type;
                            array_push($emp_id,(int)$item->emp_id);
                        }
            
                        $tempdata = array(
                            "project_id" => $project_id_result,
                            "project_name" => $str_project_name,
                            "project_item_id" => $project_item_id,
                            "project_item_name" => $project_item_name,
                            "is_active" => $is_active,
                            "emp_id" => $emp_id,
                            "uom_id" => $uom_id,
                            "value_type_id"=> $value_type_id,
                            "function_type" => $function_type
            
                        );
                        array_push($data,$tempdata);
                }   
            }
        return response()->json(['status' => 200, 'data' => $data ]); 
    }

    public function destroy(Request $request,$project_item_id){
        try {
			$item = ProjectItem::findOrFail($project_item_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Project not found.']);
        }
        try{
            $this->clearMapping($project_item_id);
        }catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Project not clear mapping.']);
        }
		$item->delete();
		return response()->json(['status' => 200,"data"=>"Delete Successfully"]);
    }

    public function store(Request $request){
        $validator = Validator::make($request->all(), [	
            'project_item_name' => 'required|unique:project_item,project_item_name',
            'uom_id' => 'required|integer',
            'value_type_id' => 'required|integer',
            'function_type' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400, 'data' => $validator->errors()]);
        } else{
            try{
                $item = new ProjectItem;
				$item->fill($request->all());
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
                $item->save();
                
                $id = ProjectItem::max('project_item_id');
                foreach($request->project_id as $item){
                    $mapping = DB::table('project_by_project_item_mapping')
                                    ->insert(array(
                                        "project_id"=>$item,
                                        "project_item_id"=>$id,
                                        'created_by' => Auth::id(),
                                        'created_dttm' => date("Y-m-d H:i:s")
                                    ));
                }
            }catch(ModelNotFoundException $e){
                return response()->json(['status' => 404, 'data' => 'Can not insert data.']);
            }
        }
        return response()->json(['status' => 200,"data"=>"Insert Successfully"]);

    }

    public function update(Request $request,$project_item_id){
        try {
			$item = ProjectItem::findOrFail($project_item_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Project Item not found.']);
        }

        $validator = Validator::make($request->all(), [	
            'project_item_name' => 'required|unique:project_item,project_item_name,'.$project_item_id . ',project_item_id',
            'uom_id' => 'required|integer',
            'value_type_id' => 'required|integer',
            'function_type' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400, 'data' => $validator->errors()]);
        } else{
            try{
				$item->fill($request->all());
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
                $item->save();
                
                $this -> clearMapping($project_item_id);

                foreach($request->project_id as $item){
                    $mapping = DB::table('project_by_project_item_mapping')
                                    ->insert(array(
                                        "project_id"=>$item,
                                        "project_item_id"=>$project_item_id,
                                        'created_by' => Auth::id(),
                                        'created_dttm' => date("Y-m-d H:i:s")
                                    ));
                }
            }catch(ModelNotFoundException $e){
                return response()->json(['status' => 404, 'data' => 'Can not update data.']);
            }
        }
        return response()->json(['status' => 200,"data"=>"Update Successfully"]);
        
    }

    public function clearMapping($clearID){
        DB::table('project_by_project_item_mapping')
            ->where('project_item_id',$clearID)
            ->delete();
    }

    

}
