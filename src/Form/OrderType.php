<?php

namespace App\Form;

use App\Entity\Order;
use App\Form\OrderItemType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('customerName')
            ->add('customerPhone')

            // ✅ Staff can pick products + quantity
            // ->add('items', CollectionType::class, [
            //     'entry_type' => OrderItemType::class,
            //     'allow_add' => true,
            //     'allow_delete' => true,
            //     'by_reference' => false,
            // ])

            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Pending' => 'Pending',
                    'Paid' => 'Paid',
                    'Shipped' => 'Shipped',
                    'Delivered' => 'Delivered',
                ],
                'data' => 'Pending',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}
