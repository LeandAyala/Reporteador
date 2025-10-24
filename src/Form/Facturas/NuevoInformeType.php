<?php

namespace App\Form\Facturas;


use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class NuevoInformeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $modulos =
        [
            'Consumo' => 'Consumo',
            'Contabilidad' => 'Contabilidad',
            'Punto de venta' => 'Punto de venta'
        ];
        $builder
            ->add('sql', TextareaType::class, ['required' => false])
            ->add('idRegistro', HiddenType::class, ['required' => false])
            ->add('tipo', TextType::class, ['attr' => ['placeholder' => 'Tipo']])
            ->add('nombre', TextType::class, ['attr' => ['placeholder' => 'Nombre']])
            ->add('modulo', ChoiceType::class, 
            [
                'label' => 'Módulo',
                'choices' => $modulos
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([]);
    }
}