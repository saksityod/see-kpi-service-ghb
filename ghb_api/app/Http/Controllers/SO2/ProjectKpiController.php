<?php

namespace App\Http\Controllers\SO2;

use App\ProjectKpi;
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
        // $sets = Set::all();
        $project_kpis = ProjectKpi::paginate(10);
        
        // for ver 5.1
        return response()->json($project_kpis);
        // return ProjectKpiResource::collection($project_kpis);
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
    public function show($id)
    {
        $project_kpi = ProjectKpi::findOrFail($id);
        ProjectKpiResource::withoutWrapping();

        // for ver 5.1
        return response()->json($project_kpi);
        // return new ProjectKpiResource($project_kpi);
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
}
