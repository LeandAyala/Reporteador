<?php

namespace App\Form\Consumo\Reporteador;

use App\Entity\Productos\Producto;
use App\Repository\Productos\ProductoRepository;
use App\Entity\Central\reportes;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Routing\RouterInterface;
use App\Repository\Central\reportesRepository;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class FiltrosReporteadorType extends AbstractType
{
    private $router;
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $tipo = $options['tipo']; 
        $modulo = $options['modulo']; 
        $builder
            ->add('desde', DateType::class, ['widget' => 'single_text', 'data' => new \DateTime('now', new \DateTimeZone('America/Bogota')), 'required' => false])
            ->add('hasta', DateType::class, ['widget' => 'single_text', 'data' => new \DateTime('now', new \DateTimeZone('America/Bogota')), 'required' => false])
            ->add('producto', EntityType::class, 
                [
                    'required' => false,
                    'choice_value' => 'id', 
                    'choice_label' => 'nombre',
                    'placeholder' => 'Seleccione producto',
                    'class' => Producto::class,  
                ]
            )
            ->add('controlLotes', CheckboxType::class, ['required' => false])

            ->add('informe', EntityType::class, 
                [
                    'label' => 'Informe', 
                    'choice_value' => 'id', 
                    'class' => reportes::class, 
                    'choice_label' => 'nombre',
                    'placeholder' => 'Seleccione',
                    'query_builder' => function(reportesRepository $repository) use ($tipo, $modulo)
                    {
                        return $repository->createQueryBuilder('r')->where("r.tipo = $tipo")->andWhere("r.modulo = '$modulo'")->andWhere('r.estado = 1');
                    },
                    'choice_attr' => function($item)
                    {   
                        $rutaPDF = '';
                        $rutaExcel = '';
                        $parametros = [];
                        $rutaControl = '';
                        $rutaFrameInforme = '';
                        $rutaFrameResumen = '';

                        if(!empty($item->getJson()) && is_array($item->getJson()))
                        {
                            $configuraciones = $item->getJson();

                            /** Se valida si el registro tiene una ruta configurada para generar el informe */
                            /** --------------------------------------------------------------------------- */

                            if(array_key_exists('rutaFrameInforme', $configuraciones) && is_array($configuraciones['rutaFrameInforme']) && !empty($configuraciones['rutaFrameInforme']))
                            {
                                if((array_key_exists('nombre', $configuraciones['rutaFrameInforme']) && !empty($configuraciones['rutaFrameInforme']['nombre'])))
                                {
                                    $rutaControl = $configuraciones['rutaFrameInforme']['nombre'];
                                }
                                if((array_key_exists('parametros', $configuraciones['rutaFrameInforme']) && is_array($configuraciones['rutaFrameInforme']['parametros']) && !empty($configuraciones['rutaFrameInforme']['parametros'])))
                                {
                                    $parametros = $configuraciones['rutaFrameInforme']['parametros'];
                                }
                                if(!empty($rutaControl))
                                {
                                    $rutaFrameInforme = $this->validarRuta($rutaControl, $parametros);
                                }
                            }

                            /** Se valida si el registro tiene una ruta configurada para descargar el informe en formato PDF */
                            /** -------------------------------------------------------------------------------------------- */

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

                            /** Se valida si el registro tiene una ruta configurada para descargar el informe en formato excel */
                            /** ---------------------------------------------------------------------------------------------- */

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

                            /** Se valida si el registro tiene una ruta configurada para visualizar una sección de resumen */
                            /** ------------------------------------------------------------------------------------------ */

                            $parametros = [];
                            $rutaControl = '';
                            if(array_key_exists('rutaFrameResumen', $configuraciones) && is_array($configuraciones['rutaFrameResumen']) && !empty($configuraciones['rutaFrameResumen']))
                            {
                                if((array_key_exists('nombre', $configuraciones['rutaFrameResumen']) && !empty($configuraciones['rutaFrameResumen']['nombre'])))
                                {
                                    $rutaControl = $configuraciones['rutaFrameResumen']['nombre'];
                                }
                                if((array_key_exists('parametros', $configuraciones['rutaFrameResumen']) && is_array($configuraciones['rutaFrameResumen']['parametros']) && !empty($configuraciones['rutaFrameResumen']['parametros'])))
                                {
                                    $parametros = $configuraciones['rutaFrameResumen']['parametros'];
                                }
                                if(!empty($rutaControl))
                                {
                                    $rutaFrameResumen = $this->validarRuta($rutaControl, $parametros);
                                }
                            }
                        }
                        return 
                        [
                            'data-rutapdf' => $rutaPDF,
                            'data-rutaexcel' => $rutaExcel, 
                            'data-icon' => 'fas fa-link text-info',
                            'data-rutaframeinforme' => $rutaFrameInforme, 
                            'data-rutaframeresumen' => $rutaFrameResumen, 
                        ];
                    }
                ]
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
        [
            'tipo' => null, 
            'modulo' => null
        ]);
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