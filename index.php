<?php
	/***********************************************************************************
	*	This script is distributed under the zlib/libpng License:
	*
	*	Copyright (c) 2013 Kevin VUILLEUMIER (kevinvuilleumier.net)
	*
	*	This software is provided 'as-is', without any express or implied warranty.
	*	In no event will the authors be held liable for any damages arising from
	*	the use of this software.
	*
	*	Permission is granted to anyone to use this software for any purpose,
	*	including commercial applications, and to alter it and redistribute it 
	*	freely, subject to the following restrictions:
	*
	*	1. The origin of this software must not be misrepresented; you must not 
	*      claim that you wrote the original software. If you use this software
	*      in a product, an acknowledgment in the product documentation would
	*      be appreciated but is not required.
	*
	*	2. Altered source versions must be plainly marked as such, and must
	*      not be misrepresented as being the original software.
	*
	*	3. This notice may not be removed or altered from any source distribution.
	***********************************************************************************/

	$begin = microtime(true);

	define(USER_AGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:20.0) Gecko/20100101 Firefox/20.0');
	define(ADMIN_EMAIL, 'your@email.com');

	//error_reporting(E_ALL^E_NOTICE);
	set_error_handler('notify_error_handler');
	
	header('Content-Type: text/html; charset=utf-8');
	header('Cache-Control: no-cache, must-revalidate');
	header('Expires: Thu, 01 Jan 1970 01:00:00 +0100');
	
	function explodeHeaders ($headers) {
		$result = array();
		
		$headers = explode("\r\n", $headers);
		
		$i = 0;
	
		foreach ($headers as $header) {
			$fragments = explode(": ", $header);
			
			$result[$i++] = array('name' => $fragments[0], 'value' => $fragments[1]);
		}
		
		return $result;
	}
	
	function addhttp($url) {
		if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
			$url = "http://" . $url;
		}
		
		return $url;
	}
	
	function fetchUrl($uri, &$hlength, $type = 'GET', $timeout = 5) {
		$handle = curl_init();

		curl_setopt($handle, CURLOPT_URL, $uri);
		curl_setopt($handle, CURLOPT_BINARYTRANSFER, false);
		curl_setopt($handle, CURLOPT_HEADER, true);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
		//curl_setopt($handle, CURLOPT_VERBOSE, true);
		
		curl_setopt($handle, CURLOPT_ENCODING, "");
		curl_setopt($handle, CURLOPT_USERAGENT, USER_AGENT);
		curl_setopt($handle, CURLOPT_AUTOREFERER, true);
		
		if ($type == 'HEAD') {
			curl_setopt($handle, CURLOPT_NOBODY, true);
		} else if ($type == 'GET') {
			curl_setopt($handle, CURLOPT_POST, false);
		}

		$response = curl_exec($handle);
		
		if ($response === false) {
			return false;
		}
		
		$hlength  = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
		$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
		
		curl_close($handle);

		// If HTTP response is greater than 400, so it has been an error
		if ($httpCode >= 400) {
			return false;
		}

		return $response;
	}
	
	function notify_error_handler($number, $message, $file, $line, $vars){
		if ($number == E_ERROR || $number == E_STRICT) {
			$email = "
				<p>An error ($number) occurred on line
				<strong>$line</strong> and in the file :<strong>$file.</strong>
				<p>$message</p>";

			$email .= "<pre>".print_r($vars, 1)."</pre>";

			$headers = 'Content-type: text/html; charset=iso-8859-1'."\r\n";

			error_log($email, 1, ADMIN_EMAIL, $headers);
			
			echo "<p class=\"error\">There was an error : \"$message\". Don't worry : the admin has been notified !</p>\n";
		}
		
		return true;
	}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<title>Redirections Tracer</title>
		
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta http-equiv="Content-Language" content="en" />
		<meta name="Author" content="Kevin Vuilleumier" />
		<meta name="Robots" content="index, nofollow" />
		<meta name="keywords" content="redirects, tracer, redirections, http, location" />
		
		<link rel="stylesheet" type="text/css" href="style.css" />
		
		<script type="text/javascript">
			/* <![CDATA[ */
				(function() {
					var s = document.createElement('script'), t = document.getElementsByTagName('script')[0];
					s.type = 'text/javascript';
					s.async = true;
					s.src = 'http://api.flattr.com/js/0.6/load.js?mode=auto';
					t.parentNode.insertBefore(s, t);
				})();
			/* ]]> */
		</script>
	</head>

	<body>
		<h1><a href="./index.php">Redirections Tracer</a></h1>
