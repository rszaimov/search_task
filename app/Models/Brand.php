<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Brand extends Model
{
	use HasFactory;

    protected $fillable = [
    	'name', 
    	'ad_limit'
	];

	public function ads(): HasMany
	{
		return $this->hasMany(Ad::class);
	}
}
