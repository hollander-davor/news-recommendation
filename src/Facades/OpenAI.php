<?php

namespace Hoks\NewsRecommendations\Facades;

use Illuminate\Support\Facades\Facade;

class OpenAI extends Facade{

    protected static function getFacadeAccessor()
    {
        return 'OpenAI';
    }

}
