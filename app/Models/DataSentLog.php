<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataSentLog extends Model
{

    protected $table = 'data_sent_log';


    protected $fillable = ['data_date', 'sent_date', 'data_sent'];

    protected $casts = [
        'data_sent' => 'boolean', // Ensuring data_sent is treated as a boolean
    ];

 
    protected $dates = ['data_date', 'sent_date'];


    public $timestamps = false;  // Disable automatic timestamps
}
