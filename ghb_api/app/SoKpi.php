<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SoKpi extends Model
{
    protected $casts = [
        'name' => 'string',
        'item_id' => 'int',
        'uom_id' => 'int',
        'value_type_id' => 'int',
        'function_type' => 'int',
        'is_active' => 'boolean',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected $fillable = [
        'name', 
        'item_id', 
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
    public function sos()
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
        return $this->belongsToMany('App\Project');
    }
}
