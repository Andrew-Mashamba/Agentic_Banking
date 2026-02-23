<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppRequestLog extends Model
{
    protected $table = 'whatsapp_request_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'phone_number',
        'prompt_version',
        'prompt_hash',
    ];
}
