<?php
	header('content-type: application/json');

	// parse() function

	function getGplusShares($url)
	{
		$buttonUrl = sprintf('https://plusone.google.com/u/0/_/+1/fastbutton?url=%s', urlencode($url));
		//$htmlData  = file_get_contents($buttonUrl);
		$htmlData  = parse($buttonUrl);
			
		@preg_match_all('#{c: (.*?),#si', $htmlData, $matches);
		$ret = isset($matches[1][0]) && strlen($matches[1][0]) > 0 ? trim($matches[1][0]) : 0;
		if(0 != $ret) {
			$ret = str_replace('.0', '', $ret);
		}
	
		return ($ret);
	}
	
	function get_counter_number_odnoklassniki( $url ) {
		$CHECK_URL_PREFIX = 'http://www.odnoklassniki.ru/dk?st.cmd=extLike&uid=odklcnt0&ref=';
	
		$check_url = $CHECK_URL_PREFIX . $url;
	
		$data   = parse( $check_url );
		$shares = array();
	
		preg_match( '/^ODKL\.updateCount\(\'odklcnt0\',\'(\d+)\'\);$/i', $data, $shares );
	
		return (int)$shares[ 1 ];
	}
	
	function getRedditScore($url) {
		$reddit_url = 'http://www.reddit.com/api/info.json?url='.$url;
		$format = "json";
		$score = $ups = $downs = 0; //initialize
		
		/* action */
		$content = parse( $reddit_url );
		if($content) {
			if($format == 'json') {
				$json = json_decode($content,true);
				foreach($json['data']['children'] as $child) { // we want all children for this example
					$ups+= (int) $child['data']['ups'];
					$downs+= (int) $child['data']['downs'];
					//$score+= (int) $child['data']['score']; //if you just want to grab the score directly
				}
				$score = $ups - $downs;
			}
		}
		
		return $score;
	}
	
	function get_counter_number__vk( $url ) {
		$CHECK_URL_PREFIX = 'http://vk.com/share.php?act=count&url=';
	
		$check_url = $CHECK_URL_PREFIX . $url;
	
		$data   = parse( $check_url );
		$shares = array();
	
		preg_match( '/^VK\.Share\.count\(\d, (\d+)\);$/i', $data, $shares );
	
		return $shares[ 1 ];
	}
	
	
	function parse( $encUrl ) {

		$options = array(
			CURLOPT_RETURNTRANSFER	=> true, 	// return web page
			CURLOPT_HEADER 			=> false, 	// don't return headers
			//CURLOPT_FOLLOWLOCATION	=> true, 	// follow redirects
			CURLOPT_ENCODING	 	=> "", 		// handle all encodings
			CURLOPT_USERAGENT	 	=> 'essb', 	// who am i
			CURLOPT_AUTOREFERER 	=> true, 	// set referer on redirect
			CURLOPT_CONNECTTIMEOUT 	=> 5, 		// timeout on connect
			CURLOPT_TIMEOUT 		=> 10, 		// timeout on response
			CURLOPT_MAXREDIRS 		=> 3, 		// stop after 3 redirects
			CURLOPT_SSL_VERIFYHOST 	=> 0,
			CURLOPT_SSL_VERIFYPEER 	=> false,
		);
		$ch = curl_init();

		if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) {
			$options[CURLOPT_FOLLOWLOCATION] = true;
		}
		
		$options[CURLOPT_URL] = $encUrl;  
		curl_setopt_array($ch, $options);
		// force usage of v4
		curl_setopt( $ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		

		$content	= curl_exec( $ch );
		$err 		= curl_errno( $ch );
		$errmsg 	= curl_error( $ch );

		curl_close( $ch );

		if ($errmsg != '' || $err != '') {
			print_r($errmsg);
		}
		return $content;
	}


	// get counters

	$json = array('url'=>'','count'=>0);
	$url = $_GET['url'];
	$json['url'] = $url;
	$network = $_GET['nw'];
	

	
	if ( filter_var($url, FILTER_VALIDATE_URL) ) {

		if ( $network == 'google2' ) {
			
			// http://www.helmutgranda.com/2011/11/01/get-a-url-google-count-via-php/
			$content = parse("https://plusone.google.com/u/0/_/+1/fastbutton?url=".$url."&count=true");
			$dom = new DOMDocument;
			$dom->preserveWhiteSpace = false;
			@$dom->loadHTML($content);
			$domxpath = new DOMXPath($dom);
			$newDom = new DOMDocument;
			$newDom->formatOutput = true;

			$filtered = $domxpath->query("//div[@id='aggregateCount']");

			if ( isset( $filtered->item(0)->nodeValue ) ) {
				$cars = array("u00c2", "u00a", 'Ã‚Â ', 'Ã‚Â', 'Ã', ',', 'Â', 'Â ');
				$count = str_replace($cars, '', $filtered->item(0)->nodeValue );
				$json['count'] = preg_replace( '#([0-9])#', '$1', $count );
			}

		}

		elseif ( $network == 'stumble' ) {
			
			$content = parse("http://www.stumbleupon.com/services/1.01/badge.getinfo?url=$url");

			$result = json_decode($content);
			if ( isset($result->result->views )) {
				$json['count'] = $result->result->views;
			}

		}
		
		elseif ($network == "ok") {
			$json['count'] = get_counter_number_odnoklassniki($url);
		}
		
		elseif ($network == "google") {
			$json['count'] = getGplusShares($url);
		
		}
		
		elseif ($network == "reddit") {
			$json['count'] = getRedditScore($url);
		
		}
		
		
		elseif ($network == 'vk') {
			$json['count'] = get_counter_number__vk($url);
		
		}
	}
	echo str_replace('\\/','/',json_encode($json));
?>