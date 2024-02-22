<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HourlyStatus extends Model
{
    protected $table = 'status_hourly';
    public $timestamps = true;
    protected $fillable = ['time_of_status', 'total_reads_today', 'reads_for_every_reader', 'memory_usage', 'storage_usage', 'diskstation_use'];
}
