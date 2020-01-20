<?php

namespace App\Http\Controllers\SO2;

use DB;
use App\Result;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
// use App\Http\Resources\ResultResource;

class ResultController extends Controller
{
    private $result;

    /**
     * ResultController constructor.
     *
     */
    // public function __construct(Result $result)
    // {
    //     $this->result = $result;
    // }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $results = Result::paginate(10);

        // return ResultResource::collection($results);
        return response()->json($results);
    }

    public function display(Request $request)
    {
        $results = Result::get();

        // เช็คประเภท {type} จากตัวแปรที่ส่งมาใน endpoint
        if($request->type == 'so'){
            $class = 'App\SoKpi';
        }else{
            $class = 'App\ProjectKpi';
        }
        // แล้วเลือกแสดงเพาะ type นั้นๆ
        $results = $results->where('mappable_type', $class);
        // $results = $results->only(['id','description']);

        // return response()->json($results);
        return response()->json($results->paginate(10));
    }
	
	/**
     * Get types เพื่อเอาใส่ dropdown (ตอนนี้มีแค่ So-KPI หรือ Project-KPI) 
     *
     * @return \Illuminate\Http\Response
     */
    public function get_types()
    {
        // นับจำนวน column 'mappable_type' ว่ามีกี่ type, และจำนวนเท่าไหร่บ้าง
        $results = DB::table('results')
                        ->select('mappable_type', DB::raw('count(*) as total'))
                        ->groupBy('mappable_type')
                        ->get();

        // เปลี่ยนชื่อของ type ให้อ่านง่ายๆ + เพื่อ security
        foreach ($results as $index => $result) {
            if( $result->mappable_type == 'App\SoKpi'){
                $result->mappable_type = 'so_kpi';
            }else{
                // ณ ตอนนี้ คือเหลือแค่ type 'App\ProjectKpi' เท่านั้น
                $result->mappable_type = 'project_kpi';
                // ถ้ามี type เพิ่มก็ใส่ if else case เพิ่มเอานะครัช
            }
        }
        return response()->json($results);
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
     * @param  \App\Result  $result
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $result = Result::findOrFail($id);
        ResultResource::withoutWrapping();

        // for ver 5.1
        return response()->json($result);
        // return new ResultResource($result);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Result  $result
     * @return \Illuminate\Http\Response
     */
    public function destroy(Result $result)
    {
        // Get Single
        $result = Result::findOrFail($id);
        // Delete entries from the pivot tables
        $result->result_months()->detach();
        $result->result_totals()->detach();
        // Not sure if this is needed ?
        // $result->mappable()->detach();   

        if($result->delete())
        {
            // return new SetResource($set);
            return response()->json(['status' => 200]);
        }
    }

    // public function update_tags(Request $request)
    // {
    //     $set = Set::findOrFail($request->id);
    //     $set->tags()->sync($request->input('taglist'));
    //     $set->save();

    //     return new SetTreeResource($set);
    // }

}
