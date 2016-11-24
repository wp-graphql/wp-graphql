<?php
/**
 * Date: 10/24/16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Validator\RequestValidator;


use Youshido\GraphQL\Execution\Request;
use Youshido\GraphQL\Parser\Exception\InvalidRequestException;

class RequestValidator implements RequestValidatorInterface
{

    public function validate(Request $request)
    {
        $this->assertFragmentReferencesValid($request);
        $this->assetFragmentsUsed($request);
        $this->assertAllVariablesExists($request);
        $this->assertAllVariablesUsed($request);
    }

    private function assetFragmentsUsed(Request $request)
    {
        foreach ($request->getFragmentReferences() as $fragmentReference) {
            $request->getFragment($fragmentReference->getName())->setUsed(true);
        }

        foreach ($request->getFragments() as $fragment) {
            if (!$fragment->isUsed()) {
                throw new InvalidRequestException(sprintf('Fragment "%s" not used', $fragment->getName()));
            }
        }
    }

    private function assertFragmentReferencesValid(Request $request)
    {
        foreach ($request->getFragmentReferences() as $fragmentReference) {
            if (!$request->getFragment($fragmentReference->getName())) {
                throw new InvalidRequestException(sprintf('Fragment "%s" not defined in query', $fragmentReference->getName()));
            }
        }
    }

    private function assertAllVariablesExists(Request $request)
    {
        foreach ($request->getVariableReferences() as $variableReference) {
            if (!$variableReference->getVariable()) {
                throw new InvalidRequestException(sprintf('Variable "%s" not exists', $variableReference->getName()));
            }
        }
    }

    private function assertAllVariablesUsed(Request $request)
    {
        foreach ($request->getQueryVariables() as $queryVariable) {
            if (!$queryVariable->isUsed()) {
                throw new InvalidRequestException(sprintf('Variable "%s" not used', $queryVariable->getName()));
            }
        }
    }
}
