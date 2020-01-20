<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProjectKpi extends Model
{
    protected $casts = [
        'name' => 'string',
        'uom_id' => 'integer',
        'value_type_id' => 'integer',
        'function_type' => 'integer',
        'is_active' => 'boolean',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected $fillable = [
        'name', 
        'uom_id', 
        'value_type_id',
        'function_type',
        'is_active',
        'created_by',
        'updated_by'
    ];

    /* 
     * Get the parent Projects
     * 
     * (Many-to-Many)
     */
    public function projects()
    {
        return $this->belongsToMany('App\Project')->withTimestamps();
    }

    /* 
     * Get Action Plans for this Project KPI
     * 
     * (One-To-Many)
     */
    public function action_plans()
    {
        return $this->hasMany('App\ActionPlan');
    }

    /* 
     * Get Results
     * 
     * (Polymorphic One-to-Many)
     */
    public function results()
    {
        return $this->morphMany('App\Result', 'mappable');
    }
}
