<?php

/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 8/22/15
 * Time: 12:33 PM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NilPortugues\Symfony\JsonApiBundle\Serializer;

use NilPortugues\Api\JsonApi\JsonApiTransformer;
use NilPortugues\Api\Mapping\Mapping;
use ReflectionClass;
use RuntimeException;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class JsonApiSerializer
 * @package NilPortugues\Symfony\JsonApiBundle\Serializer
 */
class JsonApiSerializer extends \NilPortugues\Api\JsonApi\JsonApiSerializer
{
    /**
     * @param JsonApiTransformer $transformer
     * @param RouterInterface    $router
     */
    public function __construct(JsonApiTransformer $transformer, RouterInterface $router)
    {
        $this->mapUrls($transformer, $router);

        parent::__construct($transformer);
    }

    /**
     * @param JsonApiTransformer $transformer
     * @param RouterInterface    $router
     */
    private function mapUrls(JsonApiTransformer $transformer, RouterInterface $router)
    {
        $request = Request::createFromGlobals();
        $baseUrl = $request->getSchemeAndHttpHost();

        $reflectionClass = new ReflectionClass($transformer);
        $reflectionProperty = $reflectionClass->getProperty('mappings');
        $reflectionProperty->setAccessible(true);
        $mappings = $reflectionProperty->getValue($transformer);

        foreach ($mappings as &$mapping) {
            $mappingClass = new ReflectionClass($mapping);

            $this->setUrlWithReflection($router, $mapping, $mappingClass, 'resourceUrlPattern', $baseUrl);
            $this->setUrlWithReflection($router, $mapping, $mappingClass, 'selfUrl', $baseUrl);

            $mappingProperty = $mappingClass->getProperty('otherUrls');
            $mappingProperty->setAccessible(true);
            $otherUrls = $mappingProperty->getValue($mapping);
            if (empty($otherUrls)) {
                $otherUrls = [];
            }
            foreach ($otherUrls as &$url) {
                $url = $this->getUrlPattern($router, $url, $baseUrl);
            }
            $mappingProperty->setValue($mapping, $otherUrls);

            $mappingProperty = $mappingClass->getProperty('relationshipSelfUrl');
            $mappingProperty->setAccessible(true);
            $relationshipSelfUrl = $mappingProperty->getValue($mapping);
            if (empty($relationshipSelfUrl)) {
                $relationshipSelfUrl = [];
            }
            foreach ($relationshipSelfUrl as &$urlMember) {
                foreach ($urlMember as &$url) {
                    $url = $this->getUrlPattern($router, $url, $baseUrl);
                }
            }
            $mappingProperty->setValue($mapping, $relationshipSelfUrl);
        }

        $reflectionProperty->setValue($transformer, $mappings);
    }

    /**
     * @param RouterInterface $router
     * @param Mapping         $mapping
     * @param ReflectionClass $mappingClass
     * @param string          $property
     */
    private function setUrlWithReflection(RouterInterface $router, Mapping $mapping, ReflectionClass $mappingClass, $property, $baseUrl)
    {
        $mappingProperty = $mappingClass->getProperty($property);
        $mappingProperty->setAccessible(true);
        $value = $mappingProperty->getValue($mapping);
        $value = $this->getUrlPattern($router, $value, $baseUrl);
        $mappingProperty->setValue($mapping, $value);
    }

    /**
     * @param RouterInterface $router
     * @param string          $routeNameFromMappingFile
     *
     * @return mixed
     *
     * @throws RuntimeException
     */
    private function getUrlPattern(RouterInterface $router, $routeNameFromMappingFile, $baseUrl)
    {
        if (!empty($routeNameFromMappingFile)) {
            try {
                $route = $router->getRouteCollection()->get($routeNameFromMappingFile);
                if (empty($route)) {
                    throw new Exception();
                }
            } catch (Exception $e) {
                throw new RuntimeException(
                   \sprintf('Route \'%s\' has not been defined as a Symfony route.', $routeNameFromMappingFile)
               );
            }

            \preg_match_all('/{(.*?)}/', $route->getPath(), $matches);

            $pattern = [];
            if (!empty($matches)) {
                $pattern = \array_combine($matches[1], $matches[0]);
            }

            return $baseUrl.\urldecode($router->generate($routeNameFromMappingFile, $pattern, true));
        }

        return (string) $routeNameFromMappingFile;
    }
}
