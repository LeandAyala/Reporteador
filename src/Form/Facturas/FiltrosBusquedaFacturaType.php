<?php

namespace App\Form\Facturas;

use App\Entity\Productos\Producto;
use App\Entity\Usuarios\Usuario;

use App\Repository\Productos\ProductoRepository;
use App\Repository\Usuarios\UsuarioRepository;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class FiltrosBusquedaFacturaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $builder
            ->add('fecha', DateType::class, ['widget' => 'single_text', 'data' => new \DateTime('now', new \DateTimeZone('America/Bogota')), 'required' => false])
            ->add('numero', TextType::class, ['required' => false])
            ->add('permiteActivar', CheckboxType::class, ['required' => false])
            ->add('producto', EntityType::class, 
                [
                    'required' => false,
                    'choice_value' => 'id', 
                    'choice_label' => 'nombre',
                    'placeholder' => 'Seleccione', 
                    'class' => Producto::class,  
                ]
            )
            ->add('usuario', EntityType::class, 
                [
                    'required' => false,
                    'choice_value' => 'id', 
                    'choice_label' => 'nombre',
                    'placeholder' => 'Seleccione', 
                    'class' => Usuario::class,  
                ]
            )
            ->add('valor', TextType::class, ['required' => false])

        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([]);
    }
}