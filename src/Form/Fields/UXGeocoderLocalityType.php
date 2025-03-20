<?php

namespace Akyos\UXGeocoder\Form\Fields;

use Akyos\UXGeocoder\Controller\UXGeocoderController;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UXGeocoderLocalityType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'extra_options' => [
                    UXGeocoderController::SEARCH_ENTRY_GETTER_TEXT_TYPE => 'locality',
                    UXGeocoderController::SEARCH_ENTRY_GETTER_VALUE_TYPE => 'locality',
                ],
            ])
        ;
    }

    public function getParent()
    {
        return UXGeocoderFieldType::class;
    }
}
