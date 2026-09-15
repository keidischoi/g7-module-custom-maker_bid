<?php

namespace Modules\Custom\MakerBid\Models;

use Illuminate\Database\Eloquent\Model;

class MakerCompany extends Model
{
    protected $table = 'maker_companies';

    protected $fillable = ['user_id', 'name', 'type', 'status', 'note'];
}
