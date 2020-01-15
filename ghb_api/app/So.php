<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class So extends Model
{
    protected $casts = [
        'name' => 'string',
        'abbr' => 'string',
        'color_code' => 'string',
        'is_active' => 'boolean',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected $fillable = [
        'name', 
        'abbr', 
        'color_code',
        'is_active',
        'created_by',
        'updated_by'
    ];

    /* 
     * Get KPIs for this Strategic Objective (SO)
     * 
     * (One-To-Many)
     */
    public function so_kpis()
    {
        return $this->hasMany('App\SoKpi');
    }
}
