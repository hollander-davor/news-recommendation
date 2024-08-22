<?php

namespace Hoks\NewsRecommendation;

use Illuminate\Support\ServiceProvider;
use Hoks\NewsRecommendation\OpenAI;


class NewsRecommendationServiceProvider extends ServiceProvider{

    public function boot(){
        $this->publishes([
            __DIR__.'/config/newsrecommendation.php' => config_path('newsrecommendation.php')
        ],'config');

    }

    public function register(){
        $this->app->bind('OpenAI',function(){
            return new OpenAI();
        });
        $this->mergeConfigFrom(__DIR__.'/config/newsrecommendation.php', 'newsrecommendation');
    }
}
