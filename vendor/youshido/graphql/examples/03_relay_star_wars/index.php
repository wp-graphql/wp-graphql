<?php

namespace Examples\StarWars;

use Youshido\GraphQL\Execution\Processor;
use Youshido\GraphQL\Schema;

require_once __DIR__ . '/schema-bootstrap.php';
/** @var Schema\AbstractSchema $schema */
$schema = new StarWarsRelaySchema();

$processor = new Processor($schema);

$payload = '
            query StarWarsAppHomeRoute($names_0:[String]!) {
              factions(names:$names_0) {
                id,
                ...F2
              }
            }
            fragment F0 on Ship {
              id,
              name
            }
            fragment F1 on Faction {
              id,
              factionId
            }
            fragment F2 on Faction {
              id,
              factionId,
              name,
              _shipsDRnzJ:ships(first:10) {
                edges {
                  node {
                    id,
                    ...F0
                  },
                  cursor
                },
                pageInfo {
                  hasNextPage,
                  hasPreviousPage
                }
              },
              ...F1
            }
        ';

$processor->processPayload($payload, ['names_0' => ['rebels']]);
echo json_encode($processor->getResponseData()) . "\n";
