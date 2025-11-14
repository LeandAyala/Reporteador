<?php

namespace App\Form\Consumo\Reporteador;

use App\Entity\Central\reportes;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use App\Repository\Central\reportesRepository;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class FiltrosReporteadorType extends AbstractType
{
    private $em;
    private $router;
    public function __construct(RouterInterface $router, EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->router = $router;
    }
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {   
        $listProducto = [];
        $tipo = $options['tipo']; 
        $modulo = $options['modulo'];
        $conexion = $this->em->getConnection(); 
        
        /** Registros de la tabla Producto */
        /** ------------------------------ */

        $sql = "SELECT id, nombre FROM productos.producto ORDER BY id ASC LIMIT 100";
        $result = $conexion->prepare($sql)->executeQuery()->fetchAll();
        foreach($result as $r){$listProducto[$r['nombre']] = $r['id'];}

        $builder
            ->add('desde', DateType::class, ['widget' => 'single_text', 'data' => new \DateTime('now', new \DateTimeZone('America/Bogota')), 'required' => false])
            ->add('hasta', DateType::class, ['widget' => 'single_text', 'data' => new \DateTime('now', new \DateTimeZone('America/Bogota')), 'required' => false])
            ->add('producto', ChoiceType::class, 
                [
                    'required' => false,
                    'choices' => $listProducto,
                    'placeholder' => 'Seleccione producto',  
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
                        $rutaFrameInforme = '';
                        $rutaFrameResumen = '';

                        if(!empty($item->getJson()) && is_array($item->getJson()))
                        {
                            $configuraciones = $item->getJson();

                            /** Se valida si el registro tiene una ruta configurada para generar el informe */
                            /** --------------------------------------------------------------------------- */

                            if(array_key_exists('rutaFrameInforme', $configuraciones) && !empty($configuraciones['rutaFrameInforme']))
                            {
                                
                                $rutaFrameInforme = $this->validarRuta($configuraciones['rutaFrameInforme']);
                            }

                            /** Se valida si el registro tiene una ruta configurada para descargar el informe en formato PDF */
                            /** -------------------------------------------------------------------------------------------- */

                            if(array_key_exists('pdf', $configuraciones) && is_array($configuraciones['pdf']) && !empty($configuraciones['pdf']))
                            {
                                if(array_key_exists('ruta', $configuraciones['pdf']) && !empty($configuraciones['pdf']['ruta']))
                                {
                                    $rutaPDF = $this->validarRuta($configuraciones['pdf']['ruta']);
                                }
                            }

                            /** Se valida si el registro tiene una ruta configurada para descargar el informe en formato excel */
                            /** ---------------------------------------------------------------------------------------------- */

                            if(array_key_exists('excel', $configuraciones) && !empty($configuraciones['excel']))
                            {
                                $rutaExcel = $this->validarRuta($configuraciones['excel']);
                            }

                            /** Se valida si el registro tiene una ruta configurada para visualizar una sección de resumen */
                            /** ------------------------------------------------------------------------------------------ */

                            if(array_key_exists('rutaFrameResumen', $configuraciones) && !empty($configuraciones['rutaFrameResumen']))
                            {
                                $rutaFrameResumen = $this->validarRuta($configuraciones['rutaFrameResumen']);
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

    public function validarRuta($ruta)
    {
        /** 
         * En esta función se valida si una ruta es correcta
         * -------------------------------------------------
         * @access public
        */

        $ruta = is_array($ruta)?'':$ruta;
        if(!filter_var('http://desarrollo.compuconta.com'.$ruta, FILTER_VALIDATE_URL))
        {
            $ruta = 'error';
        }
        return $ruta;
    }
}