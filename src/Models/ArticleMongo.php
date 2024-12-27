<?php

namespace Hoks\NewsRecommendation\Models;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class ArticleMongo extends Eloquent {

    protected $connection = 'mongodb';
    protected $collection = 'articles_db';
    protected $fillable;
    protected $dates = ['publish_at'];


    public function __construct(array $attributes = [])
    {
        $this->fillable = config('newsrecommendation.required_fields');
        parent::__construct($attributes);
    }

}
