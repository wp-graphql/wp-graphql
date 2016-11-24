<?php

namespace BlogTest;

use Youshido\GraphQL\Execution\Processor;
use Youshido\GraphQL\Schema\Schema;
use Youshido\GraphQL\Type\Object\ObjectType;


require_once __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/inline-schema.php';
/** @var ObjectType $rootQueryType */

$processor = new Processor(new Schema([
    'query' => $rootQueryType
]));
$payload = '{ latestPost { title(truncated: true), summary } }';

$processor->processPayload($payload);
echo json_encode($processor->getResponseData()) . "\n";
