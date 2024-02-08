<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImageCaptureStatus extends Model
{
    protected $table = 'ImageCaptureStatus';
    public $timestamps = false;
    protected $fillable = ['CaptureTime', 'Status'];
}
