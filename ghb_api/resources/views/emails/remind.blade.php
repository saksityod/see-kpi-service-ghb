<html>
<head>
<style>
table, th, td {
    border: 1px solid black;
}
th, td {
    padding: 5px;
    text-align: left;
}
</style>
</head>
<body>
<p>To {{$emp_name}}</p>

<p>กรุณาคลิก Link เพื่อไปเพิ่ม Action Plan สำหรับ KPI ที่ต่ำกว่าเกณฑ์</p>

<table>
	<tr>
		<th>Item Name</th>
		<th>Target Value</th>
		<th>Actual Value</th>
		<th>Action Plan</th>
	</tr>
	@foreach ($items as $i)
	<tr>
    <td>{{ $i->item_name }}</td>
		<td>{{$i->target_value}}</td>
		<td>{{$i->actual_value}}</td>
    <td>{{$web_domain}}kpi-result?param_link=email&param_item_result_id={{$i->item_result_id}}</a></td>
	</tr>
	@endforeach
</table>

<p>From SEE-KPI System</p>

</body>
</html>
