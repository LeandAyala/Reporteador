<?php

namespace App\Form\Facturas;

use App\Entity\Usuarios\Usuario;
use App\Entity\Facturas\Reporte;
use App\Entity\Productos\Producto;
use Symfony\Component\Form\AbstractType;
use App\Repository\Productos\ProductoRepository;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class FiltrosInformesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('hasta', DateType::class, ['widget' => 'single_text', 'label' => 'Hasta', 'data' => new \DateTime(date('Y-m-d'), new \DateTimeZone('America/Bogota'))])
            ->add('desde', DateType::class, ['widget' => 'single_text', 'label' => 'Desde', 'data' => new \DateTime(date('Y-m-01'), new \DateTimeZone('America/Bogota'))])
            ->add('informe', EntityType::class, 
                [
                    'label' => 'Informe', 
                    'choice_value' => 'id', 
                    'class' => Reporte::class, 
                    'choice_label' => 'nombre',
                    'placeholder' => 'Seleccione',
                    'choice_attr' => function($item)
                    {
                        return ['data-icon' => 'fas fa-link text-info'];
                    }
                ]
            )
            ->add('producto', EntityType::class, 
                [
                    'required' => false, 
                    'choice_value' => 'id',
                    'class' => Producto::class, 
                    'placeholder' => 'Seleccione',
                    'choice_label' => function($item)
                    {
                        return $item->getCodigo().' - '.$item->getNombre();
                    },
                    'query_builder' => function(ProductoRepository $repository)
                    {
                        return $repository->createQueryBuilder('p')->where('p.id <= 100');
                    }
                ]
            )
            ->add('usuario', EntityType::class, 
                [
                    'required' => false, 
                    'choice_value' => 'id',
                    'choice_label' => 'nombre',
                    'class' => Usuario::class, 
                    'placeholder' => 'Seleccione'
                ]
            )
        ;    
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([]);
    }
}