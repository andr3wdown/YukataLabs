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

        for($p = 1; $p < 20; $p++) {
            $crawler = $client->request('GET', 'https://www.igdb.com/companies?page='.(string)($p));

            $items = $crawler->filter('.main-container .content .col-lg-9 table > tbody tr td a')->each(function ($node) {
                return $node->attr('href');
            });

            $companyData = [];
            foreach($items as $iKey => $item)
            {
                $inCrawl = $client->request('GET', $item);

                $page = [];
                $page['pageUrl'] = $item;

                $pageLocation = $inCrawl->filter('.main-container .content .col-md-9 .panel .col-sm-4 .text-muted')->each(function($node) {
                    return $node->text();
                });
                if(!empty($pageLocation[0]) && strpos($page['pageLocation'], 'Finland') !== false) {
                    $page['pageLocation'] = $pageLocation[0];
                } else {
                    $page['pageLocation'] = "";
                }

                if(!empty($pageLocation[1]) && strpos($page['pageLocation'], 'Finland') !== false) {
                    $page['pageLocation'] = $pageLocation[0];
                } else {
                    $page['pageLocation'] = "";
                }

                if(!empty($pageLocation[2]) && strpos($page['pageLocation'], 'Finland') !== false) {
                    $page['pageLocation'] = $pageLocation[0];
                } else {
                    $page['pageLocation'] = "";
                }

                if (strpos($page['pageLocation'], 'Finland') !== false) {
                    $pageName = $inCrawl->filter('#main-contain main #content-page div div div h1 span')->each(function($node) {
                        return $node->text();
                    });
                    if(!empty($pageName[0])) {
                        $page['pageName'] = $pageName[0];
                    } else {
                        $page['pageName'] = "";
                    }
                    
                    $pageLogo = $inCrawl->filter('div.col-sm-4 img.img-responsive.logo_med')->each(function($node) {
                        return $node->attr('src');
                    });

                    if(!empty($pageLogo[0])) {
                        $page['pageLogo'] = $pageLogo[0];
                    } else {
                        $page['pageLogo'] = "";
                    }

                    $pageSite = $inCrawl->filter('div#content-page.content .btn-group a.btn-default')->each(function ($node) {
                        return $node->attr('href');
                    });

                    if(!empty($pageSite[0])) {
                        $page['pageSite'] = $pageSite[0];
                        $webCrawl = $client->request('GET', $pageSite[0]);

                        $pageDescription = $webCrawl->filter('html head meta[name="description"]')->each(function ($node) {
                            return $node->attr('content');
                        });

                        if(!empty($pageDescription[0])) {
                            $page['pageDescription'] = $pageDescription[0];
                        } else {
                            $page['pageDescription'] = "";
                        }

                    } else {
                        $page['pageSite'] = "";
                        $page['pageDescription'] = "";
                    }
        
                    $companyData[] = $page;

                    $pageData = [];
                    $pageData['info'] = $page;
                    $pageData['feeds'] = [];

                    $slug = strtolower($page['pageName']);
                    $slug = str_replace(' ', '-', $slug);
                    $slug = preg_replace('/[^A-Za-z0-9\-]/', '', $slug);
                    $slug = preg_replace('/-+/', '-', $slug);

                    if(strlen($slug) > 64)
                    {
                        $slug = substr($slug, 0, 64);
                    }

                    $pageData = json_encode($pageData, JSON_PRETTY_PRINT);
                    file_put_contents(storage_path()."/feeds/${slug}.json", stripslashes($pageData));
                }
            }
        }
        

        return response()->json(['companyData' => $companyData]);
    }

    public function getGames($url) 
    {
        $client = \Symfony\Component\Panther\Client::createChromeClient();
        $crawler = $client->request('GET', $url);

        $gamePagination = $inCrawl->filter('.tab-content div[data-react-class="GenericGameList"]')->each(function($node) {
            return $node->attr('data-react-props');
        });

        $gamePagination = json_decode($gamePagination[0], true);
        for($a = 0; $a < $gamePagination['pagination']['pages']; $a++) {
            $gameCrawl = $client->request('GET', 'https://www.igdb.com'.$gamePagination['pagination']['url'].'?rating=desc&page='.(string)($a + 1));

            $games = $gameCrawl->filter('.game-list-container .media')->each(function($node) {
                return $node->html();
            }); 

            foreach($games as $gKey => $game) {
                $gameCrawl = new Crawler($game);

                $gameLink = $gameCrawl->filter('.media-body > a')->each(function($node) {
                    return $node->attr('href');
                });

                if(!empty($gameLink[0])) {
                    $singleGame = $client->request('GET', 'https://www.igdb.com'.$gameLink[0]);
                    
                    $gameName = $singleGame->filter('')->each(function($node) {

                    });

                    $gameDate = $singleGame->filter('')->each(function($node) {

                    });

                    $gameImage = $singleGame->filters('')->each(function($node) {

                    });

                    $gameGenre = $singleGame->filters('')->each(function($node) {

                    });

                    $gamePlatforms = $singleGame->filters('')->each(function($node) {

                    });

                    $gameDescription = $singleGame->filters('')->each(function($node) {

                    });

                    $gameStore = $singleGame->filters('')->each(function($node) {

                    });

                    $gameShots = $singleGame->filters('')->each(function($node) {

                    });

                }
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
