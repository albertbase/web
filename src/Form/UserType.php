<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // ✅ Shared fields (customers + staff)
        $builder
            ->add('username', TextType::class, [
                'label' => 'Username',
            ]);
            // ->add('name', TextType::class, [
            //     'label' => 'Full Name',
            //     'required' => false,
            // ]);

        // ✅ Staff/Admin fields only
        if ($options['is_staff_form']) {

            $builder
                ->add('roles', ChoiceType::class, [
                    'label' => 'Role',
                    'choices' => [
                        'Staff' => 'ROLE_STAFF',
                        'Admin' => 'ROLE_ADMIN',
                    ],
                    'multiple' => true,
                    'expanded' => true,
                ])
                ->add('status', ChoiceType::class, [
                    'label' => 'Account Status',
                    'choices' => [
                        'Active' => 'active',
                        'Disabled' => 'disabled',
                        // 'Archived' => 'archived',
                    ],
                ])
                ->add('plainPassword', PasswordType::class, [
                    'label' => 'Reset Password (optional)',
                    'required' => false,
                    'mapped' => false,   // ✅ REQUIRED FIX
                ]);
                // ->add('isActive', CheckboxType::class, [
                //     'label' => 'Active',
                //     'required' => false,
                //     // 'false_value' => 0,
                //     // 'true_value' => 1,
                // ]);

        }
    }

    public function configureOptions(OptionsResolver $resolver): void
{
    $resolver->setDefaults([
        'data_class' => User::class,
        'is_staff_form' => false,
        'csrf_protection' => true,
        'csrf_field_name' => '_token',
        'csrf_token_id' => 'user_item', // any stable string
    ]);
}


    
}
