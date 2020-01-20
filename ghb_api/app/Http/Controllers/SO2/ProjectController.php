<?php

namespace App\Http\Controllers\SO2;

use App\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // $sets = Set::all();
        $prj = Project::paginate(10);
        
        // for ver 5.1
        return response()->json($prj);
        // return SoResource::collection($sos);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Project  $project
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $prj = Project::with('so_kpis')->find($request->id);
        // $prj->only(['id','name','abbr','color_code']);

        return response()->json($prj);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Project  $project
     * @return \Illuminate\Http\Response
     */
    public function destroy(Project $project)
    {
        //
    }

    /**
     * ดึง KPI ทั้งหมดของ Project นี้
     * 
     * สำหรับหน้า 'Project/Assign'
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function kpis(Request $request)
    {
        /* 
         *  ดึงออกมาจาก
         *  ตั้งแต่ Project --> Project_KPI
         */
        $prj = Project::with(
                    'project_kpis'
                )->find($request->id);
        
        // รอเสริมสวย ตาม spec ของ Front-End

        return response()->json($prj);
    }

    /**
     * ดึง KPIs และ weights (ในตาราง results) ทั้งหมดของ Project นี้
     * 
     * สำหรับหน้า 'Project Assignment/Edit'
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function weights(Request $request)
    {
        /* ดึงออกมาตั้งแต่
         *    Project --> Project_KPI --> Result
         */
        $prj = Project::with(
                    'project_kpis'
                    ,'project_kpis.results'
                    // ,'project_kpis.results.result_months'
                )->find($request->id);

        // รอเสริมสวย ตาม spec ของ Front-End

        return response()->json($prj);
    }

    /**
     * ดึง KPIs และ Results ลงถึงระดับ แต่ละเดือน ของ Project นี้
     * 
     * สำหรับหน้า 'SO Project Result - 1'
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function months(Request $request)
    {
        /* 
         *  ดึงออกมาจาก
         *  ตั้งแต่ Project --> Project_KPI --> Results --> ResultMonth
         */
        $prj = Project::with(
                    'project_kpis'
                    ,'project_kpis.results'
                    ,'project_kpis.results.result_months'
                )->find($request->id);
        
        // รอเสริมสวย ตาม spec ของ Front-End

        return response()->json($prj);
    }

    /**
     * เอาตัวเลข Grand Total สำหรับ Project นี้
     * 
     * สำหรับหน้า 'Project Assignment/Edit'
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function total(Request $request)
    {
        /* ดึงออกมาจาก
         *    Project --> ResultTotal
         */
        $prj = Project::with(
                    'result_totals'
                    // ,'result_totals.result_total'
                    // ,'result_totals.results.result_months'
                )->find($request->id);

        $sum = 0.0;
        foreach($prj->result_totals as $index => $total){
            $sum += $total->result_score;
        }        

        // ถ้าจะเอาแค่ตัวเลข (ไม่ต้องไป loop บน FrontEnd)
        // return response()->json($sum);

        return response()->json($prj);
    }
}
