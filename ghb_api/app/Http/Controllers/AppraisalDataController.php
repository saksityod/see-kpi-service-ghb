<?php

namespace App\Http\Controllers;

use App\CDS;
use App\AppraisalItemResult;

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

class AppraisalDataController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function structure_list()
	{
		$items = DB::select("
			Select s.structure_id, s.structure_name
			From appraisal_structure s, form_type t
			Where s.form_id = t.form_id
			And t.form_name = 'Deduct Score'
			And s.is_active = 1 order by structure_id
		");
		return response()->json($items);
	}
	
	public function period_list()
	{
		$items = DB::select("
			select period_id, appraisal_period_desc
			From appraisal_period
			Where appraisal_year = (select current_appraisal_year from system_config where config_id = 1)
			order by period_id
		");
		return response()->json($items);
	}	
	
    public function al_list()
    {
		$items = DB::select("
			select level_id, appraisal_level_name
			from appraisal_level
			where is_active = 1
			order by level_id
		");
		return response()->json($items);
    }
	
	public function appraisal_type_list()
	{
		$items = DB::select("
			select *
			from appraisal_type		
			order by appraisal_type_id
		");
		return response()->json($items);
	}
		
	public function auto_appraisal_item(Request $request)
	{
		$qinput = array();
		$query = "
			Select distinct a.item_id, a.item_name
			From appraisal_item a left outer join appraisal_item_level b
			on a.item_id = b.item_id
			Where item_name like ?
		";
		
		$qfooter = " Order by item_name limit 10 ";
		$qinput[] = '%'.$request->item_name.'%';
		empty($request->structure_id) ?: ($query .= " and structure_id = ? " AND $qinput[] = $request->structure_id);
		empty($request->level_id) ?: ($query .= " and b.level_id = ? " AND $qinput[] = $request->level_id);
		
		$items = DB::select($query.$qfooter,$qinput);
		return response()->json($items);		
	}
	
	public function auto_emp_name(Request $request)
	{
		$items = DB::select("
			Select distinct e.emp_id, e.emp_name
			From employee e
			where e.emp_name like ? and e.is_active = 1
			Order by e.emp_name	limit 10
		", array('%'.$request->emp_name.'%'));
		return response()->json($items);
	}
	
	public function import(Request $request)
	{
		$errors = array();
		foreach ($request->file() as $f) {
			$items = Excel::load($f, function($reader){})->get();			
			foreach ($items as $i) {
							
				$validator = Validator::make($i->toArray(), [
					'emp_result_id' => 'required|integer',
					'employee_id' => 'required|integer',
					'period_id' => 'required|integer',
					'item_id' => 'required|integer',
					'data_value' => 'required|numeric|digits_between:1,15',
				]);

				if ($validator->fails()) {
					$errors[] = ['employee_code' => $i->employee_code, 'errors' => $validator->errors()];
				} else {
					try {
						AppraisalItemResult::where("emp_result_id",$i->emp_result_id)->where("emp_id",$i->employee_id)->where("period_id",$i->period_id)->where('item_id',$i->item_id)->update(['actual_value' => $i->data_value, 'updated_by' => Auth::id()]);
						$items = DB::select("
							select a.item_result_id, ifnull(a.max_value,0) max_value, a.actual_value, ifnull(a.deduct_score_unit,0) deduct_score_unit
							from appraisal_item_result a
							left outer join emp_result b
							on a.emp_result_id = b.emp_result_id
							left outer join appraisal_item c
							on a.item_id = c.item_id
							left outer join appraisal_structure d
							on c.structure_id = d.structure_id
							where d.form_id = 3
							and a.period_id = ?
							and a.emp_id = ?
							and a.item_id = ?
						", array($i->period_id, $i->employee_id, $i->item_id));
						
						foreach ($items as $ai) {
							$uitem = AppraisalItemResult::find($ai->item_result_id);
							if (($ai->max_value - $ai->actual_value) > 0) {
								$uitem->over_value = 0;
								$uitem->weigh_score = 0;
							} else {
								$uitem->over_value = $ai->max_value - $ai->actual_value;
								$uitem->weigh_score = ($ai->max_value - $ai->actual_value) * $ai->deduct_score_unit;
							}
							$uitem->updated_by = Auth::id();
							$uitem->save();
						}						
					} catch (Exception $e) {
						$errors[] = ['employee_code' => $i->employee_code, 'errors' => substr($e,0,254)];
					}

				}					
			}
		}
		return response()->json(['status' => 200, 'errors' => $errors]);
	}		
	
	public function export_bk(Request $request)
	{
	
		$qinput = array();
		$query = "
			select p.appraisal_period_desc, p.period_id, s.structure_name, s.structure_id, i.item_id, i.item_name, e.emp_id, e.emp_code, e.emp_name, r.actual_value, er.emp_result_id
			from appraisal_item_result r, employee e, appraisal_period p, appraisal_item i, appraisal_structure s, form_type f, emp_result er
			where r.emp_id = e.emp_id 
			and r.period_id = p.period_id
			and r.item_id = i.item_id
			and i.structure_id = s.structure_id
			and r.emp_result_id = er.emp_result_id
			and s.form_id = f.form_id
			and f.form_name = 'Deduct Score'			
		";
			
		empty($request->structure_id) ?: ($query .= " AND i.structure_id = ? " AND $qinput[] = $request->structure_id);
		empty($request->level_id) ?: ($query .= " And exists (select 1 from appraisal_item_level lv where i.item_id = lv.item_id and lv.level_id = ?) " AND $qinput[] = $request->level_id);
		empty($request->item_id) ?: ($query .= " And r.item_id = ? " AND $qinput[] = $request->item_id);
		empty($request->period_id) ?: ($query .= " And r.period_id = ? " AND $qinput[] = $request->period_id);
		empty($request->emp_id) ?: ($query .= " And r.emp_id = ? " AND $qinput[] = $request->emp_id);
		
		$qfooter = " Order by r.period_id, s.structure_name, i.item_name, e.emp_code ";
		
		$items = DB::select($query . $qfooter, $qinput);

		// echo $query . $qfooter;
		// echo "<br>";
		// print_r($qinput);

		
		$filename = "Appraisal_Data";  //. date('dm') .  substr(date('Y') + 543,2,2);
		$x = Excel::create($filename, function($excel) use($items, $filename) {
			$excel->sheet($filename, function($sheet) use($items) {
				
				$sheet->appendRow(array('Emp Result ID', 'Employee ID', 'Structure ID', 'Structure Name', 'Period ID', 'Period Name', 'Item ID', 'Item Name', 'Data Value'));
		
				foreach ($items as $i) {
					$sheet->appendRow(array(
						$i->emp_result_id,
						$i->emp_id, 
						$i->structure_id, 
						$i->structure_name, 
						$i->period_id, 
						$i->appraisal_period_desc,
						$i->item_id,
						$i->item_name,
						$i->actual_value
						));
				}
			});

		})->export('xls');	
					
	}	

	public function export(Request $request)
	{
	
		$qinput = array();
		$query = "
			select p.appraisal_period_desc, p.period_id, s.structure_name, s.structure_id, i.item_id, i.item_name, e.emp_id, e.emp_code, e.emp_name, r.actual_value, er.emp_result_id
			from appraisal_item_result r, employee e, appraisal_period p, appraisal_item i, appraisal_structure s, form_type f, emp_result er
			where r.emp_id = e.emp_id 
			and r.period_id = p.period_id
			and r.item_id = i.item_id
			and i.structure_id = s.structure_id
			and r.emp_result_id = er.emp_result_id
			and s.form_id = f.form_id
			and f.form_name = 'Deduct Score'			
		";
			
		empty($request->structure_id) ?: ($query .= " AND i.structure_id = ? " AND $qinput[] = $request->structure_id);
		empty($request->level_id) ?: ($query .= " And r.level_id = ? " AND $qinput[] = $request->level_id);
		empty($request->item_id) ?: ($query .= " And r.item_id = ? " AND $qinput[] = $request->item_id);
		empty($request->period_id) ?: ($query .= " And r.period_id = ? " AND $qinput[] = $request->period_id);
		empty($request->emp_id) ?: ($query .= " And r.emp_id = ? " AND $qinput[] = $request->emp_id);
		
		$qfooter = " Order by r.period_id, s.structure_name, i.item_name, e.emp_code ";
		
		$items = DB::select($query . $qfooter, $qinput);

		// echo $query . $qfooter;
		// echo "<br>";
		// print_r($qinput);

		
		$filename = "Appraisal_Data";  //. date('dm') .  substr(date('Y') + 543,2,2);
		$x = Excel::create($filename, function($excel) use($items, $filename) {
			$excel->sheet($filename, function($sheet) use($items) {
				
				$sheet->appendRow(array('Emp Result ID', 'Employee ID', 'Structure ID', 'Structure Name', 'Period ID', 'Period Name', 'Item ID', 'Item Name', 'Data Value'));
		
				foreach ($items as $i) {
					$sheet->appendRow(array(
						$i->emp_result_id,
						$i->emp_id, 
						$i->structure_id, 
						$i->structure_name, 
						$i->period_id, 
						$i->appraisal_period_desc,
						$i->item_id,
						$i->item_name,
						$i->actual_value
						));
				}
			});

		})->export('xls');	
					
	}	

	
	public function index(Request $request)
	{

		$qinput = array();
		$query = "
			select p.appraisal_period_desc, s.structure_name, i.item_name, e.emp_code, e.emp_name, r.actual_value, er.emp_result_id
			from appraisal_item_result r, employee e, appraisal_period p, appraisal_item i, appraisal_structure s, form_type f, emp_result er
			where r.emp_id = e.emp_id 
			and r.period_id = p.period_id
			and r.item_id = i.item_id
			and i.structure_id = s.structure_id
			and r.emp_result_id = er.emp_result_id
			and s.form_id = f.form_id			
			and f.form_name = 'Deduct Score'
		"; 
			
		empty($request->structure_id) ?: ($query .= " AND i.structure_id = ? " AND $qinput[] = $request->structure_id);
		empty($request->level_id) ?: ($query .= " And r.level_id = ? " AND $qinput[] = $request->level_id);
		empty($request->item_id) ?: ($query .= " And r.item_id = ? " AND $qinput[] = $request->item_id);
		empty($request->period_id) ?: ($query .= " And r.period_id = ? " AND $qinput[] = $request->period_id);
		empty($request->emp_id) ?: ($query .= " And r.emp_id = ? " AND $qinput[] = $request->emp_id);
		
		$qfooter = " Order by r.period_id, s.structure_name, i.item_name, e.emp_code ";
		
		// echo $query . $qfooter;
		// echo "<br>";
		// print_r($qinput);

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
	
	public function calculate_weight(Request $request)
	{
		$items = DB::select("
			select a.appraisal_item_result_id, ifnull(a.max_value,0) max_value, a.actual_value, ifnull(a.deduct_score_unit,0) deduct_score_unit
			from appraisal_item_result a
			left outer join emp_result b
			on a.emp_result_id = b.emp_result_id
			left outer join appraisal_item c
			on a.item_id = c.item_id
			left outer join appraisal_structure d
			on c.structure_id = d.structure_id
			where d.form_id = 3
			and a.period_id = ?
			and a.emp_code = ?
			and a.item_id = ?
		", array($request->period_id, $request->emp_code, $request->item_id));
		
		foreach ($items as $i) {
			$uitem = AppraisalItemResult::find($i->appraisal_item_result_id);
			if (($i->max_value - $i->actual_value) > 0) {
				$uitem->over_value = 0;
				$uitem->weigh_score = 0;
			} else {
				$uitem->over_value = $i->max_value - $i->actual_value;
				$uitem->weigh_score = ($i->max_value - $i->actual_value) * $i->deduct_score_unit;
			}
			$uitem->updated_by = Auth::id();
			$uitem->save();
		}
		
		return response()->json(['status' => 200]);
	
	}	
	
}
