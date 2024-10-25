<?php

return [
    //api key for openai
    'openai-api-key' => '',
    //chat gpt model to use (Note: quality of data may depend on model used)
    'ai_model' => 'gpt-4-turbo',
    //required fields for saveing articles
    'required_fields' => [
        'article_id',
        'site_id',
        'preheading',
        'heading',
        'og_title',
        'lead',
        'category',
        'subcategory',
        'article_url',
        'category_url',
        'subcategory_url',
        'tags',
        'published',
        'publish_at',
        'time_created_real',
        'time_updated_real',
        'views',
        'image_orig',
        'image_t',
        'image_f',
        'image_l',
        'image_ig',
        'image_s',
        'image_iff',
        'image_xs',
        'image_kf',
        'image_kfl',
        'image_m',
        'image_pl'
    ],
    //required fields for user table
    'required_fields_for_user' => [
        'user_id',
        'tags',
        'news_recomedation',
        'latest_update',
        'firebase_uid'//used only when we use firebase for signed up users
    ],
    //image dimensions
    'image_dimensions' => [
        'image_orig',
        'image_t',
        'image_f',
        'image_l',
        'image_ig',
        'image_s',
        'image_iff',
        'image_xs',
        'image_kf',
        'image_kfl',
        'image_m',
        'image_pl'
    ],
    //how many days ago do we take the articles
    'days_ago' => 10,
    //table name for articles
    'articles_table_name' => 'articles',
    //table name for categories
    'categories_table_name' => 'categories',
    //table name for websites
    'websites_table_name' => 'websites',
    //table name for publish
    'publish_table_name' => 'publish',
   //article model
   'article_model' => \App\Models\Article::class,
   //for how many days we wait for user to visit website again before we delete it
   'days_delete_user' => 7,
   //for how many days we keep data
   'days_keep_data' => 7,
   //number of tags to consider for recommended articles pool
   'tags_array_length' => 5,
   //used to get tag coefficient (bigger constant => more articles for tags)
   'tags_constant' => 3,
   //number of recommended articles
   'recommended_articles_count' => 10,
   //is publish table beeing used
   'use_publish' => false,
   //site_id for articles: if false, then take first from table (but if site_id_from_public is true, take the one from publish table), else enter site_id
   'site_id' => false,
   //do we read site_id from public table (only works if site_id is false)
   'site_id_from_public' => false,
    //encode article url with salt
    'url_salt' => 'news@recommendations',
];
