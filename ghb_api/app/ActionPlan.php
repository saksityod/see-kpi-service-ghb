<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ActionPlan extends Model
{
    protected $casts = [
        'result_text' => 'string',
        'forecast_text' => 'string',
        'summary_text' => 'string',
        'problem_text' => 'string',
        'solution_text' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected $fillable = [
        'result_text', 
        'forecast_text', 
        'summary_text',
        'problem_text', 
        'solution_text',
        'created_by',
        'updated_by'
    ];
    
    /* 
     * Get the parent Project
     * 
     * (One-To-Many) (Inverse)
     */
    public function project()
    {
        return $this->belongsTo('App\Project');
    }

    /* 
     * Get the parent Project KPI
     * 
     * (One-To-Many) (Inverse)
     */
    public function project_kpi()
    {
        return $this->belongsTo('App\ProjectKpi');
    }

    /* 
     * Get Tasks for this action plan
     * 
     * (One-To-Many)
     */
    public function tasks()
    {
        return $this->hasMany('App\Task');
    }
}
