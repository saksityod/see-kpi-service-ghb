<?php

namespace App\Http\Controllers;

use File;
use Illuminate\Http\Request;
use Log;
use Storage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Response  as FacadeResponse;
use Illuminate\Support\Facades\Input;
use App\Model\DatabaseConnectionModel;
use App\Model\DatabaseTypeModel;
//use JasperPHP; // put here
class JasperController extends Controller
{
    public function __construct()
    {
        $this->middleware('cors');
    }
    public function generate(Request $request)
    {
        $name_gen = md5(uniqid(rand(), true));
        $template_name = $request->template_name;
        $template_format = $request->template_format;
        $used_connection = $request->used_connection;
        $is_inline = $request->inline;

        $db_connection = env("DB_CONNECTION");
        $db_host = env("DB_HOST");
        $db_database = env("DB_DATABASE");
        $db_username = env("DB_USERNAME");
        $db_password = env("DB_PASSWORD");
        $db_port = env("DB_PORT");
        if(!empty($used_connection) && $used_connection != '0') {
            $databaseConnection = DatabaseConnectionModel::where('is_report_connection', '=', $used_connection)->first();
            if(empty($databaseConnection)) {
                return "You are not set is_report_connection";
            } else {
                //$databaseConnection = DatabaseConnection::find('is_report_connection', '=', $used_connection);
                $databaseType = DatabaseTypeModel::find($databaseConnection->database_type_id);
                $db_connection = $databaseType->database_type;
                $db_host = $databaseConnection->ip_address;
                $db_database = $databaseConnection->database_name;
                $db_username = $databaseConnection->user_name;
                $db_password = $databaseConnection->password;
                $db_port = $databaseConnection->port;
            }
        }


        //$data = Input::all();
        //Log::info($data);
        // curl -X POST -d '{"logo":"/Users/imake/WORK/PROJECT/GJ/Jasper/jasper_service_api/resources/jasper/1588_6832_th.jpg","param_year":"2016","param_period":1,"param_level":"ALL","param_org":"ALL","param_kpi":"ALL"}' -v 'http://localhost:8000/generate?template_name=Appraisal_Report&template_format=pdf&used_connection=1'
        // curl -X POST -d '{"logo":"/imake/Jasper/jasper_service_api/resources/jasper/1588_6832_th.jpg","param_year":"2017","param_period":1,"param_level":"ALL","param_org":"ALL","param_kpi":"ALL"}' -v 'http://35.198.242.63:9000/generate?template_name=Appraisal_Report&template_format=pdf&used_connection=1'
        // curl -X POST -d '{}' -v 'http://localhost:8000/generate?template_name=Appraisal_Report&template_format=pdf&used_connection=1'
        // curl -X POST -d '{}' -v 'http://35.198.242.63:9000/generate?template_name=Appraisal_Report&template_format=pdf&used_connection=1'
        $params = [];
        $data_param = $request->data;
        if(!empty($data_param)){
            $params = json_decode($data_param, true);
            Log::info('data_json');
            Log::info($params);
        }else{
            $params = json_decode($request->getContent(), true);
            Log::info(' from POST');
            Log::info($params);
        }
        /*
        $params1 = json_decode($request->getContent(), true);
        Log::info($params1);
        $params2 = Input::all();
        Log::info($params2);
        $params = $params1;
        Log::info($params);
        */
        $command = 'java -jar '.base_path('jasperStarter/lib/jasperstarter.jar').'  pr '.base_path('resources/jasper/'.$template_name.'.jasper')
            .'  -f '.$template_format.'  -o '.base_path('resources/generate/'.$name_gen);
        //shell_exec('java -jar '.base_path('resources/JasperStarter/lib/jasperstarter.jar').'  pr /Users/imake/WORK/PROJECT/GJ/Jasper/jasper_service_api/resources/jasper/CherryTest.jasper  -f pdf  -o /Users/imake/WORK/PROJECT/GJ/Jasper/jasper_service_api/resources/jasper/CherryTest2');
        //shell_exec('java -jar '.base_path('vendor/cossou/jasperphp/src/JasperStarter/lib/jasperstarter.jar').'  pr /Users/imake/WORK/PROJECT/GJ/Jasper/jasper_service_api/resources/jasper/CherryTest.jasper  -f pdf  -o /Users/imake/WORK/PROJECT/GJ/Jasper/jasper_service_api/resources/jasper/CherryTest2');
        //shell_exec('java -jar '.base_path('vendor/imake/JasperStarter/lib/jasperstarter.jar').'  pr /Users/imake/WORK/PROJECT/GJ/Jasper/jasper_service_api/resources/jasper/CherryTest.jasper  -f pdf  -o /Users/imake/WORK/PROJECT/GJ/Jasper/jasper_service_api/resources/jasper/CherryTest2');

     if(!empty($used_connection) && $used_connection == '1') {
         if (!empty($db_connection) && strlen(trim($db_connection)) > 0) {
             $command .= " -t " . $db_connection;
         }
         if (!empty($db_host) && strlen(trim($db_host)) > 0) {
             $command .= " -H " . $db_host;
         }
         if (!empty($db_database) && strlen(trim($db_database)) > 0) {
             $command .= " -n " . $db_database;
         }
         if (!empty($db_username) && strlen(trim($db_username)) > 0) {
             $command .= " -u " . $db_username;
         }
         if (!empty($db_password) && strlen(trim($db_password)) > 0) {
             $command .= " -p " . $db_password;
         }
         if (!empty($db_port) && strlen(trim($db_port)) > 0) {
             $command .= " --db-port " . $db_port;
         }
     }

        $ignore_param = ['template_name','template_format','used_connection','inline'];
        if ( !empty($params) ) {
            $command .= ' -P ';
            //$command .= ' ';
            foreach ($params as $key => $value) {
                if (!in_array($key, $ignore_param))
                    $command .= $key.'='.$value.' ';
            }
        }
        shell_exec($command);
        Log::info($command);
        $pathToFile = base_path('resources/generate/'.$name_gen.'.'.$template_format);

        $content_type = 'application/pdf';
        if($template_format == 'xls')
            $content_type = 'application/vnd.ms-excel';
        $headers = array(
            'Content-Type: '.$content_type,
        );

        $name = $template_name.'.'.$template_format;
        //return response()->download($pathToFile)->deleteFileAfterSend(true);
        //return  response()->download($pathToFile, $name, $headers)->deleteFileAfterSend(true);
		 //$response->header('X-Frame-Options', 'SAMEORIGIN',false);
        if($is_inline == '1' && $template_format == 'pdf' ) {
            $content = file_get_contents($pathToFile);
            File::delete($pathToFile);
            return FacadeResponse::make($content, 200,
                array('content-type' => 'application/pdf', 'Content-Disposition' => 'inline; ' . $name));
        }else{
            return  response()->download($pathToFile, $name, $headers)->deleteFileAfterSend(true);
        }
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
