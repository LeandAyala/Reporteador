<?php

namespace App\Form\Facturas;

use App\Entity\Facturas\Factura;
use App\Entity\Usuarios\Usuario;

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

class NuevoFacturaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $id = $options['id'];
        $builder
            ->add('fecha', DateType::class, ['widget' => 'single_text', 'data' => new \DateTime('now', new \DateTimeZone('America/Bogota')), 'required' => true])
            ->add('numero', TextType::class, ['required' => true])
            ->add('usuario', EntityType::class, 
                [
                    'required' => true,
                    'choice_value' => 'id', 
                    'choice_label' => 'nombre',
                    'placeholder' => 'Seleccione', 
                    'class' => Usuario::class,  
                ]
            )

            ->add('idRegistro', HiddenType::class, ['data' => $id, 'mapped' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => Factura::class, 'id' => null]);
    }
}