<?php

namespace Hoks\NewsRecommendation\Models;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class UserMongo extends Eloquent {

    protected $connection = 'mongodb';
    protected $collection = 'users';
    protected $fillable;
    protected $casts = ['tags' => 'array'];

    public function __construct(array $attributes = [])
    {
        $this->fillable = config('newsrecommendation.required_fields_for_user');
        parent::__construct($attributes);
    }

}
