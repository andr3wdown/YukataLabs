<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\DomCrawler\Crawler;
use Fusonic\OpenGraph\Consumer;
use stdClass;

class CrawlController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function countJobs($city)
    {
        $path = storage_path() . "/json/${city}.json";
        $json = json_decode(file_get_contents($path), true);

        $jobs = [];
        foreach($json as $jKey => $job) {
            if($job['company'] == "") {
                $jobs[] = $job;
            }
        }

        $count = count($jobs);

        return response()->json(['jobCount' => $count]);
    }

    public function populateCompany($city) 
    {

        $path = storage_path() . "/json/${city}.json";
        $json = json_decode(file_get_contents($path), true);

        $jobs = [];
        foreach($json as $jKey => $job) {
            if($job['company'] == "") {
                $url = parse_url($job['url']);
                
            } else {
                $jobs[] = $job;
            }
        }
    }

    public function getMeta(Request $request)
    {
        $url = $request->query('url');
        if(empty($url)) {
            return response()->json(['error' => 'URL not found.'], 400);
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
            return response()->json(['error' => 'Not a valid URL.'], 400);
        }

        $consumer = new Consumer();
        $object = $consumer->loadUrl($url);

        $preview = [];
        if(empty($object->title)) {
            $preview['info']['title'] = "";
        } else {
            $preview['info']['title'] = $object->title;
        }

        if(empty($object->description)) {
            $preview['info']['description'] = "";
        } else {
            $preview['info']['description'] = $object->description;
        }

        if(empty($object->url)) {
            $preview['info']['url'] = $url;
        } else {
            $preview['info']['url'] = $object->url;
        }

        if(!empty($object->images)) {
            $preview['info']['imageUrl'] = $object->images[0]->url;
        } else {
            $preview['info']['imageUrl'] = 0;
        }

        $slug = strtolower($object->title);
        $slug = str_replace(' ', '-', $slug);
        $slug = preg_replace('/[^A-Za-z0-9\-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);

        if(strlen($slug) > 64)
        {
            $slug = substr($slug, 0, 64);
        }

        $preview['info']['slug'] = $slug;

        $previewData = json_encode($preview, JSON_PRETTY_PRINT);
        file_put_contents(storage_path()."/feeds/${slug}.json", stripslashes($previewData));

        return response()->json(['previewData' => stripslashes($previewData)]);
    }

    public function getCompanies()
    {
        $client = \Symfony\Component\Panther\Client::createChromeClient();
        $crawler = $client->request('GET', 'https://www.igdb.com/companies?page=317');

        $items = $crawler->filter('.main-container .content .col-lg-9 table > tbody tr td a')->each(function ($node) {
            return $node->attr('href');
        });

        $companyData = [];
        foreach($items as $iKey => $item)
        {
            $inCrawl = $client->request('GET', $item);
            /*$location = $inCrawl->filter('.main-container .content .col-md-9 .panel .col-sm-4 .text-muted')->each(function($node) {
                return $node->text();
            });*/
            $page = [];
            $pageName = $crawler->filter('.main-container .content .col-md-9 span[itemprop="name"]')->each(function($node) {
                return $node->text();
            });
            if(!empty($pageName)) {
                $page['pageName'] = $pageName[0];
            } else {
                $page['pageName'] = "";
            }

            $companyData[] = $page;
            
        }

        return response()->json(['data' => $items, 'companyData' => $companyData]);
    }

    public function getFeed($feed)
    {
        $data = storage_path() . "/feeds/${feed}.json";
        $data = json_decode(file_get_contents($data), true);

        $result = [];

        $client = \Symfony\Component\Panther\Client::createChromeClient();
        $crawler = $client->request('GET', $data['info']['url']);
        
        //$items = [];
        $items = $crawler->filter($data['feed']['container'])->each(function ($node) {
            return $node->html();
        });

        $feedData = [];
        foreach($items as $iKey => $item)
        {
            $crawler = new Crawler($item);
            
            foreach($data['feed']['objects'] as $oKey => $obj)
            {
                if($obj['type'] == 'text') {
                    $result = $crawler->filter($obj['source'])->each(function($node) {
                        return $node->text();
                    });
                } else if($obj['type'] == 'media') {
                    $result = $crawler->filter($obj['source'])->each(function ($node) use ($obj) {
                        if($node->attr($obj['mediaSrc']) != NULL)
                        {
                            return $node->attr($obj['mediaSrc']);
                        } else {
                            return $node->attr('src');
                        }
                    });
                } else if($obj['type'] == 'link') {
                    $result = $crawler->filter($obj['soruce'])->each(function ($node) {
                        return $node->attr('href');
                    });
                }
               
                if(!empty($result[0])) {
                    $feedData[$iKey][$obj['key']] = $result[0];
                } else {
                    $feedData[$iKey][$obj['key']] = "";
                }
            }
        }

        return response()->json(['feedData' => $feedData]);
        
    }
}
