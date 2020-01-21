<?php

namespace App\Http\Controllers\SO2;

use App\ProjectKpi;
use App\Result;
use App\ResultMonth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
// use App\Http\Resources\ProjectKpiResource;

class ProjectKpiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $projectKpi = ProjectKpi::paginate(10);
        
        return response()->json($projectKpi);
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
     * @param  \App\ProjectKpi  $projectKpi
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        // เลือกดูเฉพาะข้อมูลของ Project KPi นี้ (ไม่ลงไป level ล่าง)
        // $project_kpi = ProjectKpi::findOrFail($id);

        // ดึงออกมาทั้ง Tree
        $projectKpi = ProjectKpi::with(
            'results'
            ,'results.result_months'
        )->find($request->id);

        return response()->json($projectKpi);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\ProjectKpi  $projectKpi
     * @return \Illuminate\Http\Response
     */
    public function destroy(ProjectKpi $projectKpi)
    {
        //
    }

    /**
     * Update Target weight (in 'Result')
     *
     * @param  {
     *              "id":2,
     *              "results":[
     *                    {
     *                        "result_id":159,
     *                        "value_target": 11111,
     *                        "weight_percent": 22222
     *                    },
     *                   {
     *                        "result_id":170,
     *                        "value_target": 77777,
     *                        "weight_percent": 88888
     *                    }
     *                ]
     *            }
     * 
     * @return \Illuminate\Http\Response
     */
    public function update_target(Request $request)
    {
        $projectKpi = ProjectKpi::findOrFail($request->id);

        // Loop ทุกๆ Results Object
        foreach($request->results as $count => $req){

            // Loop ทุกๆ Result ของ SO KPI ตัวนี้
            foreach ($projectKpi->results as $index => $result) {

                // ถ้า id ของ Result ตรงตามที่ input เข้ามา
                if( $result->id == $req['result_id']){

                    // หา Result id ที่ว่า...
                    $res = Result::findOrFail($result->id);
                    // ...แล้ว update ค่า
                    $res->value_target = $req['value_target'];
                    $res->weight_percent = $req['weight_percent'];
                    $res->save();
                }
                // ยิง event เพื่อคำนวณผล % กับ total ใหม่
                // event(new ResultWeightUpdated($result));
            }
        }
        $projectKpi->save();

        return response()->json(['status' => 200]);
    }

    /**
     * Update Actual weight (in 'Result')
     *
     * @param  {
     *              "id":2,
     *              "results":[
     *                    {
     *                        "result_id":159,
     *                        "value_actual": 11111,
     *                        "weight_score": 22222
     *                    },
     *                   {
     *                        "result_id":170,
     *                        "value_actual": 77777,
     *                        "weight_score": 88888
     *                    }
     *                ]
     *            }
     * 
     * @return \Illuminate\Http\Response
     */
    public function update_actual(Request $request)
    {
        $projectKpi = ProjectKpi::findOrFail($request->id);

        // Loop ทุกๆ Results Object
        foreach($request->results as $count => $req){

            // Loop ทุกๆ Result ของ SO KPI ตัวนี้
            foreach ($projectKpi->results as $index => $result) {

                // ถ้า id ของ Result ตรงตามที่ input เข้ามา
                if( $result->id == $req['result_id']){

                    // หา Result id ที่ว่า...
                    $res = Result::findOrFail($result->id);
                    // ...แล้ว update ค่า
                    $res->value_actual = $req['value_actual'];
                    $res->weight_score = $req['weight_score'];
                    $res->save();
                }
                // ยิง event เพื่อคำนวณผล % กับ total ใหม่
                // event(new ResultWeightUpdated($result));
            }
        }
        $projectKpi->save();

        return response()->json(['status' => 200]);
    }

    /**
     * Update value per Months
     *
     * @param  {
     *              "id":2,
     *              "results":[
     *                    {
     *                        "month_no":10,
     *                        "year_no":2019,
     *                        "value_forecast": 11111,
     *                        "value_actual": 22222
     *                    },
     *                   {
     *                        "month_no":8,
     *                        "year_no":2018,
     *                        "value_forecast": 77777,
     *                        "value_actual": 88888
     *                    }
     *                ]
     *            }
     * 
     * @return \Illuminate\Http\Response
     */
    public function update_month(Request $request)
    {
        $projectKpi = ProjectKpi::findOrFail($request->id);

        // Loop ทุกๆ Results Object
        foreach($request->results as $count => $req){

            // Loop ทุกๆ Result ของ SO KPI ตัวนี้
            foreach ($projectKpi->results as $index => $result) {

                // Loop ทุกๆ ResultMonth (ผลลัพธ์รายเดือน)
                foreach($result->result_months as $count => $month){

                    // ถ้า เดือน และ ปี ตรงตามที่ input เข้ามา
                    if( $month->month_no == $req['month_no'] && 
                        $month->year_no == $req['year_no']){

                        // หา ResultMonth ตาม id...
                        $mo = ResultMonth::findOrFail($month->id);
                        // ...แล้ว update ค่า
                        $mo->value_forecast = $req['value_forecast'];
                        $mo->value_actual = $req['value_actual'];
                        $mo->save();
                    }
                }
                // ยิง event เพื่อคำนวณผล % กับ total ใหม่
                // event(new ResultWeightUpdated($result)); 
            }
        }
        $projectKpi->save();

        return response()->json(['status' => 200]);
    }
}
