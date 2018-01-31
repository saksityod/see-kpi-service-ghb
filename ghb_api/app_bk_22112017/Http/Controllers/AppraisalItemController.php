<?php

namespace App\Http\Controllers;

use App\AppraisalItem;
use App\AppraisalStructure;
use App\CDS;
use App\KPICDSMapping;
use App\ItemOrg;
use App\ItemPosition;
use App\ItemLevel;

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

class AppraisalItemController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function remind_list()
	{
		$items = DB::select("
			select remind_condition_id, remind_condition_name
			from remind_condition
			order by remind_condition_id asc
		");
		
		return response()->json($items);
	}
	
	public function value_type_list()
	{
		$items = DB::select("
			select value_type_id, value_type_name
			from value_type
			order by value_type_id asc
		");
		
		return response()->json($items);	
	}
	
	public function index(Request $request)
	{	
		$qinput = array();
		$query = "
			select s.seq_no, s.structure_name, s.structure_id, i.item_id, i.item_name, ifnull(i.kpi_id,'') kpi_id,
			p.perspective_name, u.uom_name, i.max_value, i.unit_deduct_score, i.is_active, f.form_name, f.app_url, f.form_id
			from appraisal_item i
			left outer join appraisal_structure s
			on i.structure_id = s.structure_id 
			left outer join perspective p
			on i.perspective_id = p.perspective_id
			left outer join uom u
			on i.uom_id = u.uom_id
			left outer join form_type f
			on s.form_id = f.form_id	
			where 1=1
		";
		
		empty($request->level_id) ?: ($query .= " and exists ( select 1 from appraisal_item_level lv left outer join appraisal_level al on lv.level_id = al.level_id where lv.item_id = i.item_id and al.is_hr = 0 and lv.level_id = ? ) " AND $qinput[] = $request->level_id);
		empty($request->structure_id) ?: ($query .= " And i.structure_id = ? " AND $qinput[] = $request->structure_id);
		empty($request->kpi_type_id) ?: ($query .= " And i.kpi_type_id = ? " AND $qinput[] = $request->kpi_type_id);
		if ($request->structure_id == 1) {
			empty($request->perspective_id) ?: ($query .= " And i.perspective_id = ? " AND $qinput[] = $request->perspective_id);
		} else {
		}
		empty($request->item_id) ?: ($query .= " And i.item_id = ? " AND $qinput[] = $request->item_id);
		empty($request->org_id) ?: ($query .= " and exists ( select 1 from appraisal_item_org lv where lv.item_id = i.item_id and lv.org_id = ? ) " AND $qinput[] = $request->org_id);		
		
		$qfooter = " Order by isnull(i.kpi_id), i.kpi_id asc, i.item_id asc";
		
		$items = DB::select($query . $qfooter, $qinput);
		
		$itemsForCurrentPage = $items;
		
		// // Get the current page from the url if it's not set default to 1
		// empty($request->page) ? $page = 1 : $page = $request->page;
		
		// // Number of items per page
		// empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;
		
		// $offSet = ($page * $perPage) - $perPage; // Start displaying items from this number

		// // Get only the items you need using array_slice (only get 10 items since that's what you need)
		// $itemsForCurrentPage = array_slice($items, $offSet, $perPage, false);
		
		// // Return the paginator with only 10 items but with the count of all items and set the it on the correct page
		// $result = new LengthAwarePaginator($itemsForCurrentPage, count($items), $perPage, $page);			
		
		$structure_template = DB::select("
			select a.structure_id, a.structure_name, b.*
			from appraisal_structure a
			left outer join form_type b
			on a.form_id = b.form_id		
			where is_active = 1
		");
		
		$groups = array();
		
		foreach ($structure_template as $s) {
			$key = $s->structure_name;
			if (!isset($groups[$key])) {
				if ($s->form_name == 'Quantity') {
					$columns = [
						[
							'column_display' => 'KPI ID',
							'column_name' => 'kpi_id',
							'data_type' => 'text',
						],							
						[
							'column_display' => 'KPI Name',
							'column_name' => 'item_name',
							'data_type' => 'text',
						],			
						[
							'column_display' => 'Perspective',
							'column_name' => 'perspective_name',
							'data_type' => 'text',
						],						
						[
							'column_display' => 'UOM',
							'column_name' => 'uom_name',
							'data_type' => 'text',
						],					
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],						
					];
				} elseif ($s->form_name == 'Quality') {
					$columns = [
						[
							'column_display' => 'KPI Name',
							'column_name' => 'item_name',
							'data_type' => 'text',
						],		
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],									
					];
				} elseif ($s->form_name == 'Deduct Score') {
					$columns = [
						[
							'column_display' => 'KPI Name',
							'column_name' => 'item_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Max Value',
							'column_name' => 'max_value',
							'data_type' => 'number',
						],						
						[
							'column_display' => 'Deduct Score/Unit',
							'column_name' => 'unit_deduct_score',
							'data_type' => 'number',
						],									
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],									
					];
				}
				$groups[$key] = array(
					'items' => array(),
					'count' => 0,
					'columns' => $columns,
					'structure_id' => $s->structure_id,
					'form_id' => $s->form_id,
					'form_url' => $s->app_url
				);
			}
		}		
		
		foreach ($itemsForCurrentPage as $item) {
			$key = $item->structure_name;
			if (!isset($groups[$key])) {
				if ($item->form_name == 'Quantity') {
					$columns = [
						[
							'column_display' => 'KPI Name',
							'column_name' => 'item_name',
							'data_type' => 'text',
						],							
						[
							'column_display' => 'Structure',
							'column_name' => 'structure_name',
							'data_type' => 'text',
						],						
						[
							'column_display' => 'Perspective',
							'column_name' => 'perspective_name',
							'data_type' => 'text',
						],						
						[
							'column_display' => 'UOM',
							'column_name' => 'uom_name',
							'data_type' => 'text',
						],					
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],						
					];
				} elseif ($item->form_name == 'Quality') {
					$columns = [
						[
							'column_display' => 'Appraisal Item Name',
							'column_name' => 'item_name',
							'data_type' => 'text',
						],			
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],									
					];
				} elseif ($item->form_name == 'Deduct Score') {
					$columns = [
						[
							'column_display' => 'KPI Name',
							'column_name' => 'item_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Max Value',
							'column_name' => 'max_value',
							'data_type' => 'number',
						],						
						[
							'column_display' => 'Deduct Score/Unit',
							'column_name' => 'unit_deduct_score',
							'data_type' => 'number',
						],									
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],									
					];
				}
				$groups[$key] = array(
					'items' => array($item),
					'count' => 1,
					'columns' => $columns,
					'structure_id' => $item->structure_id,
					'form_id' => $item->form_id,
					'form_url' => $item->app_url
				);
			} else {
				$groups[$key]['items'][] = $item;
				$groups[$key]['count'] += 1;
			}
		}		
	//	$resultT = $result->toArray();
	//	$resultT['group'] = $groups;
		return response()->json(['group' => $groups]);	

	}
	
	public function connection_list()
	{
		$items = DB::select("
			Select connection_id, connection_name
			From database_connection 
			Order by connection_name		
		");
		return response()->json($items);
	}
   
    public function al_list()
    {
		$items = DB::select("
			Select level_id, appraisal_level_name
			From appraisal_level 
			Where is_active = 1 
			and is_hr = 0
			order by level_id
		");
		return response()->json($items);
    }
	
	public function department_list()
	{
		$items = DB::select("
			Select distinct department_code, department_name
			From employee 
			order by department_code asc
		");
		return response()->json($items);	
	}
	
	public function perspective_list()
	{
		$items = DB::select("
			Select perspective_id, perspective_name
			From perspective
			Where is_active = 1 order by perspective_id		
		");
		return response()->json($items);
	}
	
	public function structure_list()
	{
		$items = DB::select("
			Select structure_id, structure_name, b.form_name
			From appraisal_structure a
			left outer join form_type b
			on a.form_id = b.form_id
			Where a.is_active = 1 order by structure_id		
		");
		return response()->json($items);
	}
	
	public function uom_list()
	{
		$items = DB::select("
			Select uom_id, uom_name
			From uom
			Where is_active = 1 order by uom_id		
		");
		return response()->json($items);
	}	
	
	public function auto_appraisal_name(Request $request)
	{
		$qinput = array();
		// $items = DB::select("
			// Select appraisal_item_id, appraisal_item_name
			// From appraisal_item
			// Where appraisal_level_id = ?
			// And perspective_id = ?
			// And structure_id = ?
			// And appraisal_item_name like ?
			
		// ", array($request->appraisal_level_id, $request->perspective_id, $request->structure_id, '%'.$request->appraisal_item_name.'%'));
		
		$query = "
			Select i.item_id, i.item_name
			From appraisal_item i
			where 1 = 1
		";
		
		empty($request->level_id) ?: ($query .= " and exists ( select 1 from appraisal_item_level lv left outer join appraisal_level al on lv.level_id = al.level_id where lv.item_id = i.item_id and al.is_hr = 0 and lv.level_id = ? ) " AND $qinput[] = $request->level_id);
		empty($request->kpi_type_id) ?: ($query .= " And i.kpi_type_id = ? " AND $qinput[] = $request->kpi_type_id);
		empty($request->org_id) ?: ($query .= " and exists ( select 1 from appraisal_item_org lv where lv.item_id = i.item_id and lv.org_id = ? ) " AND $qinput[] = $request->org_id);				
		empty($request->perspective_id) ?: ($query .= " and i.perspective_id = ? " AND $qinput[] = $request->perspective_id);
		empty($request->structure_id) ?: ($query .= " and i.structure_id = ? " AND $qinput[] = $request->structure_id);
		empty($request->item_name) ?: ($query .= " and item_name like ? " AND $qinput[] = '%'.$request->item_name.'%');
		
		$qfooter = "
			Order by i.item_name
		";
		
		$items = DB::select($query.$qfooter,$qinput);
		
		return response()->json($items);
		
	}
	
	public function show($item_id)
	{
		try {
			$cds_ar = array();
			$cds_name_ar = array();
			$item = AppraisalItem::find($item_id);
			$structure = AppraisalStructure::find($item->structure_id);
			empty($structure) ? $item->structure_name = '' : $item->structure_name = $structure->structure_name;
			$cds = DB::select("
				select a.cds_id, b.cds_name
				from kpi_cds_mapping a left outer join cds b
				on a.cds_id = b.cds_id
				where a.item_id = ?
				order by a.created_dttm asc
			", array($item->item_id));
			$key = 0;
			foreach ($cds as $c) {
				$cds_ar['{'.$key.'}'] = $c->cds_id;
				$cds_name_ar['{'.$key.'}'] = $c->cds_name;
				$key += 1;
			}
			
			$item->position = DB::select("
				select position_id
				from appraisal_item_position
				where item_id = ?
			", array($item->item_id));
			
			$item->appraisal_level = DB::select("
				select level_id
				from appraisal_item_level
				where item_id = ?
			", array($item->item_id));			
			
			$item->org = DB::select("
				select org_id
				from appraisal_item_org
				where item_id = ?
			", array($item->item_id));			
			
			$item->cds_id = $cds_ar;
			$item->cds_name = $cds_name_ar;
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Item not found.']);
		}
		return response()->json($item);		
	}
	
	public function store(Request $request)
	{

		if ($request->form_id == 1) {
			$validator = Validator::make($request->all(), [	
				'item_name' => 'required|max:255|unique:appraisal_item',
				'kpi_type_id' => 'required|integer',
				'perspective_id' => 'required|integer',
				'structure_id' => 'required|integer',
				'appraisal_level' => 'required',
				'formula_cds_id' => 'required',
				'uom_id' => 'required|integer',
				'remind_condition_id' => 'integer',
				'value_type_id' => 'integer',
				'function_type_id' => 'integer',
				'is_show_variance' => 'boolean',
				'formula_desc' => 'max:1000',
			//	'formula_cds_id' => 'required|max:1000',
			//	'formula_cds_name' => 'required|max:1000',
				'is_active' => 'required|boolean',
				'kpi_id' => 'numeric'
			]);

			if ($validator->fails()) {
				return response()->json(['status' => 400, 'data' => $validator->errors()]);
			} else {
				$item = new AppraisalItem;
				$item->fill($request->except(['form_id','cds','org','position','appraisal_level']));
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
				$item->save();
			
				preg_match_all('/cds(.*?)\]/', $request->formula_cds_id, $cds);

				foreach ($cds[1] as $c) {
					$checkmap = KPICDSMapping::where('item_id',$item->item_id)->where('cds_id',$c);
					
					if ($checkmap->count() == 0) {
						$map = new KPICDSMapping;
						$map->item_id = $item->item_id;
						$map->cds_id = $c;
						$map->created_by = Auth::id();
						$map->save();
					}
				}
				
				if (!empty($request->org)) {
					foreach ($request->org as $i) {
						$org = new ItemOrg;
						$org->item_id = $item->item_id;
						$org->org_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}
				}
				
				if (!empty($request->position)) {
					foreach ($request->position as $i) {
						$org = new ItemPosition;
						$org->item_id = $item->item_id;
						$org->position_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}	
				}
				
				if (!empty($request->appraisal_level)) {
					foreach ($request->appraisal_level as $i) {
						$org = new ItemLevel;
						$org->item_id = $item->item_id;
						$org->level_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}	
				}
		
				
			}	
		} elseif ($request->form_id == 2) {
		
			$validator = Validator::make($request->all(), [
				'item_name' => 'required|max:255|unique:appraisal_item',
				'structure_id' => 'required|integer',
				'appraisal_level' => 'required',
				'is_active' => 'required|boolean'
			]);

			if ($validator->fails()) {
				return response()->json(['status' => 400, 'data' => $validator->errors()]);
			} else {
				$item = new AppraisalItem;
				$item->fill($request->except(['form_id','org','position','appraisal_level']));
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
				$item->save();
				
				if (!empty($request->org)) {
					foreach ($request->org as $i) {
						$org = new ItemOrg;
						$org->item_id = $item->item_id;
						$org->org_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}
				}
				
				if (!empty($request->position)) {
					foreach ($request->position as $i) {
						$org = new ItemPosition;
						$org->item_id = $item->item_id;
						$org->position_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}	
				}
				
				if (!empty($request->appraisal_level)) {
					foreach ($request->appraisal_level as $i) {
						$org = new ItemLevel;
						$org->item_id = $item->item_id;
						$org->level_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}	
				}				
						
			}			
		
		} elseif ($request->form_id == 3) {
		
			$validator = Validator::make($request->all(), [
				'item_name' => 'required|max:255|unique:appraisal_item',
				'structure_id' => 'required|integer',
				'appraisal_level' => 'required',
				'max_value' => 'required|numeric',
				'unit_deduct_score' => 'required|numeric|digits_between:1,4',
				'is_active' => 'required|boolean'
			]);

			if ($validator->fails()) {
				return response()->json(['status' => 400, 'data' => $validator->errors()]);
			} else {
				$item = new AppraisalItem;
				$item->fill($request->except(['form_id','org','position','appraisal_level']));
				$item->created_by = Auth::id();
				$item->updated_by = Auth::id();
				$item->save();
				
				if (!empty($request->org)) {
					foreach ($request->org as $i) {
						$org = new ItemOrg;
						$org->item_id = $item->item_id;
						$org->org_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}
				}
				
				if (!empty($request->position)) {
					foreach ($request->position as $i) {
						$org = new ItemPosition;
						$org->item_id = $item->item_id;
						$org->position_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}	
				}
				
				if (!empty($request->appraisal_level)) {
					foreach ($request->appraisal_level as $i) {
						$org = new ItemLevel;
						$org->item_id = $item->item_id;
						$org->level_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}	
				}				
						
			}				
		
		} else {
			return response()->json(['status' => 400, 'data' => 'Form not available.']);
		}
		
		return response()->json(['status' => 200, 'data' => $item]);
	}
	
	public function cds_list(Request $request)
	{
		$items = DB::select("
			Select cds_id, cds_name
			From cds
			Where 1 = 1
			And cds_name like ?
			Order by cds_id	
		", array('%'.$request->cds_name.'%'));
		
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
	
	public function update(Request $request, $item_id)
	{
		try {
			$item = AppraisalItem::findOrFail($item_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Item not found.']);
		}
		
		if ($request->form_id == 1) {
			$validator = Validator::make($request->all(), [
				'item_name' => 'required|max:255|unique:appraisal_item,item_name,'.$item_id . ',item_id',
				'kpi_type_id' => 'required|integer',
				'perspective_id' => 'required|integer',
				'structure_id' => 'required|integer',
				'appraisal_level' => 'required',
				'formula_cds_id' => 'required',
				'uom_id' => 'required|integer',
				'remind_condition_id' => 'integer',
				'value_type_id' => 'integer',
				'is_show_variance' => 'boolean',
				'formula_desc' => 'max:1000',
				'is_active' => 'required|boolean',
				'kpi_id' => 'numeric'
			]);

			if ($validator->fails()) {
				return response()->json(['status' => 400, 'data' => $validator->errors()]);
			} else {
				$item->fill($request->except(['form_id','cds','org','position','appraisal_level']));
				$item->updated_by = Auth::id();
				$item->save();
				// $f_cds_id = array();
				// $f_cds_name = array();
				// $key = 0;
				KPICDSMapping::where('item_id',$item->item_id)->delete();
				preg_match_all('/cds(.*?)\]/', $request->formula_cds_id, $cds);
				foreach ($cds[1] as $c) {
					$checkmap = KPICDSMapping::where('item_id',$item->item_id)->where('cds_id',$c);
					
					if ($checkmap->count() == 0) {
						$map = new KPICDSMapping;
						$map->item_id = $item->item_id;
						$map->cds_id = $c;
						$map->created_by = Auth::id();
						$map->save();
					}
				}
				
				ItemOrg::where('item_id',$item->item_id)->delete();
				if (!empty($request->org)) {
					foreach ($request->org as $i) {
						$org = new ItemOrg;
						$org->item_id = $item->item_id;
						$org->org_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}
				}
				ItemPosition::where('item_id',$item->item_id)->delete();
				if (!empty($request->position)) {
					foreach ($request->position as $i) {
						$org = new ItemPosition;
						$org->item_id = $item->item_id;
						$org->position_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}		
				}
					
				ItemLevel::where('item_id',$item->item_id)->delete();
				if (!empty($request->appraisal_level)) {
					foreach ($request->appraisal_level as $i) {
						$org = new ItemLevel;
						$org->item_id = $item->item_id;
						$org->level_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}								
				}

			}	
		} elseif ($request->form_id == 2) {
		
			$validator = Validator::make($request->all(), [
				'item_name' => 'required|max:255|unique:appraisal_item,item_name,'.$item_id . ',item_id',
				'structure_id' => 'required|integer',
				'appraisal_level' => 'required',
				'is_active' => 'required|boolean'
			]);

			if ($validator->fails()) {
				return response()->json(['status' => 400, 'data' => $validator->errors()]);
			} else {
				$item->fill($request->except(['form_id','org','position','appraisal_level']));
				$item->updated_by = Auth::id();
				$item->save();
				
				ItemOrg::where('item_id',$item->item_id)->delete();
				if (!empty($request->org)) {
					foreach ($request->org as $i) {
						$org = new ItemOrg;
						$org->item_id = $item->item_id;
						$org->org_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}
				}
				ItemPosition::where('item_id',$item->item_id)->delete();
				if (!empty($request->position)) {
					foreach ($request->position as $i) {
						$org = new ItemPosition;
						$org->item_id = $item->item_id;
						$org->position_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}		
				}
					
				ItemLevel::where('item_id',$item->item_id)->delete();
				if (!empty($request->appraisal_level)) {
					foreach ($request->appraisal_level as $i) {
						$org = new ItemLevel;
						$org->item_id = $item->item_id;
						$org->level_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}								
				}							
			}			
		
		} elseif ($request->form_id == 3) {
		
			$validator = Validator::make($request->all(), [
				'item_name' => 'required|max:255|unique:appraisal_item,item_name,'.$item_id . ',item_id',
				'structure_id' => 'required|integer',
				'appraisal_level' => 'required',
				'max_value' => 'required|numeric',
				'unit_deduct_score' => 'required|numeric|digits_between:1,4',
				'is_active' => 'required|boolean'
			]);

			if ($validator->fails()) {
				return response()->json(['status' => 400, 'data' => $validator->errors()]);
			} else {
				$item->fill($request->except(['form_id','org','position','appraisal_level']));
				$item->updated_by = Auth::id();
				$item->save();
				
				ItemOrg::where('item_id',$item->item_id)->delete();
				if (!empty($request->org)) {
					foreach ($request->org as $i) {
						$org = new ItemOrg;
						$org->item_id = $item->item_id;
						$org->org_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}
				}
				ItemPosition::where('item_id',$item->item_id)->delete();
				if (!empty($request->position)) {
					foreach ($request->position as $i) {
						$org = new ItemPosition;
						$org->item_id = $item->item_id;
						$org->position_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}		
				}
					
				ItemLevel::where('item_id',$item->item_id)->delete();
				if (!empty($request->appraisal_level)) {
					foreach ($request->appraisal_level as $i) {
						$org = new ItemLevel;
						$org->item_id = $item->item_id;
						$org->level_id = $i;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						$org->save();
					}								
				}								
			}				
		
		} else {
			return response()->json(['status' => 400, 'data' => 'Form not available.']);
		}
		
		return response()->json(['status' => 200, 'data' => $item]);
				
	}
	
	public function destroy($item_id)
	{
		try {
			$item = AppraisalItem::findOrFail($item_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Appraisal Item not found.']);
		}	

		try {
			ItemOrg::where('item_id',$item_id)->delete();
			ItemLevel::where('item_id',$item_id)->delete();
			ItemPosition::where('item_id',$item_id)->delete();
			KPICDSMapping::where('item_id',$item_id)->delete();
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Appraisal Item is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
	
	public function copy(Request $request)
	{
		if (!empty($request->appraisal_level)) {
			foreach($request->appraisal_level as $a) {
				if (!empty($request->appraisal_item)) {
					foreach($request->appraisal_item as $i) {
						$item = AppraisalItem::find($i);
						if (empty($item)) {
						} else {
							$checkdup = DB::select("
								select appraisal_item_id
								from appraisal_item
								where appraisal_item_name = ?
								and department_code = ?
								and appraisal_level_id = ?
							", array($item->appraisal_item_name, $item->department_code, $a));
							if (empty($checkdup)) {
								$newitem = new AppraisalItem;
								$newitem->appraisal_item_name = $item->appraisal_item_name;
								$newitem->department_code = $item->department_code;
								$newitem->appraisal_level_id = $a;
								$newitem->structure_id = $item->structure_id;
								$newitem->perspective_id = $item->perspective_id;
								$newitem->uom_id = $item->uom_id;
								$newitem->max_value = $item->max_value;
								$newitem->unit_deduct_score = $item->unit_deduct_score;
								$newitem->is_active = $item->is_active;
								$newitem->created_by = Auth::id();
								$newitem->updated_by = Auth::id();
								$newitem->save();	
							}
						}
					}
				}
			}
		}
		
		return response()->json(['status' => 200]);
	}	
	
}
