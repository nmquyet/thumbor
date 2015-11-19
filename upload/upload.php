<?php
if (!function_exists('http_response_code')) {
    function http_response_code($code = NULL) {

        if ($code !== NULL) {

            switch ($code) {
                case 100: $text = 'Continue'; break;
                case 101: $text = 'Switching Protocols'; break;
                case 200: $text = 'OK'; break;
                case 201: $text = 'Created'; break;
                case 202: $text = 'Accepted'; break;
                case 203: $text = 'Non-Authoritative Information'; break;
                case 204: $text = 'No Content'; break;
                case 205: $text = 'Reset Content'; break;
                case 206: $text = 'Partial Content'; break;
                case 300: $text = 'Multiple Choices'; break;
                case 301: $text = 'Moved Permanently'; break;
                case 302: $text = 'Moved Temporarily'; break;
                case 303: $text = 'See Other'; break;
                case 304: $text = 'Not Modified'; break;
                case 305: $text = 'Use Proxy'; break;
                case 400: $text = 'Bad Request'; break;
                case 401: $text = 'Unauthorized'; break;
                case 402: $text = 'Payment Required'; break;
                case 403: $text = 'Forbidden'; break;
                case 404: $text = 'Not Found'; break;
                case 405: $text = 'Method Not Allowed'; break;
                case 406: $text = 'Not Acceptable'; break;
                case 407: $text = 'Proxy Authentication Required'; break;
                case 408: $text = 'Request Time-out'; break;
                case 409: $text = 'Conflict'; break;
                case 410: $text = 'Gone'; break;
                case 411: $text = 'Length Required'; break;
                case 412: $text = 'Precondition Failed'; break;
                case 413: $text = 'Request Entity Too Large'; break;
                case 414: $text = 'Request-URI Too Large'; break;
                case 415: $text = 'Unsupported Media Type'; break;
                case 500: $text = 'Internal Server Error'; break;
                case 501: $text = 'Not Implemented'; break;
                case 502: $text = 'Bad Gateway'; break;
                case 503: $text = 'Service Unavailable'; break;
                case 504: $text = 'Gateway Time-out'; break;
                case 505: $text = 'HTTP Version not supported'; break;
                default:
                    exit('Unknown http status code "' . htmlentities($code) . '"');
                break;
            }

            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

            header($protocol . ' ' . $code . ' ' . $text);

            $GLOBALS['http_response_code'] = $code;

        } else {

            $code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);

        }

        return $code;

    }
}

function thumbor_url($host, $secret, $image_url, $size) {
    $size = str_replace(' ', '', trim($size));
    $thumbnail_path = $size . '/smart/' . $image_url;
    $sign = strtr(base64_encode(hash_hmac('sha1', $thumbnail_path, $secret, true)),'/+', '_-');
    return $host . '/' . $sign . '/' . $thumbnail_path;
}

$config = array(
    'thumbor_host'       => getenv('THUMBOR_HOST') ? getenv('THUMBOR_HOST') : 'http://dockerhost:8000',
    'thumbor_proxy'      => getenv('THUMBOR_PROXY') ? getenv('THUMBOR_PROXY') : 'http://dockerhost:8000',
    'thumbor_secret_key' => getenv('THUMBOR_SECRET_KEY') ? getenv('THUMBOR_SECRET_KEY') : 'MY_SECURE_KEY',
);

// Handle file upload if any
if ( !empty($_FILES['file']) ) {
    $target_dir = 'uploads/';
    $target_file = $target_dir . basename($_FILES['file']['name']);
    // Get thumbnail sizes
    $file_path = $_FILES['file']['tmp_name']; // /Users/qunguyen/Pictures/IMG_02102014_164147.png
    $file_size = $_FILES['file']['size'];     //139602
    $file_mime = $_FILES['file']['type'];     // image/jpeg
    $file_name = $_FILES['file']['name'];     // IMG_02102014_164147.png

    // var_dump($_FILES);

    // Upload received image to thumbor
    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $config['thumbor_host'].'/image'); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, array( 
            'Content-Type: ' . $file_mime,
            'Content-Length: '.$file_size,
            'Slug: '.$file_name,
            'Expect: 100-continue' ));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($file_path));

    $response = curl_exec($ch);
    $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Success upload
    if ($response_code === 201) {
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);

        // Parse resposne header
        $response_headers = array();
        $headerLines = explode("\r\n", $header);
        $index = 0;
        foreach ($headerLines as $headerLine) {
            if ($index++ == 0) {
                continue;
            }

            $headerKeyVal = explode(':', $headerLine);
            if (count($headerKeyVal) === 2) {
                $response_headers[strtolower(trim($headerKeyVal[0]))] = trim($headerKeyVal[1]);
            }
        }

        $location = $response_headers['location'];

        $image_urls = array();
        $origin_image_name = str_replace('/image/', '', $location);
        $thumb_sizes = explode( ',', $_GET['thumb'] );
        $image_urls['original'] = thumbor_url($config['thumbor_proxy'], $config['thumbor_secret_key'], $origin_image_name, '0x0');
        if ( !empty($thumb_sizes) ) {
            foreach ($thumb_sizes as $size) {
                $size = str_replace(' ', '', trim($size));
                $image_urls[$size] = thumbor_url($config['thumbor_proxy'], $config['thumbor_secret_key'], $origin_image_name, $size);
            }
        }

        http_response_code(201);
        echo json_encode($image_urls);
    } 
    // Fail to upload to thumbor
    else {
        trigger_error( 'Cannot upload image to thumbor: response_code='.$response_code, E_USER_ERROR);
        http_response_code($response_code);
    }

    curl_close ($ch);
    exit;
}

?><!DOCTYPE html>
<html>
<head>
<title>Image Upload</title>
<link rel="stylesheet" href="css/dropzone.css">
<link href='http://fonts.googleapis.com/css?family=Roboto:400,300,500,300italic|Inconsolata:400,700' rel='stylesheet' type='text/css'>
<style>
body {
    line-height: 1.4rem;
    font-family: Roboto, "Open Sans", sans-serif;
    font-weight: 300;
    font-size: 20px;
    background: #F3F4F5;
    color: #646C7F;
    text-rendering: optimizeLegibility;
}
#main {
    display: table;
    position: absolute;
    height: 100%;
    width: 100%;
}
#main .container {
    display: table-cell;
    vertical-align: middle;
}
#dropzone-form {
    margin-left: auto;
    margin-right: auto; 
    width: 720px;
    height: 360px;
    display: table;
}
.dropzone {
    border: 2px dashed #0087F7;
    border-radius: 5px;
    background: white;
}
.dropzone .dz-message {
    text-align: center;
    margin: 2em 0;
    font-weight: 400;
    font-size: 20px;
    display:table-cell;
    vertical-align: middle;
}
</style>

<script src="js/dropzone.js"></script>
<script type="text/javascript">
Dropzone.options.dropzoneForm = {
  success: function(file, response) {
    var images = JSON.parse(response);
    console.log(images);

    parent.postMessage(images, "*");
  }
};

/*
On parent frame: 
function receiveMessage(event)
{
  alert(event.data);
}
addEventListener("message", receiveMessage, false);
*/

</script>
</head>
<body>

<div id="main">
    <div class="container">
        <form action="upload.php?thumb=<?php echo htmlentities(@$_GET['thumb'], ENT_QUOTES); ?>"
              class="dropzone" 
              id="dropzone-form"></form>
    </div>
</div>
</body>
</html>
