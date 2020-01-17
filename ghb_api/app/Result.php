<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    protected $casts = [
        'description' => 'string',
        'value_target' => 'double',
        'value_forecast' => 'double',
        'value_actual' => 'double',
        'percent_achievement' => 'double',
        'percent_forecast' => 'double',
        'weight_percent' => 'double',
        'weight_score' => 'double',
        'color_code' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected $fillable = [
        'description', 
        'value_target', 
        'value_forecast',
        'value_actual', 
        'percent_achievement',
        'percent_forecast', 
        'weight_percent',
        'weight_score',
        'color_code',
        'created_by',
        'updated_by'
    ];

    /* 
     * Get subtasks
     * 
     * (One-To-Many)
     */
    public function result_months()
    {
        return $this->hasMany('App\ResultMonth');
    }

    /* 
     * Get subtasks
     * 
     * (One-To-Many)
     */
    public function result_totals()
    {
        return $this->hasMany('App\ResultTotal');
    }

    /* 
     * Get KPI (either 'SO KPI' or 'Project KPI')
     * 
     * (Polymorphic One-to-Many)
     */
    public function mappable()
    {
        return $this->morphTo();
    }
}
