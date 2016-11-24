<?php
/**
 * Date: 10/24/16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Validator\RequestValidator;


use Youshido\GraphQL\Execution\Request;

interface RequestValidatorInterface
{

    public function validate(Request $request);

}
