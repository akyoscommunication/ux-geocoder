<?php

namespace Akyos\UXGeocoder\Controller;

use Geocoder\Exception\Exception;
use Geocoder\Location;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\UX\Autocomplete\Controller\EntityAutocompleteController;

#[Route('/_ux-geocoder', name: 'ux.geocoder.')]
final class UXGeocoderController extends AbstractController
{
    const SEARCH_ENTRY_GETTER_TEXT_TYPE = 'getter_text';
    const SEARCH_ENTRY_GETTER_VALUE_TYPE = 'getter_value';
    const SEARCH_ENTRY_DATA_KEY = 'data';

    /**
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws JsonException
     */
    #[Route('/search/{provider}', name: 'search')]
    public function search(Request $request, PropertyAccessorInterface $propertyAccessor, SerializerInterface $serializer, ContainerInterface $container, string $provider): JsonResponse
    {
        $provider = $container->get($provider);

        if (!($provider instanceof Provider)) {
            throw new \InvalidArgumentException('The provider must implement the Provider interface.');
        }

        $extraOptions = null;
        if ($request->query->has(EntityAutocompleteController::EXTRA_OPTIONS)) {
            $extraOptions = json_decode(base64_decode($request->query->get(EntityAutocompleteController::EXTRA_OPTIONS)), true, \JSON_THROW_ON_ERROR, JSON_THROW_ON_ERROR);
        }

        $query = $request->query->get('query');
        if (empty($query)) {
            return new JsonResponse([
                'results' => [],
            ]);
        }

        $geocoderQuery = GeocodeQuery::create($query);

        $getter_text = $extraOptions[self::SEARCH_ENTRY_GETTER_TEXT_TYPE] ?? 'streetName';
        $data = $extraOptions[self::SEARCH_ENTRY_DATA_KEY] ?? [];

        foreach ($data as $key => $value) {
            $geocoderQuery = $geocoderQuery->withData($key, $value);
        }

        $results = [];

        foreach ($provider->geocodeQuery($geocoderQuery)->all() as $location) {
            $text = null;

            foreach (explode('|', $getter_text) as $getter) {
                $text .= $propertyAccessor->getValue($location, $getter) . ', ';
            }

            $results[$text] = [
                'value' => $serializer->serialize($location, 'json'),
                'text' => rtrim($text, ', '),
            ];
        }

        return new JsonResponse([
            'results' => $results,
        ]);
    }
}
