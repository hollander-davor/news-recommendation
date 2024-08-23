<?php

namespace Hoks\NewsRecommendations\Models;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class ArticleMongo extends Eloquent {

    protected $connection = 'mongodb';
    protected $collection = 'articles';
    protected $fillable;

    public function __construct(array $attributes = [])
    {
        $this->fillable = config('newsrecommendations.required_fields');
        parent::__construct($attributes);
    }

}
