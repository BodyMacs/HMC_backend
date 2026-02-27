<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    use HasFactory;

    protected $guarded = ['id']; // Fillable attributes

    public function services() // Renamed to services for convention but was 'services' in design
    {
        return $this->hasMany(Service::class); // Changed from Service::class to Service::class
    }
}
