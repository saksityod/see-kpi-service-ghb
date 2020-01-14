<?php

namespace App\Http\Controllers;
use App\Model\SOItem;
use App\AppraisalPeriod;
use App\AppraisalFrequency;
use App\Model\StrategicObjective;
use App\Model\SOResult;

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

class SOAssignmentController extends Controller
{

	public function __construct()
	{
	   $this->middleware('jwt.auth');
	}
	
	public function dropdownYearAndDesc(Request $request){
		$year = !empty($request->year)?(int)$request->year:'%';

		$DB = AppraisalPeriod::select('period_id','appraisal_year','appraisal_period_desc')
								->orderBy('appraisal_year','desc')
								->orderBy('appraisal_period_desc')
								->get();
		
		$groupYear = collect($DB)->pluck('appraisal_year')->unique()->values()->all();

		if(empty($request->year)){
			$appraisal_period_desc = collect($DB);
		}else{
		$appraisal_period_desc = collect($DB)->where('appraisal_year',$year);
		}

		$data_appraisal_period_desc = array();
		foreach($appraisal_period_desc as $item){
			array_push($data_appraisal_period_desc,$item);
		}

		$data_year = array();
		foreach($groupYear as $item){
			array_push($data_year,array("year"=>$item));
		}

		$data = array(
			"data_appraisal_period_desc" => $data_appraisal_period_desc,
			"data_year" => $data_year
		);

		return response()->json(['status' => 200, 'data' => $data]); 

	}
    
    public function dropdownStrategicObjective(Request $request){
		$DB = StrategicObjective::select('so_id','so_name')
									->where('is_active',1)
									->get();
		
		return response()->json(['status' => 200, 'data' => $DB]); 
	}

	public function dropDownFrequency(Request $request){
		$DB = AppraisalFrequency::select('frequency_id','frequency_name','frequency_month_value')
									->get();

		return response()->json(['status' => 200, 'data' => $DB]); 
	}

	public function show(Request $request){
		$status = $request->status;
		$All_DB = DB::table('so_item')->select('so_item_id','so_item.so_id','so_item.item_id','strategic_objective.so_name','appraisal_item.item_name')
					->join('strategic_objective','strategic_objective.so_id','=','so_item.so_id')
					->join('appraisal_item','appraisal_item.item_id','=','so_item.item_id')
					->where('so_item.is_active',1)
					->where('strategic_objective.is_active',1)
					->where('appraisal_item.is_active',1)
					->get();

		$groupDataAssign = [];
		$groupDataUnassign = [];
		if($status == '1'){
			//All
			$Unassign_DB = DB::table('so_item')->select('so_item_id','so_item.so_id','so_item.item_id','strategic_objective.so_name','appraisal_item.item_name')
					->join('strategic_objective','strategic_objective.so_id','=','so_item.so_id')
					->join('appraisal_item','appraisal_item.item_id','=','so_item.item_id')
					->where('so_item.is_active',1)
					->where('strategic_objective.is_active',1)
					->where('appraisal_item.is_active',1)
					->get();
					
			$Assign_DB = DB::table('so_item')->select('so_item_id','so_item.so_id','so_item.item_id','strategic_objective.so_name','appraisal_item.item_name')
											->join('strategic_objective','strategic_objective.so_id','=','so_item.so_id')
											->join('appraisal_item','appraisal_item.item_id','=','so_item.item_id')
											->where('so_item.is_active',1)
											->where('strategic_objective.is_active',1)
											->where('appraisal_item.is_active',1)
											->get();


			$groupDataAssign = $Assign_DB;
			$groupDataUnassign = $Unassign_DB;

		}else if($status == '2'){
			//Assign
			$Assign_DB = DB::table('so_item')->select('so_item_id','so_item.so_id','so_item.item_id','strategic_objective.so_name','appraisal_item.item_name')
											->join('strategic_objective','strategic_objective.so_id','=','so_item.so_id')
											->join('appraisal_item','appraisal_item.item_id','=','so_item.item_id')
											->where('so_item.is_active',1)
											->where('strategic_objective.is_active',1)
											->where('appraisal_item.is_active',1)
											->get();
								
			$groupDataAssign = $Assign_DB;

		}else if($status == '3'){
			//UnAssign
			$Unassign_DB = DB::table('so_item')->select('so_item_id','so_item.so_id','so_item.item_id','strategic_objective.so_name','appraisal_item.item_name')
					->join('strategic_objective','strategic_objective.so_id','=','so_item.so_id')
					->join('appraisal_item','appraisal_item.item_id','=','so_item.item_id')
					->where('so_item.is_active',1)
					->where('strategic_objective.is_active',1)
					->where('appraisal_item.is_active',1)
					->get();

			$groupDataUnassign = $Unassign_DB;
		}

		// Get the current page from the url if it's not set default to 1
		empty($request->page) ? $page = 1 : $page = $request->page;
			
		// Number of items per page
		empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;
		
		$offSet = ($page * $perPage) - $perPage; // Start displaying items from this number

		// Get only the items you need using array_slice (only get 10 items since that's what you need)
		$itemsForCurrentPage = array_slice($All_DB, $offSet, $perPage, false);
		
		// Return the paginator with only 10 items but with the count of all items and set the it on the correct page
		$result = new LengthAwarePaginator($itemsForCurrentPage, count($All_DB), $perPage, $page);	

		$resultT = $result->toArray();

		//Group result
		if($status == '1'){
			//All
			$resultT['assign']=$Assign_DB;
			$resultT['unassign']=$Unassign_DB;
		}else if($status == '2'){
			//Assign
			$resultT['assign']=$Assign_DB;
		}else if($status == '3'){
			//UnAssign
			$resultT['unassign']=$Unassign_DB;
		}
		return response()->json($resultT);	
	}

	public function store(Request $request){

	}

	

}
