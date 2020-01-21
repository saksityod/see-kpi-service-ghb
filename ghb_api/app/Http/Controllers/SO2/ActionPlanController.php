<?php

namespace App\Http\Controllers\SO2;

use App\ActionPlan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ActionPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
     * @param  \App\ActionPlan  $actionPlan
     * @return \Illuminate\Http\Response
     */
    public function show(ActionPlan $actionPlan)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\ActionPlan  $actionPlan
     * @return \Illuminate\Http\Response
     */
    public function destroy(ActionPlan $actionPlan)
    {
        //
    }

    /**
     * Get all Tasks under this ActionPlan 
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function tasks(Request $request)
    {
        $ap = ActionPlan::with(
            'tasks'
        )->find($request->id);      

        return response()->json($ap);
    }

    /**
     * Get all Tasks under this ActionPlan 
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function subtasks(Request $request)
    {
        $ap = ActionPlan::with(
            'tasks',
            'tasks.subtasks'
        )->find($request->id);      

        return response()->json($ap);
    }

    /**
     * Update Tasks under this  ActionPlan 
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update_tasks(Request $request)
    {
        // $ap = ActionPlan::with(
        //     'tasks',
        //     'tasks.subtasks'
        // )->find($request->id);      

        return response()->json($ap);
    }
}
