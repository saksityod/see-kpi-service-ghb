<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $casts = [
        'name' => 'string',
        'objective' => 'string',
        'org_id' => 'integer',
        'date_start' => 'date',
        'date_end' => 'date',
        'value' => 'string',
        'risk' => 'string',
        'emp_id' => 'integer',
        'is_active' => 'boolean',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected $fillable = [
        'name', 
        'objective', 
        'org_id',
        'date_start', 
        'date_end',
        'value',
        'risk',
        'emp_id',
        'is_active',
        'created_by',
        'updated_by'
    ];

    /* 
     * Get the SO KPIs
     * 
     * (Many-to-Many)
     */
    public function so_kpis()
    {
        return $this->belongsToMany('App\SoKpi');
    }

    /* 
     * Get the Project KPIs
     * 
     * (Many-to-Many)
     */
    public function project_kpis()
    {
        return $this->belongsToMany('App\ProjectKpi');
    }

    /* 
     * Get Action Plans for this project
     * 
     * (One-To-Many)
     */
    public function action_plans()
    {
        return $this->hasMany('App\ActionPlan');
    }
}