<?php
	$url = "";
	
	if (isset($_GET['link'])) {
		$url = htmlentities(strtolower($_GET['link']));
	}
	
	echo "<div class=\"box\">\n";
	echo "<p>Trace all HTTP redirections of an URL !</p>
			<form action=\"./index.php\" method=\"get\">
			<table align=\"center\">  
				<tr>
					<td>URL : </td>
					<td><input type=\"text\" name=\"link\" value=\"$url\" size=\"40\" /></td>
					<td><input type=\"submit\" value=\"Go !\" /></td>
				</tr>
			</table>
			<p>Request type:
			<input type=\"radio\" name=\"type\" value=\"GET\">GET
			<input type=\"radio\" name=\"type\" value=\"HEAD\" checked=\"checked\">HEAD</p>
		</form>
	</div>";

	if (isset($_GET['link']) && (strlen($_GET['link']) > 0)) {
		$size = 0;
		$type = 'GET';
		$url = addhttp(strtolower(trim($_GET['link'])));
		
		if (isset($_GET['type']) && $_GET['type'] == 'GET' || $_GET['type'] == 'HEAD') {
			$type = $_GET['type'];
		}
		
		$result = fetchUrl($url, $size, $type);
		
		if ($result != false) {
			$headers = substr($result, 0, $size);
			
			$headers = explodeHeaders($headers);
			
			$results = array();
			$i = 0;
			
			foreach ($headers as $header) {
				$name = strtolower($header['name']);
				$value = strtolower($header['value']);
				
				if ($value == "" && $name != "") {
					$pos = strpos($name, ' ') + 1;
					$code = substr($name, $pos, 3);
					
					$results[$i]['code'] = $code;
					
					if ($code < 300) {
						$results[$i++]['location'] = $url;
					}
				}
				
				if ($name == "location") {
					if ($i == 0) {
						$results[$i]['location'] = $url;
						$results[$i + 1]['location'] = $value;
					} else {
						$results[$i + 1]['location'] = $value;
					}
					$i++;
				}
			}
			
			echo "<div class=\"box\">\n";
			
			foreach ($results as $result) {
				$code = $result['code'];
				
				echo "<p><a href=\"{$result['location']}\"><strong>{$result['location']}</strong></a></p>\n";
				
				if ($code == 301) {
					echo '<img src="301.png" alt="Redirection 301 (Moved Permanently)" /><br/>'."\n";
				} else if ($code == 302) {
					echo '<img src="302.png" alt="Redirection 302 (Moved Temporarily)" /><br/>'."\n";
				}
			}
			
			echo "</div>\n";
		} else {
			echo "<div class=\"box\">\n";
			echo "<p class=\"error\">The given URL is invalid or the website is not reachable!</p>\n";
			echo "</div>\n";
		}
	}
?>
		<div id="footer">
			<p><a class="FlattrButton" style="display:none;" rev="flattr;button:compact;" href="http://tracer.vuilleumier.tv/"></a></p>
			<p>Created by <a href="http://kevinvuilleumier.net">Kevin Vuilleumier</a>. Source code available <a href="https://github.com/vekin03/redirections-tracer">on GitHub</a>.</p>
			<p><?php echo 'Generated in '.round((microtime(true) - $begin), 6).' seconds.'; ?></p>
		</div>

	</body>
</html>