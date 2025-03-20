<?php

namespace Akyos\UXGeocoder\Form\Fields;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UXGeocoderFieldType extends AbstractType
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setAttribute('autocomplete_url', $this->urlGenerator->generate('ux.geocoder.search', ['provider' => $options['provider']]));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('provider')
            ->setDefaults([
                'autocomplete' => true,
                'provider' => null,
                'attr' => [
                    'data-controller' => 'ux-geocoder-tomselect',
                ],
                'tom_select_options' => [
                    'maxItems' => 1,
                ],
                'required' => false,
            ])
        ;

        $resolver->setAllowedTypes('provider', ['null', 'string']);
    }

    public function getParent()
    {
        return TextType::class;
    }
}
