<?php
/**
 * Date: 07.11.16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Execution;


use Youshido\GraphQL\Execution\Context\ExecutionContextInterface;
use Youshido\GraphQL\Execution\Visitor\AbstractQueryVisitor;
use Youshido\GraphQL\Field\Field;
use Youshido\GraphQL\Field\FieldInterface;
use Youshido\GraphQL\Parser\Ast\Field as FieldAst;
use Youshido\GraphQL\Parser\Ast\FragmentInterface;
use Youshido\GraphQL\Parser\Ast\FragmentReference;
use Youshido\GraphQL\Parser\Ast\Mutation;
use Youshido\GraphQL\Parser\Ast\Query;
use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Union\AbstractUnionType;

class Reducer
{

    /** @var  ExecutionContextInterface */
    private $executionContext;

    /**
     * Apply all of $reducers to this query.  Example reducer operations: checking for maximum query complexity,
     * performing look-ahead query planning, etc.
     *
     * @param ExecutionContextInterface $executionContext
     * @param AbstractQueryVisitor[]    $reducers
     */
    public function reduceQuery(ExecutionContextInterface $executionContext, array $reducers)
    {
        $this->executionContext = $executionContext;
        $schema                 = $executionContext->getSchema();

        foreach ($reducers as $reducer) {
            foreach ($executionContext->getRequest()->getAllOperations() as $operation) {
                $this->doVisit($operation, $operation instanceof Mutation ? $schema->getMutationType() : $schema->getQueryType(), $reducer);
            }
        }
    }

    /**
     * Entry point for the `walkQuery` routine.  Execution bounces between here, where the reducer's ->visit() method
     * is invoked, and `walkQuery` where we send in the scores from the `visit` call.
     *
     * @param Query                $query
     * @param AbstractType         $currentLevelSchema
     * @param AbstractQueryVisitor $reducer
     */
    protected function doVisit(Query $query, $currentLevelSchema, $reducer)
    {
        if (!($currentLevelSchema instanceof AbstractObjectType) || !$currentLevelSchema->hasField($query->getName())) {
            return;
        }

        if ($operationField = $currentLevelSchema->getField($query->getName())) {

            $coroutine = $this->walkQuery($query, $operationField);

            if ($results = $coroutine->current()) {
                $queryCost = 0;
                while ($results) {
                    // initial values come from advancing the generator via ->current, subsequent values come from ->send()
                    list($queryField, $astField, $childCost) = $results;

                    /**
                     * @var Query|FieldAst $queryField
                     * @var Field          $astField
                     */
                    $cost = $reducer->visit($queryField->getKeyValueArguments(), $astField->getConfig(), $childCost);
                    $queryCost += $cost;
                    $results = $coroutine->send($cost);
                }
            }
        }
    }

    /**
     * Coroutine to walk the query and schema in DFS manner (see AbstractQueryVisitor docs for more info) and yield a
     * tuple of (queryNode, schemaNode, childScore)
     *
     * childScore costs are accumulated via values sent into the coroutine.
     *
     * Most of the branching in this function is just to handle the different types in a query: Queries, Unions,
     * Fragments (anonymous and named), and Fields.  The core of the function is simple: recurse until we hit the base
     * case of a Field and yield that back up to the visitor up in `doVisit`.
     *
     * @param Query|Field|FragmentInterface $queryNode
     * @param FieldInterface                $currentLevelAST
     *
     * @return \Generator
     */
    protected function walkQuery($queryNode, FieldInterface $currentLevelAST)
    {
        $childrenScore = 0;
        if (!($queryNode instanceof FieldAst)) {
            foreach ($queryNode->getFields() as $queryField) {
                if ($queryField instanceof FragmentInterface) {
                    if ($queryField instanceof FragmentReference) {
                        $queryField = $this->executionContext->getRequest()->getFragment($queryField->getName());
                    }
                    // the next 7 lines are essentially equivalent to `yield from $this->walkQuery(...)` in PHP7.
                    // for backwards compatibility this is equivalent.
                    // This pattern is repeated multiple times in this function, and unfortunately cannot be extracted or
                    // made less verbose.
                    $gen  = $this->walkQuery($queryField, $currentLevelAST);
                    $next = $gen->current();
                    while ($next) {
                        $received = (yield $next);
                        $childrenScore += (int)$received;
                        $next = $gen->send($received);
                    }
                } else {
                    $fieldType = $currentLevelAST->getType()->getNamedType();
                    if ($fieldType instanceof AbstractUnionType) {
                        foreach ($fieldType->getTypes() as $unionFieldType) {
                            if ($fieldAst = $unionFieldType->getField($queryField->getName())) {
                                $gen  = $this->walkQuery($queryField, $fieldAst);
                                $next = $gen->current();
                                while ($next) {
                                    $received = (yield $next);
                                    $childrenScore += (int)$received;
                                    $next = $gen->send($received);
                                }
                            }
                        }
                    } elseif ($fieldType instanceof AbstractObjectType && $fieldAst = $fieldType->getField($queryField->getName())) {
                        $gen  = $this->walkQuery($queryField, $fieldAst);
                        $next = $gen->current();
                        while ($next) {
                            $received = (yield $next);
                            $childrenScore += (int)$received;
                            $next = $gen->send($received);
                        }
                    }
                }
            }
        }
        // sanity check.  don't yield fragments; they don't contribute to cost
        if ($queryNode instanceof Query || $queryNode instanceof FieldAst) {
            // BASE CASE.  If we're here we're done recursing -
            // this node is either a field, or a query that we've finished recursing into.
            yield [$queryNode, $currentLevelAST, $childrenScore];
        }
    }

}