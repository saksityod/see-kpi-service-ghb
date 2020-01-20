<?php

namespace App\Http\Controllers\SO2;

use App\SoKpi;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
// use App\Http\Resources\SoKpiResource;

class SoKpiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // $sets = Set::all();
        $sokpis = SoKpi::paginate(10);
        
        // for ver 5.1
        return response()->json($sokpis);
        // return SoKpiResource::collection($sokpis);
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
     * @param  \App\SoKpi  $soKpi
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $soKpi = SoKpi::findOrFail($id);
        // SoKpiResource::withoutWrapping();

        // for ver 5.1
        return response()->json($soKpi);
        // return new SoKpiResource($soKpi);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\SoKpi  $soKpi
     * @return \Illuminate\Http\Response
     */
    public function destroy(SoKpi $soKpi)
    {
        //
    }
}
