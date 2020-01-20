<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SoKpi extends Model
{
    protected $casts = [
        'name' => 'string',
        'item_id' => 'integer',
        'perspective_criteria_id' => 'int',
        'uom_id' => 'integer',
        'value_type_id' => 'integer',
        'function_type' => 'integer',
        'is_active' => 'boolean',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected $fillable = [
        'name', 
        'item_id', 
        'perspective_criteria_id',
        'uom_id',
        'value_type_id', 
        'function_type',
        'is_active',
        'created_by',
        'updated_by'
    ];

    /* 
     * Get the parent SO (Strategic Objective)
     * 
     * (One-To-Many) (Inverse)
     */
    public function so()
    {
        return $this->belongsTo('App\So');
    }

    /* 
     * Get the Projects that associates with this KPI
     * 
     * (Many-to-Many)
     */
    public function projects()
    {
        return $this->belongsToMany('App\Project')->withTimestamps();
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
