<?php

namespace Hoks\NewsRecommendation\Models;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class ArticleMongo extends Eloquent {

    protected $connection = 'mongodb';
    protected $collection = 'articles_db';
    protected $fillable;
    protected $casts = ['publish_at' => 'datetime'];

    public function __construct(array $attributes = [])
    {
        $this->fillable = config('newsrecommendation.required_fields');
        parent::__construct($attributes);
    }

}
