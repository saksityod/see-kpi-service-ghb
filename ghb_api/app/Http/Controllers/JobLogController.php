<?php

namespace App\Http\Controllers;

use App\JobLog;

use Auth;
use DB;
use File;
use Validator;
use Excel;
use Mail;
use Config;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class JobLogController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function index()
	{
		$items = DB::select("
			Select *
			From job_log
		");
		return response()->json($items);
	}

	public function show($job_log_id)
	{
		try {
			$item = JobLog::findOrFail($job_log_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Job Log not found.']);
		}
		return response()->json($item);
	}

	public function update(Request $request, $job_log_id)
	{
		try {
			$item = JobLog::findOrFail($job_log_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Job Log not found.']);
		}

		$errors_validator = [];

		$param_start_date = date('Y-m-d', strtotime($request->param_start_date. ' -1 day' ));
		$validator = Validator::make($request->all(), [
			'param_start_date' => 'required|date_format:"Y-m-d"',
			'param_end_date' => 'required|date_format:"Y-m-d"|after:'. $param_start_date,
			'destination_address' => 'email',
			'sender_address' => 'email'
		],[
			'param_end_date.after' => 'The param end date must be a date after or equal param start date.'
		]);

		if($validator->fails()) {
			$errors_validator[] = $validator->errors();
		}

		if(!empty($request['cc'])) {
			$cc_list = '';
			$last_key = count($request['cc']) - 1;

			foreach($request['cc'] as $key => $cc) {
				if($key == $last_key) {
					$cc_list .= "".$cc['cc']."";
				} else {
					$cc_list .= "".$cc['cc'].", ";
				}

				if($key == 0) {
					$validator_cc = Validator::make($cc, [
		            	'cc'          => 'email'
		            ]);
				} else {
					$validator_cc = Validator::make($cc, [
		            	'cc'          => 'required|email'
		            ]);
				}

	            if($validator_cc->fails()) {
					$errors_validator[] = $validator_cc->errors();
				}
			}
		}

		if(!empty($request['bcc'])) {
			$bcc_list = '';
			$last_key = count($request['bcc']) - 1;

			foreach($request['bcc'] as $key => $bcc) {
				if($key == $last_key) {
					$bcc_list .= "".$bcc['bcc']."";
				} else {
					$bcc_list .= "".$bcc['bcc'].", ";
				}

				if($key == 0) {
					$validator_bcc = Validator::make($bcc, [
		            	'bcc'          => 'email'
		            ]);
				} else {
					$validator_bcc = Validator::make($bcc, [
		            	'bcc'          => 'required|email'
		            ]);
				}

	            if($validator_bcc->fails()) {
					$errors_validator[] = $validator_bcc->errors();
				}
			}
		}

		if(!empty($errors_validator)) {
            return response()->json(['status' => 400, 'data' => $errors_validator]);
        }
		
        $item->job_log_name = $request->job_log_name;
        $item->param_start_date = $request->param_start_date;
        $item->param_end_date = $request->param_end_date;
        $item->path_batch_file = $request->path_batch_file;
        $item->destination_address = $request->destination_address;
        $item->cc = empty($cc_list) ? '' : $cc_list;
        $item->bcc = empty($bcc_list) ? '' : $bcc_list;
        $item->sender_name = $request->sender_name;
        $item->sender_address = $request->sender_address;
        $item->path_log_file = $request->path_log_file;
        $item->subject_error = $request->subject_error;
        $item->comment_error = $request->comment_error;
		$item->save();
	
		return response()->json(['status' => 200]);
				
	}

	public function run($job_log_id) {
		try {
			$item = JobLog::findOrFail($job_log_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Job Log not found.']);
		}

		if (file_exists($item->path_batch_file)) {

			$check_item = DB::select("
				select status from job_log where status = 'Loading'
			");

			if(!empty($check_item)) {
				return response()->json(['status' => 404, 'data' => 'Cannot Running because some ETL is runing.']);
			}
			
			//$handle = popen("start /B ". "\"".$item->path_batch_file."\"", "r");
			$handle = popen("start /B cmd /S /C ". $item->path_batch_file, "r");
			
			if ($handle === FALSE) {
				return response()->json(['status' => 404, 'data' => 'Unable to execute '.$item->path_batch_file]);	
			} else {
				pclose($handle);
				for ($i=0; $i <= 36 ; $i++) {
					sleep(5);
					$item2 = JobLog::find($job_log_id);
					if($item2->status=='Loading') {
						return response()->json(['status' => 200]);
					}
				}
				return response()->json(['status' => 404, 'data' => 'ETL is not runing.']);
			}
		} else {
			return response()->json(['status' => 404, 'data' => 'Path Bath File not found.']);
		}
		// $WshShell = new \COM("WScript.Shell");
		// $oExec = $WshShell->Run($cmd, 0, false);
	}
}