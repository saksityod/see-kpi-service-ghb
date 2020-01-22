<?php

namespace App\Http\Controllers\SO;

use App\Model\ProjectKpi;
use App\UOM;
use App\Model\Project;
use App\So;

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
    
    // เลือก id,name จาก table project_kpis 
    public function dropdownProjectKPIName(Request $request){
        $DB = ProjectKpi::select('id','name')
                ->orderBy('name')
                ->get();
        
        return response()->json(['status' => 200, 'data' => $DB]); 
    }

    // เลือก  id name จาก table projects
    public function dropdownProjectName(Request $request){
        $project_item_id= !empty($request->project_item_id)?$request->project_item_id:'%';
        $DB = DB::table('project_project_kpi')->select('projects.name','projects.id')
                    ->join('projects','project_project_kpi.project_id'
                            ,'=','projects.id')
                    ->join('project_kpis','project_project_kpi.project_kpi_id'
                            ,'=','project_kpis.id')
                    ->where('projects.is_active',1)
                    ->where('project_kpis.is_active',1)
                    ->where('project_kpis.id','like',$project_item_id)
                    ->groupBy('projects.name')
                    ->get();
        
        return response()->json(['status' => 200, 'data' => $DB]); 
    }

    public function getSelectMapping(Request $request){
        $dataSelect = Project::select('id','name')->get();

        return response()->json(['status' => 200, 'data' => $dataSelect]); 
    }

    // จะมีแบ่งเป็น admin กับ user โดย ที่ user จะดูได้แต่ ข้อมูลเฉพาะ ที่เป็นของตัวเอง และ ผู้รับผิดชอบ ของโครงการ 
    // ส่วน admin จะดู ได้ทั้งหมด
    public function show(Request $request){
        $emp_code = Auth::id();
        $project_item_id = !empty($request->project_item_id)?$request->project_item_id:'%';
        $project_id = !empty($request->project_id)?$request->project_id:'%';

        // กรณี เป็น admin
        if(strcmp($emp_code, 'admin') == 0){

        $DB = DB::table('project_project_kpi')->select('project_kpis.id')
                    ->join('projects','project_project_kpi.project_id'
                        ,'=','projects.id')
                    ->join('project_kpis','project_project_kpi.project_kpi_id',
                        '=','project_kpis.id')
                    ->where('projects.id','like',$project_id)
                    ->where('project_kpis.id','like',$project_item_id)
                    ->where('projects.is_active',1)
                    ->groupBy('project_kpis.id')
                    ->get();
        
        $data = [];
        $tempdata ;

        foreach($DB as $key){
            $DB_data=DB::table('project_project_kpi')->select('project_kpis.id',
                                'project_kpis.name','project_kpis.is_active','projects.emp_id' ,'project_kpis.uom_id'
                                ,'project_kpis.value_type_id','project_kpis.function_type' )
                            ->join('projects','project_project_kpi.project_id'
                                ,'=','projects.id')
                            ->join('project_kpis','project_project_kpi.project_kpi_id',
                                '=','project_kpis.id')
                            ->where('projects.id','like',$project_id)
                            ->where('project_kpis.id','like',$key->id)
                            ->where('projects.is_active',1)
                            ->get();

            $DB_data_project=DB::table('project_project_kpi')->select('projects.id','projects.name' )
                                    ->join('projects','project_project_kpi.project_id'
                                        ,'=','projects.id')
                                    ->join('project_kpis','project_project_kpi.project_kpi_id',
                                        '=','project_kpis.id')
                                    ->where('projects.id','like',$project_id)
                                    ->where('project_kpis.id','like',$key->id)
                                    ->where('projects.is_active',1)
                                    ->orderBy('project_kpis.id')
                                    ->get();

            //เป็นการจัดการข้อมูลที่จะส่งออก โดย จะได้ object ที่มี template ตามข้างล่าง
            // {
            // "project_id": [ 
            //     42,
            //     46
            // ],
            // "project_name": "p99, Project 3 update",
            // "project_item_id": 1,
            // "project_item_name": "testmap",
            // "is_active": 1,
            // "emp_id": [
            //     3,
            //     8
            // ],
            // "uom_id": 1,
            // "value_type_id": 1,
            // "function_type": 1
            // }
            $str_project_name="";
            $project_id_result =[];
            $emp_id =[];
            foreach($DB_data_project as $item){
                $str_project_name = $str_project_name . $item->name . ", ";
                array_push($project_id_result,(int)$item->id);
            }
            $str_project_name=substr($str_project_name,0,-2);

            foreach($DB_data as $item){
                $project_item_id=$item->id;
                $project_item_name=$item->name;
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
            // ส่วนของ user
        }else{
            $DB = DB::table('project_project_kpi')->select('projects.id','project_kpis.id','projects.name'
                        ,'project_kpis.name','project_kpis.is_active')
                    ->join('projects','project_project_kpi.project_id'
                        ,'=','projects.id')
                    ->join('project_kpis','project_project_kpi.project_kpi_id'
                        ,'=','project_kpis.id')
                    ->join('employee','projects.emp_id','=','employee.emp_id')
                    ->where('emp_code',$emp_code)
                    ->where('projects.is_active',1)
                    ->where('projects.id','like',$project_id)
                    ->where('project_kpis.id','like',$project_item_id)
                    ->groupBy('project_kpis.id')
                    ->get();
                    $data = [];
                    $tempdata ;
            
            foreach($DB as $key){
                $DB_data=DB::table('project_project_kpi')->select('project_kpis.id'
                                    ,'project_kpis.name','project_kpis.is_active','projects.emp_id','project_kpis.uom_id'
                                    ,'project_kpis.value_type_id','project_kpis.function_type' )
                                ->join('projects','project_project_kpi.project_id'
                                    ,'=','projects.id')
                                ->join('project_kpis','project_project_kpi.project_kpi_id'
                                    ,'=','project_kpis.id')
                                ->join('employee','projects.emp_id','=','employee.emp_id')
                                ->where('emp_code',$emp_code)
                                ->where('projects.id','like',$project_id)
                                ->where('project_kpis.id','like',$key->id)
                                ->where('projects.is_active',1)
                                ->get();
            
                $DB_data_project=DB::table('project_project_kpi')->select('projects.id','projects.name' )
                                        ->join('projects','project_project_kpi.id'
                                            ,'=','projects.id')
                                        ->join('project_kpis','project_project_kpi.project_kpi_id',
                                            '=','project_kpis.id')
                                        ->join('employee','projects.emp_id','=','employee.emp_id')
                                        ->where('emp_code',$emp_code)
                                        ->where('projects.id','like',$project_id)
                                        ->where('project_kpis.id','like',$key->id)
                                        ->where('projects.is_active',1)
                                        ->orderBy('project_kpis.id')
                                        ->get();
                        //เป็นการจัดการข้อมูลที่จะส่งออก โดย จะได้ object ที่มีข้อมูล ตามข้างล่าง
                        // {
                        // "project_id": [ 
                        //     42,
                        //     46
                        // ],
                        // "project_name": "p99, Project 3 update",
                        // "project_item_id": 1,
                        // "project_item_name": "testmap",
                        // "is_active": 1,
                        // "emp_id": [
                        //     3,
                        //     8
                        // ],
                        // "uom_id": 1,
                        // "value_type_id": 1,
                        // "function_type": 1
                        // }

                        $str_project_name="";
                        $project_id_result =[];
                        $emp_id =[];
                        foreach($DB_data_project as $item){
                            $str_project_name = $str_project_name . $item->name . " ";
                            array_push($project_id_result,(int)$item->id);
                        }
                        $str_project_name=substr($str_project_name,0,-2);
            
                        foreach($DB_data as $item){
                            $project_item_id=$item->id;
                            $project_item_name=$item->name;
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

    //ลบ ข้อมูล ใน table project_kpis และ ลบ ข้อมูลที่mapping ใน table project_project_kpi *โดยเรียก function clearMapping
    public function destroy(Request $request,$project_item_id){
        try {
			$item = ProjectKpi::findOrFail($project_item_id);
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
    
    //เพิ่ม ข้อมูล ลงใน table project_kpis และ เพิ่ม ข้อมูลในการ mapping ระหว่าง projects กับ project_kpis โดย บันทึกลงใน project_project_kpi
    public function store(Request $request){
        $validator = Validator::make($request->all(), [	
            'name' => 'required|unique:project_kpis,name',
            'uom_id' => 'required|integer',
            'value_type_id' => 'required|integer',
            'function_type' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400, 'data' => $validator->errors()]);
        } else{
            try{
                $item = new ProjectKpi;
				$item->fill($request->all());
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
                $item->save();
                
                $id = ProjectKpi::max('id');
                foreach($request->project_id as $item){
                    $mapping = DB::table('project_project_kpi')
                                    ->insert(array(
                                        "project_id"=>$item,
                                        "project_kpi_id"=>$id,
                                        'created_by' => Auth::id(),
                                        'created_at' => date("Y-m-d H:i:s")
                                    ));
                }
            }catch(ModelNotFoundException $e){
                return response()->json(['status' => 404, 'data' => 'Can not insert data.']);
            }
        }
        return response()->json(['status' => 200,"data"=>"Insert Successfully"]);

    }

    // update ข้อมูล ลงใน table project_kpis และ update ข้อมูลในการ mapping ระหว่าง projects กับ project_kpis โดย บันทึกลงใน project_project_kpi
    // *เรียก function clearMapping เพื่อล้างข้อมูลเก่าที่mapping ก่อน เพื่อจะบันทึกใหม่ ลงไป
    public function update(Request $request,$id){
        try {
			$item = ProjectKpi::findOrFail($id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Project Item not found.']);
        }

        $validator = Validator::make($request->all(), [	
            'name' => 'required|unique:project_kpis,name,'.$id . ',id',
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
                $item->updated_by = Auth::id();
                $item->updated_at = date("Y-m-d H:i:s");
                $item->save();
                $this -> clearMapping($id);

                foreach($request->project_id as $item){
                    $mapping = DB::table('project_project_kpi')
                                    ->insert(array(
                                        "project_id"=>$item,
                                        "project_kpi_id"=>$id,
                                        'created_by' => Auth::id(),
                                        'created_at' => date("Y-m-d H:i:s")
                                    ));
                }
            }catch(ModelNotFoundException $e){
                return response()->json(['status' => 404, 'data' => 'Can not update data.']);
            }
        }
        return response()->json(['status' => 200,"data"=>"Update Successfully"]);
        
    }

    // clear ข้อมูลที่ mapping โดยส่ง id ของ project_kpis
    public function clearMapping($clearID){
        DB::table('project_project_kpi')
            ->where('project_kpi_id',$clearID)
            ->delete();
    }
}
