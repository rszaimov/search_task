<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ad extends Model
{
	use HasFactory;

    protected $fillable = [
		'brand_id',
		'title',
		'keywords',
		'country_iso',
		'start_date',
		'relevance_score',
	];

	protected $casts = [
		'start_date' => 'date',
	];

	public function brand(): BelongsTo
	{
		return $this->belongsTo(Brand::class);
	}
}
