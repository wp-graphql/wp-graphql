<?php
/**
 * Date: 01.12.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Validator\ConfigValidator;


interface ConfigValidatorInterface
{

    public function validate($data, $rules = [], $allowExtraFields = null);

}
