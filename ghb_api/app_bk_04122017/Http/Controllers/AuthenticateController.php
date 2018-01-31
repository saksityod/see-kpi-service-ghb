<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\User;
use App\AppraisalCriteria;
use App\SystemConfiguration;
use App\UsageLog;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use JWTAuth;
use Auth;
use Validator;
use DB;
use Mail;
use Hash;
use Adldap;
use Exception;
use Tymon\JWTAuth\Exceptions\JWTException;
use Adldap\Exceptions\Auth\BindException;
//use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;


class AuthenticateController extends Controller
{

	public function __construct()
	{
		$this->middleware('jwt.auth', ['only' => ['index']]);
	}
   
    public function index(Request $request)
    {
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		
		
		$emp = DB::select("
			select b.is_hr
			from employee a
			inner join appraisal_level b
			on a.level_id = b.level_id
			where a.emp_code = ?
		", array(Auth::id()));
		
		
		if (empty($emp)) {
			return response()->json(['error' => 'User not found.'], 500);
		}

		
		
		return response()->json(['status' => 200, 'theme_color' => $config->theme_color, 'is_hr' => $emp[0]->is_hr]);
    }    
	
	public function debug(Request $request)
	{
		// $a = [['a' => 1, 'b' => 2], ['a' => 2, 'b' => 1], ['a' => 1, 'b' => 2], ['a' => 3, 'b' => 2]];
		return 'done';
		// $a = array_unique($a,SORT_REGULAR);
		
		// return $a;
		// // Get array keys
		// $arrayKeys = array_keys($a);
		// // Fetch last array key
		// $lastArrayKey = array_pop($arrayKeys);
		// //iterate array
		// $string = '';
		// foreach($a as $k => $v) {
			// if($k == $lastArrayKey) {
				// //during array iteration this condition states the last element.
				// $string .= $v;
			// } else {
				// $string .= $v . ',';
			// }
		// }		
	//	$hash = Hash::make('secret');
		//return $hash;
		$mail_body = "
Hello from TYW KPI,

You have been appraised please click https://www.google.com

Best Regards,

From Going Jesse Team
		";
		$error = '';
		try {
		Mail::raw($mail_body, function($message)
		{
			$message->from('msuksang@gmail.com', 'TYW Team');

			$message->to('methee@goingjesse.com')->subject('You have been Appraised :-)');
		});
		} catch (Exception $e)
		{
			$error = $e->getMessage();
		}
		
		// Mail::later(5,'emails.welcome', array('msg' => $mail_body), function($message)
		// {
			// $message->from('msuksang@gmail.com', 'TYW Team');

			// $message->to('methee@goingjesse.com')->subject('You have been Appraised :-)');
		// });	
		
		return response()->json(['error' => $error]);
		
		
		//return response()->json($test);
	}

    public function authenticate(Request $request)
    {	
      //  $credentials = $request->only('username', 'password');
		// if (Auth::attempt($credentials)) {
			// return 'pass';
		// } else {
			// return 'fail';
		// }	
    
		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}			

        try {
            // verify the credentials and create a token for the user
            if (! $token = JWTAuth::attempt(array('screenName' => $request->username, 'password' => $request->password))) {
                return response()->json(['error' => 'invalid_credentials'], 401);
            }
        } catch (JWTException $e) {
            // something went wrong
            return response()->json(['error' => 'could_not_create_token'], 500);
        } catch (BindException $e) {
			return response()->json(['error' => 'Cannot connect to Server.'], 500);
		}
		
        // if no errors are encountered we can return a JWT
		
		$emp = DB::select("
			select b.is_hr
			from employee a
			inner join appraisal_level b
			on a.level_id = b.level_id
			where a.emp_code = ?
		", array($request->username));
		
		if (empty($emp)) {
			return response()->json(['error' => 'User not found.'], 500);
		}
		
		$u = new UsageLog;
		$u->emp_code = Auth::id();
		$u->plid = $request->plid;
		$u->save();		
		
        return response()->json(['token' => $token, 'theme_color' => $config->theme_color, 'is_hr' => $emp[0]->is_hr]);
    }
	
	public function destroy()
	{
		$token = JWTAuth::getToken();
		if (empty($token)) {
			return response()->json(['status' => 401,'message' => 'no token provided']);
		} else {
			try
			{
				$response = JWTAuth::invalidate(JWTAuth::getToken());
				return response()->json(['status' => 200,'t_stat' => $response]);
				
			} catch (JWTException $e) {
				return response()->json(['status' => 401, 'message' => $e->getMessage()], $e->getStatusCode());
			}
			
		}
	}
}