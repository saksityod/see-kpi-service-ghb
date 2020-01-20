<?php

namespace App\Http\Controllers\SO2;

use App\So;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // $sets = Set::all();
        $sos = So::paginate(10);
        
        // for ver 5.1
        return response()->json($sos);
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
     * @param  \App\So  $so
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $so = So::find($id);
        // $so->only(['id','name','abbr','color_code']);

        return response()->json($so);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\So  $so
     * @return \Illuminate\Http\Response
     */
    public function destroy(So $so)
    {
        //
    }
	
	/**
     * Get only UNASSIGNED SO
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function unassign(Request $request)
    {
        $results = collect();

        // Eager Loading + เลือกมาแค่ 2 column
        $sos = So::with('so_kpis')->get(['id','name']);

        // เอาเฉพาะ SO ที่ "ไม่มี" SO-KPI ใส่ $result ไว้
        foreach ($sos as $index => $so) {
            if($so->so_kpis->isEmpty()){
                $results->push(collect($so)->only(['id', 'name']));
                // $results->push($so);
            }
        }
        return response()->json($results->paginate(10));
    }

    /**
     * Get already ASSIGNED SO
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function assigned(Request $request)
    {
        $results = collect();

        // Eager Loading + เลือกมาแค่ 2 column
        $sos = So::with('so_kpis')->get(['id','name']);

        // นับเฉพาะ SO ที่มี SO-KPI อยู่แล้ว ใส่ $result
        foreach ($sos as $index => $so) {
            if(!$so->so_kpis->isEmpty()){
                $results->push(collect($so)->only(['id', 'name']));
                // $results->push($so);
            }
        }
        return response()->json($results->paginate(10));
    }

    /**
     * ดึง KPI ทั้งหมดของ SO ตัวนี้
     * 
     * สำหรับหน้า 'SO Assignment/Assign'
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function kpis(Request $request)
    {
        /* 
         *  ดึงออกมาจาก
         *  ตั้งแต่ So --> So_KPI
         */
        $so = So::with(
                    'so_kpis'
                    // ,'so_kpis.results'
                    // ,'so_kpis.results.result_months'
                )->find($request->id);
        
        // รอเสริมสวย ตาม spec ของ Front-End

        return response()->json($so);
    }

    /**
     * ดึง KPIs และ weights (ในตาราง results) ทั้งหมดของ SO ตัวนี้
     * 
     * สำหรับหน้า 'SO Assignment/Assign'
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function weights(Request $request)
    {
        /* ดึงออกมาตั้งแต่
         *    So --> So_KPI --> Result
         */
        $so = So::with(
                    'so_kpis'
                    ,'so_kpis.results'
                    // ,'so_kpis.results.result_months'
                )->find($request->id);

        // รอเสริมสวย ตาม spec ของ Front-End

        return response()->json($so);
    }

    /**
     * ดึง KPIs และ Results ลงถึงระดับ แต่ละเดือน ของ SO ตัวนี้
     * 
     * สำหรับหน้า 'SO Assignment/Assign'
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function months(Request $request)
    {
        /* 
         *  ดึงออกมาจาก
         *  ตั้งแต่ So --> So_KPI --> Results --> ResultMonth
         */
        $so = So::with(
                    'so_kpis'
                    ,'so_kpis.results'
                    ,'so_kpis.results.result_months'
                )->find($request->id);
        
        // รอเสริมสวย ตาม spec ของ Front-End

        return response()->json($so);
    }

    /**
     * เอาตัวเลข Grand Total สำหรับ SO ตัวนี้
     * 
     * สำหรับหน้า 'SO Assignment/Edit'
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function total(Request $request)
    {
        /* ดึงออกมาจาก
         *    So --> ResultTotal
         */
        $so = So::with(
                    'result_totals'
                    // ,'result_totals.result_total'
                    // ,'result_totals.results.result_months'
                )->find($request->id);

        $sum = 0.0;
        foreach($so->result_totals as $index => $total){
            $sum += $total->result_score;
        }        

        // ถ้าจะเอาแค่ตัวเลข (ไม่ต้องไป loop บน FrontEnd)
        // return response()->json($sum);

        return response()->json($so);
    }

    /**
     * Filter ผลลัพท์จากปุ่ม Search
     * 
     * สำหรับหน้า 'SO Assignment/Insert'
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        if(is_null($request->year)){

        }

        return response()->json($so);
    }
}
