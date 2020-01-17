<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Subtask extends Model
{
    protected $casts = [
        'year_no' => 'double',
        'month_no' => 'string',
        'month_name' => 'string',
        'weight_plan' => 'string',
        'weight_actual' => 'string',
        'budget_plan' => 'string',
        'budget_actual' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected $fillable = [
        'year_no', 
        'month_no', 
        'month_name',
        'weight_plan', 
        'weight_actual',
        'budget_plan', 
        'budget_actual',
        'created_by',
        'updated_by'
    ];

    /* 
     * Get the parent Action Plan
     * 
     * (One-To-Many) (Inverse)
     */
    public function tasks()
    {
        return $this->belongsTo('App\Task');
    }
}
