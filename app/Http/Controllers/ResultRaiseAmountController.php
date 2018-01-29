<?php

namespace App\Http\Controllers;

use App\AppraisalItem;
use App\AppraisalStructure;
use App\CDS;
use App\KPICDSMapping;

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

class ResultRaiseAmountController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	
	
	public function appraisal_year()
	{
		$items = DB::select("
			select current_appraisal_year 
			from system_config	
		");
		return response()->json($items);
	}
	
	public function salary_period()
	{
		/*
		$items = DB::select("
			select DISTINCT salary_period_desc as param_salary_period_desc
			from appraisal_period
			where appraisal_year = (select current_appraisal_year from system_config)
			and is_raise = 1
			order by salary_period_desc		
		");

		*/
		$items = DB::select("
			select period_id, salary_period_desc 
			from appraisal_period 
			where is_raise = 1 
			and appraisal_frequency_id <> 1 
			order by period_id	
		");
		
		return response()->json($items);
		
	}

	public function result_raise_amount(Request $request)
	{
		$items = DB::select("
			call emp_result_raise_amount(?,?,?)
		", array($request->param_appraisal_year,$request->param_salary_period_desc,$request->param_salary_period_id));
		//return response()->json($items);
		return response()->json(['status' => 200]);
		
	}

	
	

	
   
}
?>