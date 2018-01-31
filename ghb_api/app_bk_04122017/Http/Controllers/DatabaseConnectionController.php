<?php

namespace App\Http\Controllers;

use App\DatabaseConnection;

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

class DatabaseConnectionController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function index(Request $request)
	{		
		$items = DB::select("
			SELECT a.connection_id, a.connection_name, b.database_type
			FROM database_connection a
			left outer join database_type b
			on a.database_type_id = b.database_type_id
			order by connection_name asc		
		");
		return response()->json($items);
	}
	
	public function db_type_list()
	{
		$items = DB::select("
			select *
			from database_type
			order by database_type asc
		");
		return response()->json($items);
	}
	
	
	public function store(Request $request)
	{
	
		$validator = Validator::make($request->all(), [
			'connection_name' => 'required|max:100|unique:database_connection',
			'database_type_id' => 'required|integer',
			'ip_address' => 'required|max:100',
			'database_name' => 'required|max:100',
			'user_name' => 'required|max:100',
			'password' => 'required|max:100'
		]);

		if ($validator->fails()) {
			return response()->json(['status' => 400, 'data' => $validator->errors()]);
		} else {
			$item = new DatabaseConnection;
			$item->fill($request->all());
			$item->created_by = Auth::id();
			$item->updated_by = Auth::id();
			$item->save();
		}
	
		return response()->json(['status' => 200, 'data' => $item]);	
	}
	
	public function show($connection_id)
	{
		try {
			$item = DatabaseConnection::findOrFail($connection_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Database Connection not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $connection_id)
	{
		try {
			$item = DatabaseConnection::findOrFail($connection_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Database Connection not found.']);
		}
		
		$validator = Validator::make($request->all(), [
			'connection_name' => 'required|max:100|unique:database_connection,connection_name,' . $connection_id . ',connection_id',
			'database_type_id' => 'required|integer',
			'ip_address' => 'required|max:100',
			'database_name' => 'required|max:100',
			'user_name' => 'required|max:100',
			'password' => 'required|max:100'
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
	
	public function destroy($connection_id)
	{
		try {
			$item = DatabaseConnection::findOrFail($connection_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Database Connection not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Database Connection is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
