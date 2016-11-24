<?php
/**
 * Date: 01.12.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Validator\ErrorContainer;


use Youshido\GraphQL\Validator\Exception\DatableResolveException;

trait ErrorContainerTrait
{

    /** @var \Exception[] */
    protected $errors = [];

    public function addError(\Exception $exception)
    {
        $this->errors[] = $exception;

        return $this;
    }

    public function hasErrors()
    {
        return !empty($this->errors);
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function mergeErrors(ErrorContainerInterface $errorContainer)
    {
        if ($errorContainer->hasErrors()) {
            foreach ($errorContainer->getErrors() as $error) {
                $this->addError($error);
            }
        }

        return $this;
    }

    public function getErrorsArray($inGraphQLStyle = true)
    {
        $errors = [];

        foreach ($this->errors as $error) {
            if ($inGraphQLStyle) {
                if ($error instanceof DatableResolveException) {
                    $errors[] = array_merge(
                        ['message' => $error->getMessage()],
                        $error->getData() ?: [],
                        $error->getCode() ? ['code' => $error->getCode()] : []
                    );
                } else {
                    $errors[] = array_merge(
                        ['message' => $error->getMessage()],
                        $error->getCode() ? ['code' => $error->getCode()] : []
                    );
                }
            } else {
                $errors[] = $error->getMessage();
            }
        }

        return $errors;
    }

    public function clearErrors()
    {
        $this->errors = [];

        return $this;
    }

}