<?php

namespace App\Form\Facturas;

use App\Entity\Usuarios\Usuario;
use App\Entity\Facturas\Reporte;
use App\Entity\Productos\Producto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Routing\RouterInterface;
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
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class FiltrosInformesType extends AbstractType
{
    private $router;
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

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
                        $rutaPDF = '';
                        $rutaFrame = '';
                        $rutaExcel = '';
                        $parametros = [];
                        $rutaControl = '';

                        if(!empty($item->getJson()) && is_array($item->getJson()))
                        {
                            $configuraciones = $item->getJson();

                            /** Se valida si el registro tiene una ruta ruta configurada para generar el informe */
                            /** -------------------------------------------------------------------------------- */

                            if(array_key_exists('rutaFrame', $configuraciones) && is_array($configuraciones['rutaFrame']) && !empty($configuraciones['rutaFrame']))
                            {
                                if((array_key_exists('nombre', $configuraciones['rutaFrame']) && !empty($configuraciones['rutaFrame']['nombre'])))
                                {
                                    $rutaControl = $configuraciones['rutaFrame']['nombre'];
                                }
                                if((array_key_exists('parametros', $configuraciones['rutaFrame']) && is_array($configuraciones['rutaFrame']['parametros']) && !empty($configuraciones['rutaFrame']['parametros'])))
                                {
                                    $parametros = $configuraciones['rutaFrame']['parametros'];
                                }
                                if(!empty($rutaControl))
                                {
                                    $rutaFrame = $this->validarRuta($rutaControl, $parametros);
                                }
                            }

                            /** Se valida si el registro tiene una ruta ruta configurada para descargar el informe en formato PDF */
                            /** ------------------------------------------------------------------------------------------------- */

                            $parametros = [];
                            $rutaControl = '';
                            if(array_key_exists('pdf', $configuraciones) && is_array($configuraciones['pdf']) && !empty($configuraciones['pdf']))
                            {
                                if(array_key_exists('ruta', $configuraciones['pdf']) && is_array($configuraciones['pdf']['ruta']) && !empty($configuraciones['pdf']['ruta']))
                                {
                                    if((array_key_exists('nombre', $configuraciones['pdf']['ruta']) && !empty($configuraciones['pdf']['ruta']['nombre'])))
                                    {
                                        $rutaControl = $configuraciones['pdf']['ruta']['nombre'];
                                    }
                                    if((array_key_exists('parametros', $configuraciones['pdf']['ruta']) && is_array($configuraciones['pdf']['ruta']['parametros']) && !empty($configuraciones['pdf']['ruta']['parametros'])))
                                    {
                                        $parametros = $configuraciones['pdf']['ruta']['parametros'];
                                    }
                                    if(!empty($rutaControl))
                                    {
                                        $rutaPDF = $this->validarRuta($rutaControl, $parametros);
                                    }
                                }
                            }

                            /** Se valida si el registro tiene una ruta ruta configurada para descargar el informe en formato excel */
                            /** --------------------------------------------------------------------------------------------------- */

                            $parametros = [];
                            $rutaControl = '';
                            if(array_key_exists('excel', $configuraciones) && is_array($configuraciones['excel']) && !empty($configuraciones['excel']))
                            {
                                if(array_key_exists('ruta', $configuraciones['excel']) && is_array($configuraciones['excel']['ruta']) && !empty($configuraciones['excel']['ruta']))
                                {
                                    if((array_key_exists('nombre', $configuraciones['excel']['ruta']) && !empty($configuraciones['excel']['ruta']['nombre'])))
                                    {
                                        $rutaControl = $configuraciones['excel']['ruta']['nombre'];
                                    }
                                    if((array_key_exists('parametros', $configuraciones['excel']['ruta']) && is_array($configuraciones['excel']['ruta']['parametros']) && !empty($configuraciones['excel']['ruta']['parametros'])))
                                    {
                                        $parametros = $configuraciones['excel']['ruta']['parametros'];
                                    }
                                    if(!empty($rutaControl))
                                    {
                                        $rutaExcel = $this->validarRuta($rutaControl, $parametros);
                                    }
                                }
                            }

                        }
                        return 
                        [
                            'data-rutapdf' => $rutaPDF,
                            'data-rutaframe' => $rutaFrame, 
                            'data-rutaexcel' => $rutaExcel, 
                            'data-icon' => 'fas fa-link text-info'
                        ];
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

    public function validarRuta($ruta, $parametros)
    {
        /** 
         * En esta función se valida si una ruta es correcta
         * -------------------------------------------------
         * @access public
        */

        try 
        {
            $ruta = $this->router->generate($ruta, $parametros);
        } 
        catch(RouteNotFoundException $e) 
        {
            $ruta = 'error';
        }
        return $ruta;
    }
}