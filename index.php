<?php
ini_set('display_errors', false);
require_once('config.php');

$cache_timeout = 30;
$cache_name = '_cache';

$cache = unserialize(file_get_contents($cache_name));

function loadCoverLastfm(string $artist, string $album)
{
    $data = json_decode(
        file_get_contents("http://ws.audioscrobbler.com/2.0/?method=album.getinfo&api_key=b58e18fa4eb27f124bace67b01ecac76&artist=" . $artist . "&album=" . $album . "&format=json"), true);
    return array_values(array_slice($data['album']['image'], -1))[0]['#text'];
}

function loadCoverAmazon(string $artist, string $album)
{
    global $amazon_public_key, $amazon_private_key;
    require_once('amazon_api.class.php');
    /** @noinspection PhpUndefinedClassInspection */
    $amazon = new AmazonAPI("de", $amazon_public_key, $amazon_private_key);
    $amazon->Titel = $album;
    $amazon->Artist = $artist;
    /** @noinspection PhpUndefinedMethodInspection */
    $pxml = $amazon->search("Music");

    if (isset($pxml)) {
        if (isset($pxml[0]->MediumImage["URL"])) {
            $http_url = $pxml[0]->MediumImage["URL"];
            $http_url = str_replace('SL160', 'SL250', $http_url);
            $https_url = preg_replace('/http:\/\/(.*?)amazon\.com/', 'https://images-na.ssl-images-amazon.com', $http_url);
            return $https_url;
        }
    }
    return null;
}

function loadCover(?string $full_title): string
{
    $song_title = urlencode(explode(" - ", $full_title)[1]);
    $song_artist = urlencode(explode(" - ", $full_title)[0]);

    $image = loadCoverLastfm($song_artist, $song_title);

    if (empty($image)) {
        $image = loadCoverAmazon($song_artist, $song_title);
    }
    if (empty($image)) {
        $image = 'https://streamdata.radiohitwave.com/api/placeholder-cover.jpg';
    }
    return $image;
}

function loadTitle(): ?string
{
    $data = json_decode(file_get_contents('https://stream-public.radiohitwave.com/status-json.xsl'));
    $title = $data->icestats->source->title;
    return $title;
}

if (!$cache || $cache->timestamp + $cache_timeout < time()) {
    $cache->title = loadTitle();
    $cache->cover = loadCover($cache->title);
    $cache->timestamp = time();
    file_put_contents($cache_name, serialize($cache));
}

header("Access-Control-Allow-Origin: *");
if (isset($_GET['cover'])) {
    if (isset($_GET['string'])) {
        die($cache->cover);
    } elseif (isset($_GET['redirect'])) {
        header('Location: ' . $cache->cover);
    }
} elseif (isset($_GET['title'])) {
    die($cache->title);
}

?>
