<?php

namespace App\Http\Controllers;

use App\Perspective;

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

class SmartGoalDashboard extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function getSmartColor(Request $request){
        $PerspectiveData = Perspective::select('perspective_id','perspective_name','color_code')
                                ->where("is_active",1)
                                ->get();
                                        
        return response()->json(["status" => 200,"data" => $PerspectiveData]);
    }

}
