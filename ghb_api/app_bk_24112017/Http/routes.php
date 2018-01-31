<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/
if (isset($_SERVER['HTTP_ORIGIN'])) {
	header('Access-Control-Allow-Credentials: true');
	header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
	header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
	header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, useXDomain, withCredentials');
	//header('Keep-Alive: timeout=10, max=100');
}
// Route::get('/', function () {
    // return Response::json(array('hello' => 'hehe'));
// });

//Route::resource('authenticate', 'AuthenticateController', ['only' => ['index']]);
Route::group(['middleware' => 'cors'], function()
{	
	// Session //
	Route::get('session','AuthenticateController@index');
	Route::post('session', 'AuthenticateController@authenticate');
	Route::get('session/debug', 'AuthenticateController@debug');
	Route::delete('session', 'AuthenticateController@destroy');
	
	// Common Data Set //
	Route::get('cds/al_list','CommonDataSetController@al_list');
	Route::get('cds/connection_list','CommonDataSetController@connection_list');
	Route::post('cds/auto_cds','CommonDataSetController@auto_cds_name');
	Route::patch('cds/{id}','CommonDataSetController@update');
	Route::get('cds/{id}','CommonDataSetController@show');
	Route::delete('cds/{id}','CommonDataSetController@destroy');
	Route::post('cds','CommonDataSetController@store');
	Route::get('cds','CommonDataSetController@index');
	Route::post('cds/test_sql','CommonDataSetController@test_sql');
	Route::post('cds/copy','CommonDataSetController@copy');
	
	// Appraisal Item //
	Route::post('appraisal_item','AppraisalItemController@store');
	Route::get('appraisal_item/al_list','AppraisalItemController@al_list');
	Route::get('appraisal_item/remind_list','AppraisalItemController@remind_list');
	Route::get('appraisal_item/value_type_list','AppraisalItemController@value_type_list');
	Route::get('appraisal_item/department_list','AppraisalItemController@department_list');
	Route::get('appraisal_item/uom_list','AppraisalItemController@uom_list');	
	Route::get('appraisal_item/cds_list','AppraisalItemController@cds_list');
	Route::get('appraisal_item/perspective_list','AppraisalItemController@perspective_list');
	Route::get('appraisal_item/structure_list','AppraisalItemController@structure_list');
	Route::post('appraisal_item/auto_appraisal_name','AppraisalItemController@auto_appraisal_name');
	Route::post('appraisal_item/copy','AppraisalItemController@copy');
	Route::get('appraisal_item','AppraisalItemController@index');
	Route::get('appraisal_item/{item_id}','AppraisalItemController@show');
	Route::patch('appraisal_item/{item_id}','AppraisalItemController@update');
	Route::delete('appraisal_item/{item_id}','AppraisalItemController@destroy');
	
	// Import Employee //
	Route::get('import_employee/role_list','ImportEmployeeController@role_list');
	Route::get('import_employee/dep_list','ImportEmployeeController@dep_list');
	Route::get('import_employee/sec_list','ImportEmployeeController@sec_list');
	Route::get('import_employee/auto_position_name','ImportEmployeeController@auto_position_name');
	Route::post('import_employee/auto_employee_name','ImportEmployeeController@auto_employee_name');
	Route::get('import_employee/{emp_code}/role', 'ImportEmployeeController@show_role');
	Route::patch('import_employee/{emp_code}/role', 'ImportEmployeeController@assign_role');
	Route::patch('import_employee/role', 'ImportEmployeeController@batch_role');
	Route::get('import_employee','ImportEmployeeController@index');
	Route::get('import_employee/{emp_id}', 'ImportEmployeeController@show');
	Route::patch('import_employee/{emp_id}', 'ImportEmployeeController@update');
	Route::delete('import_employee/{emp_id}', 'ImportEmployeeController@destroy');
	Route::post('import_employee', 'ImportEmployeeController@import');
	
	// CDS Result //
	Route::get('cds_result/al_list','CDSResultController@al_list');
	Route::get('cds_result/year_list', 'CDSResultController@year_list');
	Route::get('cds_result/month_list', 'CDSResultController@month_list');
	Route::post('cds_result/auto_position_name', 'CDSResultController@auto_position_name');
	Route::post('cds_result/auto_emp_name', 'CDSResultController@auto_emp_name');
	Route::get('cds_result', 'CDSResultController@index');
	Route::post('cds_result/export', 'CDSResultController@export');
	Route::post('cds_result', 'CDSResultController@import');
	Route::delete('cds_result/{cds_result_id}','CDSResultController@destroy');
	
	// Appraisal Data //
	Route::get('appraisal_data/structure_list','AppraisalDataController@structure_list');
	Route::get('appraisal_data/al_list','AppraisalDataController@al_list');
	Route::get('appraisal_data/period_list','AppraisalDataController@period_list');
	Route::get('appraisal_data/appraisal_type_list','AppraisalDataController@appraisal_type_list');
	Route::post('appraisal_data/auto_appraisal_item','AppraisalDataController@auto_appraisal_item');
	Route::post('appraisal_data/auto_emp_name','AppraisalDataController@auto_emp_name');
	Route::post('appraisal_data/calculate_weight','AppraisalDataController@calculate_weight');
	Route::get('appraisal_data','AppraisalDataController@index');
	Route::post('appraisal_data/export','AppraisalDataController@export');
	Route::post('appraisal_data','AppraisalDataController@import');
	
	// Appraisal Assignment //
	Route::get('appraisal_assignment/appraisal_type_list', 'AppraisalAssignmentController@appraisal_type_list');
	Route::post('appraisal_assignment/auto_position_name', 'AppraisalAssignmentController@auto_position_name');
	Route::get('appraisal_assignment/al_list', 'AppraisalAssignmentController@al_list');
	Route::get('appraisal_assignment/period_list', 'AppraisalAssignmentController@period_list');
	Route::get('appraisal_assignment/frequency_list', 'AppraisalAssignmentController@frequency_list');
	Route::post('appraisal_assignment/auto_employee_name', 'AppraisalAssignmentController@auto_employee_name');
	Route::get('appraisal_assignment', 'AppraisalAssignmentController@index');
	Route::get('appraisal_assignment/template', 'AppraisalAssignmentController@assign_template');
	Route::get('appraisal_assignment/new_assign_to', 'AppraisalAssignmentController@new_assign_to');
	Route::get('appraisal_assignment/new_action_to', 'AppraisalAssignmentController@new_action_to');
	Route::get('appraisal_assignment/edit_assign_to', 'AppraisalAssignmentController@edit_assign_to');
	Route::get('appraisal_assignment/edit_action_to', 'AppraisalAssignmentController@edit_action_to');	
	Route::get('appraisal_assignment/{emp_result_id}', 'AppraisalAssignmentController@show');	
	Route::patch('appraisal_assignment/{emp_result_id}', 'AppraisalAssignmentController@update');	
	Route::delete('appraisal_assignment/{emp_result_id}', 'AppraisalAssignmentController@destroy');	
	Route::post('appraisal_assignment', 'AppraisalAssignmentController@store');	
	
	// Appraisal //
	Route::get('appraisal/year_list', 'AppraisalController@year_list');
	Route::get('appraisal/period_list', 'AppraisalController@period_list');
	Route::get('appraisal/al_list', 'AppraisalController@al_list');
	Route::get('appraisal/phase_list/{item_result_id}','AppraisalController@phase_list');
	Route::get('appraisal/auto_org_name','AppraisalController@auto_org_name');
	Route::get('appraisal/auto_position_name','AppraisalController@auto_position_name');
	Route::get('appraisal/auto_employee_name','AppraisalController@auto_employee_name');
	Route::post('appraisal/calculate_weight','AppraisalController@calculate_weight');
	Route::get('appraisal','AppraisalController@index');
	Route::get('appraisal/edit_assign_to', 'AppraisalController@edit_assign_to');
	Route::get('appraisal/edit_action_to', 'AppraisalController@edit_action_to');		
	Route::get('appraisal/{emp_result_id}','AppraisalController@show');	
	Route::patch('appraisal/{emp_result_id}','AppraisalController@update');	
	Route::get('appraisal/action_plan/auto_employee_name','AppraisalController@auto_action_employee_name');
	Route::get('appraisal/action_plan/{item_result_id}','AppraisalController@show_action');
	Route::post('appraisal/action_plan/{item_result_id}','AppraisalController@add_action');	
	Route::patch('appraisal/action_plan/{item_result_id}','AppraisalController@update_action');	
	Route::delete('appraisal/action_plan/{item_result_id}','AppraisalController@delete_action');	
	Route::get('appraisal/reason/{item_result_id}','AppraisalController@list_reason');
	Route::get('appraisal/reason/{item_result_id}/{reason_id}','AppraisalController@show_reason');
	Route::post('appraisal/reason/{item_result_id}','AppraisalController@add_reason');	
	Route::patch('appraisal/reason/{item_result_id}','AppraisalController@update_reason');	
	Route::delete('appraisal/reason/{item_result_id}','AppraisalController@delete_reason');	
	Route::post('appraisal/upload_file/{item_result_id}', 'AppraisalController@appraisal_upload_files');
	Route::get('appraisal/upload_file/{item_result_id}','AppraisalController@upload_files_list');
	Route::get('appraisal/delete_file/{result_doc_id}','AppraisalController@delete_file');
		
	
	// Database Connection //
	Route::get('database_connection', 'DatabaseConnectionController@index');
	Route::get('database_connection/db_type_list', 'DatabaseConnectionController@db_type_list');	
	Route::post('database_connection', 'DatabaseConnectionController@store');
	Route::get('database_connection/{connection_id}', 'DatabaseConnectionController@show');
	Route::patch('database_connection/{connection_id}', 'DatabaseConnectionController@update');
	Route::delete('database_connection/{connection_id}', 'DatabaseConnectionController@destroy');
	
	// System Config //
	Route::get('system_config', 'SystemConfigController@index');
	Route::patch('system_config', 'SystemConfigController@update');
	Route::get('system_config/month_list', 'SystemConfigController@month_list');
	Route::get('system_config/frequency_list', 'SystemConfigController@frequency_list');
	
	// Perspective //
	Route::get('perspective', 'PerspectiveController@index');
	Route::post('perspective', 'PerspectiveController@store');
	Route::get('perspective/{perspective_id}', 'PerspectiveController@show');
	Route::patch('perspective/{perspective_id}', 'PerspectiveController@update');
	Route::delete('perspective/{perspective_id}', 'PerspectiveController@destroy');	
	
	// UOM //
	Route::get('uom', 'UOMController@index');
	Route::post('uom', 'UOMController@store');
	Route::get('uom/{uom_id}', 'UOMController@show');
	Route::patch('uom/{uom_id}', 'UOMController@update');
	Route::delete('uom/{uom_id}', 'UOMController@destroy');		
	
	// Position //
	Route::get('position', 'PositionController@index');
	Route::post('position/auto', 'PositionController@auto');
	Route::post('position', 'PositionController@store');
	Route::post('position/import', 'PositionController@import');
	Route::get('position/{position_id}', 'PositionController@show');
	Route::patch('position/{position_id}', 'PositionController@update');
	Route::delete('position/{position_id}', 'PositionController@destroy');		
	
	// Phase //
	Route::get('phase', 'PhaseController@index');
	Route::post('phase', 'PhaseController@store');
	Route::get('phase/{phase_id}', 'PhaseController@show');
	Route::patch('phase/{phase_id}', 'PhaseController@update');
	Route::delete('phase/{phase_id}', 'PhaseController@destroy');		
	
	// KPI Type //
	Route::get('kpi_type', 'KPITypeController@index');
	Route::post('kpi_type', 'KPITypeController@store');
	Route::get('kpi_type/{kpi_type_id}', 'KPITypeController@show');
	Route::patch('kpi_type/{kpi_type_id}', 'KPITypeController@update');
	Route::delete('kpi_type/{kpi_type_id}', 'KPITypeController@destroy');	

	// Org //
	Route::get('org', 'OrgController@index');
	Route::get('org/parent_list', 'OrgController@parent_list');
	Route::get('org/province_list', 'OrgController@province_list');
	Route::get('org/al_list', 'OrgController@al_list');
	Route::post('org', 'OrgController@store');
	Route::post('org/import', 'OrgController@import');
	Route::post('org/auto_org_name', 'OrgController@auto_org_name');
	Route::get('org/{org_id}', 'OrgController@show');
	Route::patch('org/{org_id}', 'OrgController@update');
	Route::delete('org/{org_id}', 'OrgController@destroy');			
	
	// Appraisal Structure //
	Route::get('appraisal_structure', 'AppraisalStructureController@index');
	Route::get('appraisal_structure/form_list', 'AppraisalStructureController@form_list');
	Route::post('appraisal_structure', 'AppraisalStructureController@store');
	Route::get('appraisal_structure/{structure_id}', 'AppraisalStructureController@show');
	Route::patch('appraisal_structure/{structure_id}', 'AppraisalStructureController@update');
	Route::delete('appraisal_structure/{structure_id}', 'AppraisalStructureController@destroy');
	
	// Threshold Group //
	Route::get('threshold/group', 'ThresholdController@group_list');
	Route::post('threshold/group', 'ThresholdController@add_group');
	Route::get('threshold/group/{threshold_group_id}', 'ThresholdController@show_group');
	Route::patch('threshold/group/{threshold_group_id}', 'ThresholdController@edit_group');
	Route::delete('threshold/group/{threshold_group_id}', 'ThresholdController@delete_group');	
	
	// Threshold //
	Route::get('threshold', 'ThresholdController@index');
	Route::get('threshold/structure_list', 'ThresholdController@structure_list');
	Route::post('threshold', 'ThresholdController@store');
	Route::get('threshold/{threshold_id}', 'ThresholdController@show');
	Route::patch('threshold/{threshold_id}', 'ThresholdController@update');
	Route::delete('threshold/{threshold_id}', 'ThresholdController@destroy');	
	
	// Result Threshold Group //
	Route::get('result_threshold/group', 'ResultThresholdController@group_list');
	Route::post('result_threshold/group', 'ResultThresholdController@add_group');
	Route::get('result_threshold/group/{result_threshold_group_id}', 'ResultThresholdController@show_group');
	Route::patch('result_threshold/group/{result_threshold_group_id}', 'ResultThresholdController@edit_group');
	Route::delete('result_threshold/group/{result_threshold_group_id}', 'ResultThresholdController@delete_group');	
	
	// Result Threshold //
	Route::get('result_threshold', 'ResultThresholdController@index');
	Route::post('result_threshold', 'ResultThresholdController@store');
	Route::get('result_threshold/{result_threshold_id}', 'ResultThresholdController@show');
	Route::patch('result_threshold/{result_threshold_id}', 'ResultThresholdController@update');
	Route::delete('result_threshold/{result_threshold_id}', 'ResultThresholdController@destroy');		
	
	// Appraisal Level //
	Route::get('appraisal_level', 'AppraisalLevelController@index');
	Route::post('appraisal_level', 'AppraisalLevelController@store');
	Route::get('appraisal_level/{level_id}', 'AppraisalLevelController@show');
	Route::patch('appraisal_level/{level_id}', 'AppraisalLevelController@update');
	Route::delete('appraisal_level/{level_id}', 'AppraisalLevelController@destroy');	
	Route::get('appraisal_level/{level_id}/criteria', 'AppraisalLevelController@appraisal_criteria');	
	Route::patch('appraisal_level/{level_id}/criteria', 'AppraisalLevelController@update_criteria');

	// Appraisal Grade //
	Route::get('appraisal_grade', 'AppraisalGradeController@index');
	Route::get('appraisal_grade/al_list', 'AppraisalGradeController@al_list');
	Route::post('appraisal_grade', 'AppraisalGradeController@store');
	Route::get('appraisal_grade/{grade_id}', 'AppraisalGradeController@show');
	Route::patch('appraisal_grade/{grade_id}', 'AppraisalGradeController@update');
	Route::delete('appraisal_grade/{grade_id}', 'AppraisalGradeController@destroy');


	// Axis Mapping //
	Route::get('axis_mapping', 'AxisMappingController@index');
	Route::get('axis_mapping/axis_type_list', 'AxisMappingController@axis_type_list');
	Route::post('axis_mapping', 'AxisMappingController@store');
	Route::get('axis_mapping/{axis_mapping_id}', 'AxisMappingController@show');
	Route::patch('axis_mapping/{axis_mapping_id}', 'AxisMappingController@update');
	Route::delete('axis_mapping/{axis_mapping_id}', 'AxisMappingController@destroy');	

	// Database Type //
	Route::get('database_type', 'DatabaseTypeController@index');
	Route::post('database_type', 'DatabaseTypeController@store');
	Route::get('database_type/{database_type_id}', 'DatabaseTypeController@show');
	Route::patch('database_type/{database_type_id}', 'DatabaseTypeController@update');
	Route::delete('database_type/{database_type_id}', 'DatabaseTypeController@destroy');	

	// Appraisal Stage//
	Route::get('appraisal_stage', 'AppraisalStageController@index');
	Route::get('appraisal_stage/appraisal_type_list', 'AppraisalStageController@appraisal_type_list');
	Route::post('appraisal_stage', 'AppraisalStageController@store');
	Route::get('appraisal_stage/{stage_id}', 'AppraisalStageController@show');
	Route::patch('appraisal_stage/{stage_id}', 'AppraisalStageController@update');
	Route::delete('appraisal_stage/{stage_id}', 'AppraisalStageController@destroy');	

	
	// Appraisal Period //
	Route::get('appraisal_period', 'AppraisalPeriodController@index');
	Route::get('appraisal_period/appraisal_year_list', 'AppraisalPeriodController@appraisal_year_list');
	Route::get('appraisal_period/start_month_list', 'AppraisalPeriodController@start_month_list');
	Route::get('appraisal_period/frequency_list', 'AppraisalPeriodController@frequency_list');
	Route::get('appraisal_period/add_frequency_list', 'AppraisalPeriodController@add_frequency_list');
	Route::post('appraisal_period/auto_desc', 'AppraisalPeriodController@auto_desc');
	Route::post('appraisal_period/create', 'AppraisalPeriodController@create');
	Route::post('appraisal_period', 'AppraisalPeriodController@store');
	Route::get('appraisal_period/{period_id}', 'AppraisalPeriodController@show');
	Route::patch('appraisal_period/{period_id}', 'AppraisalPeriodController@update');
	Route::delete('appraisal_period/{period_id}', 'AppraisalPeriodController@destroy');		
	
	//Dashboard //
	/*Route::get('dashboard/year_list', 'DashboardController@year_list');
	Route::post('dashboard/month_list', 'DashboardController@month_list');
	Route::post('dashboard/balance_scorecard', 'DashboardController@balance_scorecard');
	Route::post('dashboard/monthly_variance', 'DashboardController@monthly_variance');
	Route::post('dashboard/monthly_growth', 'DashboardController@monthly_growth');
	Route::post('dashboard/ytd_monthly_variance', 'DashboardController@ytd_monthly_variance');
	Route::post('dashboard/ytd_monthly_growth', 'DashboardController@ytd_monthly_growth');	
	Route::post('dashboard/emp_list', 'DashboardController@emp_list');*/
	Route::get('dashboard/year_list', 'DashboardController@year_list');
	Route::post('dashboard/period_list', 'DashboardController@period_list');
	Route::get('dashboard/region_list', 'DashboardController@region_list');
	Route::get('dashboard/district_list', 'DashboardController@district_list');
	Route::get('dashboard/appraisal_level', 'DashboardController@appraisal_level');
	Route::post('dashboard/org_list', 'DashboardController@org_list');
	Route::post('dashboard/kpi_map_list', 'DashboardController@kpi_map_list');
	Route::post('dashboard/kpi_list', 'DashboardController@kpi_list');
	Route::post('dashboard/content', 'DashboardController@dashboard_content'); //Post Method
	Route::post('dashboard/all_content', 'DashboardController@all_dashboard_content'); //Post Method
	Route::get('dashboard/kpi_overall', 'DashboardController@kpi_overall');
	Route::get('dashboard/kpi_overall_pie', 'DashboardController@kpi_overall_pie');
	Route::get('dashboard/kpi_overall_bubble', 'DashboardController@kpi_overall_bubble');
	Route::get('dashboard/performance_trend', 'DashboardController@performance_trend');
	Route::get('dashboard/gantt', 'DashboardController@gantt');
	Route::get('dashboard/branch_performance', 'DashboardController@branch_performance');
	Route::get('dashboard/branch_details', 'DashboardController@branch_details');	
	Route::get('dashboard/perspective_details', 'DashboardController@perspective_details');	
	
	//Dashbaord Emp
	Route::get('dashboard_emp/year_list', 'DashboardEmpController@year_list');
	Route::post('dashboard_emp/month_list', 'DashboardEmpController@month_list');
	Route::post('dashboard_emp/result_emp_by_structure', 'DashboardEmpController@result_emp_by_structure');	
	
	//Result Bonus //
	Route::get('result_bonus/appraisal_year', 'ResultBonusController@appraisal_year');
	Route::get('result_bonus/bonus_period', 'ResultBonusController@bonus_period');
	Route::post('result_bonus/result_bonus', 'ResultBonusController@result_bonus');

	//Result Raise Amount //
	Route::get('result_raise_amount/appraisal_year', 'ResultRaiseAmountController@appraisal_year');
	Route::get('result_raise_amount/salary_period', 'ResultRaiseAmountController@salary_period');
	Route::post('result_raise_amount/result_raise_amount', 'ResultRaiseAmountController@result_raise_amount');	
	
	// Mail //
	Route::get('mail/send','MailController@send');
	Route::get('mail/monthly','MailController@monthly');
	
	// Report //
	Route::get('report/usage_log','ReportController@usage_log');
	Route::get('report/al_list','ReportController@al_list');
	
	Route::get('404', ['as' => 'notfound', function () {
		return response()->json(['status' => '404']);
	}]);

	Route::get('405', ['as' => 'notallow', function () {
		return response()->json(['status' => '405']);
	}]);	
});



