<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComputerStatus extends Model
{
    protected $table = 'ComputerStatus';
    public $timestamps = false;
    protected $fillable = ['CheckTime', 'RAMUsage', 'StorageUsage'];
}
