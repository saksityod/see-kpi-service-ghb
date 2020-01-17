<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ResultMonth extends Model
{
    protected $casts = [
        'year_no' => 'double',
        'month_no' => 'double',
        'month_name' => 'double',
        'value_forecast' => 'double',
        'value_actual' => 'double',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected $fillable = [
        'year_no', 
        'month_no',
        'month_name', 
        'value_forecast',
        'value_actual', 
        'created_by',
        'updated_by'
    ];

    /* 
     * Get the parent Result
     * 
     * (One-To-Many) (Inverse)
     */
    public function result()
    {
        return $this->belongsTo('App\Result');
    }
}
