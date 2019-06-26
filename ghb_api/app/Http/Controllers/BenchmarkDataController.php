<?php

namespace App\Http\Controllers;

use App\BenchmarkData;

use Auth;
use DB;
use Excel;
use Validator;
use Log;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
//use Illuminate\Pagination\LengthAwarePaginator;
//use Illuminate\Database\Eloquent\ModelNotFoundException;

class BenchmarkDataController extends Controller
{

	public function __construct()
	{
		$this->middleware('jwt.auth', ['except' => ['download_benchmark']]);
	}

	public function select_list_search(Request $request)
	{
		//header('Access-Control-Allow-Origin: *');
		$resultArray = [];
		$year = DB::select("
			SELECT DISTINCT year
			FROM benchmark_data
		");

		$quarter = DB::select("
			SELECT DISTINCT quarter
			FROM benchmark_data
		");

		$resultArray['year'] = $year;
		$resultArray['quarter'] = $quarter;

		if(in_array(null,$resultArray))
		{
			$resultArray['nodata'] = 'nodata';
			return response()->json($resultArray);
		}
		else
		{
			return response()->json($resultArray);
		}
	}

	public function search_quarter(Request $request)
	{
		//header('Access-Control-Allow-Origin: *');
		$data = json_decode($request['datas'], true);
		if($data['s_check']=='wdsdwokd@wkdo')
		{
			$resultArray = [];
			$s_yr = $data['s_yr'];
			$benchmark = DB::select("
				SELECT quarter
				FROM benchmark_data
				WHERE year = '$s_yr'
				GROUP BY quarter
			");

			$resultArray['quarter'] = $benchmark;

			if(in_array(null,$resultArray))
			{
				$resultArray['nodata'] = 'nodata';
				return response()->json($resultArray);
			}
			else
			{
				return response()->json($resultArray);
			}
		}
	}

	public function search_kpi(Request $request)
	{
		//header('Access-Control-Allow-Origin: *');
		$data = json_decode($request['datas'], true);
		if($data['s_check']=='wdsdwaswdokd')
		{
			$resultArray = [];
			$s_yr1 = $data['s_yr1'];
			$s_yr2 = $data['s_yr2'];
			$s_type = $data['s_type'];

			$benchmark = DB::select("
				SELECT kpi_name
				FROM benchmark_data
				WHERE year BETWEEN '$s_yr1' AND '$s_yr2'
				AND type = '$s_type'
				GROUP BY kpi_name
				ORDER BY kpi_name asc
			");

			$resultArray['kpi'] = $benchmark;

			if(in_array(null,$resultArray))
			{
				$resultArray['nodata'] = 'nodata';
				return response()->json($resultArray);
			}
			else
			{
				return response()->json($resultArray);
			}
		}
	}

	public function select_list_search_q(Request $request)
	{
		//header('Access-Control-Allow-Origin: *');
		$resultArray = [];
		$year = DB::select("
				SELECT year
				FROM benchmark_data
				GROUP BY year
		");

		$kpi = DB::select("
				SELECT kpi_name
				FROM benchmark_data
				GROUP BY kpi_name
		");
		
		$type = array('Year', 'Quarter', 'Month');
		// $quarter = array('Q1', 'Q2', 'Q3', 'Q4');
		// $month = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
		
		$resultArray['type'] = $type; 
		// $resultArray['quarter'] = $quarter;
		// $resultArray['month'] = $month;
		$resultArray['year'] = $year;
		$resultArray['kpi'] = $kpi;

		if(in_array(null,$resultArray))
		{
			$resultArray['nodata'] = 'nodata';
			return response()->json($resultArray);
		}
		else
		{
			return response()->json($resultArray);
		}
	}

	public function search_benchmark(Request $request)
	{
		//header('Access-Control-Allow-Origin: *');
		$data = json_decode($request['datas'], true);
		if($data['s_check']=='Kcsodiow48')
		{
			$s_yr = $data['s_yr'];
			$s_qt = $data['s_qt'];
			$benchmark = DB::select("
				SELECT *
				FROM benchmark_data
				WHERE year = '$s_yr'
				AND quarter = '$s_qt'
			");
			return response()->json($benchmark);
		}
	}

	public function search_chart(Request $request)
	{
		//header('Access-Control-Allow-Origin: *');
		// return $request['datas'];
		$data = $request['datas'];
		if($data['s_check']=='Kcsodwdw2iow48')
		{
			$s_yr1 = $data['s_yr1'];
			$s_yr2 = $data['s_yr2'];
			$s_type = strtolower($data['s_type']);
			$s_kpi = $data['s_kpi'];
			$resultArray = [];

			$listYear = [];
			for($i = (int) $s_yr1; $i <= (int) $s_yr2; $i++ ) {
				$listYear[] = $i;
			}

			$sumByGroup = [];
			$companies = [];
			$records = [];

			switch ($s_type) {
				case 'year': {
					$records = DB::select("
						SELECT company_code, value, year
						FROM benchmark_data
						WHERE year BETWEEN '$s_yr1' AND '$s_yr2'
						AND kpi_name = '$s_kpi' 
						AND type = '$s_type'
						ORDER BY year,
						FIELD( company_code, 'ธอส', 'ธ.กสิกรไทย', 'ธ.กรุงเทพ', 'ธ.ไทยพาาณิช', 'ธ.ออมสิน', 'ธ.กรุงไทย', 'อื่นๆ', '' )
					");

					$category = [];
					$sumCount = [];

					foreach ($listYear as $year) {
						$category[] = ['label'=>(string) $year];
					}

					foreach ($records as $record) {
						if (!isset($sumByGroup[$record->year])) {
							$sumByGroup[$record->year] = 0; 
							$sumCount[$record->year] = 0;
						}
						$sumByGroup[$record->year] += (float) $record->value;
						$sumCount[$record->year] += 1;

						if (!in_array($record->company_code, $companies)) {
							$companies[] = $record->company_code;
						}
					}

					$resultItem = [];
					$resultItem['year'] = "$s_yr1 - $s_yr2";
					$resultItem['kpi'] = $s_kpi;
					$resultItem['category'] = $category;
					$resultItem['dataset'] = [];
					$resultItem['type'] = $data['s_type'];
					$tempLineDataset = [];
					$average = [];
					foreach ($companies as $company) {
						$dataItem['seriesname'] = $company;
						$dataItem['initiallyHidden'] = $company === 'ธอส.' ? '0' : '1';
						$dataItem['data'] = [];
						foreach ($records as $record) {
							foreach ($listYear as $yearIndex => $year) {
								if ($record->year === $year && $record->company_code === $company) {
									if(!isset($dataItem['data'][$yearIndex]['value'])) {
										$dataItem['data'][$yearIndex]['value'] = 0;
									}
									$dataItem['data'][$yearIndex]['value'] += (float) $record->value;
								} else {
									if (!isset($dataItem['data'][$yearIndex])) {
										$dataItem['data'][$yearIndex] = ['value'=>0];
									}
								}
							}
						}
						$resultItem['dataset'][] = $dataItem;
					}
					$resultArray[] = $resultItem;
					$average['seriesname'] = 'ค่าเฉลี่ย';
					$average['initiallyHidden'] = '1';
					$average['data'] = [];
					foreach($listYear as $year) {
						if (isset($sumByGroup[$year])) {
							$average['data'][] = ['value'=>$sumByGroup[$year]/$sumCount[$year]];
						} else {
							$average['data'][] = ['value'=>0];
						}
					}
					$resultArray[0]['dataset'][] = $average;
					foreach ($resultArray[0]['dataset'] as $data) {
						$data['renderas'] = 'line';
						$data['initiallyHidden'] = '1';
						$tempLineDataset[] = $data;
					}
					$resultArray[0]['dataset'] = array_merge($resultArray[0]['dataset'], $tempLineDataset);
					break;
				}
				case 'quarter': {
					$records = DB::select("
						SELECT company_code, value, quarter, year 
						FROM benchmark_data
						WHERE year BETWEEN '$s_yr1' AND '$s_yr2'
						AND kpi_name = '$s_kpi' AND type = '$s_type'
						ORDER BY year, quarter,
						FIELD( company_code, 'ธอส', 'ธ.กสิกรไทย', 'ธ.กรุงเทพ', 'ธ.ไทยพาณิช', 'ธ.ออมสิน', 'ธ.กรุงไทย', 'อื่นๆ', '' )
					");

					$quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
					$category = [];
					$sumCount = [];

					foreach ($quarters as $quarter) {
						$category[] = ['label'=>$quarter];
					}

					foreach ($records as $record) {
						if (!isset($sumByGroup[$record->year][$record->quarter])) {
							$sumByGroup[$record->year][$record->quarter] = 0; 
							$sumCount[$record->year][$record->quarter] = 0;
						}
						$sumByGroup[$record->year][$record->quarter] += (float) $record->value;
						$sumCount[$record->year][$record->quarter] += 1;
						
						if (!in_array($record->company_code, $companies)) {
							$companies[] = $record->company_code;
						}
					}

					foreach ($listYear as $yearIndex=>$year) {
						$resultItem = [];
						$resultItem['year'] = $year;
						$resultItem['kpi'] = $s_kpi;
						$resultItem['category'] = $category;
						$resultItem['dataset'] = [];
						$resultItem['type'] = $data['s_type'];
						$tempLineDataset = [];
						$average = [];
						foreach ($companies as $company) {
							$dataItem['seriesname'] = $company;
							$dataItem['initiallyHidden'] = $company === 'ธอส.' ? '0' : '1';
							$dataItem['data'] = [];
							foreach ($records as $record) {
								foreach ($quarters as $quarterIndex => $quarter) {
									if ($record->year === $year && $record->company_code === $company) {
										if(!isset($dataItem['data'][$quarterIndex]['value'])) {
											$dataItem['data'][$quarterIndex]['value'] = 0;
										}
										if ($record->quarter === $quarter) {
											// if(!isset($dataItem['data'][$quarterIndex]['value'])) {
											// 	$dataItem['data'][$quarterIndex]['value'] = 0;
											// }
											$dataItem['data'][$quarterIndex]['value'] += (float) $record->value;
										} else {
											$dataItem['data'][$quarterIndex]['value'] += 0;
										}
									} else {
										if (!isset($dataItem['data'][$quarterIndex])) {
											$dataItem['data'][$quarterIndex] = ['value'=>0];
										}
									}
								}
							}
							$resultItem['dataset'][] = $dataItem;
						}
						$resultArray[] = $resultItem;
						$average['seriesname'] = 'ค่าเฉลี่ย';
						$average['initiallyHidden'] = '1';
						$average['data'] = [];
						foreach($quarters as $quarter) {
							if (isset($sumByGroup[$year][$quarter])) {
								$average['data'][] = ['value'=>$sumByGroup[$year][$quarter]/$sumCount[$year][$quarter]];
							} else {
								$average['data'][] = ['value'=>0];
							}
						}
						$resultArray[$yearIndex]['dataset'][] = $average;
						foreach ($resultArray[$yearIndex]['dataset'] as $stackData) {
							$stackData['renderas'] = 'line';
							$stackData['initiallyHidden'] = '1';
							$tempLineDataset[] = $stackData;
						}
						$resultArray[$yearIndex]['dataset'] = array_merge($resultArray[$yearIndex]['dataset'], $tempLineDataset);
					}
					
					break;
				}
				case 'month': {
					$records = DB::select("
						SELECT company_code, value, month, year
						FROM benchmark_data
						WHERE year BETWEEN '$s_yr1' AND '$s_yr2'
						AND kpi_name = '$s_kpi' AND type = '$s_type'
						ORDER BY year, FIELD(month, 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', ''),
						FIELD( company_code, 'ธอส', 'ธ.กสิกรไทย', 'ธ.กรุงเทพ', 'ธ.ไทยพาณิช', 'ธ.ออมสิน', 'ธ.กรุงไทย', 'อื่นๆ', '' )
					");

					$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
					$category = [];
					$sumCount = [];

					foreach ($months as $month) {
						$category[] = ['label'=>$month];
					}

					foreach ($records as $record) {
						if (!isset($sumByGroup[$record->year][$record->month])) {
							$sumByGroup[$record->year][$record->month] = 0; 
							$sumCount[$record->year][$record->month] = 0;
						}
						$sumByGroup[$record->year][$record->month] += (float) $record->value;
						$sumCount[$record->year][$record->month] += 1;

						if (!in_array($record->company_code, $companies)) {
							$companies[] = $record->company_code;
						}
					}

					foreach ($listYear as $yearIndex=>$year) {
						$resultItem = [];
						$resultItem['year'] = $year;
						$resultItem['kpi'] = $s_kpi;
						$resultItem['category'] = $category;
						$resultItem['dataset'] = [];
						$resultItem['type'] = $data['s_type'];
						$tempLineDataset = [];
						$average = [];
						foreach ($companies as $company) {
							$dataItem['seriesname'] = $company;
							$dataItem['initiallyHidden'] = $company === 'ธอส.' ? '0' : '1';
							$dataItem['data'] = [];
							foreach ($records as $record) {
								foreach ($months as $monthIndex => $month) {
									if ($record->year === $year && $record->company_code === $company) {
										if(!isset($dataItem['data'][$monthIndex]['value'])) {
											$dataItem['data'][$monthIndex]['value'] = 0;
										}
										if ($record->month === $month) {
											$dataItem['data'][$monthIndex]['value'] += (float) $record->value;
										} else {
											$dataItem['data'][$monthIndex]['value'] += 0;
										}
									} else {
										if (!isset($dataItem['data'][$monthIndex])) {
											$dataItem['data'][$monthIndex] = ['value'=>0];
										}
									}
								}
							}
							$resultItem['dataset'][] = $dataItem;
						}
						$resultArray[] = $resultItem;
						$average['seriesname'] = 'ค่าเฉลี่ย';
						$average['initiallyHidden'] = '1';
						$average['data'] = [];
						foreach($months as $month) {
							if (isset($sumByGroup[$year][$month])) {
								$average['data'][] = ['value'=>$sumByGroup[$year][$month]/$sumCount[$year][$month]];
							} else {
								$average['data'][] = ['value'=>0];
							}
						}
						$resultArray[$yearIndex]['dataset'][] = $average;
						foreach ($resultArray[$yearIndex]['dataset'] as $stackData) {
							$stackData['renderas'] = 'line';
							$stackData['initiallyHidden'] = '1';
							$tempLineDataset[] = $stackData;
						}
						$resultArray[$yearIndex]['dataset'] = array_merge($resultArray[$yearIndex]['dataset'], $tempLineDataset);
					}
					break;
				}
				default: {}
			}

			// return response() -> json(compact('resultArray', 'sumByGroup', 'sumCount', 'average'));
			return response() -> json($resultArray);
		}
	}

	public function search_chart_quarter(Request $request){
	  //header('Access-Control-Allow-Origin: *');
	  $data = json_decode($request['datas'], true);
      $jsonResponse = [];

      $s_yr = $data['s_yr'];
	  $s_kpi = $data['s_kpi'];
	  $s_qt = $data['s_q'];
	  $s_yr3 = $s_yr-3;

      // Get data
      $sqlStr = "
        SELECT
			company_code category,
			year series,
			value
		FROM benchmark_data
		WHERE quarter = '{$s_qt}'
		AND year BETWEEN {$s_yr3} AND {$s_yr}
		AND kpi_name = '{$s_kpi}'
		ORDER BY quarter ASC, FIELD(company_code,'ธ.กรุงเทพ','ธอส.','ธ.ออมสิน','ธ.กสิกรไทย','ธ.กรุงไทย','ธ.ไทยพาณิช',''), 
				 year ASC";

  		$sqlResults = DB::select($sqlStr);


      // Generate categories
      $category = [];
      foreach ($sqlResults as $result) {
        if (!in_array($result->category, array_column($category, "label"))) {
          array_push($category, ["label" => $result->category]);
        }
  		}
      $jsonResponse["categories"][] = ["category" => $category];


      // Generate series of dataset
      $seriesData = [];
      foreach ($sqlResults as $series) {
        if (!in_array($series->series, array_column($seriesData, "seriesname"))) {

          // Generate value of series array
          $dataData = [];
          foreach ($sqlResults as $data) {
            if ($series->series == $data->series) {
              array_push($dataData, ["value" => $data->value]);
            }
          }

          // Push series and data to object array
          array_push($seriesData, ["seriesname" => "$series->series", "data" => $dataData]);

        }
      }
      $jsonResponse["dataset"] = $seriesData;

      $jsonResponse["quarter"] = $s_qt;


      return response()->json($jsonResponse);
    }

	public function import_benchmark(Request $request)
	{
		$errors = array();
		$errors_validator = array();
        DB::beginTransaction();
		foreach ($request->file() as $f) {
			$items = Excel::load($f, function($reader){})->get();
			DB::table('benchmark_data')->delete();
			foreach ($items as $index => $i) {
				$validator = Validator::make($i->toArray(), [
							'year' => 'required',
							'type' => 'required',
							'quarter' => 'required_if:type,quarter',
							'month' => 'required_if:type,month',
							'kpi_name' => 'required',
							'company_code' => 'required',
							'value' => 'required',
				]);

				if($validator->fails()) {
		            $errors_validator[] = ['row' => $index + 2, 'errors' => $validator->errors()];
		            return response()->json(['status' => 400, 'errors' => $errors_validator]);
		        } else {
					$insert = new BenchmarkData;
					$insert->type = $i['type'];
					$insert->year = $i['year'];
					$insert->quarter = $i['quarter'];
					$insert->month = $i['month'];
					$insert->kpi_name = $i['kpi_name'];
					$insert->company_code = $i['company_code'];
					$insert->value = $i['value'];
					$insert->created_by = Auth::id();
					$insert->created_dttm = date('Y-m-d H:i:s');
					 try {
	                    $insert->save();
	                } catch (Exception $e) {
	                    $errors[] = ['year' => $i['year'], 'quarter' => $i['quarter'], 'month'=>$i['month'], 'kpi_name' => $i['kpi_name'], 'type' => $i['type'], 'errors' => $e];
	                }
	            }		
			}
		}

		if(empty($errors)) {
			DB::commit();
			$status = 200;
		} else {
			DB::rollback();
			$status = 400;
		}

        return response()->json(['status' => $status, 'errors' => $errors]);
	}

	public function download_benchmark($dCheck)
	{
		if($dCheck=='Swc4w8dQ88Wcd8')
		{
			$benchmark = DB::select("
				SELECT type,year,quarter,month,kpi_name,company_code,value
				FROM benchmark_data
			");

			$resultArray2['download'] = $benchmark;
			$resultArray = json_decode(json_encode($benchmark), true);

			if(in_array(null, $resultArray2))
			{
				return Excel::create('Benchmark', function($excel) use ($resultArray)
				{
					$excel->sheet('Benchmark', function($sheet) use ($resultArray)
				    {
						$headings = array('type', 'year', 'quarter', 'month', 'kpi_name', 'company_code', 'value');
						$sheet->prependRow(1, $headings);
				    });
				})->download('xls');
			}
			else
			{
				return Excel::create('Benchmark', function($excel) use ($resultArray)
				{
					$excel->sheet('Benchmark', function($sheet) use ($resultArray)
				    {
						$sheet->fromArray($resultArray);
				    });
				})->download('xls');
			}
		}
	}

	public function search_chart_month(Request $request){
		$data = json_decode($request['datas'], true);
		$jsonResponse = [];
  
		$s_yr = $data['s_yr'];
		$s_kpi = $data['s_kpi'];
		$s_qt = $data['s_q'];
  
		// Get data
		$sqlStr = "
		  SELECT
			  company_code series,
			  month category,
			  value
		  FROM benchmark_data
		  WHERE quarter = '{$s_qt}'
		  AND year = '{$s_yr}'
		  AND kpi_name = '{$s_kpi}'
		  ORDER BY FIELD(company_code,'ธ.กรุงเทพ','ธอส.','ธ.ออมสิน','ธ.กสิกรไทย','ธ.กรุงไทย','ธ.ไทยพาณิช',''), 
		  		   FIELD(month, 'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec', '')";
  
		$sqlResults = DB::select($sqlStr);
  
		$i_qt = (int) substr($s_qt, 1, 1);
		$months = array('Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');
		$start = 0 + (($i_qt - 1) * 3);

		// Generate categories
		$category = [];
		// $category = array_slice($months, $start, 2);
		foreach (array_slice($months, $start, 3) as $month) {
		  if (!in_array($month, array_column($category, "label"))) {
			array_push($category, ["label" => $month]);
		  }
		}
		$jsonResponse["categories"][] = ["category" => $category];
  
  
		// Generate series of dataset
		$seriesData = [];
		foreach ($sqlResults as $series) {
		  if (!in_array($series->series, array_column($seriesData, "seriesname"))) {
  
			// Generate value of series array
			$dataData = [];
			foreach ($sqlResults as $data) {
			  if ($series->series == $data->series) {
				array_push($dataData, ["value" => $data->value]);
			  }
			}
  
			// Push series and data to object array
			array_push($seriesData, ["seriesname" => "$series->series", "data" => $dataData]);
  
		  }
		}
		$jsonResponse["dataset"] = $seriesData;
  
		$jsonResponse["quarter"] = $s_qt;
  
  
		return response()->json($jsonResponse);
	  }
}
