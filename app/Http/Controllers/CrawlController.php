<?php

namespace App\Http\Controllers;

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

    public function getFeed($feed)
    {
        $data = storage_path() . "/feeds/${feed}.json";
        $data = json_decode(file_get_contents($data), true);

        $result = [];

        $client = \Symfony\Component\Panther\Client::createChromeClient();
        $crawler = $client->request('GET', $data['info']['url']);

        $items = $crawler->filter($data['feed']['container'])->each(function ($node) {
            return $node->html();
        });

        $feedData = [];
        foreach($items as $iKey => $item)
        {
            $crawler = \Symfony\Component\Panther\DomCrawler\Crawler($item);
            
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
