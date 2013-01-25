<?php

include ("lib/imageflip.php");

if (!isset($_GET['u'])) {
	header("HTTP/1.1 406");
	exit();
}

$url = urldecode($_GET['u']);
$host_meta = parse_url($url);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HEADER, FALSE);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');

// curl_setopt($ch, CURLOPT_NOBODY, TRUE);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
$response_body = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = strtolower(curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
curl_close($ch);

if ($http_status !== 200) {
	header("HTTP/1.1 {$http_status}");
	echo "Bad $http_status";
	exit();
}

if (eregi("image/[gif|png|jpg|jpeg]", $content_type)) {

	$image = imagecreatefromstring($response_body);

	$x = imagesx($image);
	$y = imagesy($image);

	imageflip($image, 0, 0, $x, $y);

	header("Content-type: image/jpeg");
	imagejpeg($image, null, 1);

	/*
	 header("Content-type: $content_type");

	 switch($content_type) {

	 default :
	 case "image/jpg" :
	 case "image/jpeg" :
	 imagejpeg($image);
	 break;

	 case "image/png" :
	 imagepng($image);
	 break;

	 case "image/gif" :
	 imagegif($image);
	 break;
	 }
	 */

} else if (eregi("[text|application]/[html|xhtml|xml](.*)", $content_type)) {

	$dom = new DOMDocument;
	libxml_use_internal_errors(true);

	$dom -> loadHTML($response_body);
	$xpath = new DOMXPath($dom);
	libxml_clear_errors();

	$doc = $dom -> getElementsByTagName("html") -> item(0);

	// fix images
	$img = $xpath -> query("//img");
	foreach ($img as $i) {
		$src = $i -> getAttribute("src");
		if (substr($src, 0, 4) !== "http") {
			if (substr($src, 0, 1) !== "/")
				$src = "/" . $src;
			$src = "{$host_meta['scheme']}://{$host_meta['host']}{$src}";
		}
		$src = "http://{$_SERVER['HTTP_HOST']}/?u=" . urlencode($src);
		$i -> setAttribute("src", $src);
	}

	// fix links
	$anchors = $xpath -> query("//a");
	foreach ($anchors as $a) {
		$href = $a -> getAttribute("href");
		if (substr($href, 0, 4) !== "http") {
			if (substr($href, 0, 1) !== "/")
				$href = "/" . $href;
			$href = "{$host_meta['scheme']}://{$host_meta['host']}{$href}";
		}
		$href = "http://{$_SERVER['HTTP_HOST']}/?u=" . urlencode($href);
		$a -> setAttribute("href", $href);
	}

	// fix CSS
	$links = $xpath -> query("//link");
	foreach ($links as $link) {
		$href = $link -> getAttribute("href");
		if (substr($href, 0, 4) !== "http") {
			if (substr($href, 0, 1) !== "/")
				$href = "/" . $href;
			$href = "{$host_meta['scheme']}://{$host_meta['host']}{$href}";
		}
		// $href = "http://{$_SERVER['HTTP_HOST']}/?u=" . urlencode($href);
		$link -> setAttribute("href", $href);
	}

	// fix script
	$scripts = $xpath -> query("//script");
	foreach ($scripts as $script) {
		$href = $script -> getAttribute("src");

		// might be inline JS
		if (empty($href))
			continue;

		if (substr($href, 0, 4) !== "http") {
			if (substr($href, 0, 1) !== "/")
				$href = "/" . $href;
			$href = "{$host_meta['scheme']}://{$host_meta['host']}{$href}";
		}
		// $href = "http://{$_SERVER['HTTP_HOST']}/?u=" . urlencode($href);
		$script -> setAttribute("src", $href);
	}

	// flip document with CSS is another option too!
	/*
	 $body = $xpath -> query("//body")->item(0);
	 $style = $body->getAttribute("style");
	 $style = "transform:rotateY(180deg);{$style}";
	 $body->setAttribute("style",$style);
	 */

	// DOCTYPE ?
	$output = $dom -> saveHTML($doc);

	echo $output;

} else {

	header("Content-type: $content_type");
	echo $response_body;

}
