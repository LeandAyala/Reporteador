<?php

namespace App\Form\Facturas;

use App\Entity\Facturas\Pedido;
use App\Entity\Facturas\Cotizacion;
use App\Entity\Usuarios\Usuario;

use App\Repository\Facturas\CotizacionRepository;
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

class NuevoPedidoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $builder
            ->add('cantidadProductos', TextType::class, ['required' => true])
            ->add('cotizacion', EntityType::class, 
                [
                    'required' => true,
                    'choice_value' => 'id', 
                    'choice_label' => 'id',
                    'placeholder' => 'Seleccione', 
                    'class' => Cotizacion::class,  
                ]
            )
            ->add('fecha', DateType::class, ['widget' => 'single_text', 'data' => new \DateTime('now', new \DateTimeZone('America/Bogota')), 'required' => true])
            ->add('usuario', EntityType::class, 
                [
                    'required' => true,
                    'choice_value' => 'id', 
                    'choice_label' => 'nombre',
                    'placeholder' => 'Seleccione', 
                    'class' => Usuario::class,  
                ]
            )
            ->add('valor', TextType::class, ['required' => true])

            ->add('idRegistro', HiddenType::class, ['data' => 0, 'mapped' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => Pedido::class]);
    }
}