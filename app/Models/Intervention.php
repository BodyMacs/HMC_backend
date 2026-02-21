<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Intervention extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function provider()
    {
        return $this->hasOneThrough(User::class, Service::class, 'id', 'id', 'service_id', 'provider_id');
    }
}
