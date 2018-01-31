<?php

namespace App\Http\Controllers;

use App\AppraisalGrade;
use App\SystemConfiguration;

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

class AppraisalGradeController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
    public function al_list()
    {
		$items = DB::select("
			select level_id, appraisal_level_name
			from appraisal_level
			where is_active = 1
			order by appraisal_level_name
		");
		return response()->json($items);
    }
	
	public function index(Request $request)
	{		
	
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		
	
		$qinput = array();
		
		if ($config->raise_type == 1) {
			$query = "
				select a.grade_id, a.appraisal_level_id, b.appraisal_level_name, a.grade, a.begin_score, a.end_score, a.salary_raise_amount, a.is_active
				from appraisal_grade a
				left outer join appraisal_level b
				on a.appraisal_level_id = b.level_id
			";
		} else {
			$query = "
				select a.grade_id, a.appraisal_level_id, b.appraisal_level_name, a.grade, a.begin_score, a.end_score, a.salary_raise_percent salary_raise_amount, a.is_active
				from appraisal_grade a
				left outer join appraisal_level b
				on a.appraisal_level_id = b.level_id
			";		
		}
				
		empty($request->appraisal_level_id) ?: ($query .= " where a.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
		
		$qfooter = " Order by b.appraisal_level_name asc, a.grade asc ";
		
		$items = DB::select($query . $qfooter, $qinput);
		
		
		// Get the current page from the url if it's not set default to 1
		empty($request->page) ? $page = 1 : $page = $request->page;
		
		// Number of items per page
		empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;
		
		$offSet = ($page * $perPage) - $perPage; // Start displaying items from this number

		// Get only the items you need using array_slice (only get 10 items since that's what you need)
		$itemsForCurrentPage = array_slice($items, $offSet, $perPage, false);
		
		// Return the paginator with only 10 items but with the count of all items and set the it on the correct page
		$result = new LengthAwarePaginator($itemsForCurrentPage, count($items), $perPage, $page);			


		return response()->json($result);
	}
	
	public function store(Request $request)
	{
		$errors = array();

		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		

		
		$validator = Validator::make($request->all(), [	
			'appraisal_level_id' => 'required|integer',
			'grade' => 'required|max:10|unique:appraisal_grade,grade,null,appraisal_level_id,appraisal_level_id,' . $request->appraisal_level_id,
			'begin_score' => 'required|numeric',
			'end_score' => 'required|numeric',
			'salary_raise_amount' => 'required|numeric',
			'is_active' => 'required|boolean',
		]);
		
		$range_check = DB::select("
			select grade, begin_score, end_score
			from appraisal_grade
			where appraisal_level_id = ?
			and (? between begin_score and end_score 
			or ? between end_score and begin_score
			or ? between begin_score and end_score
			or ? between end_score and begin_score)		
		", array($request->appraisal_level_id, $request->begin_score, $request->begin_score, $request->end_score, $request->end_score));
		
		if ($validator->fails()) {
			$errors = $validator->errors()->toArray();
			if (!empty($range_check)) {
				$errors['overlap'] = "The begin score and end score is overlapped with another grade.";//$range_check;
			}
			return response()->json(['status' => 400, 'data' => $errors]);
		} else {
			if (!empty($range_check)) {
				$errors['overlap'] = "The begin score and end score is overlapped with another grade.";//$range_check;
				return response()->json(['status' => 400, 'data' => $errors]);
			}		
			$item = new AppraisalGrade;
			$item->fill($request->except('salary_raise_amount'));
			
			if ($config->raise_type == 1) {
				$item->salary_raise_amount = $request->salary_raise_amount;
			} else {
				$item->salary_raise_percent = $request->salary_raise_amount;			
			}
			
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($grade_id)
	{
		try {
			$item = AppraisalGrade::findOrFail($grade_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Grade not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $grade_id)
	{
		try {
			$item = AppraisalGrade::findOrFail($grade_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Grade not found.']);
		}
		
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		
		
		$errors = array();
		$validator = Validator::make($request->all(), [	
			'appraisal_level_id' => 'required|integer',	
			'grade' => 'required|max:10|unique:appraisal_grade,grade,' . $grade_id . ',grade_id,appraisal_level_id,' . $request->appraisal_level_id,
			'begin_score' => 'required|numeric',
			'end_score' => 'required|numeric',
			'salary_raise_amount' => 'required|numeric',
			'is_active' => 'required|boolean',
		]);
		
		$range_check = DB::select("
			select grade, begin_score, end_score
			from appraisal_grade
			where appraisal_level_id = ?
			and (? between begin_score and end_score 
			or ? between end_score and begin_score
			or ? between begin_score and end_score
			or ? between end_score and begin_score)		
			and grade <> ?
		", array($request->appraisal_level_id, $request->begin_score, $request->begin_score, $request->end_score, $request->end_score, $item->grade));
		
		if ($validator->fails()) {
			$errors = $validator->errors()->toArray();
			if (!empty($range_check)) {
				$errors['overlap'] = "The begin score and end score is overlapped with another grade.";//$range_check;
			}
			return response()->json(['status' => 400, 'data' => $errors]);
		} else {
			if (!empty($range_check)) {
				$errors['overlap'] = "The begin score and end score is overlapped with another grade.";//$range_check;
				return response()->json(['status' => 400, 'data' => $errors]);
			}		
			$item->fill($request->except('salary_raise_amount'));
			if ($config->raise_type == 1) {
				$item->salary_raise_amount = $request->salary_raise_amount;
			} else {
				$item->salary_raise_percent = $request->salary_raise_amount;			
			}			
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);
				
	}
	
	public function destroy($grade_id)
	{
		try {
			$item = AppraisalGrade::findOrFail($grade_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Grade not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Appraisal Grade is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
