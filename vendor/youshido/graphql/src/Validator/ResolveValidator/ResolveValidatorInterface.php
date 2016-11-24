<?php
/**
 * Date: 01.12.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Validator\ResolveValidator;


use Youshido\GraphQL\Execution\Request;
use Youshido\GraphQL\Field\FieldInterface;
use Youshido\GraphQL\Parser\Ast\Interfaces\FieldInterface as AstFieldInterface;
use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\InterfaceType\AbstractInterfaceType;
use Youshido\GraphQL\Type\Union\AbstractUnionType;

interface ResolveValidatorInterface
{

    public function assetTypeHasField(AbstractType $objectType, AstFieldInterface $ast);

    public function assertValidArguments(FieldInterface $field, AstFieldInterface $query, Request $request);

    public function assertValidResolvedValueForField(FieldInterface $field, $resolvedValue);

    public function assertTypeImplementsInterface(AbstractType $type, AbstractInterfaceType $interface);

    public function assertTypeInUnionTypes(AbstractType $type, AbstractUnionType $unionType);
}
