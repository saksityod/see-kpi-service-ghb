<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $casts = [
        'value' => 'double',
        'name' => 'string',
        'result' => 'string',
        'responsible' => 'string',
        'description' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected $fillable = [
        'value', 
        'name', 
        'result',
        'responsible', 
        'description',
        'created_by',
        'updated_by'
    ];

    /* 
     * Get the parent Action Plan
     * 
     * (One-To-Many) (Inverse)
     */
    public function action_plan()
    {
        return $this->belongsTo('App\ActionPlan');
    }

    /* 
     * Get subtasks
     * 
     * (One-To-Many)
     */
    public function subtasks()
    {
        return $this->hasMany('App\Subtask');
    }
}
