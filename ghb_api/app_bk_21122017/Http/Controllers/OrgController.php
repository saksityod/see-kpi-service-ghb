<?php

namespace App\Http\Controllers;

use App\Org;
use App\AppraisalLevel;

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

class OrgController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function index(Request $request)
	{		
		empty($request->level_id) ? $level = "" : $level = " and a.level_id = " . $request->level_id . " ";
		empty($request->org_code) ? $org = "" : $org = " and a.org_code = " . $request->org_code . " ";
		$items = DB::select("
			select a.org_id, a.org_code, a.org_name, a.org_abbr, a.is_active, b.org_name parent_org_name, a.parent_org_code, a.level_id, c.appraisal_level_name, a.longitude, a.latitude, a.province_code, d.province_name
			from org a left outer join
			org b on b.org_code = a.parent_org_code
			left outer join appraisal_level c
			on a.level_id = c.level_id 
			left outer join province d on a.province_code = d.province_code
			where 1=1 " . $level . $org . "
			order by a.org_code asc
		");
		return response()->json($items);
	}
	
	public function auto_org_name(Request $request)
	{
		empty($request->level_id) ? $level = "" : $level = " and a.level_id = " . $request->level_id . " ";
		$items = DB::select("
			select a.org_id, a.org_code, a.org_name, a.org_abbr
			from org a 
			where org_name like ? " . $level . "
			order by a.org_code asc
			limit 10
		", array('%'.$request->org_name.'%'));
		return response()->json($items);		
	}
	
	public function province_list()
	{
		$items = DB::select("
			select province_code, province_name
			from province
			order by province_name asc
		");
		return response()->json($items);	
	}
	
	public function al_list()
	{
		$items = DB::select("
			select level_id, appraisal_level_name, parent_id
			from appraisal_level
			where is_active = 1
			order by level_id asc
		");
		foreach ($items as $i) {
			$parent_org = DB::select("
				select org_code, org_name, org_abbr
				from org
				where level_id = ?
				order by org_code asc
			", array($i->parent_id));
			
			$org = DB::select("
				select org_code, org_name, org_abbr
				from org
				where level_id = ?
				order by org_code asc
			", array($i->level_id));
			
			$i->parent_org = $parent_org;
			$i->org = $org;
		}
		return response()->json($items);
	}
	
	public function parent_list()
	{
		$items = DB::select("
			select a.org_code, a.org_name, a.org_abbr
			from org a 
			order by a.org_code asc
		");
		return response()->json($items);		
	}	
	
	public function import(Request $request)
	{
		$errors = array();
		foreach ($request->file() as $f) {
			$items = Excel::load($f, function($reader){})->get();	
			foreach ($items as $i) {
				
				$validator = Validator::make($i->toArray(), [
					'org_code' => 'required|max:15',
					'org_name' => 'required|max:255',
					'org_abbr' => 'max:255',
					'parent_org_code' => 'max:15'
				]);

				if ($validator->fails()) {
					$errors[] = ['org_code' => $i->org_code, 'errors' => $validator->errors()];
				} else {
					$org = DB::select("
						select org_id
						from org
						where org_code = ?
					",array($i->org_code));
					if (empty($org)) {
						$org = new Org;		
						$org->org_code = $i->org_code;
						$org->org_name = $i->org_name;
						$org->org_abbr = $i->org_abbr;
						$org->parent_org_code = $i->parent_org_code;
						$org->is_active = 1;
						$org->latitude = $i->latitude;
						$org->longitude = $i->longitude;
						$org->created_by = Auth::id();
						$org->updated_by = Auth::id();
						try {
							$org->save();
						} catch (Exception $e) {
							$errors[] = ['org_code' => $i->org_code, 'errors' => substr($e,0,254)];
						}
					} else {
 						Org::where('org_code',$i->org_code)->update(['org_name' => $i->org_name,'org_abbr' => $i->org_abbr,'parent_org_code' => $i->parent_org_code,'latitude' => $i->latitude,'longitude' => $i->longitude]);
					}
				}					
			}
		}
		return response()->json(['status' => 200, 'errors' => $errors]);
	}	
	
	public function store(Request $request)
	{
	
		$validator = Validator::make($request->all(), [
			'org_code' => 'required|max:15|unique:org',
			'org_name' => 'required|max:255|unique:org',
			'org_abbr' => 'max:255',
			'parent_org_code' => 'max:15',
			'level_id' => 'integer',
			'latitude' => 'numeric',
			'longitude' => 'numeric',
			'province_code' => 'integer',			
			'is_active' => 'required|integer',
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new Org;
			$item->fill($request->all());
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($org_id)
	{
		try {
			$item = Org::findOrFail($org_id);
			
			$parent = DB::select("
				select org_name
				from org
				where org_code = ?
			", array($item->parent_org_code));
			
			if (!empty($parent)) {
				$item->parent_org_name = $parent[0]->org_name;
			}
			
			$al = AppraisalLevel::find($item->level_id);
			
			empty($al) ? $item->level_name = null : $item->level_name = $al->appraisal_level_name;
			
			
			
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Organization not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $org_id)
	{
		try {
			$item = Org::findOrFail($org_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Organization not found.']);
		}
		
		$validator = Validator::make($request->all(), [
			'org_code' => 'required|max:15|unique:org,org_name,' . $org_id . ',org_id',
			'org_name' => 'required|max:255|unique:org,org_name,' . $org_id . ',org_id',
			'org_abbr' => 'max:255',
			'parent_org_code' => 'max:15',
			'level_id' => 'integer',
			'latitude' => 'numeric',
			'longitude' => 'numeric',
			'province_code' => 'integer',
			'is_active' => 'required|integer',
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item->fill($request->all());
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);
				
	}
	
	public function destroy($org_id)
	{
		try {
			$item = Org::findOrFail($org_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Organization not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Organization is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
