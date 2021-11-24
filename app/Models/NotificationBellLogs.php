<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationBellLogs extends Model
{
    use HasFactory;


    protected $table = 'notification_open_logs';
    protected $primaryKey = 'notif_log_id';

    protected $fillable = [];
}
