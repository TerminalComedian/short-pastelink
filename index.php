<?php

$config = [
    'database' => [
        'username' => "username",
        'password' => "password",
        'name' => "pastelink",
        'table' => "pastes",
    ],
    'id_length' => 8,
    'valid_chars' => "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ",
    'url_regex' => '/^https?:\/\/([a-zA-Z0-9\-]+\.)+[a-zA-Z0-9\-]+(\/[^\s]*)?$/',
];

$headers = [
    "content-type" => "text/plain; charset=UTF-8; imeanit=yes",
    "X-Content-Type-Options" => "nosniff",
    "Content-Disposition" => "inline",
    "Access-Control-Allow-Origin" => "*",
    "Access-Control-Allow-Methods" => "GET, POST",
    "Access-Control-Allow-Headers" => "Content-Type",
    "Access-Control-Expose-Headers" => "Content-Type",
    "Access-Control-Max-Age" => 600,
];

$protocol = empty($_SERVER['HTTPS']) ? "http" : "https";
$queryString = $_SERVER['QUERY_STRING'];
$pasteId = preg_replace("/[^a-zA-Z0-9]/", "", $queryString);
$pasteData = $_SERVER["REQUEST_METHOD"] == "POST" ? urldecode(str_replace("+", "%2B", file_get_contents('php://input'))) : false;

function connectToDatabase($config)
{
    try {
        $db = new PDO("mysql:dbname={$config['database']['name']};host=127.0.0.1", $config['database']['username'], $config['database']['password']);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        // Display a error if you choose
        return null;
    }
}

function handleGetRequest($db, $sqlQuery, $config, $queryString, $baseUrl)
{
    try {
        $statement = $db->prepare($sqlQuery);
        $statement->execute([$queryString]);
        $result = $statement->fetchAll();

        if (count($result) == 1) {
            $data = $result[0][0];
            if (preg_match($config['url_regex'], $data) == 1 && substr($queryString, -1) != "!") {
                header("Location: " . trim($data));
            } else {
                // Respond with the HTML output for successful GET requests
                $info = <<<INFO
                <html>
                <head>
                    <title>POST</title>
                </head>
                <body>
<h1> Send a POST request containing any data, we will create a paste link for you, if the data is purely a URL it will automatically forward, you can use ! in URL to prevent this.
                </body>
                </html>
INFO;
                header("Content-Length: " . strlen($info));
                print($info);
            }
        } else {
            header("HTTP/1.0 404 Not Found");
        }
    } catch (PDOException $e) {
        // Respond with a universal JSON error message for database errors
        $errorResponse = json_encode(['error' => 'Our database is currently overwhelmed, please try again later', 'data' => '']);
        header("Content-Type: application/json");
        header("Content-Length: " . strlen($errorResponse));
        print($errorResponse);
    }
}

function handlePostRequest($db, $sqlQuery, $config, $baseUrl, $pasteData)
{
    try {
        $statement = $db->prepare($sqlQuery);
        $statement->execute([$queryString]);
        $generatedId = generateUniqueID($config['id_length'], $config['valid_chars'], $statement);

        $insertStatement = $db->prepare("INSERT INTO `{$config['database']['table']}` (`id`, `data`) VALUES (?, ?)");
        $insertStatement->execute([$generatedId, $pasteData]);

        $output = "$baseUrl?$generatedId";
        // Respond with a universal JSON success message for successful POST requests
        $successResponse = json_encode(['error' => '', 'data' => $output]);
        header("Content-Type: application/json");
        header("Content-Length: " . strlen($successResponse));
        print($successResponse);
    } catch (PDOException $e) {
        // Respond with a universal JSON error message for database errors
        $errorResponse = json_encode(['error' => 'Our database is currently overwhelmed, please try again later', 'data' => '']);
        header("Content-Type: application/json");
        header("Content-Length: " . strlen($errorResponse));
        print($errorResponse);
    }
}

$db = connectToDatabase($config);

if ($db !== null) {
    // Handle requests based on the request method
    switch ($_SERVER["REQUEST_METHOD"]) {
        case "GET":
            if ($pasteId && (strlen($pasteId) > $config['id_length'] || strlen($pasteId) <= 1)) {
                die(header("HTTP/1.0 414 Request-URI Too Long"));
            }
            handleGetRequest($db, $sqlQuery, $config, $pasteId, $baseUrl);
            break;
        case "POST":
            handlePostRequest($db, $sqlQuery, $config, $baseUrl, $pasteData);
            break;
        default:
            // Respond with a universal JSON info message for other requests
            $infoResponse = json_encode(['error' => '', 'data' => '']);
            header("Content-Type: application/json");
            header("Content-Length: " . strlen($infoResponse));
            print($infoResponse);
            break;
    }
} else {
    $maintenanceResponse = json_encode(['error' => 'Maintenance message', 'data' => '']);
    header("Content-Type: application/json");
    header("Content-Length: " . strlen($maintenanceResponse));
    print($maintenanceResponse);
}
?>
