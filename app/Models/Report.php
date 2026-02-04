<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = ['profile_id', 'input_data', 'report_content', 'esg_score'];

    protected $casts = [
        'input_data' => 'array'
    ];
}
