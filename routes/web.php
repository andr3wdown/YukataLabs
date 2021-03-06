<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('countJobs/{city}', 'CrawlController@countJobs');
$router->get('getFeed/{feed}', 'CrawlController@getFeed');
$router->get('getMeta', 'CrawlController@getMeta');

$router->get('getCompanies/{rangeOne}/{rangeTwo}', 'CrawlController@getCompanies');

$router->get('detectDead', 'CrawlController@detectDead');
$router->get('countDeadImages', 'CrawlController@countDeadImages');
$router->get('detectNoCover', 'CrawlController@detectNoCover');

$router->get('setRepoFeeds', 'CrawlController@setRepoFeeds');
$router->get('downloadImages', 'CrawlController@downloadImages');