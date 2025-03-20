<?php

namespace Akyos\UXGeocoder\Form\Fields;

use Akyos\UXGeocoder\Controller\UXGeocoderController;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UXGeocoderCountryType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'extra_options' => [
                    UXGeocoderController::SEARCH_ENTRY_GETTER_TEXT_TYPE => 'country.name',
                    UXGeocoderController::SEARCH_ENTRY_GETTER_VALUE_TYPE => 'country.name',
                ],
            ])
        ;
    }

    public function getParent()
    {
        return UXGeocoderFieldType::class;
    }
}
