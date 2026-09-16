<?php

namespace Modules\Custom\MakerBid\Models;

use Illuminate\Database\Eloquent\Model;

class MakerModuleSetting extends Model
{
    protected $table = 'maker_module_settings';

    protected $fillable = ['category', 'payload'];

    protected $casts = [
        'payload' => 'array',
    ];
}
