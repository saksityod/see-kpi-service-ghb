<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ResultTotal extends Model
{
    protected $casts = [
        'form_type' => 'integer',
        'result_score' => 'double',
        'color_code' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected $fillable = [
        'form_type', 
        'result_score',
        'color_code', 
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
	
	/* 
     * Get the parent KPI (either 'SO KPI' or 'Project KPI')
     * 
     * (Polymorphic One-to-Many)
     */
    public function mappable()
    {
        return $this->morphTo();
    }
}
