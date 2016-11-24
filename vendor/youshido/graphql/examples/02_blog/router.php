<?php
namespace BlogTest;

header('Access-Control-Allow-Credentials: false', true);
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
    return;
}

use Examples\Blog\Schema\BlogSchema;
use Youshido\GraphQL\Execution\Processor;
use Youshido\GraphQL\Schema\Schema;

require_once __DIR__ . '/schema-bootstrap.php';
/** @var Schema $schema */
$schema = new BlogSchema();

if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] === 'application/json') {
    $rawBody     = file_get_contents('php://input');
    $requestData = json_decode($rawBody ?: '', true);
} else {
    $requestData = $_POST;
}

$payload   = isset($requestData['query']) ? $requestData['query'] : null;
$variables = isset($requestData['variables']) ? $requestData['variables'] : null;

$processor = new Processor($schema);

$response = $processor->processPayload($payload, $variables)->getResponseData();

header('Content-Type: application/json');
echo json_encode($response);
