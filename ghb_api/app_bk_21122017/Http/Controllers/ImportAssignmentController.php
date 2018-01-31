<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use File;
use Excel;
use Response;
use Exception;
use App\KPIType;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ImportAssignmentController extends Controller
{

  public function __construct(){
	   //$this->middleware('jwt.auth');
	}


  public function org_list(Request $request){
    $levelStr = "'".implode(",", $request->level_id)."'";
		$items = DB::select("
			SELECT org_id, org_name
			FROM org
			WHERE is_active = 1
			AND level_id IN({$levelStr})
			ORDER BY org_id
		");
		return response()->json($items);
	}

  /**
   * Display item list.
   *
   * @author P.Wirun (GJ)
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
   public function item_list(Request $request){
     $levelStr = "'".implode("','", $request->level_id)."'";
     $orgIdStr = "'".implode("','", $request->org_id)."'";

     $items = DB::select("
      SELECT distinct ai.structure_id, strc.structure_name, ai.item_id, ai.item_name, strc.form_id
      FROM appraisal_item ai
      INNER JOIN appraisal_structure strc ON strc.structure_id = ai.structure_id
      INNER JOIN appraisal_item_level vel ON vel.item_id = ai.item_id
      INNER JOIN appraisal_item_org iorg ON iorg.item_id = ai.item_id
      INNER JOIN appraisal_item_position post ON post.item_id = ai.item_id
      WHERE strc.is_active = 1
      AND vel.level_id IN({$levelStr})
      AND iorg.org_id IN({$orgIdStr})
      AND post.position_id = {$request->position_id}
      ORDER BY ai.structure_id, ai.item_id");

      $itemList = [];
      foreach($items as $value) {
        $itemList[$value->structure_name][] = [
          "structure_id" => $value->structure_id,
          "structure_name" => $value->structure_name,
          "item_id" => $value->item_id,
          "item_name" => $value->item_name,
          "form_id" => $value->form_id
        ];
      }
      $jsonResult["group"] = $itemList;

     return response()->json($itemList);
   }


   /**
    * Assignment export to Excel.
    *
    * @author P.Wirun (GJ)
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
    public function export_template(Request $request){

      // Set file name and directory.
      $extension = "xlsx";
      $fileName = "Assignment";
      $outpath = public_path()."/export_file";
      File::isDirectory($outpath) or File::makeDirectory($outpath, 0777, true, true);

      $items = DB::select("
        SELECT distinct ai.structure_id, strc.structure_name, ai.item_id, ai.item_name, strc.form_id
        FROM appraisal_item ai
        INNER JOIN appraisal_structure strc ON strc.structure_id = ai.structure_id
        INNER JOIN appraisal_item_level vel ON vel.item_id = ai.item_id
        INNER JOIN appraisal_item_org iorg ON iorg.item_id = ai.item_id
        INNER JOIN appraisal_item_position post ON post.item_id = ai.item_id
        WHERE strc.is_active = 1
        ORDER BY ai.structure_id, ai.item_id");


      // Set data array
      //$itemsArr = json_decode(json_encode($items), true);
      $itemList = [];
      foreach($items as $value) {
        $itemList[$value->structure_name][] = [
          "structure_id" => $value->structure_id,
          "structure_name" => $value->structure_name,
          "item_id" => $value->item_id,
          "item_name" => $value->item_name,
          "form_id" => $value->form_id
        ];
      }

      Excel::create($fileName, function($excel) use ($itemList) {

        foreach ($itemList as $key => $group) {

          $excel->sheet($key, function($sheet) use ($key, $itemList){
            $sheet->fromArray($itemList[$key]);
          });

        }

      })->store($extension, $outpath);

      return response()->download($outpath."/".$fileName.".".$extension, $fileName.".".$extension);
    }

}
