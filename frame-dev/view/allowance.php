<?php

require '../../vendor/autoload.php';

use GuzzleHttp\Client;

$client = new \GuzzleHttp\Client();

$currentURL = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$currentHost = dirname($_SERVER['PHP_SELF']);
$currentFolder = "https://$_SERVER[HTTP_HOST]$currentHost";
$currentFolder = rtrim($currentFolder, '/');
$parentFolder = dirname($currentFolder);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

$imageTimestamp = time();

// Force getting fid from GET
$fid = $_GET['fid'] ?? '';

// Find the position of "/"
$slashPos = strpos($fid, '/');

if ($slashPos !== false) {
    // Remove everything after "/"
    $fid = substr($fid, 0, $slashPos);
}

$image = $parentFolder.'/share/'.$fid.'-all.jpg?ts='.$imageTimestamp;

$image_data = file_get_contents($image);

// Encode image data to Base64
$base64_image = base64_encode($image_data);

$frameImageResult = 'data:image/jpg;base64,'.$base64_image;

echo '
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Rare Stats Pro</title>
        <!-- Fonts -->

        <link rel="preconnect" href="https://fonts.gstatic.com"> 

        <meta property="og:description" content="Get Your Rare Tip" />
        <meta property="og:title" content="Rare Stats" />
        <meta property="og:url" content="'.$parentFolder.'/view" />
        <meta property="og:type" content="article" />
        <meta property="og:locale" content="en-us" />
        <meta property="og:locale:alternate" content="en-us" />
        
        <meta property="og:image" content="'.$frameImageResult.'" />

        <meta name="twitter:card" content="summary" />
        <meta name="twitter:description" content="Get Your Rare Stats" />
        <meta name="twitter:title" content="Rare Stats" />
        <meta name="twitter:site" content="@compez.eth" />
        <meta property="twitter:image" content="'.$parentFolder.'/assets/start-back-24h.jpg" />
        <!-- Frames -->

        <meta property="fc:frame" content="vNext" />
        <meta name="viewport" content="width=device-width"/>
        <meta property="fc:frame:image" content="'.$frameImageResult.'" />
        <meta property="fc:frame:image:aspect_ratio" content="1:1" />
        <meta property="fc:frame:post_url" content="'.$currentFolder.'/view" />
        
        <meta name="fc:frame:button:1" content="🔄 Share Frame">
        <meta name="fc:frame:button:1:action" content="link">
        <meta name="fc:frame:button:1:target" content="https://warpcast.com/~/compose?text=Check your $RARE stats.  %0AFrame by @compez.eth%0A&embeds%5B%5D='.$parentFolder.'/allowance">

        <meta property="fc:frame:button:2" content="Check Me">
        <meta property="fc:frame:button:2:action" content="post">
        <meta property="fc:frame:button:2:target" content="'.$parentFolder.'/allowance">

        </head>
        <body class="font-sans antialiased">
            <div class="bg-gray-100">
                <!-- Page Heading -->
                <!-- Page Content -->
                <main class="">
                    <div class="py-0">
            <div class="w-full bg-white">
                <div class="bg-white overflow-x-hidden shadow-xl">
                    <div class="bg-white p-0 bg-white h-full">
                        <div class="max-w-7xl mx-auto mt-8 lg:px-8 text-center text-17 tracking-wide">
                            <img class="w-full max-w-xl mx-auto" src="'.$frameImageResult.'">
                            <div class="mt-4"><a class="underline text-blue-400" href="'.$parentFolder.'/allowance" target="_blank">Get Your Rare Tip</a></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </main>
        </div>
    </body>
</html>';
?>
