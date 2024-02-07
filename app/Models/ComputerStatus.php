<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComputerStatus extends Model
{
    protected $table = 'ComputerStatus'; // Explicitly define the table if it doesn't follow Laravel's naming conventions
    protected $fillable = ['CheckTime', 'RAMUsage', 'StorageUsage']; // Allow mass assignment
}
