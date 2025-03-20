<?php

namespace Akyos\UXGeocoder\Form;

use Akyos\UXGeocoder\Controller\UXGeocoderController;
use Akyos\UXGeocoder\Enum\Syncs;
use Akyos\UXGeocoder\Form\Fields\UXGeocoderCountryType;
use Akyos\UXGeocoder\Form\Fields\UXGeocoderFieldType;
use Akyos\UXGeocoder\Form\Fields\UXGeocoderLocalityType;
use Akyos\UXGeocoder\Form\Fields\UXGeocoderPostalCodeType;
use Akyos\UXGeocoder\Form\Fields\UXGeocoderStreetNameType;
use Geocoder\Model\Address;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

class UXGeocoderType extends AbstractType
{
    private array $fields;
    private Syncs $sync;
    private bool $simple;
    private string $normalizer_class;
    private string $provider;

    const ORDERED_FIELDS = [
        'streetName',
        'postalCode',
        'locality',
        'country',
    ];

    public function __construct(
        private readonly SerializerInterface       $serializer,
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {}

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['map'] = null;

        $lat = $form->get('lat')->getData();
        $lng = $form->get('lng')->getData();

        if ($lat && $lng && $options['show_map']) {
            $view->vars['map'] = (new Map('default'))
                ->addMarker(
                    new Marker(
                        position: new Point(
                            $lat,
                            $lng,
                        ),
                        title: 'Marker',
                    )
                )
                ->fitBoundsToMarkers()
            ;
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->fields = $options['fields'];
        $this->sync = $options['sync'];
        $this->simple = $options['simple'];
        $this->normalizer_class = $options['normalizer_class'];
        $this->provider = $options['provider'];

        $attrFields = [
            'provider' => $this->provider,
        ];

        if ($this->simple) {
            $builder
                ->add('location', UXGeocoderFieldType::class, [
                    'mapped' => false,
                    'extra_options' => [
                        UXGeocoderController::SEARCH_ENTRY_GETTER_TEXT_TYPE => 'streetName|locality|postalCode|country.name',
                        UXGeocoderController::SEARCH_ENTRY_GETTER_VALUE_TYPE => 'streetName',
                    ],
                    'provider' => $this->provider,
                ])
            ;

            $attrFields['row_attr'] = [
                'style' => 'display: none;',
            ];
        }

        if (in_array('streetName', $this->fields, true)) {
            $builder
                ->add('streetName', UXGeocoderStreetNameType::class, $attrFields)
            ;
        }

        if (in_array('postalCode', $this->fields, true)) {
            $builder
                ->add('postalCode', UXGeocoderPostalCodeType::class, $attrFields)
            ;
        }

        if (in_array('locality', $this->fields, true)) {
            $builder
                ->add('locality', UXGeocoderLocalityType::class, $attrFields)
            ;
        }

        if (in_array('country', $this->fields, true)) {
            $builder
                ->add('country', UXGeocoderCountryType::class, $attrFields)
            ;
        }

        $builder
            ->add('lat', HiddenType::class, [
                'mapped' => false,
            ])
            ->add('lng', HiddenType::class, [
                'mapped' => false,
            ])
        ;

        switch ($this->sync) {
            case Syncs::ALL:
                $builder
                    ->addEventListener(
                        FormEvents::PRE_SUBMIT,
                        function (FormEvent $event) {
                            $form = $event->getForm();
                            $data = $event->getData();

                            $changedData = null;
                            $changedKey = null;
                            foreach ($data as $key => $value) {
                                // is json
                                if (is_string($value) && is_array(json_decode($value, true))) {
                                    $changedData = $value;
                                    $changedKey = $key;
                                    break;
                                }
                            }

                            if (!$changedData) {
                                return;
                            }

                            $address = $this->serializer->deserialize($changedData, $this->normalizer_class, 'json', [
                                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true,
                            ]);

                            $newData = [];

                            // set data to form with field existing
                            foreach ($this->fields as $field) {
                                $keyOfField = array_search($field, self::ORDERED_FIELDS, true);
                                // if keyOfField is less than key of changedKey
                                if ($keyOfField < array_search($changedKey, self::ORDERED_FIELDS, true)) {
                                    $newData[$field] = null;
                                    continue;
                                }

                                $subForm = $form->get($field);

                                $getter = $subForm->getConfig()->getOption('extra_options')[UXGeocoderController::SEARCH_ENTRY_GETTER_VALUE_TYPE] ?? 'streetName';
                                $value = $this->propertyAccessor->getValue($address, $getter);

                                $newData[$field] = $value;
                            }

                            $newData['lat'] = $address->getCoordinates()->getLatitude();
                            $newData['lng'] = $address->getCoordinates()->getLongitude();

                            $event->setData($newData);
                        }
                    )
                ;
                break;
            case Syncs::CASCADE:
                // @TODO: make something flexible for all providers
                break;
        }

        $builder
            ->addModelTransformer(
                new CallbackTransformer(
                    function ($data) {
                        return $data;
                    },
                    function ($data) {
                        if (is_array($data)) {
                            return json_encode($data);
                        }

                        return $data;
                    }
                )
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'fields' => [
                    'streetName',
                    'postalCode',
                    'locality',
                    'country',
                ],
                'sync' => Syncs::ALL,
                'simple' => false,
                'show_map' => true,
                'normalizer_class' => Address::class,
                'provider' => null,
            ])
            ->setRequired('provider')
        ;
    }

    public function getBlockPrefix()
    {
        return 'ux_geocoder';
    }
}
