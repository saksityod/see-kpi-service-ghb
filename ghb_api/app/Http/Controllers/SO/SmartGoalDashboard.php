<?php

namespace App\Http\Controllers\SO;

use App\Perspective;
use App\Subtask;
use App\So;
use App\Result;
use App\Project;
use App\AppraisalPeriod;
use App\ResultTotal;

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

class SmartGoalDashboard extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
    
    //สี ที่ใช้กำหนดสี S M A R T 
	public function getSmartColor(Request $request){
        $PerspectiveData = Perspective::select('perspective_id','perspective_abbr','color_code')
                                ->where("is_active",1)
                                ->get();
                                        
        return response()->json(["status" => 200,"data" => $PerspectiveData]);
    }

    //ปีในการ กรอง ข้อมูล โดย มาจาก Appraisal Period
    public function getYear(Request $request){
        $SubtaskData = AppraisalPeriod::select('appraisal_year')
                                ->orderBy('appraisal_year','DESC')
                                ->groupBy('appraisal_year')
                                ->get();

        $data = [];
        foreach($SubtaskData as $item){
            $tdata = array("year"=>$item->appraisal_year);
            array_push($data,$tdata);
        }
        
        return response()->json(["status" => 200,"data" => $data]); 
    }

    //ข้อมูลที่จะสร้าง circle และ สีที่ใช้ โดยแยก SOโดยแบ่งเป็น2ส่วน 1 คือ SO ,2 คือ project
    //* $SoOrProject จะส่ง ชื่อ คือ Project KPI หรือไม่ SO KPI
    public function getSOData(Request $request){

        $smart = !empty($request->smart)?$request->smart:"%";
    
        $resultTotal = So::select('sos.id as so_id','sos.abbr as so_abbr','so_kpis.id as so_kpi_id','so_kpis.name as so_kpi_name',
                                'appraisal_item.item_id','appraisal_item.item_name','perspective.perspective_id','result_totals.result_score',
                                'perspective_name','perspective.perspective_abbr','result_totals.id as result_total_id','result_totals.color_code')
                            ->leftJoin('so_kpis','so_kpis.so_id','=','sos.id')
                            ->leftJoin('appraisal_item','appraisal_item.item_id','=','so_kpis.item_id')
                            ->leftJoin('perspective','perspective.perspective_id','=','appraisal_item.perspective_id')
                            ->leftJoin('result_totals','result_totals.spmapable_id','=','sos.id')
                            ->where('perspective.perspective_abbr','like','%'.$smart.'%')
                            ->groupBy('sos.id')
                            ->orderBy('sos.id')
                            ->get(); 

        return response()->json(["status" => 200,"data" => $resultTotal]); 
    }

    //ข้อมูลที่จะไปสร้างGraph (circle) โดยที่ 1 = SO , 2 = Project
    public function getDataGraphCircle(Request $request,$id){
        $so_or_project = !empty($request->so_or_project)?$request->so_or_project:null;
        
        if(empty($id)){
            return response()->json(["status" => 400,"Error" => "id not send"]); 
        }else{
            //Check SO or Project
            if($so_or_project == 1){
                // is SO use function getDataGraphCircleSO 
                $circleData = $this->getDataGraphCircleSO($id); // id นี้คือ id ใน table sos

            }else if($so_or_project == 2){
                //is Project function getDataGraphCircleProject
                $circleData = $this->getDataGraphCircleProject($id);// id นี้คือ id ใน table so_kpis

                }else{
                    return response()->json(["status" => 400,"Error" => "Can not Check SO or Project"]); 
                }
            }
        //set optput Data
         $dataSource = [];
         foreach($circleData as $item){
             $tdata = [];
             $value_target = $item->value_target;
             $value_actual = $item->value_actual;
 
             $percen_actual_target = intval(($value_actual*100)/$value_target);
 
             array_push($tdata,array(
                 "label" => "Target",
                 "value" => $percen_actual_target>100?"0":100-$percen_actual_target,
             ));
 
             array_push($tdata,array(
                 "label" => "Actual",
                 "value" => $percen_actual_target,
             ));
 
         
 
             array_push($dataSource,array(
                 "id" => $item->id,
                 "name" => $item->name,
                 "kpi_id" => $item->kpi_id,
                 "kpi_name" => $item->kpi_name,
                 "item_id" => $item->item_id,
                 "item_name" => $item->item_name,
                 "value_target" => intval($item->value_target),
                 "value_actual" => intval($item->value_actual),
                 "percen_actual_target" => $percen_actual_target,
                 "dataSource" => $tdata
             ));
         }
         
         
         return response()->json(["status" => 200,"data" => $dataSource]); 

            
    }

    //function to send data from DB
    public function getDataGraphCircleSO($id){
        $circleData = So::select('sos.id','sos.abbr as name','so_kpis.id as kpi_id','so_kpis.name as kpi_name','results.id as result_id',
                                        'results.value_target','results.value_actual','appraisal_item.item_id as item_id','appraisal_item.item_name as item_name')
                                    ->leftJoin('so_kpis','so_kpis.so_id','=','sos.id')
                                    ->leftJoin('results','so_kpis.id','=','results.mappable_id')
                                    ->leftJoin('appraisal_item','appraisal_item.item_id','=','so_kpis.item_id')
                                    ->where('sos.id',$id)
                                    ->orderBy('sos.id')
                                    ->orderBy('so_kpis.id')     
                                    ->get();
        
        return $circleData; 

    }

    //function to send data from DB
    public function getDataGraphCircleProject($id){

        $map_so_kpi_project = Project::select('project_so_kpi.so_kpi_id','project_so_kpi.project_id')
                                    ->leftJoin('project_so_kpi','project_so_kpi.project_id','=','projects.id')
                                    ->leftJoin('so_kpis','so_kpis.id','=','project_so_kpi.so_kpi_id')
                                    ->where('project_so_kpi.so_kpi_id',$id)
                                    ->get();

        
        $whereIn = [];
        foreach($map_so_kpi_project as $items){
            array_push($whereIn,$items->project_id);
        }

        $circleData = Project::select('projects.id','projects.name','project_kpis.id AS kpi_id','project_kpis.name AS kpi_name',
                                        'results.id AS result_id','results.value_target','results.value_actual','so_kpis.id as item_id','so_kpis.name as item_name')
                                    ->leftJoin('project_project_kpi','projects.id','=','project_project_kpi.project_id')
                                    ->leftJoin('project_kpis','project_kpis.id','=','project_project_kpi.project_kpi_id')
                                    ->leftJoin('results','results.mappable_id','=','project_kpis.id')
                                    ->leftJoin('project_so_kpi','project_so_kpi.project_id','=','projects.id')
                                    ->leftJoin('so_kpis','so_kpis.id','=','project_so_kpi.so_kpi_id')
                                    ->whereIn('projects.id',$whereIn)
                                    ->where('project_so_kpi.so_kpi_id',$id)
                                    ->orderBy('projects.id')
                                    ->orderBy('project_kpis.id')       
                                    ->get();
        
        return $circleData;
         
    }

    
    public function getDataGraphHistogram(Request $request,$id){
        $so_or_project = !empty($request->so_or_project)?$request->so_or_project:null;
        if($so_or_project == 1){
            // is SO use function getDataGraphCircleSO 
            $circleData = $this->getDataGraphHistogramSo($id);// id นี้คือ id ใน table sos

        }else if($so_or_project == 2){
            //is Project function getDataGraphCircleProject
            $circleData = $this->getDataGraphHistogramProject($id);// id นี้คือ id ใน table sos


            }else{
                return response()->json(["status" => 400,"Error" => "Can not Check SO or Project"]); 
            }
        

            // set output
        
            $data = [];
            $kpi_id = null;
            $id = null;
            $tdata_actual = [];
            $tdata_forecast = [];
            $category = [];
            $categories = [];
            $t_item;
            $start_item;
            $sum =0;
            //start set Data
            foreach($circleData as $key=>$item){

                // ถ้าเป็น null ไม่เอา
                if($item->month_no == null){
                    continue;
                }   
                
                // set ครั้งแรก
                if($kpi_id==null){
                    $kpi_id = $item->kpi_id;
                    $id = $item->id;
                    $start_item=$item;
                }
                
                // ถ้า kpi_id ไม่เหมือน ให้ set เป็น group ใหม่
                if($kpi_id != $item->kpi_id || $id != $item->id){
                    $t_item = $item;
                    $start_item = $circleData[$key-1];
                    array_push($categories,array(
                        "category" => $category
                    ));

                    array_push($data,array(
                        "id" => $start_item->id,
                        "name" => $start_item->name,
                        "kpi_id" => $start_item->kpi_id,
                        "kpi_name" => $start_item->kpi_name,
                        "item_id" => $start_item->item_id,
                        "item_name" => $start_item->item_name,
                        "total" => $sum,
                        "dataSource_actual" => $tdata_actual,
                        "dataSource_forecast" => $tdata_forecast,
                        "category" => $categories
                    ));

                    $tdata_actual = [];
                    $tdata_forecast = [];
                    $category = [];
                    $categories = [];
                    $sum=0;
                    $id = $item->id;
                    $kpi_id = $item->kpi_id;
                }

                
                array_push($tdata_forecast,array(
                    "value" => strval($item->value_forecast)
                ));

                $sum+=$item->value_actual;
                array_push($tdata_actual,array(
                    "value" => strval($item->value_actual)
                ));

                array_push($category,array(
                    "label" => $item->month_name
                )); 
                $temp_data=$item;
                
            }
            // Add last array
            $categories = [];
            array_push($categories,array(
                "category" => $category
            ));

            array_push($data,array(
                "id" => $t_item->id,
                "name" => $t_item->name,
                "kpi_id" => $t_item->kpi_id,
                "kpi_name" => $t_item->kpi_name,
                "item_id" => $t_item->item_id,
                "item_name" => $t_item->item_name,
                "total" => $sum,
                "dataSource_actual" => $tdata_actual,
                "dataSource_forecast" => $tdata_forecast,
                "category" => $categories
            ));
            //End Set Data 

        return response()->json(["status" => 200,"data" => $data]); 
    }

    public function getDataGraphHistogramSo($id){
        $circleData = So::select('sos.id','sos.abbr as name','so_kpis.id as kpi_id','so_kpis.name as kpi_name','result_months.id as result_month_id',
                                'result_months.month_no','result_months.month_name','result_months.value_forecast','result_months.value_actual',
                                'appraisal_item.item_id as item_id','appraisal_item.item_name as item_name')
                            ->leftJoin('so_kpis','so_kpis.so_id','=','sos.id')
                            ->leftJoin('results','so_kpis.id','=','results.mappable_id')
                            ->leftJoin('appraisal_item','appraisal_item.item_id','=','so_kpis.item_id')
                            ->leftJoin('result_months','result_months.result_id','=','results.id')
                            ->where('sos.id',$id)
                            ->orderBy('sos.id')
                            ->orderBy('so_kpis.id')    
                            ->orderBy('month_no')
                            ->get();

        return $circleData;

    }

    public function getDataGraphHistogramProject($id){

        $map_so_kpi_project = Project::select('project_so_kpi.so_kpi_id','project_so_kpi.project_id')
                                    ->leftJoin('project_so_kpi','project_so_kpi.project_id','=','projects.id')
                                    ->leftJoin('so_kpis','so_kpis.id','=','project_so_kpi.so_kpi_id')
                                    ->where('project_so_kpi.so_kpi_id',$id)
                                    ->get();
        
        $whereIn = [];
        foreach($map_so_kpi_project as $items){
            array_push($whereIn,$items->project_id);
        }

        $circleData = Project::select('projects.id','projects.name','project_kpis.id AS kpi_id','project_kpis.name AS kpi_name',
                                    'result_months.month_no','result_months.month_name','result_months.value_forecast','result_months.value_actual',
                                    'result_months.id as result_month_id','so_kpis.id as item_id','so_kpis.name as item_name')
                                ->leftJoin('project_project_kpi','projects.id','=','project_project_kpi.project_id')
                                ->leftJoin('project_kpis','project_kpis.id','=','project_project_kpi.project_kpi_id')
                                ->leftJoin('results','results.mappable_id','=','project_kpis.id')
                                ->leftJoin('project_so_kpi','project_so_kpi.project_id','=','projects.id')
                                ->leftJoin('so_kpis','so_kpis.id','=','project_so_kpi.so_kpi_id')
                                ->leftJoin('result_months','result_months.result_id','=','results.id')
                                ->whereIn('projects.id',$whereIn)
                                ->where('project_so_kpi.so_kpi_id',$id)
                                ->orderBy('projects.id')
                                ->orderBy('project_kpis.id')
                                ->orderBy('month_no')
                                ->get();
                                
        return $circleData;

    }

}
