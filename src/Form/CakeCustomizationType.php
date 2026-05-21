<?php

namespace App\Form;

use App\Entity\CakeCustomization;
use App\Service\CakeCustomizationService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class CakeCustomizationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var CakeCustomizationService $service */
        $service = $options['customization_service'];

        $builder
            ->add('size', ChoiceType::class, [
                'choices' => $service->getSizeOptions(),
                'expanded' => true,
                'multiple' => false,
                'label' => 'Cake Size',
                'constraints' => [new NotBlank()],
            ])
            ->add('flavor', ChoiceType::class, [
                'choices' => $service->getFlavorOptions(),
                'expanded' => true,
                'multiple' => false,
                'label' => 'Cake Flavor',
                'constraints' => [new NotBlank()],
            ])
            ->add('decorations', ChoiceType::class, [
                'choices' => $service->getDecorationOptions(),
                'expanded' => true,
                'multiple' => true,
                'label' => 'Decorations & Toppings',
                'required' => false,
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Personalized Message',
                'required' => false,
                'attr' => [
                    'rows' => 2,
                    'maxlength' => 60,
                    'placeholder' => 'Happy Birthday, Emma!',
                ],
                'constraints' => [
                    new Length([
                        'max' => 60,
                        'maxMessage' => 'The message cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantity',
                'data' => 1,
                'attr' => ['min' => 1, 'step' => 1],
                'constraints' => [new Positive()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CakeCustomization::class,
            'customization_service' => null,
        ]);
    }
}
