<?php

namespace App\Http\Controllers;

use App\BenchmarkData;

use Auth;
use DB;
use Excel;
use Validator;
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
				SELECT year
				FROM benchmark_data
				GROUP BY year
		");

		$quarter = DB::select("
				SELECT quarter
				FROM benchmark_data
				GROUP BY quarter
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
			$s_yr = $data['s_yr'];
			$benchmark = DB::select("
				SELECT kpi_name
				FROM benchmark_data
				WHERE year = '$s_yr'
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
		$data = json_decode($request['datas'], true);
		if($data['s_check']=='Kcsodwdw2iow48')
		{
			$s_yr = $data['s_yr'];
			$s_kpi = $data['s_kpi'];
			$resultArray = [];
			$resultArray['year'] = $s_yr;
			$resultArray['kpi'] = $s_kpi;

			$benchmark = DB::select("
				SELECT quarter,SUM(value) as sum
				FROM benchmark_data
				WHERE year = '$s_yr'
				AND kpi_name = '$s_kpi'
				GROUP BY quarter
				ORDER BY quarter ASC
			");
			$benchmark2 = DB::select("
				SELECT quarter,value,company_code
				FROM benchmark_data
				WHERE year = '$s_yr'
				AND kpi_name = '$s_kpi'
				ORDER BY quarter ASC, FIELD(company_code,'ธ.กรุงเทพ','ธอส.','ธ.ออมสิน','ธ.กสิกรไทย','ธ.กรุงไทย','ธ.ไทยพาณิช',''), value ASC
			");

			$benchmark11 = DB::select("
				SELECT benchmark_id
				FROM benchmark_data
				WHERE year = '$s_yr'
				AND kpi_name = '$s_kpi'
				GROUP BY company_code
			");

			$benchmark_previous = DB::select("
				SELECT year,kpi_name
				FROM benchmark_data
				WHERE year < '$s_yr'
				GROUP BY year
				ORDER BY year DESC
				LIMIT 0,1
			");
			if(empty($benchmark_previous))
			{
				$resultArray['nodata_year_previos'] = "nodata_p";
			}
			else
			{
				foreach($benchmark_previous as $benchmark_previouss)
				{
					$resultArray['year_previos'] = $benchmark_previouss->year;
					$resultArray['kpi_previos'] = $benchmark_previouss->kpi_name;
				}

			}

			$benchmark_next = DB::select("
				SELECT year,kpi_name
				FROM benchmark_data
				WHERE year > '$s_yr'
				GROUP BY year
				ORDER BY year ASC
				LIMIT 0,1
			");
			if(empty($benchmark_next))
			{
				$resultArray['nodata_year_next'] = "nodata_next";
			}
			else
			{
				foreach($benchmark_next as $benchmark_nexts)
				{
					$resultArray['year_next'] = $benchmark_nexts->year;
					$resultArray['kpi_next'] = $benchmark_nexts->kpi_name;
				}
			}


			$count_cv = 0;
			foreach ($benchmark11 as $benchmark11s) {
				$count_cv ++;
			}

			$nc = 0;
			foreach($benchmark2 as $benchmark2s)
			{
				if($nc<$count_cv)
				{
					//$resultArray['colorcompany'][] = $benchmark2s->company_color;
					$resultArray['dataset'][]['seriesname'] = $benchmark2s->company_code;
					$benchmark3 = DB::select("
						SELECT value
						FROM benchmark_data
						WHERE year = '$s_yr'
						AND kpi_name = '$s_kpi'
						AND company_code = '$benchmark2s->company_code'
						ORDER BY quarter ASC, FIELD(company_code,'ธ.กรุงเทพ','ธอส.','ธ.ออมสิน','ธ.กสิกรไทย','ธ.กรุงไทย','ธ.ไทยพาณิช',''), value ASC
					");

				    $bc = 1;
					foreach($benchmark3 as $benchmark3s)
					{
						if($bc==1)
						{
							$resultArray['dataset'][$nc]['data'][]['value'] = $benchmark3s->value;
						}
						else
						{
							$resultArray['dataset'][$nc]['data'][]['value'] = $benchmark3s->value;
						}
						$bc ++;
					}
					$nc ++;
				}
			}

			$tempCategory = [];
			$nll = 0;
			foreach($benchmark as $benchmarks)
			{
				$benchmark_avg = DB::select("
				SELECT benchmark_id
				FROM benchmark_data
				WHERE quarter = '$benchmarks->quarter'
				AND year = '$s_yr'
				AND kpi_name = '$s_kpi'
				");

				$count_avg = 0;
				foreach ($benchmark_avg as $benchmarks_avgs) {
					$count_avg ++;
				}

				$avg_quarter = $benchmarks->sum / $count_avg;
				if($nll==0)
				{
					$resultArray['dataset'][]['seriesname'] = "ค่าเฉลี่ย";
				}

				$resultArray['dataset'][$nc]['data'][]['value'] = number_format($avg_quarter,2);

				$resultArray['category'][$nll]['label'] = $benchmarks->quarter;
				$resultArray['quarter_previos_next'] = $benchmarks->quarter;
				$resultArray['category'][$nll]['link'] = "JavaScript:s_chart_q(".$benchmarks->quarter.")";
				$nll ++;
			}

			$resultForMixed = $resultArray;
			foreach($resultArray['category'] as $value) {
				$value['link'] = str_replace('s_chart_q', 's_chart_month', $value['link']);
				$tempCategory[] = $value;
			}

			$tempStackDataset = [];
			$tempLineDataset = [];
			foreach ($resultForMixed['dataset'] as $value) {
				$stack = $value;
				$line = $value;
				$stack['initiallyHidden'] = "1";
				$line['initiallyHidden'] = "1";
				if ($value['seriesname'] === 'ธอส.') {
					$stack['initiallyHidden'] = "0";
				}
				$line['renderas'] = "line";
				$tempStackDataset[] = $stack;
				$tempLineDataset[] = $line;
			}

			$resultForMixed['category'] = $tempCategory;
			$resultForMixed['dataset'] = array_merge($tempStackDataset, $tempLineDataset);

			return response()->json(compact('resultArray', 'resultForMixed'));
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
							'quarter' => 'required',
							'month' => 'required',
							'kpi_name' => 'required',
							'company_code' => 'required',
							'value' => 'required',
				]);

				if($validator->fails()) {
		            $errors_validator[] = ['row' => $index + 2, 'errors' => $validator->errors()];
		            return response()->json(['status' => 400, 'errors' => $errors_validator]);
		        } else {

					$insert = new BenchmarkData;
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
	                    $errors[] = ['year' => $i['year'], 'quarter' => $i['quarter'], 'month'=>$i['month'], 'kpi_name' => $i['kpi_name'], 'errors' => $e];
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
				SELECT year,quarter,month,kpi_name,company_code,value
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
						$headings = array('year', 'quarter', 'month', 'kpi_name', 'company_code', 'value');
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
