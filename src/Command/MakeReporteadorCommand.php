<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class MakeReporteadorCommand extends Command
{
    private $fs;
    private $em;
    private $campos;
    private $modulo;
    private $archivosCreados;
    protected static $defaultName = 'app:make:reporteador';
    protected static $defaultDescription = 'Genera un reporteador con estilos personalizados de CC3';

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->campos = [];
        $this->modulo = '';
        $this->entidades = [];
        parent::__construct();
        $this->controladores = [];
        $this->archivosCreados = [];
        $this->fs = new Filesystem();

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** 
         * En esta función se genera un reporteador, aplicando estilos personalizados de CC3. 
         * Para ello, se crean los siguientes archivos:
         * ----------------------------------------------------------------------------------
         *   » reporteador.html.twig
         *   » reporteador_controller.js
         *   » FiltrosReporteadorType.php
         *   » frameErrorInforme.html.twig
         *   » ReporteadorController.php (Módulo)
         *   » ReporteadorController.php (Central)
         * ----------------------------------------------------------------------------------
         * @access public
        */

        /** Definición de variables */
        /** ----------------------- */

        $ruta = '';
        $entidad = '';
        $entidades = [];
        $controladores = [];
        $rutaEntdidad ='src/Entity';
        $rutaControlador ='src/Controller';
        $helper = $this->getHelper('question');
        $io = new SymfonyStyle($input, $output);
        $this->campos['informe'] = ['tipo' => 'relation', 'entidad' => 'Facturas\\Reporte'];
        $tipoDatos = ['text' => 'text', 'integer' => 'integer', 'float' => 'float', 'date' => 'date', 'boolean' => 'boolean', 'relation' => 'realtion'];

        /** Se obtienen las carpetas de la ruta src/Controller */
        /** -------------------------------------------------- */

        foreach(scandir($rutaControlador) as $item)
        {
            if($item !== '.' && $item !== '..' && $item !== '.gitignore')
            {
                $ruta = $rutaControlador.'/'.$item;
                if(is_dir($ruta))
                {
                    $this->obtenerControladores($rutaControlador, $item);
                }
            }
        }

        /** Se obtiene el módulo del directorio Controller donde se agregará el reporteador */
        /** ------------------------------------------------------------------------------- */

        $this->controladores = array_map(fn($item) => str_replace('src/Controller', '', $item), $this->controladores);
        foreach($this->controladores as $controller)
        {
            $controller = explode('/', $controller);
            unset($controller[0]);
            $controladores[implode('/', $controller)] = implode('/', $controller);
        }

        while(true)
        {
            $output->writeln('');
            $io->writeln("<fg=#28A745> ► Seleccione el directorio de la carpeta Controller donde desea agregar el reporteador</>\n<fg=#28A745>   ------------------------------------------------------------------------------------ </>");        
            $question = new Question(' » <fg=#0000FF>src/Controller/</>');
            $question->setAutocompleterValues($controladores);
            $modulo = trim($helper->ask($input, $output, $question));
            $rutaModulo = empty($modulo)?'src/Controller/':'src/Controller/'.$modulo.'/';

            /** Se valida si el directorio seleccionado existe */
            /** ---------------------------------------------- */

            if(!is_dir($rutaModulo))
            {
                $io->error("El directorio $rutaModulo no existe");
            }
            else
            {
                break;
            }
        }
        
        /** Se obtienen las entidades de la ruta src/Entity */
        /** ----------------------------------------------- */

        $io->writeln('');
        $this->modulo = $modulo;
        $io->writeln("<fg=#28A745> ► Ingrese los campos de los filtros de búsqueda</>\n<fg=#28A745>   --------------------------------------------- </>");        


        foreach(scandir($rutaEntdidad) as $item)
        {
            if($item !== '.' && $item !== '..' && $item !== '.gitignore')
            {
                $ruta = $rutaEntdidad.'/'.$item;
                if(is_file($ruta))
                {
                    $this->entidades[] = $ruta;
                }
                else
                {
                    $this->obtenerEntidades($rutaEntdidad, $item);
                }
            }
        }
        $this->entidades = array_map(fn($item) => str_replace(['src/Entity', '/', '.php'], ['', '\\', ''], $item), $this->entidades);
        foreach($this->entidades as $entidad)
        {
            $entidad = explode('\\', $entidad);
            unset($entidad[0]);
            $entidades[implode('\\', $entidad)] = implode('\\', $entidad);
        }

        /** Se ingresan los campos de los filtros de búsqueda */
        /** ------------------------------------------------- */

        while(true)
        {
            /** Se obtiene el nombre del campo */
            /** ------------------------------ */

            while(true)
            {
                $question = new Question(' » [Name]: ');
                $nombre = trim($helper->ask($input, $output, $question));
                
                if(empty($nombre))
                {
                    $io->error("El nombre no puede estar vacío");
                }
                else
                {
                    break;
                }
            }

            /** Se obtiene el tipo del campo */
            /** ---------------------------- */

            while(true)
            {
                $question = new Question(' » [Type]: ');
                $question->setAutocompleterValues($tipoDatos);
                $tipo = trim($helper->ask($input, $output, $question));
                
                if(!array_key_exists($tipo, $tipoDatos))
                {
                    $io->error('Ingrese un tipo de dato válido');
                }
                else
                {
                    break;
                }
            }

            /** Se obtiene la entidad a relacionar */
            /** ---------------------------------- */

            $entidad = '';
            if($tipo == 'relation')
            {
                while(true)
                {
                    $output->writeln('');
                    $io->writeln("<fg=#28A745> ► Seleccione la entidad a relacionar</>\n<fg=#28A745>   ---------------------------------- </>");        
                    $question = new Question(' » ');
                    $question->setAutocompleterValues($entidades);
                    $entidad = trim($helper->ask($input, $output, $question));

                    /** Se valida si la entidad seleccionada existe */
                    /** ------------------------------------------- */

                    if(!array_key_exists($entidad, $entidades))
                    {
                        $io->error("La entidad $entidad no existe");
                    }
                    else
                    {
                        break;
                    }
                }
            }

            /** Se guarda la información de los campos */
            /** -------------------------------------- */

            $dataCampo = ['tipo' => $tipo, 'entidad' => $entidad];
            $this->campos[$nombre] = $dataCampo;
            $io->writeln('');
            $confirmarCampo = $io->confirm("► ¿Desea agregar otro campo?");
            if(!$confirmarCampo){break;}
        }

        /** Se crean los distintos archivos del CRUD */
        /** ---------------------------------------- */

        $this->crearPlantillaReporteadorTwig();
        $this->crearPlantillaControllerModulo();
        $this->crearPlantillaFrameErrorInforme();
        $this->crearPlantillaControllerCentral();
        $this->crearPlantillaFiltrosReporteadorType();
        $this->crearPlantillaControllerStimulusModulo();
        $this->crearPlantillaControllerStimulusCentral();
        ksort($this->archivosCreados);
        $io->success("El reporteador se ha creado con éxito. Se agregaron los siguientes archivos:");
        $io->table(["\t    Archivos creados/actualizados"], $this->archivosCreados);
        return Command::SUCCESS;
    }

    public function obtenerEntidades($rutaEntdidad, $ruta)
    {
        /** 
         * En esta función se obtiene de forma recursiva todas las entidades de la ruta src/Entity
         * ---------------------------------------------------------------------------------------
         * @access public
        */

        $rutaEntdidad = $rutaEntdidad.'/'.$ruta;
        foreach(scandir($rutaEntdidad) as $item)
        {
            if($item !== '.' && $item !== '..' && $item !== '.gitignore')
            {
                $ruta = $rutaEntdidad.'/'.$item;
                if(is_file($ruta))
                {
                    $this->entidades[] = $ruta;
                }
                else
                {
                    $this->obtenerEntidades($rutaEntdidad, $item);
                }
            }
        }
    }

    public function obtenerControladores($rutaControlador, $ruta)
    {
        /** 
         * En esta función se obtiene de forma recursiva todas las carpetas de la ruta src/Controller
         * ------------------------------------------------------------------------------------------
         * @access public
        */

        $rutaControlador = $rutaControlador.'/'.$ruta;
        foreach(scandir($rutaControlador) as $item)
        {
            if($item !== '.' && $item !== '..' && $item !== '.gitignore')
            {
                $ruta = $rutaControlador.'/'.$item;
                if(is_file($ruta))
                {
                    $this->controladores[$rutaControlador] = $rutaControlador;
                }
                else
                {
                    if(count(scandir($ruta)) <= 2)
                    {
                        $this->controladores[$ruta] = $ruta;
                    }
                    else
                    {
                        $this->obtenerControladores($rutaControlador, $item);
                    }
                }
            }
        }
    }

    public function crearPlantillaFiltrosReporteadorType()
    {
        /** 
         * En esta función se crea el archivo FiltrosReporteadorType.php en el path correspondiente
         * ----------------------------------------------------------------------------------------
         * @access public
        */ 

        /** Definición de variables */
        /** ----------------------- */

        $camposFiltros = '';
        $campos = $this->campos;
        $entidadesImportadas = [];
        $repositoriosImportados = [];
        $ruta = 'src/Form/'.$this->modulo.'/Reporteador/FiltrosReporteadorType.php';
        $modulo = empty($this->modulo)?'\Reporteador':'\\'.str_replace('/', '\\', $this->modulo).'\\Reporteador';
        $this->archivosCreados[3] = ['» '.$ruta];

        /** Se generan los campos del formulario */
        /** ------------------------------------ */

        foreach($campos as $key => $campo)
        {
            if($key == 'informe'){continue;}
            $placeholder = ucfirst(implode(' ', preg_split('/(?=[A-Z])/', $key, -1, PREG_SPLIT_NO_EMPTY)));
            if($campo['tipo'] !== 'relation')
            {
                if(in_array($campo['tipo'], ['date', 'datetime']))
                {
                    $camposFiltros .= 
                    <<<PHP
                                ->add('$key', DateType::class, ['widget' => 'single_text', 'data' => new \DateTime('now', new \DateTimeZone('America/Bogota')), 'required' => false])\n
                    PHP;
                }
                elseif($campo['tipo'] == 'boolean')
                {
                    $camposFiltros .= 
                    <<<PHP
                                ->add('$key', CheckboxType::class, ['required' => false])\n
                    PHP;
                }
                else
                {
                    $camposFiltros .= 
                    <<<PHP
                                ->add('$key', TextType::class, ['attr' => ['placeholder' => '$placeholder'], 'required' => false])\n
                    PHP;
                }
            }
            else
            {
                $placeholder = strtolower($placeholder);
                $nombreClase = explode("\\", $campo['entidad']);
                $entidadesImportadas[$campo['entidad']] = 'use App\Entity\\'.$campo['entidad'].';';
                $choiceLabel = property_exists("App\Entity\\".$campo['entidad'], 'nombre')?'nombre':'id';
                $repositoriosImportados[$campo['entidad']] = 'use App\Repository\\'.$campo['entidad'].'Repository;';
                $camposFiltros .= 
                <<<PHP
                            ->add('$key', EntityType::class, 
                                [
                                    'required' => false,
                                    'choice_value' => 'id', 
                                    'choice_label' => '$choiceLabel',
                                    'placeholder' => 'Seleccione $placeholder',
                                    'class' => {$nombreClase[count($nombreClase) - 1]}::class,  
                                ]
                            )\n
                PHP;
            }
        }
        $entidadesImportadas = implode("\n", $entidadesImportadas);
        $repositoriosImportados = implode("\n", $repositoriosImportados);

        $plantilla =
        <<<PHP
        <?php

        namespace App\Form$modulo;
        
        $entidadesImportadas
        $repositoriosImportados
        use App\Entity\Central\\reportes;
        use Doctrine\ORM\EntityRepository;
        use Symfony\Component\Form\AbstractType;
        use Symfony\Component\Routing\RouterInterface;
        use App\Repository\Central\\reportesRepository;
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
            private \$router;
            public function __construct(RouterInterface \$router)
            {
                \$this->router = \$router;
            }
            
            public function buildForm(FormBuilderInterface \$builder, array \$options)
            {
                \$tipo = \$options['tipo']; 
                \$modulo = \$options['modulo']; 
                \$builder\n$camposFiltros
                    ->add('informe', EntityType::class, 
                        [
                            'label' => 'Informe', 
                            'choice_value' => 'id', 
                            'class' => reportes::class, 
                            'choice_label' => 'nombre',
                            'placeholder' => 'Seleccione',
                            'query_builder' => function(reportesRepository \$repository) use (\$tipo, \$modulo)
                            {
                                return \$repository->createQueryBuilder('r')->where("r.tipo = \$tipo")->andWhere("r.modulo = '\$modulo'");
                            },
                            'choice_attr' => function(\$item)
                            {   
                                \$rutaPDF = '';
                                \$rutaFrame = '';
                                \$rutaExcel = '';
                                \$parametros = [];
                                \$rutaControl = '';

                                if(!empty(\$item->getJson()) && is_array(\$item->getJson()))
                                {
                                    \$configuraciones = \$item->getJson();

                                    /** Se valida si el registro tiene una ruta ruta configurada para generar el informe */
                                    /** -------------------------------------------------------------------------------- */

                                    if(array_key_exists('rutaFrame', \$configuraciones) && is_array(\$configuraciones['rutaFrame']) && !empty(\$configuraciones['rutaFrame']))
                                    {
                                        if((array_key_exists('nombre', \$configuraciones['rutaFrame']) && !empty(\$configuraciones['rutaFrame']['nombre'])))
                                        {
                                            \$rutaControl = \$configuraciones['rutaFrame']['nombre'];
                                        }
                                        if((array_key_exists('parametros', \$configuraciones['rutaFrame']) && is_array(\$configuraciones['rutaFrame']['parametros']) && !empty(\$configuraciones['rutaFrame']['parametros'])))
                                        {
                                            \$parametros = \$configuraciones['rutaFrame']['parametros'];
                                        }
                                        if(!empty(\$rutaControl))
                                        {
                                            \$rutaFrame = \$this->validarRuta(\$rutaControl, \$parametros);
                                        }
                                    }

                                    /** Se valida si el registro tiene una ruta ruta configurada para descargar el informe en formato PDF */
                                    /** ------------------------------------------------------------------------------------------------- */

                                    \$parametros = [];
                                    \$rutaControl = '';
                                    if(array_key_exists('pdf', \$configuraciones) && is_array(\$configuraciones['pdf']) && !empty(\$configuraciones['pdf']))
                                    {
                                        if(array_key_exists('ruta', \$configuraciones['pdf']) && is_array(\$configuraciones['pdf']['ruta']) && !empty(\$configuraciones['pdf']['ruta']))
                                        {
                                            if((array_key_exists('nombre', \$configuraciones['pdf']['ruta']) && !empty(\$configuraciones['pdf']['ruta']['nombre'])))
                                            {
                                                \$rutaControl = \$configuraciones['pdf']['ruta']['nombre'];
                                            }
                                            if((array_key_exists('parametros', \$configuraciones['pdf']['ruta']) && is_array(\$configuraciones['pdf']['ruta']['parametros']) && !empty(\$configuraciones['pdf']['ruta']['parametros'])))
                                            {
                                                \$parametros = \$configuraciones['pdf']['ruta']['parametros'];
                                            }
                                            if(!empty(\$rutaControl))
                                            {
                                                \$rutaPDF = \$this->validarRuta(\$rutaControl, \$parametros);
                                            }
                                        }
                                    }

                                    /** Se valida si el registro tiene una ruta ruta configurada para descargar el informe en formato excel */
                                    /** --------------------------------------------------------------------------------------------------- */

                                    \$parametros = [];
                                    \$rutaControl = '';
                                    if(array_key_exists('excel', \$configuraciones) && is_array(\$configuraciones['excel']) && !empty(\$configuraciones['excel']))
                                    {
                                        if(array_key_exists('ruta', \$configuraciones['excel']) && is_array(\$configuraciones['excel']['ruta']) && !empty(\$configuraciones['excel']['ruta']))
                                        {
                                            if((array_key_exists('nombre', \$configuraciones['excel']['ruta']) && !empty(\$configuraciones['excel']['ruta']['nombre'])))
                                            {
                                                \$rutaControl = \$configuraciones['excel']['ruta']['nombre'];
                                            }
                                            if((array_key_exists('parametros', \$configuraciones['excel']['ruta']) && is_array(\$configuraciones['excel']['ruta']['parametros']) && !empty(\$configuraciones['excel']['ruta']['parametros'])))
                                            {
                                                \$parametros = \$configuraciones['excel']['ruta']['parametros'];
                                            }
                                            if(!empty(\$rutaControl))
                                            {
                                                \$rutaExcel = \$this->validarRuta(\$rutaControl, \$parametros);
                                            }
                                        }
                                    }

                                }
                                return 
                                [
                                    'data-rutapdf' => \$rutaPDF,
                                    'data-rutaframe' => \$rutaFrame, 
                                    'data-rutaexcel' => \$rutaExcel, 
                                    'data-icon' => 'fas fa-link text-info'
                                ];
                            }
                        ]
                    )
                ;
            }
        
            public function configureOptions(OptionsResolver \$resolver)
            {
                \$resolver->setDefaults(
                [
                    'tipo' => null, 
                    'modulo' => null
                ]);
            }

            public function validarRuta(\$ruta, \$parametros)
            {
                /** 
                 * En esta función se valida si una ruta es correcta
                 * -------------------------------------------------
                 * @access public
                */

                try 
                {
                    \$ruta = \$this->router->generate(\$ruta, \$parametros);
                } 
                catch(RouteNotFoundException \$e) 
                {
                    \$ruta = 'error';
                }
                return \$ruta;
            }
        }
        PHP;
        $this->fs->dumpFile($ruta, $plantilla);
    }

    public function crearPlantillaControllerStimulusCentral()
    {
        /** 
         * En esta función se crea el archivo reporteador_controller.js en la ruta asset/controllers/central 
         * -------------------------------------------------------------------------------------------------
         * @access public
        */

        $ruta = 'assets/controllers/central/reporteador_controller.js';
        $this->archivosCreados[0] = ['» '.$ruta];
        $plantilla =
        <<<STIMULUS
        import mensajes from '../central/mensajes';
        import { Controller } from "@hotwired/stimulus";

        export default class extends Controller 
        {
            estadoDescarga = 0;
            form = new FormData();
            mensaje = new mensajes();
            estadoSeccionFiltros = 1;
            estadoBusquedaRapida = 0;
            estadoPaginaSeleccionada = 0;

            static values = 
            {
                'urlGenerarInforme' : String,
                'urlFrameErrorInforme' : String,
                'urlDescargarInformePdf' : String,
                'urlDescargarInformeExcel' : String,
            };

            static targets = 
            [
                'cargandoFiltros', 'paginaHidden', 'busquedaRapida', 'busquedaRapidaHidden'
            ];

            connect()
            {
                var self = this;
                console.log('connect');
                $('.selectpicker').selectpicker('refresh');
                $('#btnRegresar').on('click', function(){\$(this).html('<i class="fas fa-spinner fa-spin"></i> Regresando')});
            }

            async generarInforme(event)
            {
                /** En esta función se genera un informe específico de acuerdo a los filtros de búsqueda seleccionados */
                /** -------------------------------------------------------------------------------------------------- */

                event.preventDefault();
                let form = new FormData();
                let formulario = Object.fromEntries(new FormData(event.currentTarget));
                Object.keys(formulario).forEach((item) => {form.append(item, formulario[item])});
                let rutaInforme = $('#filtros_reporteador_informe option:selected').data('rutaframe');
                let paginaActual = (this.targets.find('paginaHidden') != undefined)?this.paginaHiddenTarget.value:1;
                let busquedaRapida = (this.targets.find('busquedaRapida') != undefined)?this.busquedaRapidaTarget.value.trim():'';
                let busquedaRapidaActual = (this.targets.find('busquedaRapidaHidden') != undefined)?this.busquedaRapidaHiddenTarget.value.trim():'';
                busquedaRapida = (this.estadoBusquedaRapida == 1)?busquedaRapida:busquedaRapidaActual;
                paginaActual = (this.estadoPaginaSeleccionada == 1)?paginaActual:1;
                form.append('busquedaRapida', busquedaRapida);
                form.append('pagina', paginaActual);
                this.estadoPaginaSeleccionada = 0;
                this.estadoBusquedaRapida = 0;

                /** Se valida si hay una descarga en proceso */
                /** ---------------------------------------- */

                if(this.estadoDescarga == 1)
                {
                    this.mensaje.mostrarMensaje('¡No se puede realizar esta acción porque hay una descarga en proceso!');
                    return;
                }

                /** Se valida si existe una ruta configurada para la descarga del excel */
                /** ------------------------------------------------------------------- */

                if(rutaInforme == 'error')
                {
                    this.mensaje.mostrarMensaje('¡La ruta configurada para generar el informe no es válida!');
                    return;
                }

                /** Se genera el informe a partir de los filtros de búsqueda */
                /** -------------------------------------------------------- */
                
                this.form = form;
                let ruta = (rutaInforme !== '')?rutaInforme:this.urlGenerarInformeValue;
                this.cargandoFiltrosTarget.style.display = '';
                if(this.estadoSeccionFiltros == 1){this.showSeccionFiltros()}
                let consulta = await fetch(ruta, {'method' : 'POST', 'body' : form});
                let result = await consulta.json();
                $('#frameInforme').html(result.plantilla);
                this.cargandoFiltrosTarget.style.display = 'none';
                $('.listado').on('scroll', function()
                {
                    if(this.scrollTop == 0)
                    {
                        $('.tituloFinal').css('border-radius', '0px 10px 3px 0px');
                        $('.tituloInicial').css('border-radius', '10px 0px 0px 3px');
                    }
                    else
                    {
                        $('.tituloFinal').css('border-radius', '0px');
                        $('.tituloInicial').css('border-radius', '0px');
                    }
                });
            }

            async seleccionarPagina(event)
            {
                /** En esta función se realiza la búsqueda de registros de acuerdo a la página seleccionada */
                /** --------------------------------------------------------------------------------------- */

                let pagina = 1;
                this.estadoPaginaSeleccionada = 1;
                let opc = event.currentTarget.dataset.opc
                let paginaActual = Number($('#paginaHidden').val());
                let paginaSeleccionada = event.currentTarget.dataset.pagina;

                /** Se asigna la página seleccionada y se efectúa la búsqueda de registros */
                /** ---------------------------------------------------------------------- */

                if(opc == 3){pagina = Number(paginaActual) + 1}
                if(opc == 2){pagina = Number(paginaActual) - 1}
                if(opc == 1){pagina = Number(paginaSeleccionada)}
                if(paginaActual != paginaSeleccionada)
                {
                    $('#paginaHidden').val(pagina);
                    $('#btnGenerarInforme').click();
                }
            }

            showSeccionFiltros()
            {
                /** En esta función se hace visible/oculta la sección que contiene los filtros de búsqueda */
                /** -------------------------------------------------------------------------------------- */

                $('.seccionFiltros').toggle('400');
                this.estadoSeccionFiltros = (this.estadoSeccionFiltros == 1)?0:1;
            }

            busquedaRapida(event)
            {
                /** En esta función se realiza la búsqueda de registros teniendo en cuenta el campo Búsqueda rápida */
                /** ----------------------------------------------------------------------------------------------- */

                let opc = event.currentTarget.dataset.opc;
                if(opc == 1 || event.keyCode == 13)
                {
                    if($('#busquedaRapidaHidden').val().trim() != '' || $('#busquedaRapida').val().trim() != '')
                    { 
                        this.estadoBusquedaRapida = 1;
                        $('#btnGenerarInforme').click();
                    }
                    else
                    {
                        $('#busquedaRapida').css('border-color', '#DC3545');
                        $('#btnBusquedaRapida').css('background', '#DC3545');
                        setTimeout(() =>
                        {
                            $('#busquedaRapida').css('border-color', '');
                            $('#btnBusquedaRapida').css('background', '#17A');
                        }, 3000);
                    }
                }
            }

            showMenuReporteador(event)
            {
                /** En esta función se hace visible/oculta el menú del reporteador */
                /** -------------------------------------------------------------- */
                
                let opc = event.currentTarget.dataset.opc;
                let icono = $('.menuReporteador').find('i');
                let btnMenuReporteador = event.currentTarget;

                if(opc == 0)
                {
                    opc = 1;
                    icono.removeClass('fa-bars').addClass('fa-times');
                    btnMenuReporteador.classList.add('menuReporteadorError');
                    $('#menuReporteador').attr('transition-style', 'in:custom:circle-swoop').css('display', 'flex');
                }
                else
                {
                    opc = 0;
                    btnMenuReporteador.style.pointerEvents = 'none';
                    icono.removeClass('fa-times').addClass('fa-bars');
                    btnMenuReporteador.classList.remove('menuReporteadorError');
                    $('#menuReporteador').attr('transition-style', 'out:custom:circle-swoop');
                    setTimeout(() => {\$('#menuReporteador').hide(); btnMenuReporteador.style.pointerEvents = '';}, 1100);
                }
                btnMenuReporteador.dataset.opc = opc;
            }

            async descargarPDF()
            {
                /** En esta función se realiza la descarga del informe en formato PDF */
                /** ----------------------------------------------------------------- */

                let icono = $('.menuReporteador').find('i');
                let btnMenuReporteador = $('.menuReporteador');
                btnMenuReporteador.css('pointer-events', 'none');
                let form = new FormData($('#filtrosInforme')[0]);
                icono.removeClass('fa-times').addClass('fa-bars');
                btnMenuReporteador.removeClass('menuReporteadorError');
                form.append('busquedaRapida', $('#busquedaRapidaHidden').val().trim());
                $('#menuReporteador').attr('transition-style', 'out:custom:circle-swoop');
                let rutaPDF = $('#filtros_reporteador_informe option:selected').data('rutapdf');
                setTimeout(() => {\$('#menuReporteador').hide(); btnMenuReporteador.css('pointer-events', '');}, 1100);
                let nombreInforme = $('#filtros_reporteador_informe option:selected').text().toLowerCase().replaceAll(' ', '_');
                btnMenuReporteador[0].dataset.opc = 0;

                /** Se valida si existe una ruta configurada para la descarga del PDF */
                /** ----------------------------------------------------------------- */

                if(rutaPDF == 'error')
                {
                    this.mensaje.mostrarMensaje('¡La ruta configurada para la descarga del PDF no es válida!');
                    return;
                }

                /** Se hace visible el loader de descarga */
                /** ------------------------------------- */

                this.estadoDescarga = 1;
                let porcentajeDescarga = 0;
                let iconoDescarga = $('#divIconoDescarga').find('i');
                $('#divIconoDescarga').css('background', '#DC354526');
                $('#loaderDescargaInforme').css({'opacity' : '1', 'right' : '0px'});
                iconoDescarga.removeClass('fa-file-excel text-success').addClass('fa-file-pdf text-danger');
                let intervaloLoaderDescarga = setInterval(() =>
                {
                    $('#porcentajeDescarga').html(`\${porcentajeDescarga}%`);
                    $('#barraProgresoDescarga').css('width', `\${porcentajeDescarga}%`);
                    if(porcentajeDescarga == 99){clearInterval(intervaloLoaderDescarga)}
                    porcentajeDescarga ++;
                }, 500);

                /** Se genera el informe en formato PDF */
                /** ----------------------------------- */

                let ruta = (rutaPDF != '')?rutaPDF:this.urlDescargarInformePdfValue;
                let consulta = await fetch(ruta, {'method' : 'POST', 'body' : this.form});
                clearInterval(intervaloLoaderDescarga);

                /** Se valida si el archivo PDF se generó con éxito */
                /** ----------------------------------------------- */

                if(!consulta.ok) 
                {
                    this.estadoDescarga = 0;
                    $('#barraProgresoDescarga').addClass('bg-danger');
                    $('#porcentajeDescarga').html('<i class="fas fa-ban text-danger animate__animated animate__fadeIn"></i>');
                    consulta = await fetch(this.urlFrameErrorInformeValue);
                    $('#frameErrorInforme').html(await consulta.text()).css('display', '');
                    setTimeout(() =>{\$('#loaderDescargaInforme').css({'opacity' : '0', 'right' : '-260px'})}, 3000);
                    setTimeout(() =>{\$('#barraProgresoDescarga').removeClass('bg-danger').css('width', '0%');}, 4000);
                    return;
                }

                /** Se finaliza el porcentaje de descarga */
                /** ------------------------------------- */

                intervaloLoaderDescarga = setInterval(() =>
                {
                    $('#porcentajeDescarga').html(`\${porcentajeDescarga}%`);
                    $('#barraProgresoDescarga').css('width', `\${porcentajeDescarga}%`);
                    if(porcentajeDescarga == 100)
                    {
                        clearInterval(intervaloLoaderDescarga);
                        $('#barraProgresoDescarga').addClass('bg-success');
                        $('#porcentajeDescarga').html('<i class="fas fa-check-circle text-success animate__animated animate__fadeIn"></i>');        
                    }
                    porcentajeDescarga ++;
                }, 1);

                /** Se realiza la descarga del archivo PDF */
                /** -------------------------------------- */

                let blob = await consulta.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.download = `\${nombreInforme}.pdf`;
                a.href = url;
                document.body.appendChild(a);
                a.click();
                a.remove();

                /** Se oculta el loader de descarga */
                /** ------------------------------- */

                this.estadoDescarga = 0;
                setTimeout(() =>{\$('#loaderDescargaInforme').css({'opacity' : '0', 'right' : '-260px'})}, 3000);
                setTimeout(() =>{\$('#barraProgresoDescarga').removeClass('bg-success').css('width', '0%');}, 4000);
            }

            async descargarExcel()
            {
                /** En esta función se realiza la descarga del informe en formato excel */
                /** ------------------------------------------------------------------- */

                let icono = $('.menuReporteador').find('i');
                let btnMenuReporteador = $('.menuReporteador');
                btnMenuReporteador.css('pointer-events', 'none');
                let form = new FormData($('#filtrosInforme')[0]);
                icono.removeClass('fa-times').addClass('fa-bars');
                btnMenuReporteador.removeClass('menuReporteadorError');
                form.append('busquedaRapida', $('#busquedaRapidaHidden').val().trim());
                $('#menuReporteador').attr('transition-style', 'out:custom:circle-swoop');
                let rutaExcel = $('#filtros_reporteador_informe option:selected').data('rutaexcel');
                setTimeout(() => {\$('#menuReporteador').hide(); btnMenuReporteador.css('pointer-events', '');}, 1100);
                let nombreInforme = $('#filtros_reporteador_informe option:selected').text().toLowerCase().replaceAll(' ', '_');
                btnMenuReporteador[0].dataset.opc = 0;

                /** Se valida si existe una ruta configurada para la descarga del excel */
                /** ------------------------------------------------------------------- */

                if(rutaExcel == 'error')
                {
                    this.mensaje.mostrarMensaje('¡La ruta configurada para la descarga del excel no es válida!');
                    return;
                }

                /** Se hace visible el loader de descarga */
                /** ------------------------------------- */

                this.estadoDescarga = 1;
                let porcentajeDescarga = 0;
                let iconoDescarga = $('#divIconoDescarga').find('i');
                $('#divIconoDescarga').css('background', '#28A74526');
                $('#loaderDescargaInforme').css({'opacity' : '1', 'right' : '0px'});
                iconoDescarga.removeClass('fa-file-pdf text-danger').addClass('fa-file-excel text-success');
                let intervaloLoaderDescarga = setInterval(() =>
                {
                    $('#porcentajeDescarga').html(`\${porcentajeDescarga}%`);
                    $('#barraProgresoDescarga').css('width', `\${porcentajeDescarga}%`);
                    if(porcentajeDescarga == 99){clearInterval(intervaloLoaderDescarga)}
                    porcentajeDescarga ++;
                }, 500);

                /** Se genera el informe en formato excel */
                /** ------------------------------------- */

                let ruta = (rutaExcel != '')?rutaExcel:this.urlDescargarInformeExcelValue;
                let consulta = await fetch(ruta, {'method' : 'POST', 'body' : this.form});
                clearInterval(intervaloLoaderDescarga);

                /** Se valida si el archivo excel se generó con éxito */
                /** ------------------------------------------------- */

                if(!consulta.ok) 
                {
                    this.estadoDescarga = 0;
                    $('#barraProgresoDescarga').addClass('bg-danger');
                    $('#porcentajeDescarga').html('<i class="fas fa-ban text-danger animate__animated animate__fadeIn"></i>');
                    consulta = await fetch(this.urlFrameErrorInformeValue);
                    $('#frameErrorInforme').html(await consulta.text()).css('display', '');
                    setTimeout(() =>{\$('#loaderDescargaInforme').css({'opacity' : '0', 'right' : '-260px'})}, 3000);
                    setTimeout(() =>{\$('#barraProgresoDescarga').removeClass('bg-danger').css('width', '0%');}, 4000);
                    return;
                }

                /** Se finaliza el porcentaje de descarga */
                /** ------------------------------------- */

                intervaloLoaderDescarga = setInterval(() =>
                {
                    $('#porcentajeDescarga').html(`\${porcentajeDescarga}%`);
                    $('#barraProgresoDescarga').css('width', `\${porcentajeDescarga}%`);
                    if(porcentajeDescarga == 100)
                    {
                        clearInterval(intervaloLoaderDescarga);
                        $('#barraProgresoDescarga').addClass('bg-success');
                        $('#porcentajeDescarga').html('<i class="fas fa-check-circle text-success animate__animated animate__fadeIn"></i>');
                    }
                    porcentajeDescarga ++;
                }, 1);

                /** Se realiza la descarga del archivo excel */
                /** ---------------------------------------- */

                let blob = await consulta.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.download = `\${nombreInforme}.xls`;
                a.href = url;
                document.body.appendChild(a);
                a.click();
                a.remove();

                /** Se oculta el loader de descarga */
                /** ------------------------------- */

                this.estadoDescarga = 0;
                setTimeout(() =>{\$('#loaderDescargaInforme').css({'opacity' : '0', 'right' : '-260px'})}, 3000);
                setTimeout(() =>{\$('#barraProgresoDescarga').removeClass('bg-success').css('width', '0%');}, 4000);
            }

            cerrarErrorInforme()
            {
                /** En esta función se cierra el mensaje de error generado al descargar el informe */
                /** ------------------------------------------------------------------------------ */

                $('#frameErrorInforme').addClass('animate__animated animate__fadeOut');
                setTimeout(() => {\$('#frameErrorInforme').html('').hide().removeClass('animate__animated animate__fadeOut')}, 800);
            }

            seleccionarInforme()
            {
                $('#busquedaRapidaHidden').val('');
            }

            limpiarCampos()
            {
                $('#filtrosInforme')[0].reset();
                $('.selectpicker').selectpicker('refresh');
            }
        }   
        STIMULUS;
        $this->fs->dumpFile($ruta, $plantilla);
    }

    public function crearPlantillaControllerStimulusModulo()
    {
        /** 
         * En esta función se crea el archivo reporteador_controller.js en el path correspondiente
         * ---------------------------------------------------------------------------------------
         * @access public
        */

        $rutaModulo = strtolower($this->modulo);
        $ruta = "assets/controllers/$rutaModulo/reporteador_controller.js";
        $this->archivosCreados[2] = ['» '.$ruta];
        $plantilla =
        <<<STIMULUS
        import mensajes from '../central/mensajes';
        import { Controller } from "@hotwired/stimulus";

        export default class extends Controller 
        {
            mensaje = new mensajes();

            static values = {};
            static targets = [];

            connect()
            {
                var self = this;
                console.log('connect');
                $('.selectpicker').selectpicker('refresh');
            }
        }   
        STIMULUS;
        $this->fs->dumpFile($ruta, $plantilla);
    }

    public function crearPlantillaControllerModulo()
    {
        /** 
         * En esta función se crea el controlador ReporteadorController.php en el path correspondiente. Este archivo
         * contendrá, además de la vista para generar informes, toda la lógica que sea requerida.
         * ---------------------------------------------------------------------------------------------------------
         * @access public
        */

        $modulo = empty($this->modulo)?'\Reporteador':'\\'.str_replace('/', '\\', $this->modulo).'\\Reporteador';
        $ruta = 'src/Controller/'.$this->modulo.'/Reporteador/ReporteadorController.php';
        $useFiltrosReporteador = 'use App\Form'.$modulo.'\\FiltrosReporteadorType;';
        $moduloRuta = strtolower(str_replace('/', '_', $this->modulo));
        $this->archivosCreados[6] = ['» '.$ruta];
        $moduloGeneral = $this->modulo;
        $plantilla = 
        <<<PHP
        <?php

        namespace App\Controller$modulo;

        $useFiltrosReporteador
        use Doctrine\ORM\EntityManagerInterface;
        use Symfony\Component\HttpFoundation\Request;
        use Symfony\Component\HttpFoundation\Response;
        use Symfony\Component\Routing\Annotation\Route;
        use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

        class ReporteadorController extends AbstractController
        {
            private \$em;
            public function __construct(EntityManagerInterface \$em)
            {
                \$this->em = \$em;
            }

            /**
             * @Route("/$moduloGeneral/Reporteador/informes", name="{$moduloRuta}_reporteador_informes")
            */
            public function informes(Request \$request)
            {
                /** 
                 * En esta función se crea la vista para generar informes
                 * ------------------------------------------------------
                 * @access public
                */

                \$formFiltros = \$this->createForm(FiltrosReporteadorType::class, null, ['tipo' => 1, 'modulo' => 'puntoventa']);
                return \$this->render('$moduloGeneral\\Reporteador\\reporteador.html.twig', ['formFiltros' => \$formFiltros->createView()]);
            }
        }
        PHP;
        $this->fs->dumpFile($ruta, $plantilla);
    }

    public function crearPlantillaControllerCentral()
    {
        /** 
         * En esta función se crea el controlador ReporteadorController.php en la ruta Central/Reporteador, el cual 
         * contiene todas las funciones para la generación y descarga de informes en los formatos PDF y Excel.
         * --------------------------------------------------------------------------------------------------------
         * @access public
        */

        $ruta = 'src/Controller/Central/Reporteador/ReporteadorController.php';
        $nombreControlador = 'central--reporteador';
        $this->archivosCreados[5] = ['» '.$ruta];
        $modulo = $this->modulo;
        $plantilla = 
        <<<PHP
        <?php

        namespace App\Controller\Central\Reporteador;

        use Dompdf\Dompdf;
        use Dompdf\Options;
        use App\Entity\Central\meses;
        use App\Entity\Central\compania;
        use App\Entity\Central\\reportes;
        use PhpOffice\PhpSpreadsheet\IOFactory;
        use Doctrine\ORM\EntityManagerInterface;
        use PhpOffice\PhpSpreadsheet\Style\Fill;
        use PhpOffice\PhpSpreadsheet\Spreadsheet;
        use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
        use PhpOffice\PhpSpreadsheet\Style\Border;
        use Symfony\Component\Filesystem\Filesystem;
        use Symfony\Component\HttpFoundation\Request;
        use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
        use Symfony\Component\HttpFoundation\Response;
        use Symfony\Component\Routing\Annotation\Route;
        use PhpOffice\PhpSpreadsheet\RichText\RichText;
        use Symfony\Component\HttpFoundation\ResponseHeaderBag;
        use Doctrine\DBAL\Exception\ConstraintViolationException;
        use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

        class ReporteadorController extends AbstractController
        {
            private \$em;
            private \$filaGeneral;
            private \$ultimaColumna;
            private \$camposTotalizados;
            public function __construct(EntityManagerInterface \$em)
            {
                \$this->em = \$em;
                \$this->filaGeneral = 6;
                \$this->ultimaColumna = '';
                \$this->camposTotalizados = [];
            }

            /**
             * @Route("/Central/Reporteador/generarInforme", name="central_reporteador_generar_informe")
            */
            public function generarInforme(Request \$request)
            {
                /** 
                 * En esta función se genera el contenido html del informe con base al sql almacenado para un reporte específico
                 * -------------------------------------------------------------------------------------------------------------
                 * @access public
                */

                /** Definición de variables */
                /** ----------------------- */

                \$index = 1;
                \$filtros = [];
                \$message = '';
                \$periodo = '';
                \$paginas = [];
                \$cabecera = [];
                \$bd = \$this->em;
                \$plantilla = '';
                \$keyCampos = [];
                \$indexPagina = 1;
                \$paginacion = 100;
                \$tablaTotales = [];
                \$agrupamiento = [];
                \$status = 'success';
                \$totalRegistros = 0;
                \$indexPaginacion = 1;
                \$contenidoInforme = '';
                \$indexTotalPaginas = 1;
                \$camposAgrupacion = [];
                \$botonesPaginator = '';
                \$camposTotalizacion = [];
                \$camposPeriodoValido = [];
                \$contenidoPaginacion = '';
                \$configuracionCampos = [];
                \$anchoContenidoInforme = '';
                \$rellenoOpcionPaginator = '';
                \$iconoBloqueo = 'color:gray;';
                \$listRegistrosPaginacion = [];
                \$conexion = \$bd->getConnection();
                \$listRegistrosBusquedaRapida = [];
                \$accionBloqueo = 'pointer-events:none;';
                \$form = \$request->request->get('filtros_reporteador');
                \$busquedaRapida = \$request->request->get('busquedaRapida');
                \$bloqueoMenu = 'pointer-events:none; opacity:0.6 !important;';
                \$compania = \$bd->getRepository(compania::class)->findOneBy([]);
                \$alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];
                \$informe = \$bd->getRepository(reportes::class)->findOneBy(['id' => \$form['informe']]);
                \$fondo = base64_encode(file_get_contents(\$this->getParameter('imgs_directory').'fondo.jpg'));
                \$logoError = base64_encode(file_get_contents(\$this->getParameter('imgs_directory').'logoActualizado.png'));

                /** Se obtienen los filtros de búsqueda seleccionados */
                /** ------------------------------------------------- */

                \$sqlInforme = \$informe->getSql();
                \$nitCompania = \$compania->getNit();
                \$logo = \$compania->getLogocompania();
                \$nombreInforme = \$informe->getNombre();
                \$pagina = \$request->request->get('pagina');
                \$nombreCompania = strtoupper(\$compania->getNombre());
                preg_match_all('/\[(.*?)\]/', \$sqlInforme, \$camposSQL);
                foreach(\$form as \$key => \$campo){\$filtros['['.\$key.']'] = !empty(\$campo)?\$campo:-1;}
                \$direccionCompania = substr(\$compania->getDireccion(), 0, 50).' - '.\$compania->getTelefonos();

                /** Se valida si las variables definidas en el sql se encuentran en los filtros de búsqueda */
                /** --------------------------------------------------------------------------------------- */

                foreach(\$camposSQL[0] as \$campoSQL)
                {
                    if(!array_key_exists(\$campoSQL, \$filtros))
                    {
                        \$filtros[\$campoSQL] = '-1';
                    }
                }
                \$sqlInforme = strtr(\$sqlInforme, \$filtros);

                /** Se obtiene el json que contiene las configuraciones del informe */
                /** --------------------------------------------------------------- */

                \$tablaTotales['colspan'] = 0;
                \$configuraciones = \$informe->getJson();
                if(!empty(\$configuraciones))
                {
                    if(array_key_exists('campos', \$configuraciones)){\$configuracionCampos = \$configuraciones['campos'];}
                    if(array_key_exists('anchoTabla', \$configuraciones) && !empty(\$configuraciones['anchoTabla'])){\$anchoContenidoInforme = 'width:'.\$configuraciones['anchoTabla'];}
                    if(array_key_exists('paginacion', \$configuraciones) && \$configuraciones['paginacion'] && \$configuraciones['paginacion'] >= 10){\$paginacion = \$configuraciones['paginacion'];}
                    if(array_key_exists('cabecera', \$configuraciones) && is_array(\$configuraciones['cabecera']) && !empty(\$configuraciones['cabecera'])){\$cabecera = \$configuraciones['cabecera'];}
                    if(array_key_exists('agrupamiento', \$configuraciones) && is_array(\$configuraciones['agrupamiento']) && !empty(\$configuraciones['agrupamiento'])){\$agrupamiento = \$configuraciones['agrupamiento'];}
                    if(array_key_exists('totalizacion', \$configuraciones) && !empty(\$configuraciones['totalizacion']) && is_array(\$configuraciones['totalizacion'])){\$camposTotalizacion = \$configuraciones['totalizacion'];}
                    if(array_key_exists('periodo', \$configuraciones) && !empty(\$configuraciones['periodo']))
                    {
                        preg_match_all('/\[(.*?)\]/', \$configuraciones['periodo'], \$campos);
                        if(!empty(\$campos))
                        {
                            foreach(\$campos[0] as \$campo)
                            {
                                if(array_key_exists(\$campo, \$filtros) && date('Y-m-d', strtotime(\$filtros[\$campo])) == \$filtros[\$campo])
                                {
                                    \$fecha = explode('-', \$filtros[\$campo]);
                                    \$mes = \$bd->getRepository(meses::class)->findOneBy(['numero' => \$fecha[1]]);
                                    \$camposPeriodoValido[\$campo] = \$fecha[2].' de '.\$mes->getNombre().' de '.\$fecha[0];
                                }
                            }
                            if(count(\$camposPeriodoValido) == count(\$campos[0]))
                            {
                                \$periodo = strtr(\$configuraciones['periodo'], \$camposPeriodoValido);
                                \$periodo =
                                <<<TWIG
                                <div style="display:flex; align-items:center; gap:5px;">
                                    <i class="fas fa-calendar" style="font-size:11px"></i>
                                    <span class="montserrat-text" style="font-size:11px; width:max-content">\$periodo</span>
                                </div>
                                TWIG;
                            }
                        }
                    }
                }

                /** Se realiza la consulta de los registros */
                /** --------------------------------------- */

                try 
                {
                    \$listRegistros = \$conexion->prepare(\$sqlInforme)->executeQuery()->fetchAll();

                    /** Se filtran los registros de acuerdo a la búsqueda rápida */
                    /** -------------------------------------------------------- */

                    if(\$busquedaRapida != '')
                    {
                        foreach(\$listRegistros as \$registro)
                        {
                            foreach(\$registro as \$campo)
                            {
                                if(strpos(\$campo, \$busquedaRapida) !== false)
                                {
                                    \$listRegistrosBusquedaRapida[] = \$registro;
                                }
                            }
                        }
                        \$listRegistros = \$listRegistrosBusquedaRapida;
                    }
                    
                    /** Se genera la paginación de los registros */
                    /** ---------------------------------------- */

                    \$totalRegistros = count(\$listRegistros);
                    if(\$totalRegistros > 0){\$bloqueoMenu = '';}
                    foreach(\$listRegistros as \$indexRegistro => \$registro)
                    {
                        \$dataRegistro[] = \$registro;
                        if(\$indexPaginacion == \$paginacion || (\$indexRegistro == count(\$listRegistros) - 1))
                        {
                            \$listRegistrosPaginacion[] = \$dataRegistro;
                            \$paginas[] = \$indexTotalPaginas; 
                            \$indexTotalPaginas ++;
                            \$indexPaginacion = 0;
                            \$dataRegistro = [];
                            \$indexPagina ++;
                        }
                        \$indexPaginacion ++;

                        /** Se obtiene la totalización de los campos */
                        /** ---------------------------------------- */

                        foreach(\$camposTotalizacion as \$ct)
                        {
                            if(array_key_exists('campo', \$ct) && array_key_exists(\$ct['campo'], \$registro))
                            {
                                if(array_key_exists(\$ct['campo'], \$this->camposTotalizados))
                                {
                                    \$this->camposTotalizados[\$ct['campo']] = \$this->camposTotalizados[\$ct['campo']] + \$registro[\$ct['campo']];
                                }
                                else
                                {
                                    \$this->camposTotalizados[\$ct['campo']] = \$registro[\$ct['campo']];
                                }
                            }
                        }
                    }

                    /** Se crean las opciones del paginator */
                    /** ----------------------------------- */

                    if(\$pagina > 5)
                    {
                        \$paginas = array_slice(\$paginas, \$pagina - 1, 5);
                        if(count(\$paginas) < 5)
                        {
                            \$paginasCompletar = 5 - count(\$paginas);
                            for(\$i = \$paginasCompletar; \$i >= 1; \$i--)
                            {
                                \$paginasControl[] = \$pagina - \$i; 
                            }
                            \$paginas = array_merge(\$paginasControl, \$paginas);
                        }
                    }
                    else
                    {
                        \$paginas = array_slice(\$paginas, 0, 5);
                    }

                    foreach(\$paginas as \$p)
                    {
                        \$rellenoOpcionPaginator = (\$pagina == \$p)?'background:#17A; color:white;':'background:white';
                        \$botonesPaginator .=
                            <<<TWIG
                            <div class="montserrat paginas" data-action="click->$nombreControlador#seleccionarPagina" data-opc="1" data-pagina="\$p" style=
                            "
                                width:25px; 
                                height:25px; 
                                display:flex;
                                cursor:pointer;
                                border-radius:50%;
                                align-items:center; 
                                justify-content:center; 
                                \$rellenoOpcionPaginator
                            ">
                                \$p
                            </div>
                            TWIG;
                    }

                    /** Se validan las opciones back y next del paginator para asignar los estilos respectivos de acuerdo a la página seleccionada */
                    /** -------------------------------------------------------------------------------------------------------------------------- */

                    \$iconoBotonAnterior = (\$pagina == 1)?\$iconoBloqueo:'';
                    \$accionBotonAnterior = (\$pagina == 1)?\$accionBloqueo:'';
                    \$iconoBotonSiguiente = (\$pagina == count(\$listRegistrosPaginacion))?\$iconoBloqueo:'';
                    \$accionBotonSiguiente = (\$pagina == count(\$listRegistrosPaginacion))?\$accionBloqueo:'';

                    if(!empty(\$listRegistrosPaginacion))
                    {
                        \$listRegistros = \$listRegistrosPaginacion[\$pagina - 1];

                        /** Se valida si existen campos de agrupación configurados */
                        /** ------------------------------------------------------ */

                        if(array_key_exists('campos', \$agrupamiento[0]) && is_array(\$agrupamiento[0]['campos']) && !empty(\$agrupamiento[0]['campos']))
                        {
                            \$keyCampos = array_keys(\$listRegistros[0]);
                            foreach(\$keyCampos as \$campo)
                            {
                                foreach(\$agrupamiento[0]['campos'] as \$a)
                                {
                                    if(\$a['nombre'] == \$campo){\$camposAgrupacion[] = \$campo;}
                                }
                            }
                        }

                        if(!empty(\$camposAgrupacion))
                        {
                            /** Se genera el informe con campos de agrupación */
                            /** --------------------------------------------- */
                            
                            \$listAgrupada = [];
                            \$campoControl = '';
                            \$campoAnterior = '';
                            \$camposReferencia = [];
                            \$divTotalesGenerales = '';
                            \$camposAgrupacion = array_slice(\$camposAgrupacion, 0, 3);
                            
                            /** Se ordena la información de acuerdo a los campos de agrupación configurados en el informe */
                            /** ----------------------------------------------------------------------------------------- */
                    
                            foreach(\$camposAgrupacion as \$index => \$campo)
                            {
                                if(empty(\$campoControl))
                                {
                                    foreach(\$listRegistros as \$registro)
                                    {
                                        \$listAgrupada[\$campo][\$registro[\$campo]] = \$registro[\$campo];
                                    }
                                }
                                else
                                {
                                    if(\$index == 1)
                                    {
                                        foreach(\$listAgrupada[\$campoControl] as \$c)
                                        {
                                            \$camposReferencia[] = \$c;
                                            foreach(\$listRegistros as \$registro)
                                            {
                                                if(\$registro[\$campoControl] == \$c)
                                                {
                                                    \$listAgrupada[\$campo][\$c][\$registro[\$campo]] = \$registro[\$campo];
                                                }
                                            }
                                        }
                                        unset(\$camposReferencia[array_key_last(\$camposReferencia)]);
                                    }
                                    else
                                    {
                                        foreach(\$camposReferencia as \$cr)
                                        {
                                            foreach(\$listAgrupada[\$campoControl][\$cr] as \$c)
                                            {
                                                \$camposReferenciaControl[] = \$c;
                                                foreach(\$listRegistros as \$registro)
                                                {
                                                    if(\$registro[\$campoControl] == \$c)
                                                    {
                                                        \$listAgrupada[\$campo][\$c][\$registro[\$campo]] = \$registro[\$campo];
                                                    }
                                                }
                                            }
                                        }
                                        \$camposReferencia = \$camposReferenciaControl;
                                    }
                                }
                                \$campoControl = \$campo;
                                \$listAgrupada[\$campo]['referencia'] = array_key_exists(\$index + 1, \$camposAgrupacion)?\$camposAgrupacion[\$index + 1]:'registros';
                    
                                /** Se guardan los registros de tal manera que se asocien al último nivel de agrupación */
                                /** ----------------------------------------------------------------------------------- */
                    
                                if(\$listAgrupada[\$campo]['referencia'] == 'registros')
                                {
                                    if(empty(\$camposReferencia))
                                    {
                                        \$campoAnterior = \$camposAgrupacion[0];
                                        foreach(\$listAgrupada[\$campoAnterior] as \$c)
                                        {
                                            foreach(\$listRegistros as \$registro)
                                            {
                                                if(\$registro[\$campoAnterior] == \$c)
                                                {
                                                    foreach(\$camposAgrupacion as \$campo){unset(\$registro[\$campo]);}
                                                    \$listAgrupada['registros'][\$c][] = \$registro;
                                                }
                                            }
                                        }
                                    }
                                    else
                                    {
                                        foreach(\$camposReferencia as \$cr)
                                        {
                                            foreach(\$listAgrupada[\$campoControl][\$cr] as \$c)
                                            {
                                                foreach(\$listRegistros as \$registro)
                                                {
                                                    \$campoAnterior = \$registro[\$camposAgrupacion[count(\$camposAgrupacion) - 2]];
                                                    if(\$campoAnterior == \$cr && \$registro[\$campoControl] == \$c)
                                                    {
                                                        foreach(\$camposAgrupacion as \$campo){unset(\$registro[\$campo]);}
                                                        \$listAgrupada['registros'][\$campoAnterior.\$c][] = \$registro;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            /** Se crea la sección de agrupamiento con todos los campos seleccionados */
                            /** --------------------------------------------------------------------- */

                            \$index = 0;
                            \$indexFila = 0;
                            \$divAgrupacion = '';
                            \$registrosAgrupados = [];
                            \$divAgrupacionGeneral = '';
                            \$divRegistrosAgrupacion = '';
                            foreach(\$listAgrupada as \$key => \$lista)
                            {
                                if(\$key == 'registros'){break;}
                                \$campo = array_filter(\$agrupamiento[0]['campos'], fn(\$item) => \$item['nombre'] == \$key);
                                sort(\$campo);
                                \$titulo = \$campo[0]['titulo'];
                                foreach(\$lista as \$keyFila => \$items)
                                {
                                    if(\$keyFila == 'referencia'){continue;}
                                    \$keyFila = str_replace(' ', '_', \$keyFila);
                                    \$marginTopFila = (\$indexFila == 0)?'':'margin-top:3px;';
                                    if(\$index == 0)
                                    {
                                        \$registrosAgrupados[] = \$keyFila; 
                                        \$nombreAgrupacion = explode('-', \$items);
                                        if(count(\$nombreAgrupacion) > 1)
                                        {
                                            unset(\$nombreAgrupacion[0]);
                                            \$nombreAgrupacion = implode('-', \$nombreAgrupacion);
                                        }
                                        \$divAgrupacionGeneral .=
                                        <<<TWIG
                                        <div class="bg-light montserrat" style=
                                        "
                                            gap:10px; 
                                            width:100%; 
                                            display:flex; 
                                            \$marginTopFila
                                            font-size:11px;
                                            border-radius:5px; 
                                            padding:12px 17px; 
                                            align-items:center; 
                                            border:1px solid #dee2e6; 
                                        ">
                                            <div class="titulo" style="transition:all 0.5s ease; font-size:11px; cursor:pointer" onclick="\$('#\$keyFila').toggle('400')">\$titulo</div>
                                            <i class="fas fa-angle-double-right text-info" style="font-size:10px"></i>
                                            <span class="montserrat-text" style="font-size:11px">\$nombreAgrupacion</span>
                                        </div>
                                        <div id="\$keyFila" style="width:100%; border: 1px solid #dee2e6; padding: 10px; border-radius: 0px 0px 5px 5px; margin-top:-1px;">
                                            replace_\$keyFila
                                        </div>
                                        TWIG;
                                    }
                                    else
                                    {
                                        foreach(\$items as \$keyItem => \$item)
                                        {
                                            \$keyItem = str_replace(' ', '_', \$keyItem);
                                            if(\$index == (count(\$listAgrupada) - 2))
                                            {
                                                \$keyItem = \$keyFila.str_replace(' ', '_', \$keyItem);
                                                \$registrosAgrupados[] = \$keyItem; 
                                            }
                                            \$nombreAgrupacion = explode('-', \$item);
                                            if(count(\$nombreAgrupacion) > 1)
                                            {
                                                unset(\$nombreAgrupacion[0]);
                                                \$nombreAgrupacion = implode('-', \$nombreAgrupacion);
                                            }
                                            \$divAgrupacion .=
                                            <<<TWIG
                                            <div class="bg-light montserrat" style=
                                            "
                                                gap:10px; 
                                                width:100%; 
                                                display:flex; 
                                                margin-top:3px; 
                                                font-size:11px;
                                                border-radius:5px; 
                                                padding:12px 17px; 
                                                align-items:center; 
                                                border:1px solid #dee2e6; 
                                            ">
                                                <div class="titulo" style="transition:all 0.5s ease; font-size:11px; cursor:pointer" onclick="\$('#\$keyItem').toggle('400')">\$titulo</div>
                                                <i class="fas fa-angle-double-right text-info" style="font-size:10px"></i>
                                                <span class="montserrat-text" style="font-size:11px">\$nombreAgrupacion</span>
                                            </div>
                                            <div id="\$keyItem" style="width:100%; border: 1px solid #dee2e6; padding: 10px; border-radius: 0px 0px 5px 5px; margin-top:-1px;">
                                                <div style="display:flex; align-items:center; justify-content:center; width:100%; flex-direction:column">
                                                    replace_\$keyItem
                                                </div>
                                            </div>
                                            TWIG;
                                        }
                                        \$divAgrupacionGeneral = str_replace('replace_'.\$keyFila, \$divAgrupacion, \$divAgrupacionGeneral);
                                        \$divAgrupacion = '';

                                    }
                                    
                                    /** Se agrega a los items del último campo de agrupación los registros correspondientes */
                                    /** ----------------------------------------------------------------------------------- */

                                    if(\$index == (count(\$listAgrupada) - 2))
                                    {
                                        foreach(\$registrosAgrupados as \$indexAgrupacion => \$registros)
                                        {
                                            if(array_key_exists(str_replace('_', ' ', \$registros), \$listAgrupada['registros']))
                                            {
                                                \$divAgrupacion = \$this->crearTablaRegistros(\$request, \$configuraciones, \$listAgrupada['registros'][str_replace('_', ' ', \$registros)], true);
                                                \$divAgrupacionGeneral = str_replace('replace_'.\$registros, \$divAgrupacion, \$divAgrupacionGeneral);
                                            }
                                        }
                                    }
                                    \$indexFila ++;
                                    \$divAgrupacion = '';
                                }
                                \$index ++;
                            }

                            /** Se genera la sección de totales obtenidos a partir de los campos de agrupación */
                            /** ------------------------------------------------------------------------------ */

                            if(!empty(\$this->camposTotalizados))
                            {
                                foreach(\$this->camposTotalizados as \$index => \$ct)
                                {
                                    \$tituloTotal = \$ct[0];
                                    \$valorTotal = number_format(\$ct[1], 2, ',', '.');
                                    \$divTotalesGenerales .= 
                                    <<<TWIG
                                    <tr>
                                        <th class="montserrat">
                                            <div style="background:#f8f9fa; display:flex; align-items:center; border:1px solid #dee2e6; height:31px; padding:0px 15px; width:100%; border-right:none; font-size:11px; border-radius:15px 0px 0px 15px; position:relative; z-index:1; overflow:hidden; color:white">
                                                \$tituloTotal
                                                <div style="
                                                    left: 0;
                                                    z-index: -1;
                                                    width: 100%;
                                                    height: 100%;
                                                    position: absolute;
                                                    background: #17A;
                                                    border-radius: 0px 15px 15px 0px;
                                                "></div>
                                            </div>
                                        </th>
                                        <th class="montserrat">
                                            <div style="background:#f8f9fa; display:flex; align-items:center; justify-content:center; border:1px solid #dee2e6; height:31px; padding:0px 15px; width:30px; border-right:none; border-left:none;">
                                                <i class="fas fa-angle-double-right" style="font-size:10px; color:#17A"></i>
                                            </div>
                                        </th>
                                        <td class="montserrat">
                                            <div style="background:#f8f9fa; display:flex; align-items:center; border:1px solid #dee2e6; height:31px; padding:0px 10px; border-left:none; font-size:11px; border-radius:0px 5px 5px 0px">
                                                \$valorTotal
                                            </div>
                                        </td>
                                    </tr>
                                    <tr><td style="height:5px"></td></tr>
                                    TWIG;
                                }
                                \$divTotalesGenerales =
                                <<<TWIG
                                <div class="animate__animated animate__fadeInRight animate__delay-1s" style="display:flex; align-items:center; justify-content:end; margin-top:15px; width:100%">
                                    <table class="mb-0" border="0" cellpadding="0" cellspacing="0">
                                        \$divTotalesGenerales    
                                    </table>
                                </div>
                                TWIG;
                            }
                            \$contenidoInforme = 
                            <<<TWIG
                            <div style="display:flex; align-items:center; justify-content:center; flex-direction:column; width:100%">
                                \$divAgrupacionGeneral
                                \$divTotalesGenerales
                            </div>
                            TWIG;
                        }
                        else
                        {
                            /** Se genera el informe sin campos de agrupacion */
                            /** --------------------------------------------- */
                            
                            \$contenidoInforme = \$this->crearTablaRegistros(\$request, \$configuraciones, \$listRegistros);
                        }

                        /** Contenido paginación */
                        /** -------------------- */

                        \$contenidoPaginacion =
                        <<<TWIG
                        <div class="list-group-item animate__animated animate__fadeIn" style=
                        "    
                            border: none;
                            display:flex;
                            margin-top:4px;
                            width:fit-content;
                            align-items:center;
                            background:#ececec;
                            justify-content:center;
                            border-radius:5px 0px 16px 0px;
                        ">
                            <table border="0" cellpadding="0" cellspacing="0" style="width:100%">
                                <tr>   
                                    <td>
                                        <div style="display:flex; align-items:center; justify-content:center; gap:5px">
                                            <span class="montserrat" style="font-size:12px">Total registros:</span>
                                            <span class="montserrat-text" style="font-size:12px">\$totalRegistros</span>
                                        </div>
                                    </td>
                                    <td style="width:40px"></td>
                                    <td style="border-left:1px solid #d1d4da; width:40px"></td>
                                    <td>
                                        <div style="display:flex; align-items:center; justify-content:center; gap:3px">
                                            <div class="montserrat paginas" data-action="click->$nombreControlador#seleccionarPagina" data-opc="2" style=
                                            "
                                                width:25px; 
                                                height:25px; 
                                                display:flex;
                                                cursor:pointer; 
                                                background:white;
                                                border-radius:50%;
                                                align-items:center;
                                                \$accionBotonAnterior
                                                justify-content:center; 
                                            ">
                                                <i class="fas fa-caret-left" style="\$iconoBotonAnterior"></i>
                                            </div>
                                            \$botonesPaginator
                                            <div class="montserrat paginas" data-action="click->$nombreControlador#seleccionarPagina" data-opc="3" style=
                                            "
                                                width:25px; 
                                                height:25px; 
                                                display:flex;
                                                cursor:pointer; 
                                                background:white;
                                                border-radius:50%;
                                                align-items:center;
                                                \$accionBotonSiguiente
                                                justify-content:center; 
                                            ">
                                                <i class="fas fa-caret-right" style="\$iconoBotonSiguiente"></i>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        TWIG;
                    }
                    else
                    {
                        \$contenidoInforme =
                        <<<TWIG
                            <div class="text-danger" style="height:50px; font-weight:bold; display:flex; align-items:center; justify-content:center">¡No se encontraron registros para listar!</div>
                        TWIG;
                    }
                } 
                catch(\Exception \$e) 
                {
                    \$status = 'error';
                    \$contenidoInforme = \$this->renderView('Central/Reporteador/frameErrorInforme.html.twig', 
                    [
                        'line' => \$e->getLine(), 
                        'file' => \$e->getFile(), 
                        'message' => \$e->getMessage()
                    ]);
                }

                /** Se genera la plantilla del informe */
                /** ---------------------------------- */

                \$plantilla =
                <<<TWIG
                <div class="list-group-item p-0" style="width:100%; border:1px solid #d1d4da; border-radius:6px 6px 0px 0px; display:flex; align-items:center; justify-content:center" contenteditable="false">
                    <div class="row animate__animated animate__fadeIn" style=
                    "
                        width:100%; 
                        overflow:hidden; 
                        background:white; 
                        position:relative; 
                        border-radius:5px;
                        padding:12px 15px 20px 15px; 
                    ">
                        <div class="col-12">
                            <div style=
                            "   
                                top: -28px;
                                z-index: -1;
                                left: -15px;
                                height: 112px;
                                overflow: hidden;
                                position: absolute;
                                background: #f8f9fa;
                                width: calc(100% + 30px);
                                border-radius: 0px 0px 12px 12px;
                                filter: drop-shadow(2px 2px 6px gray);
                            ">
                                <img src="data:image;base64,\$fondo" style=
                                "
                                    left: 0px;
                                    opacity: 0.1;
                                    width: 520px;
                                    height: 330px;
                                    position: absolute;
                                ">
                                <img src="data:image;base64,\$fondo" style=
                                "
                                    left: 520px;
                                    opacity: 0.1;
                                    width: 520px;
                                    height: 330px;
                                    position: absolute;
                                ">
                                <img src="data:image;base64,\$fondo" style=
                                "
                                    left: 1040px;
                                    opacity: 0.1;
                                    width: 520px;
                                    height: 330px;
                                    position: absolute;
                                ">
                                <img src="data:image;base64,\$fondo" style=
                                "
                                    left: 1560px;
                                    opacity: 0.1;
                                    width: 520px;
                                    height: 330px;
                                    position: absolute;
                                ">
                            </div>
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px">
                                <div style="display:flex; align-items:center; gap:20px; flex:4">
                                    <div style=
                                    "
                                        width: 70px;
                                        display: flex;
                                        padding: 9px;
                                        height: 70px;
                                        background: white;
                                        border-radius: 50%;
                                        align-items: center;
                                        justify-content: center;
                                        border: 2px solid #d2d4da;
                                    ">
                                        <img src="data:image;base64,\$logo" style="width:100%; height:100%; object-fit:contain">
                                    </div>
                                    <div style="display:flex; justify-content:center; flex-direction:column; gap:3px">
                                        <span class="montserrat">\$nombreCompania</span>
                                        <div style="display:flex; align-items:center; gap:5px">
                                            <i class="fas fa-circle-check" style="font-size:11px"></i>
                                            <span class="montserrat" style="font-size:11px; color:#2f2f2f">NIT:</span>
                                            <span class="montserrat-text" style="font-size:11px;">\$nitCompania</span>
                                        </div>
                                        <div style="display:flex; align-items:center; gap:5px">
                                            <i class="fas fa-location-dot" style="font-size:11px"></i>
                                            <span class="montserrat-text" style="font-size:11px; margin-left:4px; width:max-content">\$direccionCompania</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="animate__animated animate__fadeIn" style="position:relative; width:fit-content; display:flex; align-items:center; justify-content:center; gap:5px; flex:4; margin-left:20px">
                                    <div style="display:flex; justify-content:center; flex-direction:column; gap:2px">
                                        <div style="display:flex; align-items:center; gap:5px;">
                                            <i class="fas fa-info-circle" style="font-size:11px"></i>
                                            <span class="montserrat" style="font-size:13px;">\$nombreInforme</span>
                                        </div>
                                        \$periodo
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; justify-content:end; flex:4; position:relative">
                                    <div class="menuReporteador" data-action="click->$nombreControlador#showMenuReporteador" data-opc="0" style=
                                        "
                                            width:0px; 
                                            height:0px; 
                                            \$bloqueoMenu
                                            display:flex; 
                                            padding:15px;
                                            cursor:pointer; 
                                            border-radius:50%;
                                            background:#d2d4da; 
                                            align-items:center; 
                                            justify-content:center; 
                                            transition:all 0.5s ease; 
                                        ">
                                        <i class="fas fa-bars" style="transition:all 0.5s ease; font-size:13px"></i>
                                    </div>  
                                    <div id="menuReporteador" transition-style="in:custom:circle-swoop" style=
                                        "
                                            top:36px; 
                                            display:none; 
                                            width: 220px;
                                            position:absolute; 
                                            align-items:start; 
                                            padding: 15px 2px; 
                                            background: white; 
                                            border-radius: 5px; 
                                            flex-direction:column; 
                                            transition:all 0.5s ease; 
                                            border: 1px solid #d2d4da; 
                                        ">
                                        <div class="itemMenu" style="cursor:pointer; border-radius:3px; display:flex; align-items:center; gap:10px; padding:5px 15px; width:100%" data-action="click->$nombreControlador#descargarPDF">
                                            <div style="width:16px">
                                                <i class="far fa-file-pdf text-danger" style="font-size:15px"></i>
                                            </div>
                                            <i class="fas fa-angle-double-right flecha text-danger" style="opacity:0; font-size:9px; transition:all 0.5s ease"></i>
                                            <span class="montserrat-text" style="font-size:12px; margin-left:-19px; transition:all 0.5s ease">Descargar PDF</span>
                                        </div>
                                        <div class="itemMenu" style="cursor:pointer; border-radius:3px; display:flex; align-items:center; gap:10px; padding:5px 15px; width:100%" data-action="click->$nombreControlador#descargarExcel">
                                            <div style="width:16px">
                                                <i class="far fa-file-excel text-success" style="font-size:15px"></i>
                                            </div>
                                            <i class="fas fa-angle-double-right flecha text-success" style="opacity:0; font-size:9px; transition:all 0.5s ease"></i>
                                            <span class="montserrat-text" style="font-size:12px; margin-left:-19px; transition:all 0.5s ease">Descargar EXCEL</span>
                                        </div>
                                    </div>
                                    <div id="loaderDescargaInforme" style=
                                    "
                                        gap:7px; 
                                        opacity:0;
                                        width:230px; 
                                        right:-260px;
                                        display:flex; 
                                        background:white; 
                                        border-radius:6px;
                                        position:absolute; 
                                        padding:12px 15px; 
                                        align-items:center; 
                                        transition:all 1s ease;
                                        border:1px solid #dfdfdf; 
                                    ">
                                        <div style="display:flex; align-items:center; justify-content:center; border-radius:5px; background:#DC354526; padding:7px" id="divIconoDescarga">
                                            <i class="far fa-file-pdf text-danger" style="font-size:15px"></i>
                                        </div>
                                        <div style="width:100%; display:flex; justify-content:center; gap:1px; flex-direction:column">
                                            <div style="display:flex; align-items:center; justify-content:center; gap:5px">
                                                <div class="progress" style="height:8px; width:100%">
                                                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuemin="0" aria-valuemax="100" style="width: 0%" id="barraProgresoDescarga"></div>
                                                </div>
                                                <div class="montserrat" style="font-size:9px; width:30px; text-align:end" id="porcentajeDescarga">0%</div>
                                            </div>
                                            <span class="montserrat" style="font-size:10px;">Descargando informe</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="animate__animated animate__fadeInLeft" style="position:relative; margin-top:50px; margin-left:15px; width:fit-content; display:flex; align-items:center; \$bloqueoMenu">
                                <button id="btnBusquedaRapida" style="transition:all 0.5s ease; position:absolute; border-radius:50%; right:6px; background:#17A; color:white" class="btn btn-sm" data-action="$nombreControlador#busquedaRapida" data-opc="1"><i class="fas fa-search" style="font-size:12px"></i></button>
                                <input id="busquedaRapida" class="form-control buscar montserrat-text" type="text" placeholder="Búsqueda rápida" data-$nombreControlador-target="busquedaRapida" style=
                                "
                                    width:220px; 
                                    height:36px; 
                                    font-size:12px; 
                                    transition:all 0.5s ease;
                                    padding:0px 53px 0px 19px;
                                    border-radius:20px 20px 20px 5px; 
                                " data-action="keypress->$nombreControlador#busquedaRapida" data-opc="2" value="\$busquedaRapida">
                            </div>
                            <hr style="margin-left:15px; margin-right:15px">
                            <div class="animate__animated animate__fadeIn animate__delay-1s listadoTabla" style="margin-top:25px; padding:3px; overflow-y:auto; overflow-x:auto; transition:all 0.5s ease">
                                <div style="display:flex; align-items:center; justify-content:center; \$anchoContenidoInforme">
                                    \$contenidoInforme
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                \$contenidoPaginacion
                <input type="hidden" id="paginaHidden" value="\$pagina" data-$nombreControlador-target="paginaHidden">
                <input type="hidden" id="busquedaRapidaHidden" value="\$busquedaRapida" data-$nombreControlador-target="busquedaRapidaHidden">
                TWIG;
                return new Response(json_encode(['status' => \$status, 'message' => \$message, 'plantilla' => \$plantilla]));
            }
            
            public function crearTablaRegistros(Request \$request, \$configuraciones, \$listRegistros, \$agrupacion = false)
            {   
                /** 
                    * En esta función se crea la tabla principal del informe, la cual contiene todos los registros de la página seleccionada
                    * ----------------------------------------------------------------------------------------------------------------------
                    * @access public
                */

                /** Definición de variables */
                /** ----------------------- */

                \$index = 1;
                \$tdCampo = '';
                \$cabecera = [];
                \$trTotales = '';
                \$thCabecera = '';
                \$trCabecera = '';
                \$divRelleno = '';
                \$estiloBordes = '';
                \$rellenoCampo = '';
                \$filasInforme = '';
                \$tablaTotales = [];
                \$titulosInforme = '';
                \$contenidoInforme = '';
                \$camposTotalizacion = [];
                \$tablaTotales['colspan'] = 0;
                \$camposTotalizacionAgrupamiento = [];
                \$camposTotalizados = \$this->camposTotalizados;
                \$ruta = \$request->getScheme().'://'.\$request->server->get('HTTP_HOST');
                \$alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];

                /** Se obtiene el json que contiene las configuraciones del informe */
                /** --------------------------------------------------------------- */

                if(!empty(\$configuraciones))
                {
                    if(array_key_exists('campos', \$configuraciones)){\$configuracionCampos = \$configuraciones['campos'];}
                    if(array_key_exists('cabecera', \$configuraciones) && is_array(\$configuraciones['cabecera']) && !empty(\$configuraciones['cabecera'])){\$cabecera = \$configuraciones['cabecera'];}
                    if(array_key_exists('totalizacion', \$configuraciones['agrupamiento'][0]) && !empty(\$configuraciones['agrupamiento'][0]['totalizacion']) && is_array(\$configuraciones['agrupamiento'][0]['totalizacion']))
                    {
                        \$camposTotalizados = [];
                        \$camposTotalizacion = \$configuraciones['agrupamiento'][0]['totalizacion'];
                    }
                }

                /** Se obtiene la totalización de los campos */
                /** ---------------------------------------- */

                if(\$agrupacion)
                {
                    foreach(\$listRegistros as \$indexRegistro => \$registro)
                    {
                        foreach(\$camposTotalizacion as \$ct)
                        {
                            if(array_key_exists('campo', \$ct) && array_key_exists(\$ct['campo'], \$registro))
                            {
                                if(array_key_exists(\$ct['campo'], \$camposTotalizados))
                                {
                                    \$camposTotalizados[\$ct['campo']] = \$camposTotalizados[\$ct['campo']] + \$registro[\$ct['campo']];
                                }
                                else
                                {
                                    \$camposTotalizados[\$ct['campo']] = \$registro[\$ct['campo']];
                                }
                            }
                        }
                    }
                }

                /** Se genera la tabla de registros */
                /** ------------------------------- */
                
                foreach(\$listRegistros as \$indexRegistro => \$registro)
                {   
                    \$finColspan = false;
                    \$rellenoCampo = ((\$indexRegistro + 1) % 2 == 0) ? '#17A2B814':'';
                    foreach(\$registro as \$key => \$campo)
                    {
                        /** Se crean los títulos del informe con sus respectivos estilos */
                        /** ------------------------------------------------------------ */
                        
                        \$alineacionCampo = 'left';
                        \$alineacionTitulo = 'center';
                        \$titulo = ucfirst(str_replace('_', ' ', \$key));
                        \$configuracionCampo = array_filter(\$configuracionCampos, fn(\$item) => \$item['nombre'] == \$key);

                        /** Se validan las configuraciones de cada campo */
                        /** -------------------------------------------- */

                        sort(\$configuracionCampo);
                        if(!empty(\$configuracionCampo))
                        {
                            /** Configuraciones del título */
                            /** -------------------------- */

                            if(array_key_exists('titulo', \$configuracionCampo[0])){\$titulo = \$configuracionCampo[0]['titulo'];}
                            if(array_key_exists('alineacionTitulo', \$configuracionCampo[0]) && array_key_exists(\$configuracionCampo[0]['alineacionTitulo'], \$alineaciones))
                            {
                                \$alineacionTitulo = \$alineaciones[\$configuracionCampo[0]['alineacionTitulo']];
                            }

                            /** Configuraciones de campos */
                            /** ------------------------- */

                            if(array_key_exists('alineacionCampo', \$configuracionCampo[0]) && array_key_exists(\$configuracionCampo[0]['alineacionCampo'], \$alineaciones))
                            {
                                \$alineacionCampo = \$alineaciones[\$configuracionCampo[0]['alineacionCampo']];
                            }

                            if(array_key_exists('tipoDato', \$configuracionCampo[0]) && \$configuracionCampo[0]['tipoDato'] == 'moneda')
                            {
                                \$campo = number_format(\$campo, 2, ',', '.');
                            }

                            if(array_key_exists('tipoDato', \$configuracionCampo[0]) && \$configuracionCampo[0]['tipoDato'] == 'numero')
                            {
                                \$campo = number_format(\$campo, 2, '.', '');
                            }

                            if(array_key_exists('tipoDato', \$configuracionCampo[0]) && \$configuracionCampo[0]['tipoDato'] == 'fecha')
                            {
                                \$campo = (new \DateTime(\$campo))->format('Y-m-d');
                            }

                            if(array_key_exists('html', \$configuracionCampo[0]) && !empty(\$configuracionCampo[0]['html']))
                            {
                                \$html = \$configuracionCampo[0]['html'];
                                if(is_array(\$html))
                                {
                                    \$valorCondicion = array_key_exists('valor', \$html)?\$html['valor']:'';
                                    if(!empty(\$valorCondicion))
                                    {
                                        if(\$valorCondicion == \$campo)
                                        {
                                            \$html = array_key_exists('si', \$html)?\$html['si']:\$campo;
                                        }
                                        else
                                        {
                                            \$html = array_key_exists('no', \$html)?\$html['no']:\$campo;
                                        }
                                    }
                                    else
                                    {
                                        \$html = \$campo;
                                    }
                                }
                                \$html = str_replace('\$campo', \$campo, \$html);

                                /** Se valida si el campo tiene una ruta configurada */
                                /** ------------------------------------------------ */

                                if(array_key_exists('ruta', \$configuracionCampo[0]) && is_array(\$configuracionCampo[0]['ruta']) && !empty(\$configuracionCampo[0]['ruta']) && array_key_exists('nombre', \$configuracionCampo[0]['ruta']))
                                {
                                    /** Se valida si existen parámetros configurados */
                                    /** -------------------------------------------- */

                                    \$parametros = [];
                                    if(array_key_exists('parametros', \$configuracionCampo[0]['ruta']) && is_array(\$configuracionCampo[0]['ruta']['parametros']) && !empty(\$configuracionCampo[0]['ruta']['parametros']))
                                    {
                                        \$parametros = str_replace('\$campo', \$campo, json_encode(\$configuracionCampo[0]['ruta']['parametros']));
                                        \$parametros = json_decode(\$parametros, true);
                                    }
                                    \$rutaCampo = \$ruta.\$this->generateUrl(\$configuracionCampo[0]['ruta']['nombre'], \$parametros);
                                    \$html = str_replace('\$ruta', \$rutaCampo, \$html);
                                }
                                \$campo = \$html;
                            }
                        }

                        if(\$index == 1)
                        {
                            \$estiloBordesCampo = 'border-left:1px solid #d0d4da';
                            \$claseTitulo = empty(\$cabecera)?'class="tituloInicial"':'';
                            \$estiloBordesTitulo = empty(\$cabecera)?'border-radius:10px 0px 0px 3px':'';
                        }

                        if(\$index == count(\$registro))
                        {
                            \$estiloBordesCampo = 'border-right:1px solid #d0d4da';
                            \$claseTitulo = empty(\$cabecera)?'class="tituloFinal"':'';
                            \$estiloBordesTitulo = empty(\$cabecera)?'border-radius:0px 10px 3px 0px; border-right:1px solid #d0d4da':'border-right:1px solid #d0d4da';
                        }

                        /** Se crean los títulos del informe */
                        /** -------------------------------- */

                        if(\$indexRegistro == 0)
                        {   
                            \$titulosInforme .=
                            <<<TWIG
                            <th>
                                <div \$claseTitulo style="transition:all 0.5s ease; background:#f8f9fa; display:flex; align-items:center; justify-content:\$alineacionTitulo; padding:9px 10px 9px 12px; font-size:12px; border:1px solid #d0d4da; height:38px; border-right:none; \$estiloBordesTitulo">
                                    \$titulo
                                </div>
                            </th>
                            TWIG;
                            \$divRelleno = '';
                            \$claseTitulo = '';
                            \$estiloBordesTitulo = '';
                        }

                        /** Se crea cada registro del informe */
                        /** --------------------------------- */

                        \$tdCampo .= 
                        <<<TWIG
                        <td style="padding:7px; font-size:12px; border-bottom:1px solid #E2E2E2; text-align:\$alineacionCampo; \$estiloBordesCampo">\$campo</td>
                        TWIG;

                        /** Se diseña la tabla de acuerdo a los totales configurados */
                        /** -------------------------------------------------------- */

                        if(\$indexRegistro == array_key_last(\$listRegistros))
                        {
                            if(array_key_exists(\$key, \$camposTotalizados))
                            {
                                \$finColspan = true;
                                \$total = \$camposTotalizados[\$key];
                                if(!empty(\$configuracionCampo))
                                {
                                    if(array_key_exists('tipoDato', \$configuracionCampo[0]) && \$configuracionCampo[0]['tipoDato'] == 'moneda')
                                    {
                                        \$total = number_format(\$total, 2, ',', '.');
                                    }

                                    if(array_key_exists('tipoDato', \$configuracionCampo[0]) && \$configuracionCampo[0]['tipoDato'] == 'numero')
                                    {
                                        \$total = number_format(\$total, 2, '.', '');
                                    }

                                    if(array_key_exists('alineacionCampo', \$configuracionCampo[0]) && array_key_exists(\$configuracionCampo[0]['alineacionCampo'], \$alineaciones))
                                    {
                                        \$alineacionCampo = \$alineaciones[\$configuracionCampo[0]['alineacionCampo']];
                                    }
                                }
                                \$tablaTotales['campo'.\$index] = [\$total, \$alineacionCampo];
                            }
                            else
                            {
                                if(!\$finColspan)
                                {
                                    \$tablaTotales['colspan'] = \$tablaTotales['colspan'] + 1;
                                }
                                else
                                {
                                    \$tablaTotales['campo'.\$index] = '';
                                }
                            }

                            /** Se obtienen los títulos de los totales generales */
                            /** ------------------------------------------------ */

                            if(array_key_exists(\$key, \$this->camposTotalizados))
                            {
                                if(!is_array(\$this->camposTotalizados[\$key]))
                                {
                                    \$this->camposTotalizados[\$key] = [\$titulo, \$this->camposTotalizados[\$key]];
                                }
                            }
                        }
                        \$estiloBordesCampo = '';
                        \$index ++;
                    }
                    \$filasInforme .=
                    <<<TWIG
                        <tr class="registroInfome" style="transition:all 0.2s ease; background:\$rellenoCampo">
                            \$tdCampo
                        </tr>
                    TWIG;
                    \$tdCampo = '';
                    \$index = 1;
                }

                /** Se crea la sección de la cabecera */
                /** --------------------------------- */
                
                if(!empty(\$cabecera))
                {
                    foreach(\$cabecera as \$index => \$c)
                    {
                        \$tituloCabecera = \$c['nombre'];
                        \$colSpanCabecera = \$c['colspan'];

                        if(\$index == 0)
                        {
                            \$estiloBordesTitulo = 'border-radius:10px 0px 0px 0px';
                        }

                        if(\$index == (count(\$cabecera) - 1))
                        {
                            \$estiloBordesTitulo = 'border-radius:0px 10px 0px 0px; border-right:1px solid #d0d4da';
                        }

                        if(count(\$cabecera) == 1)
                        {
                            \$estiloBordesTitulo = 'border-radius:10px 10px 0px 0px; border-right:1px solid #d0d4da';
                        }

                        \$thCabecera .=
                        <<<TWIG
                        <th colspan="\$colSpanCabecera">
                            <div style="transition:all 0.5s ease; background:#f8f9fa; display:flex; align-items:center; justify-content:center; padding:9px 10px 9px 12px; font-size:12px; border:1px solid #d0d4da; border-bottom:none; border-right:none; \$estiloBordesTitulo">
                                \$tituloCabecera
                            </div>
                        </th>
                        TWIG;
                        \$estiloBordes = '';
                    }
                    \$trCabecera = 
                    <<<TWIG
                    <tr class="montserrat text-primary" style="position:sticky; top:-3px">
                        \$thCabecera
                    </tr>
                    TWIG;
                }

                /** Se crea la sección de totales */
                /** ----------------------------- */

                \$index = 0;
                \$tdTotal = '';
                if(!empty(\$camposTotalizados))
                {
                    foreach(\$tablaTotales as \$key => \$campoTotal)
                    {
                        if(\$key == 'colspan' && \$campoTotal > 0)
                        {
                            \$tdTotal .= 
                            <<<TWIG
                            <th colspan="\$campoTotal">
                                <div style="background:#f8f9fa; display:flex; align-items:center; justify-content:right; padding:0px 10px 0px 12px; height:38px; font-size:12px; border:1px solid #d0d4da; border-right:none; border-top:none; border-radius:0px 0px 0px 10px">
                                    <div>
                                        <div class="ripple" style="position:relative">
                                            <i 
                                                style="position: absolute; top:1px; left:1px; font-size:12px" 
                                                class="fas fa-info-circle text-primary" 
                                            ></i>
                                        </div>
                                    </div>
                                    <div style="display:flex; align-items:center; justify-content:center; gap:7px">
                                        <span class="montserrat text-primary" style="margin-left:8px; font-size:12px; margin-top:1px">Totales</span>
                                        <i class="fas fa-angle-double-right text-primary" style="font-size:10px; margin-top:1px"></i>
                                    </div>
                                </div>
                            </th>
                            TWIG;
                        }
                        else
                        {
                            \$campo = !empty(\$campoTotal)?\$campoTotal[0]:'';
                            \$alineacionCampo = !empty(\$campoTotal)?\$campoTotal[1]:'';
                            \$estiloBordes = (\$index == (count(\$tablaTotales) - 1))?'border-bottom:1px solid #d0d4da; border-right:1px solid #d0d4da; border-radius:0px 0px 10px 0px;':'border-bottom:1px solid #d0d4da; border-right:none';
                            \$tdTotal .= 
                            <<<TWIG
                            <th>
                                <div style="background:#f8f9fa; display:flex; align-items:center; justify-content:\$alineacionCampo; padding:0px 10px 0px 12px; height:38px; font-size:12px; \$estiloBordes">
                                    \$campo
                                </div>
                            </th>
                            TWIG;
                        }
                        \$index ++;
                    }
                    \$trTotales = 
                    <<<TWIG
                        <tr>
                            \$tdTotal
                        </tr>
                    TWIG;
                }

                /** Contenido del informe */
                /** --------------------- */

                \$contenidoInforme =
                <<<TWIG
                <table border="0" cellpadding="0" cellspacing="0" class="mb-0" style="width:100%">
                    \$trCabecera
                    <tr class="montserrat text-primary" style="position:sticky; top:-3px">
                        \$titulosInforme
                    </tr>
                    \$filasInforme
                    \$trTotales
                </table>
                TWIG;
                return \$contenidoInforme;
            }

            /**
             * @Route("/Central/Reporteador/descargarInformePDF", name="central_reporteador_descargar_informe_pdf")
            */
            public function descargarInformePDF(Request \$request)
            {
                /** 
                    * En esta función se descarga el informe en formato PDF. Para ello, se emplea el sql configurado en el informe y se obtiene
                    * la información respectiva a partir de los filtros de búsqueda seleccionados. Además, se genera la plantila html con las
                    * especificaciones que se hayan definido para cada campo.
                    * -------------------------------------------------------------------------------------------------------------------------
                    * @access public
                */
                
                /** Definición de variables */
                /** ----------------------- */

                \$index = 1;
                \$filtros = [];
                \$message = '';
                \$periodo = '';
                \$cabecera = [];
                \$bd = \$this->em;
                \$plantilla = '';
                \$keyCampos = [];
                set_time_limit(0);
                \$tablaTotales = [];
                \$agrupamiento = [];
                \$contenidoPDF = '';
                \$totalRegistros = 0;
                \$camposAgrupacion = [];
                \$configuracionesPDF = [];
                \$camposTotalizacion = [];
                \$camposPeriodoValido = [];
                \$configuracionCampos = [];
                \$pdfOptions = new Options();
                \$conexion = \$bd->getConnection();
                \$session = \$request->getSession();
                \$listRegistrosBusquedaRapida = [];
                \$form = \$request->request->get('filtros_reporteador');
                \$busquedaRapida = \$request->request->get('busquedaRapida');
                \$compania = \$bd->getRepository(compania::class)->findOneBy([]);
                \$alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];
                \$informe = \$bd->getRepository(reportes::class)->findOneBy(['id' => \$form['informe']]);
                \$pdfOptions->set('defaultFont', 'Helvetica')->set('sizeFont', '9')->setIsRemoteEnabled(true);
                \$fechaActual = (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');

                try 
                {            
                    /** Se obtienen los filtros de búsqueda seleccionados */
                    /** ------------------------------------------------- */

                    \$sqlInforme = \$informe->getSql();
                    \$nitCompania = \$compania->getNit();
                    \$logo = \$compania->getLogocompania();
                    \$nombreInforme = \$informe->getNombre();
                    \$telefonoCompania = \$compania->getTelefonos();
                    \$direccionCompania = \$compania->getDireccion();
                    \$nombreCompania = strtoupper(\$compania->getNombre());
                    preg_match_all('/\[(.*?)\]/', \$sqlInforme, \$camposSQL);
                    foreach(\$form as \$key => \$campo){\$filtros['['.\$key.']'] = !empty(\$campo)?\$campo:-1;}

                    /** Se valida si las variables definidas en el sql se encuentran en los filtros de búsqueda */
                    /** --------------------------------------------------------------------------------------- */
    
                    foreach(\$camposSQL[0] as \$campoSQL)
                    {
                        if(!array_key_exists(\$campoSQL, \$filtros))
                        {
                            \$filtros[\$campoSQL] = '-1';
                        }
                    }
                    \$sqlInforme = strtr(\$sqlInforme, \$filtros);

                    /** Se obtiene el json que contiene las configuraciones del informe */
                    /** --------------------------------------------------------------- */

                    \$tablaTotales['colspan'] = 0;
                    \$configuraciones = \$informe->getJson();
                    if(!empty(\$configuraciones))
                    {
                        if(array_key_exists('campos', \$configuraciones)){\$configuracionCampos = \$configuraciones['campos'];}
                        if(array_key_exists('pdf', \$configuraciones) && !empty(\$configuraciones['pdf']) && is_array(\$configuraciones['pdf'])){\$configuracionesPDF = \$configuraciones['pdf'];}
                        if(array_key_exists('cabecera', \$configuraciones) && is_array(\$configuraciones['cabecera']) && !empty(\$configuraciones['cabecera'])){\$cabecera = \$configuraciones['cabecera'];}
                        if(array_key_exists('agrupamiento', \$configuraciones) && is_array(\$configuraciones['agrupamiento']) && !empty(\$configuraciones['agrupamiento'])){\$agrupamiento = \$configuraciones['agrupamiento'];}
                        if(array_key_exists('totalizacion', \$configuraciones) && !empty(\$configuraciones['totalizacion']) && is_array(\$configuraciones['totalizacion'])){\$camposTotalizacion = \$configuraciones['totalizacion'];}
                        if(array_key_exists('periodo', \$configuraciones) && !empty(\$configuraciones['periodo']))
                        {
                            preg_match_all('/\[(.*?)\]/', \$configuraciones['periodo'], \$campos);
                            if(!empty(\$campos))
                            {
                                foreach(\$campos[0] as \$campo)
                                {
                                    if(array_key_exists(\$campo, \$filtros) && date('Y-m-d', strtotime(\$filtros[\$campo])) == \$filtros[\$campo])
                                    {
                                        \$fecha = explode('-', \$filtros[\$campo]);
                                        \$mes = \$bd->getRepository(meses::class)->findOneBy(['numero' => \$fecha[1]]);
                                        \$camposPeriodoValido[\$campo] = \$fecha[2].' de '.\$mes->getNombre().' de '.\$fecha[0];
                                    }
                                }
                                if(count(\$camposPeriodoValido) == count(\$campos[0]))
                                {
                                    \$periodo = strtr(\$configuraciones['periodo'], \$camposPeriodoValido);
                                }
                            }
                        }
                    }

                    /** Se realiza la consulta de los registros */
                    /** --------------------------------------- */

                    \$listRegistros = \$conexion->prepare(\$sqlInforme)->executeQuery()->fetchAll();

                    /** Se filtran los registros de acuerdo a la búsqueda rápida */
                    /** -------------------------------------------------------- */

                    if(\$busquedaRapida != '')
                    {
                        foreach(\$listRegistros as \$registro)
                        {
                            foreach(\$registro as \$campo)
                            {
                                if(strpos(\$campo, \$busquedaRapida) !== false)
                                {
                                    \$listRegistrosBusquedaRapida[] = \$registro;
                                }
                            }
                        }
                        \$listRegistros = \$listRegistrosBusquedaRapida;
                    }

                    /** Se obtiene la totalización de los campos */
                    /** ---------------------------------------- */

                    \$totalRegistros = count(\$listRegistros);
                    foreach(\$listRegistros as \$indexRegistro => \$registro)
                    {
                        /** Se obtiene la totalización de los campos */
                        /** ---------------------------------------- */

                        foreach(\$camposTotalizacion as \$ct)
                        {
                            if(array_key_exists('campo', \$ct) && array_key_exists(\$ct['campo'], \$registro))
                            {
                                if(array_key_exists(\$ct['campo'], \$this->camposTotalizados))
                                {
                                    \$this->camposTotalizados[\$ct['campo']] = \$this->camposTotalizados[\$ct['campo']] + \$registro[\$ct['campo']];
                                }
                                else
                                {
                                    \$this->camposTotalizados[\$ct['campo']] = \$registro[\$ct['campo']];
                                }
                            }
                        }
                    }

                    /** Se valida si existen campos de agrupación configurados */
                    /** ------------------------------------------------------ */

                    if(array_key_exists('campos', \$agrupamiento[0]) && is_array(\$agrupamiento[0]['campos']) && !empty(\$agrupamiento[0]['campos']))
                    {
                        \$keyCampos = array_keys(\$listRegistros[0]);
                        foreach(\$keyCampos as \$campo)
                        {
                            foreach(\$agrupamiento[0]['campos'] as \$a)
                            {
                                if(\$a['nombre'] == \$campo){\$camposAgrupacion[] = \$campo;}
                            }
                        }
                    }

                    if(!empty(\$camposAgrupacion))
                    {
                        /** Se genera el informe con campos de agrupación */
                        /** --------------------------------------------- */
                        
                        \$listAgrupada = [];
                        \$campoControl = '';
                        \$campoAnterior = '';
                        \$camposReferencia = [];
                        \$divTotalesGenerales = '';
                        \$camposAgrupacion = array_slice(\$camposAgrupacion, 0, 3);
                        
                        /** Se ordena la información de acuerdo a los campos de agrupación configurados en el informe */
                        /** ----------------------------------------------------------------------------------------- */

                        \$keyAgrupacion = [];
                        foreach(\$listRegistros as \$registro)
                        {
                            foreach(\$camposAgrupacion as \$campo)
                            {
                                \$keyAgrupacion[] = \$registro[\$campo];
                                unset(\$registro[\$campo]);
                            }
                            \$keyAgrupacion = implode('_', \$keyAgrupacion);
                            \$listAgrupada[\$keyAgrupacion][] = \$registro;
                            \$keyAgrupacion = [];
                        }

                        /** Se crea la sección de agrupamiento con todos los campos seleccionados */
                        /** --------------------------------------------------------------------- */

                        \$tituloAgrupado = [];
                        \$divAgrupacionGeneral = '';
                        \$camposAgrupadosCabecera = [];
                        foreach(\$listAgrupada as \$key => \$registros)
                        {
                            \$camposAgrupadosCabecera = explode('_', \$key);
                            foreach(\$camposAgrupadosCabecera as \$keyCampoAgrupado => \$campoAgrupado)
                            {
                                \$key = \$camposAgrupacion[\$keyCampoAgrupado];
                                \$campo = array_filter(\$agrupamiento[0]['campos'], fn(\$item) => \$item['nombre'] == \$key);
                                sort(\$campo);
                                \$titulo = strip_tags(\$campo[0]['titulo']);
                                \$campoAgrupado = explode('-', \$campoAgrupado);
                                \$campoAgrupado = (count(\$campoAgrupado) > 1)?\$campoAgrupado[1]:\$campoAgrupado[0];
                                \$tituloAgrupado[] = \$titulo.' » '.\$campoAgrupado;
                            }
                            \$tituloAgrupado = implode('  |  ', \$tituloAgrupado);

                            /** Se crea la tabla de cada agrupación con sus respectivos registros */
                            /** ----------------------------------------------------------------- */

                            \$tablaRegistros = \$this->crearTablaRegistrosPDF(\$request, \$configuraciones, \$registros, true);                    
                            \$divAgrupacionGeneral .=
                            <<<TWIG
                            <div style=
                            "
                                margin-top:5px;
                                padding:12px 17px;
                                background:#f2f2f2;
                                border:1px solid gray; 
                                border-radius:5px 5px 0px 0px; 
                            ">
                                <table border="0" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <th>\$tituloAgrupado</th>
                                    </tr>
                                </table>
                            </div>
                            <div style="border: 1px solid gray; padding:10px; border-radius: 0px 0px 5px 5px; margin-top:-1px;">
                                \$tablaRegistros
                            </div>
                            TWIG;
                            \$tituloAgrupado = [];
                        }

                        /** Se genera la sección de totales obtenidos a partir de los campos de agrupación */
                        /** ------------------------------------------------------------------------------ */

                        if(!empty(\$this->camposTotalizados))
                        {
                            foreach(\$this->camposTotalizados as \$ct)
                            {
                                \$tituloTotal = \$ct[0];
                                \$valorTotal = number_format(\$ct[1], 2, ',', '.');
                                \$divTotalesGenerales .= 
                                <<<TWIG
                                    <tr>
                                        <td style="text-align:center; padding:5px 7px">
                                            \$tituloTotal
                                        </td>
                                        <td style="text-align:right; padding:5px 7px">
                                            \$valorTotal
                                        </td>
                                    </tr>
                                </div>
                                TWIG;
                            }
                            \$divTotalesGenerales =
                            <<<TWIG
                            <div style="margin-top:20px;">
                                <div style="background:#f2f2f2; text-align:center; font-weight:bold; padding:7px; border:1px solid gray; border-bottom:none; border-radius:5px 5px 0px 0px;">
                                    TOTALES DEL INFORME
                                </div>
                                <table border="1" cellpadding="0" cellspacing="0" style="width:100%">
                                    \$divTotalesGenerales
                                </table>
                            </div>
                            TWIG;
                        }
                        \$contenidoPDF = 
                        <<<TWIG
                        <div style="width:100%">
                            \$divAgrupacionGeneral
                            \$divTotalesGenerales
                        </div>
                        TWIG;
                    }
                    else
                    {
                        /** Se genera el informe sin campos de agrupacion */
                        /** --------------------------------------------- */
                        
                        \$contenidoPDF = \$this->crearTablaRegistrosPDF(\$request, \$configuraciones, \$listRegistros);
                    }
                } 
                catch(\Exception \$e) 
                {
                    \$status = 'error';
                    \$session->set('errorDescargaInforme', 
                    [
                        'line' => \$e->getLine(), 
                        'file' => \$e->getFile(), 
                        'message' => \$e->getMessage()
                    ]);    
                }

                /** Se asignan las configuraciones del PDF */
                /** -------------------------------------- */

                \$tipoHoja = 'letter';
                \$orientacion = 'portrait';
                \$anchoInformacionEmpresa = '280px';
                if(!empty(\$configuracionesPDF))
                {
                    if(array_key_exists('tipoHoja', \$configuracionesPDF) && !empty(\$configuracionesPDF['tipoHoja'])){\$tipoHoja = \$configuracionesPDF['tipoHoja'];}
                    if(array_key_exists('orientacion', \$configuracionesPDF) && !empty(\$configuracionesPDF['orientacion'])){\$orientacion = \$configuracionesPDF['orientacion'];}
                    if(\$orientacion == 'landscape'){\$anchoInformacionEmpresa = '400px';}
                }

                /** Se genera la plantilla del informe */
                /** ---------------------------------- */

                \$dompdf = new Dompdf(\$pdfOptions);
                \$html =
                <<<TWIG
                <style>
                    * {
                        font-size: 10px;
                    }

                    @page {
                        margin: 220px 20px 40px 20px;
                    }

                    header {
                        position: fixed;
                        top: -160px;
                        left: 0px;
                        right: 0px;
                        height: 50px;
                    }
                </style>
                <body>
                    <header>
                        <table border="0" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="width:70px">
                                    <img src="data:application/image;base64,\$logo" style="width:70px">
                                </td>
                                <td style="padding-left:15px; width:\$anchoInformacionEmpresa">
                                    <div style="font-weight:bold; font-size:14px">\$nombreCompania</div>
                                    <div style="margin-top:2px">N.I.T: \$nitCompania</div>
                                    <div style="margin-top:2px">Dirección: \$direccionCompania</div>
                                    <div style="margin-top:2px">Teléfono: \$telefonoCompania</div>
                                </td>
                                <td style="padding-left:10px">
                                    <table border="0" style="width:100%" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td>
                                                <div style="font-weight:bold; background:#f2f2f2; border-radius:5px 0px 0px 0px; padding:5px 7px; border:1px solid gray; border-right:none">Informe</div>
                                            </td>
                                            <td>
                                                <div style="background:#f2f2f2; border-radius:0px 5px 0px 0px; padding:5px 7px; border:1px solid gray">\$nombreInforme</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div style="font-weight:bold; padding:5px 7px; border:1px solid gray; border-right:none; border-top:none">Periodo</div>
                                            </td>
                                            <td>
                                                <div style="; padding:5px 7px; border:1px solid gray; border-top:none">\$periodo</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div style="font-weight:bold; background:#f2f2f2; border-radius:0px 0px 0px 5px; padding:5px 7px; border:1px solid gray; border-top:none; border-right:none">Fecha imprime</div>
                                            </td>
                                            <td>
                                                <div style="background:#f2f2f2; border-radius:0px 0px 5px 0px; padding:5px 7px; border:1px solid gray; border-top:none;">\$fechaActual</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </header>
                    <div>
                        \$contenidoPDF
                    </div>
                </body>
                TWIG;

                /** Se genera el PDF del informe */
                /** ---------------------------- */

                \$dompdf->loadHtml(\$html);
                \$dompdf->setPaper(\$tipoHoja, \$orientacion);
                \$dompdf->render();
                \$nombreInforme = strtolower(str_replace(' ', '_', \$nombreInforme));
                \$dompdf->get_canvas()->page_text(282, 766, "Pagina: {PAGE_NUM} de {PAGE_COUNT}", 'Helvetica', 6, array(0, 0, 0));
                \$pdf = \$dompdf->output();
                return new Response(
                    \$pdf,
                    200,
                    [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => ResponseHeaderBag::DISPOSITION_ATTACHMENT
                    ]
                );
            }

            public function crearTablaRegistrosPDF(Request \$request, \$configuraciones, \$listRegistros, \$agrupacion = false)
            {   
                /** 
                    * En esta función se crea la tabla del PDF con todos los registros del informe
                    * ----------------------------------------------------------------------------
                    * @access public
                */

                /** Definición de variables */
                /** ----------------------- */

                \$index = 1;
                \$tdCampo = '';
                \$cabecera = [];
                \$filasPDF = '';
                \$trTotales = '';
                \$thCabecera = '';
                \$trCabecera = '';
                \$titulosPDF = '';
                \$divRelleno = '';
                \$estiloBordes = '';
                \$rellenoCampo = '';
                \$contenidoPDF = '';
                \$tablaTotales = [];
                \$camposTotalizacion = [];
                \$tablaTotales['colspan'] = 0;
                \$camposTotalizacionAgrupamiento = [];
                \$camposTotalizados = \$this->camposTotalizados;
                \$ruta = \$request->getScheme().'://'.\$request->server->get('HTTP_HOST');
                \$alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];

                /** Se obtiene el json que contiene las configuraciones del informe */
                /** --------------------------------------------------------------- */

                if(!empty(\$configuraciones))
                {
                    if(array_key_exists('campos', \$configuraciones)){\$configuracionCampos = \$configuraciones['campos'];}
                    if(array_key_exists('cabecera', \$configuraciones) && is_array(\$configuraciones['cabecera']) && !empty(\$configuraciones['cabecera'])){\$cabecera = \$configuraciones['cabecera'];}
                    if(array_key_exists('totalizacion', \$configuraciones['agrupamiento'][0]) && !empty(\$configuraciones['agrupamiento'][0]['totalizacion']) && is_array(\$configuraciones['agrupamiento'][0]['totalizacion']))
                    {
                        \$camposTotalizados = [];
                        \$camposTotalizacion = \$configuraciones['agrupamiento'][0]['totalizacion'];
                    }
                }

                /** Se obtiene la totalización de los campos */
                /** ---------------------------------------- */

                if(\$agrupacion)
                {
                    foreach(\$listRegistros as \$indexRegistro => \$registro)
                    {
                        foreach(\$camposTotalizacion as \$ct)
                        {
                            if(array_key_exists('campo', \$ct) && array_key_exists(\$ct['campo'], \$registro))
                            {
                                if(array_key_exists(\$ct['campo'], \$camposTotalizados))
                                {
                                    \$camposTotalizados[\$ct['campo']] = \$camposTotalizados[\$ct['campo']] + \$registro[\$ct['campo']];
                                }
                                else
                                {
                                    \$camposTotalizados[\$ct['campo']] = \$registro[\$ct['campo']];
                                }
                            }
                        }
                    }
                }

                /** Se genera la tabla de registros */
                /** ------------------------------- */
                
                foreach(\$listRegistros as \$indexRegistro => \$registro)
                {   
                    \$finColspan = false;
                    \$rellenoCampo = ((\$indexRegistro + 1) % 2 == 0) ? '#17A2B814':'';
                    foreach(\$registro as \$key => \$campo)
                    {
                        /** Se crean los títulos del informe con sus respectivos estilos */
                        /** ------------------------------------------------------------ */
                        
                        \$alineacionCampo = 'left';
                        \$alineacionTitulo = 'center';
                        \$titulo = ucfirst(str_replace('_', ' ', \$key));
                        \$configuracionCampo = array_filter(\$configuracionCampos, fn(\$item) => \$item['nombre'] == \$key);

                        /** Se validan las configuraciones de cada campo */
                        /** -------------------------------------------- */

                        sort(\$configuracionCampo);
                        if(!empty(\$configuracionCampo))
                        {
                            /** Configuraciones del título */
                            /** -------------------------- */

                            if(array_key_exists('titulo', \$configuracionCampo[0])){\$titulo = \$configuracionCampo[0]['titulo'];}
                            if(array_key_exists('alineacionTitulo', \$configuracionCampo[0]) && array_key_exists(\$configuracionCampo[0]['alineacionTitulo'], \$alineaciones))
                            {
                                \$alineacionTitulo = \$alineaciones[\$configuracionCampo[0]['alineacionTitulo']];
                            }

                            /** Configuraciones de campos */
                            /** ------------------------- */

                            if(array_key_exists('alineacionCampo', \$configuracionCampo[0]) && array_key_exists(\$configuracionCampo[0]['alineacionCampo'], \$alineaciones))
                            {
                                \$alineacionCampo = \$alineaciones[\$configuracionCampo[0]['alineacionCampo']];
                            }

                            if(array_key_exists('tipoDato', \$configuracionCampo[0]) && \$configuracionCampo[0]['tipoDato'] == 'moneda')
                            {
                                \$campo = number_format(\$campo, 2, ',', '.');
                            }

                            if(array_key_exists('tipoDato', \$configuracionCampo[0]) && \$configuracionCampo[0]['tipoDato'] == 'numero')
                            {
                                \$campo = number_format(\$campo, 2, '.', '');
                            }

                            if(array_key_exists('tipoDato', \$configuracionCampo[0]) && \$configuracionCampo[0]['tipoDato'] == 'fecha')
                            {
                                \$campo = (new \DateTime(\$campo))->format('Y-m-d');
                            }

                            /** Se valida si el campo tiene una ruta configurada */
                            /** ------------------------------------------------ */

                            if(array_key_exists('ruta', \$configuracionCampo[0]) && is_array(\$configuracionCampo[0]['ruta']) && !empty(\$configuracionCampo[0]['ruta']) && array_key_exists('nombre', \$configuracionCampo[0]['ruta']))
                            {
                                \$parametros = [];
                                \$alineacionCampo = 'center';
                                if(array_key_exists('parametros', \$configuracionCampo[0]['ruta']) && is_array(\$configuracionCampo[0]['ruta']['parametros']) && !empty(\$configuracionCampo[0]['ruta']['parametros']))
                                {
                                    \$parametros = str_replace('\$campo', \$campo, json_encode(\$configuracionCampo[0]['ruta']['parametros']));
                                    \$parametros = json_decode(\$parametros, true);
                                }
                                \$rutaCampo = \$ruta.\$this->generateUrl(\$configuracionCampo[0]['ruta']['nombre'], \$parametros);
                                \$campo = 
                                <<<TWIG
                                    <a href="\$rutaCampo" target="_blank" style="color:#007BFF; text-decoration:none">\$campo</a>
                                TWIG;
                            }
                        }

                        /** Se crean los títulos del informe */
                        /** -------------------------------- */

                        if(\$indexRegistro == 0)
                        {   
                            \$titulosPDF .=
                            <<<TWIG
                            <td style="font-weight:bold; padding:4px; text-align:\$alineacionTitulo; background:#f2f2f2; border:1px solid gray">\$titulo</td>
                            TWIG;
                            \$divRelleno = '';
                            \$claseTitulo = '';
                            \$estiloBordesTitulo = '';
                        }

                        /** Se crea cada registro del informe */
                        /** --------------------------------- */

                        \$tdCampo .= 
                        <<<TWIG
                        <td style="padding:3px 7px; border:1px solid gray; text-align:\$alineacionCampo">\$campo</td>
                        TWIG;

                        /** Se diseña la tabla de acuerdo a los totales configurados */
                        /** -------------------------------------------------------- */

                        if(\$indexRegistro == array_key_last(\$listRegistros))
                        {
                            if(array_key_exists(\$key, \$camposTotalizados))
                            {
                                \$finColspan = true;
                                \$total = \$camposTotalizados[\$key];
                                if(!empty(\$configuracionCampo))
                                {
                                    if(array_key_exists('tipoDato', \$configuracionCampo[0]) && \$configuracionCampo[0]['tipoDato'] == 'moneda')
                                    {
                                        \$total = number_format(\$total, 2, ',', '.');
                                    }

                                    if(array_key_exists('tipoDato', \$configuracionCampo[0]) && \$configuracionCampo[0]['tipoDato'] == 'numero')
                                    {
                                        \$total = number_format(\$total, 2, '.', '');
                                    }

                                    if(array_key_exists('alineacionCampo', \$configuracionCampo[0]) && array_key_exists(\$configuracionCampo[0]['alineacionCampo'], \$alineaciones))
                                    {
                                        \$alineacionCampo = \$alineaciones[\$configuracionCampo[0]['alineacionCampo']];
                                    }
                                }
                                \$tablaTotales['campo'.\$index] = [\$total, \$alineacionCampo];
                            }
                            else
                            {
                                if(!\$finColspan)
                                {
                                    \$tablaTotales['colspan'] = \$tablaTotales['colspan'] + 1;
                                }
                                else
                                {
                                    \$tablaTotales['campo'.\$index] = '';
                                }
                            }

                            /** Se obtienen los títulos de los totales generales */
                            /** ------------------------------------------------ */

                            if(array_key_exists(\$key, \$this->camposTotalizados))
                            {
                                if(!is_array(\$this->camposTotalizados[\$key]))
                                {
                                    \$this->camposTotalizados[\$key] = [\$titulo, \$this->camposTotalizados[\$key]];
                                }
                            }
                        }
                        \$estiloBordesCampo = '';
                        \$index ++;
                    }
                    \$filasPDF .=
                    <<<TWIG
                        <tr>
                            \$tdCampo
                        </tr>
                    TWIG;
                    \$tdCampo = '';
                    \$index = 1;
                }

                /** Se crea la sección de la cabecera */
                /** --------------------------------- */
                
                if(!empty(\$cabecera))
                {
                    foreach(\$cabecera as \$index => \$c)
                    {
                        \$colSpanCabecera = \$c['colspan'];
                        \$tituloCabecera = strip_tags(\$c['nombre']);

                        if(\$index == 0)
                        {
                            \$estiloBordesTitulo = 'border-radius:5px 0px 0px 0px';
                        }

                        if(\$index == (count(\$cabecera) - 1))
                        {
                            \$estiloBordesTitulo = 'border-radius:0px 5px 0px 0px; border-right:1px solid gray';
                        }

                        if(count(\$cabecera) == 1)
                        {
                            \$estiloBordesTitulo = 'border-radius:5px 5px 0px 0px; border-right:1px solid gray';
                        }

                        \$thCabecera .=
                        <<<TWIG
                        <th colspan="\$colSpanCabecera">
                            <div style="background:#f2f2f2; text-align:center; padding:7px; border:1px solid gray; border-bottom:none; border-right:none; \$estiloBordesTitulo">
                                \$tituloCabecera
                            </div>
                        </th>
                        TWIG;
                        \$estiloBordes = '';
                    }
                    \$trCabecera = 
                    <<<TWIG
                    <tr>
                        \$thCabecera
                    </tr>
                    TWIG;
                }

                /** Se crea la sección de totales */
                /** ----------------------------- */

                \$index = 0;
                \$tdTotal = '';
                if(!empty(\$camposTotalizados))
                {
                    foreach(\$tablaTotales as \$key => \$campoTotal)
                    {
                        if(\$key == 'colspan' && \$campoTotal > 0)
                        {
                            \$tdTotal .= 
                            <<<TWIG
                            <th colspan="\$campoTotal">
                                <div style="background:#f2f2f2; text-align:right; padding:7px; border:1px solid gray; border-right:1px solid #f2f2f2; border-top:none; border-radius:0px 0px 0px 5px">
                                    Total &raquo;
                                </div>
                            </th>
                            TWIG;
                        }
                        else
                        {
                            \$campo = !empty(\$campoTotal)?\$campoTotal[0]:'';
                            \$alineacionCampo = !empty(\$campoTotal)?\$campoTotal[1]:'';
                            \$estiloBordes = (\$index == (count(\$tablaTotales) - 1))?'border-bottom:1px solid gray; border-right:1px solid gray; border-radius:0px 0px 5px 0px;':'border-bottom:1px solid gray; border-right:1px solid #f2f2f2';
                            \$tdTotal .= 
                            <<<TWIG
                            <th>
                                <div style="background:#f2f2f2; text-align:\$alineacionCampo; padding:7px; height:12px; \$estiloBordes">
                                    \$campo
                                </div>
                            </th>
                            TWIG;
                        }
                        \$index ++;
                    }
                    \$trTotales = 
                    <<<TWIG
                        <tr>
                            \$tdTotal
                        </tr>
                    TWIG;
                }

                /** Contenido del PDF */
                /** ----------------- */

                \$contenidoPDF =
                <<<TWIG
                <table border="0" cellpadding="0" cellspacing="0" style="width:100%">
                    \$trCabecera
                    <tr>
                        \$titulosPDF
                    </tr>
                    \$filasPDF
                    \$trTotales
                </table>
                TWIG;
                return \$contenidoPDF;
            }

            /**
             * @Route("/Central/Reporteador/descargarInformeExcel", name="central_reporteador_descargar_informe_excel")
            */
            public function descargarInformeExcel(Request \$request)
            {
                /** 
                    * En esta función se descarga el informe en formato excel. Para ello, se emplea el sql configurado en el informe y se obtiene
                    * la información respectiva a partir de los filtros de búsqueda seleccionados. Además, se genera la plantila html con las
                    * especificaciones que se hayan definido para cada campo.
                    * ---------------------------------------------------------------------------------------------------------------------------
                    * @access public
                */
                
                /** Definición de variables */
                /** ----------------------- */

                \$index = 1;
                \$filtros = [];
                \$message = '';
                \$periodo = '';
                \$cabecera = [];
                \$bd = \$this->em;
                \$plantilla = '';
                \$keyCampos = [];
                set_time_limit(0);
                \$tablaTotales = [];
                \$agrupamiento = [];
                \$contenidoPDF = '';
                \$totalRegistros = 0;
                \$camposAgrupacion = [];
                \$configuracionesPDF = [];
                \$camposTotalizacion = [];
                \$camposPeriodoValido = [];
                \$contenidoPaginacion = '';
                \$configuracionCampos = [];
                \$fsObject = new Filesystem();
                \$fechaCabecera = new RichText();
                \$spreadsheet = new Spreadsheet();
                \$conexion = \$bd->getConnection();
                \$periodoCabecera = new RichText();
                \$listRegistrosBusquedaRapida = [];
                \$session = \$request->getSession();
                \$sheet = \$spreadsheet->getActiveSheet();
                \$logoTmp = tempnam(sys_get_temp_dir(), 'logoTmp');
                \$rutaLogo = \$this->getParameter('imgs_directory');
                \$form = \$request->request->get('filtros_reporteador');
                \$busquedaRapida = \$request->request->get('busquedaRapida');
                \$compania = \$bd->getRepository(compania::class)->findOneBy([]);
                \$alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];
                \$informe = \$bd->getRepository(reportes::class)->findOneBy(['id' => \$form['informe']]);
                \$fechaActual = (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');

                try 
                {
                    /** Se obtienen los filtros de búsqueda seleccionados */
                    /** ------------------------------------------------- */
                    
                    \$sqlInforme = \$informe->getSql();
                    \$nitCompania = \$compania->getNit();
                    \$logo = \$compania->getLogocompania();
                    \$nombreInforme = \$informe->getNombre();
                    \$telefonoCompania = \$compania->getTelefonos();
                    \$direccionCompania = \$compania->getDireccion();
                    \$nombreCompania = strtoupper(\$compania->getNombre());
                    preg_match_all('/\[(.*?)\]/', \$sqlInforme, \$camposSQL);
                    \$logoCompania = base64_decode(\$compania->getLogocompania());
                    foreach(\$form as \$key => \$campo){\$filtros['['.\$key.']'] = !empty(\$campo)?\$campo:-1;}
                    file_put_contents(\$logoTmp, \$logoCompania);

                    /** Se valida si las variables definidas en el sql se encuentran en los filtros de búsqueda */
                    /** --------------------------------------------------------------------------------------- */
    
                    foreach(\$camposSQL[0] as \$campoSQL)
                    {
                        if(!array_key_exists(\$campoSQL, \$filtros))
                        {
                            \$filtros[\$campoSQL] = '-1';
                        }
                    }
                    \$sqlInforme = strtr(\$sqlInforme, \$filtros);

                    /** Se obtiene el json que contiene las configuraciones del informe */
                    /** --------------------------------------------------------------- */

                    \$tablaTotales['colspan'] = 0;
                    \$configuraciones = \$informe->getJson();
                    if(!empty(\$configuraciones))
                    {
                        if(array_key_exists('campos', \$configuraciones)){\$configuracionCampos = \$configuraciones['campos'];}
                        if(array_key_exists('pdf', \$configuraciones) && !empty(\$configuraciones['pdf']) && is_array(\$configuraciones['pdf'])){\$configuracionesPDF = \$configuraciones['pdf'];}
                        if(array_key_exists('paginacion', \$configuraciones) && \$configuraciones['paginacion'] && \$configuraciones['paginacion'] >= 10){\$paginacion = \$configuraciones['paginacion'];}
                        if(array_key_exists('cabecera', \$configuraciones) && is_array(\$configuraciones['cabecera']) && !empty(\$configuraciones['cabecera'])){\$cabecera = \$configuraciones['cabecera'];}
                        if(array_key_exists('agrupamiento', \$configuraciones) && is_array(\$configuraciones['agrupamiento']) && !empty(\$configuraciones['agrupamiento'])){\$agrupamiento = \$configuraciones['agrupamiento'];}
                        if(array_key_exists('totalizacion', \$configuraciones) && !empty(\$configuraciones['totalizacion']) && is_array(\$configuraciones['totalizacion'])){\$camposTotalizacion = \$configuraciones['totalizacion'];}
                        if(array_key_exists('periodo', \$configuraciones) && !empty(\$configuraciones['periodo']))
                        {
                            preg_match_all('/\[(.*?)\]/', \$configuraciones['periodo'], \$campos);
                            if(!empty(\$campos))
                            {
                                foreach(\$campos[0] as \$campo)
                                {
                                    if(array_key_exists(\$campo, \$filtros) && date('Y-m-d', strtotime(\$filtros[\$campo])) == \$filtros[\$campo])
                                    {
                                        \$fecha = explode('-', \$filtros[\$campo]);
                                        \$mes = \$bd->getRepository(meses::class)->findOneBy(['numero' => \$fecha[1]]);
                                        \$camposPeriodoValido[\$campo] = \$fecha[2].' de '.\$mes->getNombre().' de '.\$fecha[0];
                                    }
                                }
                                if(count(\$camposPeriodoValido) == count(\$campos[0]))
                                {
                                    \$periodo = strtr(\$configuraciones['periodo'], \$camposPeriodoValido);
                                }
                            }
                        }
                    }

                    /** Se realiza la consulta de los registros */
                    /** --------------------------------------- */

                    \$listRegistros = \$conexion->prepare(\$sqlInforme)->executeQuery()->fetchAll();

                    /** Se filtran los registros de acuerdo a la búsqueda rápida */
                    /** -------------------------------------------------------- */

                    if(\$busquedaRapida != '')
                    {
                        foreach(\$listRegistros as \$registro)
                        {
                            foreach(\$registro as \$campo)
                            {
                                if(strpos(\$campo, \$busquedaRapida) !== false)
                                {
                                    \$listRegistrosBusquedaRapida[] = \$registro;
                                }
                            }
                        }
                        \$listRegistros = \$listRegistrosBusquedaRapida;
                    }

                    /** Se obtiene la totalización de los campos */
                    /** ---------------------------------------- */

                    \$totalRegistros = count(\$listRegistros);
                    foreach(\$listRegistros as \$indexRegistro => \$registro)
                    {
                        /** Se obtiene la totalización de los campos */
                        /** ---------------------------------------- */

                        foreach(\$camposTotalizacion as \$ct)
                        {
                            if(array_key_exists('campo', \$ct) && array_key_exists(\$ct['campo'], \$registro))
                            {
                                if(array_key_exists(\$ct['campo'], \$this->camposTotalizados))
                                {
                                    \$this->camposTotalizados[\$ct['campo']] = \$this->camposTotalizados[\$ct['campo']] + \$registro[\$ct['campo']];
                                }
                                else
                                {
                                    \$this->camposTotalizados[\$ct['campo']] = \$registro[\$ct['campo']];
                                }
                            }
                        }
                    }

                    /** Se valida si existen campos de agrupación configurados */
                    /** ------------------------------------------------------ */

                    if(array_key_exists('campos', \$agrupamiento[0]) && is_array(\$agrupamiento[0]['campos']) && !empty(\$agrupamiento[0]['campos']))
                    {
                        \$keyCampos = array_keys(\$listRegistros[0]);
                        foreach(\$keyCampos as \$campo)
                        {
                            foreach(\$agrupamiento[0]['campos'] as \$a)
                            {
                                if(\$a['nombre'] == \$campo){\$camposAgrupacion[] = \$campo;}
                            }
                        }
                    }

                    if(!empty(\$camposAgrupacion))
                    {
                        /** Se genera el informe con campos de agrupación */
                        /** --------------------------------------------- */
                        
                        \$listAgrupada = [];
                        \$campoControl = '';
                        \$campoAnterior = '';
                        \$camposReferencia = [];
                        \$divTotalesGenerales = '';
                        \$camposAgrupacion = array_slice(\$camposAgrupacion, 0, 3);
                        
                        /** Se ordena la información de acuerdo a los campos de agrupación configurados en el informe */
                        /** ----------------------------------------------------------------------------------------- */
                
                        foreach(\$camposAgrupacion as \$index => \$campo)
                        {
                            if(empty(\$campoControl))
                            {
                                foreach(\$listRegistros as \$registro)
                                {
                                    \$listAgrupada[\$campo][\$registro[\$campo]] = \$registro[\$campo];
                                }
                            }
                            else
                            {
                                if(\$index == 1)
                                {
                                    foreach(\$listAgrupada[\$campoControl] as \$c)
                                    {
                                        \$camposReferencia[] = \$c;
                                        foreach(\$listRegistros as \$registro)
                                        {
                                            if(\$registro[\$campoControl] == \$c)
                                            {
                                                \$listAgrupada[\$campo][\$c][\$registro[\$campo]] = \$registro[\$campo];
                                            }
                                        }
                                    }
                                    unset(\$camposReferencia[array_key_last(\$camposReferencia)]);
                                }
                                else
                                {
                                    foreach(\$camposReferencia as \$cr)
                                    {
                                        foreach(\$listAgrupada[\$campoControl][\$cr] as \$c)
                                        {
                                            \$camposReferenciaControl[] = \$c;
                                            foreach(\$listRegistros as \$registro)
                                            {
                                                if(\$registro[\$campoControl] == \$c)
                                                {
                                                    \$listAgrupada[\$campo][\$c][\$registro[\$campo]] = \$registro[\$campo];
                                                }
                                            }
                                        }
                                    }
                                    \$camposReferencia = \$camposReferenciaControl;
                                }
                            }
                            \$campoControl = \$campo;
                            \$listAgrupada[\$campo]['referencia'] = array_key_exists(\$index + 1, \$camposAgrupacion)?\$camposAgrupacion[\$index + 1]:'registros';
                
                            /** Se guardan los registros de tal manera que se asocien al último nivel de agrupación */
                            /** ----------------------------------------------------------------------------------- */
                
                            if(\$listAgrupada[\$campo]['referencia'] == 'registros')
                            {
                                if(empty(\$camposReferencia))
                                {
                                    \$campoAnterior = \$camposAgrupacion[0];
                                    foreach(\$listAgrupada[\$campoAnterior] as \$c)
                                    {
                                        foreach(\$listRegistros as \$registro)
                                        {
                                            if(\$registro[\$campoAnterior] == \$c)
                                            {
                                                foreach(\$camposAgrupacion as \$campo){unset(\$registro[\$campo]);}
                                                \$listAgrupada['registros'][\$c][] = \$registro;
                                            }
                                        }
                                    }
                                }
                                else
                                {
                                    foreach(\$camposReferencia as \$cr)
                                    {
                                        foreach(\$listAgrupada[\$campoControl][\$cr] as \$c)
                                        {
                                            foreach(\$listRegistros as \$registro)
                                            {
                                                \$campoAnterior = \$registro[\$camposAgrupacion[count(\$camposAgrupacion) - 2]];
                                                if(\$campoAnterior == \$cr && \$registro[\$campoControl] == \$c)
                                                {
                                                    foreach(\$camposAgrupacion as \$campo){unset(\$registro[\$campo]);}
                                                    \$listAgrupada['registros'][\$campoAnterior.\$c][] = \$registro;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        /** Se crea la sección de agrupamiento con todos los campos seleccionados */
                        /** --------------------------------------------------------------------- */

                        \$this->ultimaColumna = Coordinate::stringFromColumnIndex(count(\$listAgrupada['registros'][array_key_first(\$listAgrupada['registros'])][0]));
                        foreach(\$listAgrupada as \$key => \$items)
                        {
                            if(\$key === array_key_first(\$listAgrupada))
                            {
                                \$campo = array_filter(\$agrupamiento[0]['campos'], fn(\$item) => \$item['nombre'] == \$key);
                                sort(\$campo);
                                \$titulo = \$campo[0]['titulo'];

                                /** Se obtiene la información de cada campo de agrupación */
                                /** ----------------------------------------------------- */

                                foreach(\$items as \$keyItem => \$item)
                                {
                                    if(\$keyItem == 'referencia'){continue;}
                                    \$nombreAgrupacion = explode('-', \$item);
                                    if(count(\$nombreAgrupacion) > 1)
                                    {
                                        unset(\$nombreAgrupacion[0]);
                                        \$nombreAgrupacion = implode('-', \$nombreAgrupacion);
                                    }
                                    else
                                    {
                                        \$nombreAgrupacion = \$item;
                                    }
                                    \$tituloCampo = '      '.\$titulo.' » '.\$nombreAgrupacion;
                                    \$this->crearTablaAgrupadaExcel(\$request, \$listAgrupada[\$items['referencia']], \$item, \$items['referencia'], \$listAgrupada, \$tituloCampo, \$agrupamiento[0]['campos'], \$sheet, \$configuraciones);
                                }
                            }
                        }

                        /** Se genera la sección de totales obtenidos a partir de los campos de agrupación */
                        /** ------------------------------------------------------------------------------ */

                        if(!empty(\$this->camposTotalizados))
                        {
                            \$sheet->getRowDimension(\$this->filaGeneral)->setRowHeight(25);
                            \$totalColumnas = Coordinate::columnIndexFromString(\$this->ultimaColumna);
                            \$columnaInicial = Coordinate::stringFromColumnIndex(\$totalColumnas - 1);
                            \$sheet->setCellValue(\$columnaInicial.\$this->filaGeneral, 'TOTALES DEL INFORME');
                            \$sheet->mergeCells(\$columnaInicial.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral);
                            \$sheet->getStyle(\$columnaInicial.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                            \$sheet->getStyle(\$columnaInicial.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                            /** Se aplican estilos a los títulos del informe */
                            /** -------------------------------------------- */

                            \$sheet->getStyle(\$columnaInicial.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->getFill()
                                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('f2f2f2')
                            ;
                            \$styles = 
                            [
                                'borders' => 
                                [
                                    'allBorders' => 
                                    [
                                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                        'color' => ['argb' => 'FFB0B0B0'],
                                    ],
                                ],
                            ];
                            \$sheet->getStyle(\$columnaInicial.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->getFont()->setBold(true)->setSize(11);
                            \$sheet->getStyle(\$columnaInicial.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->applyFromArray(\$styles);
                            \$this->filaGeneral ++;

                            foreach(\$this->camposTotalizados as \$ct)
                            {
                                \$sheet->getRowDimension(\$this->filaGeneral)->setRowHeight(20);
                                \$sheet->getStyle(\$columnaInicial.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->applyFromArray(\$styles, false);
                                \$sheet->getStyle(\$columnaInicial.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                                \$sheet->getStyle(\$columnaInicial.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);    

                                /** Se asignan estilos a cada campo */
                                /** ------------------------------- */
                
                                \$styles = 
                                [
                                    'borders' => 
                                    [
                                        'right' => 
                                        [
                                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                            'color' => ['argb' => 'FFB0B0B0'],
                                        ],
                                        'bottom' => 
                                        [
                                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                            'color' => ['argb' => 'FFB0B0B0'],
                                        ]
                                    ],
                                ];

                                /** Se asigna el título y el valor de cada total */
                                /** -------------------------------------------- */

                                \$tituloTotal = \$ct[0];
                                \$valorTotal = number_format(\$ct[1], 2, ',', '.');
                                \$sheet->setCellValue(\$columnaInicial.\$this->filaGeneral, \$tituloTotal);
                                \$sheet->setCellValue(\$this->ultimaColumna.\$this->filaGeneral, \$valorTotal);
                                \$this->filaGeneral ++;
                            }
                        }
                    }
                    else
                    {
                        /** Se genera el informe sin campos de agrupacion */
                        /** --------------------------------------------- */
                        
                        \$this->crearTablaRegistrosExcel(\$request, \$configuraciones, \$listRegistros, false, \$sheet);
                    }
                } 
                catch(\Exception \$e) 
                {
                    \$status = 'error';
                    \$session->set('errorDescargaInforme', 
                    [
                        'line' => \$e->getLine(), 
                        'file' => \$e->getFile(), 
                        'message' => \$e->getMessage()
                    ]);
                }

                \$ultimaColumna = \$this->ultimaColumna; 
                \$sheet->getRowDimension('1')->setRowHeight(35);
                \$sheet->getRowDimension('2')->setRowHeight(20);
                \$sheet->getRowDimension('3')->setRowHeight(20);
                \$sheet->getRowDimension('4')->setRowHeight(20);
                \$sheet->getRowDimension('5')->setRowHeight(20);
                if(empty(\$camposAgrupacion)){\$sheet->getRowDimension('6')->setRowHeight(25);}

                \$sheet->mergeCells('A1:A4');
                \$sheet->mergeCells('A5:'.\$ultimaColumna.'5');
                \$sheet->mergeCells('B1:'.\$ultimaColumna.'1');
                \$sheet->mergeCells('B2:'.\$ultimaColumna.'2');
                \$sheet->mergeCells('B3:'.\$ultimaColumna.'3');
                \$sheet->mergeCells('B4:'.\$ultimaColumna.'4');
                \$sheet->mergeCells('B5:'.\$ultimaColumna.'5');
                \$sheet->getStyle('B2:B4')->getFont()->setSize(15);
                \$sheet->getStyle('B1')->getFont()->setBold(true)->setSize(13);
                \$sheet->getStyle('B2:B4')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

                /** Información cabecera */
                /** -------------------- */

                \$periodoText = \$periodoCabecera->createTextRun('  » Periodo: ');
                \$periodoText->getFont()->setBold(true);
                \$periodoCabecera->createText(\$periodo);

                \$cabeceraText = \$fechaCabecera->createTextRun('  » Fecha imprime: ');
                \$cabeceraText->getFont()->setBold(true);
                \$fechaCabecera->createText(\$fechaActual);

                \$sheet->setCellValue('B1', '  '.strtoupper(\$nombreInforme));
                \$sheet->setCellValue('B2', \$periodoCabecera);
                \$sheet->setCellValue('B3', \$fechaCabecera);

                /* Color de fondo Title */
                /* -------------------- */

                \$sheet->getStyle('A6:'.\$ultimaColumna.'6')->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('f2f2f2')
                ;

                /* Estilo de Bordes */
                /* ---------------- */

                \$styles = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['argb' => 'FFB0B0B0'],
                        ],
                    ],
                ];

                \$stylesCabecera = [
                    'borders' => [
                        'outline' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['argb' => 'FFB0B0B0'],
                        ],
                    ],
                ];

                \$stylesCabeceraInterior = [
                    'borders' => [
                        'inside' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['argb' => 'FFFFFF'],
                        ],
                    ],
                ];

                \$sheet->getStyle('A1:'.\$ultimaColumna.'4')->applyFromArray(\$stylesCabecera);
                \$sheet->getStyle('A1:'.\$ultimaColumna.'4')->applyFromArray(\$stylesCabeceraInterior);
                \$sheet->getSheetView()->setZoomScale(80);
                \$sheet->setTitle(\$nombreInforme);

                /* Configuración del logo */
                /* ---------------------- */
                
                \$drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                \$drawing->setName('Informe');
                \$drawing->setDescription('Informe');
                \$drawing->setPath(\$logoTmp);
                \$drawing->setCoordinates('A1');
                \$drawing->setWidthAndHeight(130, 44);
                \$drawing->setResizeProportional(true);
                \$drawing->setOffsetX(8);
                \$drawing->setOffsetY(65);
                \$drawing->setWorksheet(\$spreadsheet->getActiveSheet());

                /* Crear y guardar archivo */
                /* ----------------------- */

                \$writer = new Xlsx(\$spreadsheet);
                \$temp_file = tempnam(sys_get_temp_dir(), 'informe.xls');
                \$writer->save(\$temp_file);
                \$nombreInforme = strtolower(str_replace(' ', '_', \$nombreInforme));
                return \$this->file(\$temp_file, \$nombreInforme.'.xls', ResponseHeaderBag::DISPOSITION_ATTACHMENT);
            }

            public function crearTablaRegistrosExcel(Request \$request, \$configuraciones, \$listRegistros, \$agrupacion = false, \$sheet)
            {   
                /** 
                    * En esta función se crea la tabla del PDF con todos los registros del informe
                    * ----------------------------------------------------------------------------
                    * @access public
                */

                /** Definición de variables */
                /** ----------------------- */

                \$index = 1;
                \$cabecera = [];
                \$rutaCampo = '';
                \$indexCabecera = 0;
                \$camposTotalizacion = [];
                \$tablaTotales['colspan'] = 0;
                \$camposTotalizacionAgrupamiento = [];
                \$camposTotalizados = \$this->camposTotalizados;
                \$ruta = \$request->getScheme().'://'.\$request->server->get('HTTP_HOST');
                \$alineaciones = ['centro' => 'center', 'derecha' => 'right', 'izquierda' => 'left'];

                /** Se definen los campos iniciales del excel */
                /** ----------------------------------------- */

                if(\$agrupacion)
                {
                    \$filaTitulo = \$this->filaGeneral;
                    \$inicioRegistros = \$this->filaGeneral + 1;
                    \$filaInicioRegistros = \$this->filaGeneral; 
                }
                else
                {
                    \$filaTitulo = 6;
                    \$inicioRegistros = 7;
                    \$filaInicioRegistros = 6;    
                }

                /** Se obtiene el json que contiene las configuraciones del informe */
                /** --------------------------------------------------------------- */

                if(!empty(\$configuraciones))
                {
                    if(array_key_exists('campos', \$configuraciones)){\$configuracionCampos = \$configuraciones['campos'];}
                    if(array_key_exists('cabecera', \$configuraciones) && is_array(\$configuraciones['cabecera']) && !empty(\$configuraciones['cabecera'])){\$cabecera = \$configuraciones['cabecera'];}
                    if(array_key_exists('totalizacion', \$configuraciones['agrupamiento'][0]) && !empty(\$configuraciones['agrupamiento'][0]['totalizacion']) && is_array(\$configuraciones['agrupamiento'][0]['totalizacion']))
                    {
                        \$camposTotalizados = [];
                        \$camposTotalizacion = \$configuraciones['agrupamiento'][0]['totalizacion'];
                    }
                }

                /** Se obtiene la totalización de los campos */
                /** ---------------------------------------- */

                if(\$agrupacion)
                {
                    foreach(\$listRegistros as \$indexRegistro => \$registro)
                    {
                        foreach(\$camposTotalizacion as \$ct)
                        {
                            if(array_key_exists('campo', \$ct) && array_key_exists(\$ct['campo'], \$registro))
                            {
                                if(array_key_exists(\$ct['campo'], \$camposTotalizados))
                                {
                                    \$camposTotalizados[\$ct['campo']] = \$camposTotalizados[\$ct['campo']] + \$registro[\$ct['campo']];
                                }
                                else
                                {
                                    \$camposTotalizados[\$ct['campo']] = \$registro[\$ct['campo']];
                                }
                            }
                        }
                    }
                }

                /** Se genera la tabla de registros */
                /** ------------------------------- */
                
                foreach(\$listRegistros as \$indexRegistro => \$registro)
                {   
                    \$finColspan = false;
                    \$rellenoCampo = ((\$indexRegistro + 1) % 2 == 0)?'#17A2B814':'';
                    \$this->ultimaColumna = Coordinate::stringFromColumnIndex(count(\$registro));
                    foreach(\$registro as \$key => \$campo)
                    {
                        /** Se crean los títulos del informe con sus respectivos estilos */
                        /** ------------------------------------------------------------ */
                        
                        \$alineacionCampo = 'left';
                        \$alineacionTitulo = 'center';
                        \$titulo = ucfirst(str_replace('_', ' ', \$key));
                        \$columna = Coordinate::stringFromColumnIndex(\$index);
                        \$configuracionCampo = array_filter(\$configuracionCampos, fn(\$item) => \$item['nombre'] == \$key);

                        /** Se validan las configuraciones de cada campo */
                        /** -------------------------------------------- */

                        sort(\$configuracionCampo);
                        if(!empty(\$configuracionCampo))
                        {
                            /** Configuraciones del título */
                            /** -------------------------- */

                            if(array_key_exists('titulo', \$configuracionCampo[0])){\$titulo = \$configuracionCampo[0]['titulo'];}
                            if(array_key_exists('alineacionTitulo', \$configuracionCampo[0]) && array_key_exists(\$configuracionCampo[0]['alineacionTitulo'], \$alineaciones))
                            {
                                \$alineacionTitulo = \$alineaciones[\$configuracionCampo[0]['alineacionTitulo']];
                            }

                            /** Configuraciones de campos */
                            /** ------------------------- */

                            if(array_key_exists('alineacionCampo', \$configuracionCampo[0]) && array_key_exists(\$configuracionCampo[0]['alineacionCampo'], \$alineaciones))
                            {
                                \$alineacionCampo = \$alineaciones[\$configuracionCampo[0]['alineacionCampo']];
                            }

                            if(array_key_exists('tipoDato', \$configuracionCampo[0]) && \$configuracionCampo[0]['tipoDato'] == 'moneda')
                            {
                                \$campo = number_format(\$campo, 2, ',', '.');
                            }

                            if(array_key_exists('tipoDato', \$configuracionCampo[0]) && \$configuracionCampo[0]['tipoDato'] == 'numero')
                            {
                                \$campo = number_format(\$campo, 2, '.', '');
                            }

                            if(array_key_exists('tipoDato', \$configuracionCampo[0]) && \$configuracionCampo[0]['tipoDato'] == 'fecha')
                            {
                                \$campo = (new \DateTime(\$campo))->format('Y-m-d');
                            }

                            /** Se valida si el campo tiene una ruta configurada */
                            /** ------------------------------------------------ */

                            if(array_key_exists('ruta', \$configuracionCampo[0]) && is_array(\$configuracionCampo[0]['ruta']) && !empty(\$configuracionCampo[0]['ruta']) && array_key_exists('nombre', \$configuracionCampo[0]['ruta']))
                            {
                                \$parametros = [];
                                \$alineacionCampo = 'center';
                                if(array_key_exists('parametros', \$configuracionCampo[0]['ruta']) && is_array(\$configuracionCampo[0]['ruta']['parametros']) && !empty(\$configuracionCampo[0]['ruta']['parametros']))
                                {
                                    \$parametros = str_replace('\$campo', \$campo, json_encode(\$configuracionCampo[0]['ruta']['parametros']));
                                    \$parametros = json_decode(\$parametros, true);
                                }
                                \$rutaCampo = \$ruta.\$this->generateUrl(\$configuracionCampo[0]['ruta']['nombre'], \$parametros);
                            }
                        }

                        /** Se crean los títulos del informe */
                        /** -------------------------------- */

                        if(\$indexRegistro == 0)
                        {   
                            /** Se crea la sección de la cabecera */
                            /** --------------------------------- */
                            
                            if(!empty(\$cabecera))
                            {
                                if(\$indexCabecera == 0)
                                {
                                    if(!\$agrupacion)
                                    {
                                        \$filaTitulo = 7;
                                        \$columnaInicio = 1;
                                        \$inicioRegistros = 8;
                                        \$filaInicioRegistros = 7;
                                        \$sheet->getRowDimension('7')->setRowHeight(25);
                                        \$sheet->getStyle('A6:'.\$this->ultimaColumna.'6')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                                        \$sheet->getStyle('A6:'.\$this->ultimaColumna.'6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                
                                        foreach(\$cabecera as \$index => \$c)
                                        {
                                            \$colSpanCabecera = \$c['colspan'];
                                            \$tituloCabecera = strip_tags(\$c['nombre']);
                                            \$columnaFinal = Coordinate::stringFromColumnIndex((\$columnaInicio + \$colSpanCabecera) - 1);
                                            \$columnaInicial = Coordinate::stringFromColumnIndex(\$columnaInicio);
                                            \$sheet->setCellValue(\$columnaInicial.'6', \$tituloCabecera);
                                            \$sheet->mergeCells(\$columnaInicial.'6:'.\$columnaFinal.'6');
                                            \$columnaInicio += \$colSpanCabecera;
                                        }
                
                                        /** Se aplican estilos a la cabecera del informe */
                                        /** -------------------------------------------- */
                
                                        \$sheet->getStyle('A6:'.\$this->ultimaColumna.'7')->getFill()
                                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                            ->getStartColor()->setARGB('f2f2f2')
                                        ;
                                        \$styles = 
                                        [
                                            'borders' => 
                                            [
                                                'allBorders' => 
                                                [
                                                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                                    'color' => ['argb' => 'FFB0B0B0'],
                                                ],
                                            ],
                                        ];
                                        \$sheet->getStyle('A6:'.\$this->ultimaColumna.'6')->getFont()->setBold(true)->setSize(11);
                                        \$sheet->getStyle('A6:'.\$this->ultimaColumna.'6')->applyFromArray(\$styles);
                                        \$indexCabecera ++;
                                    }
                                    else
                                    {
                                        \$columnaInicio = 1;
                                        \$filaTitulo = \$this->filaGeneral + 1;
                                        \$inicioRegistros = \$this->filaGeneral + 2;
                                        \$filaInicioRegistros = \$this->filaGeneral + 1;
                                        \$sheet->getRowDimension(\$filaTitulo)->setRowHeight(25);
                                        \$sheet->getRowDimension(\$filaTitulo - 1)->setRowHeight(25);
                                        \$sheet->getStyle('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                                        \$sheet->getStyle('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                
                                        foreach(\$cabecera as \$c)
                                        {
                                            \$colSpanCabecera = \$c['colspan'];
                                            \$tituloCabecera = strip_tags(\$c['nombre']);
                                            \$columnaInicial = Coordinate::stringFromColumnIndex(\$columnaInicio);
                                            \$sheet->setCellValue(\$columnaInicial.\$this->filaGeneral, \$tituloCabecera);
                                            \$columnaFinal = Coordinate::stringFromColumnIndex((\$columnaInicio + \$colSpanCabecera) - 1);
                                            \$sheet->mergeCells(\$columnaInicial.\$this->filaGeneral.':'.\$columnaFinal.\$this->filaGeneral);
                                            \$columnaInicio += \$colSpanCabecera;
                                        }
                
                                        /** Se aplican estilos a la cabecera del informe */
                                        /** -------------------------------------------- */
                
                                        \$sheet->getStyle('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$filaTitulo)->getFill()
                                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                            ->getStartColor()->setARGB('f2f2f2')
                                        ;
                                        \$styles = 
                                        [
                                            'borders' => 
                                            [
                                                'allBorders' => 
                                                [
                                                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                                    'color' => ['argb' => 'FFB0B0B0'],
                                                ],
                                            ],
                                        ];
                                        \$sheet->getStyle('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->getFont()->setBold(true)->setSize(11);
                                        \$sheet->getStyle('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->applyFromArray(\$styles);
                                        \$indexCabecera ++;
                                    }
                                }
                            }
                            \$anchoCampo = (\$index == 1)?20:30;
                            \$sheet->setCellValue(\$columna.\$filaTitulo, \$titulo);
                            \$sheet->getRowDimension(\$filaTitulo)->setRowHeight(25);
                            \$sheet->getColumnDimension(\$columna)->setWidth(\$anchoCampo);
                            \$sheet->getStyle('A'.\$filaTitulo.':'.\$this->ultimaColumna.\$filaTitulo)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                            \$sheet->getStyle('A'.\$filaTitulo.':'.\$this->ultimaColumna.\$filaTitulo)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                            /** Se aplican estilos a los títulos del informe */
                            /** -------------------------------------------- */

                            \$sheet->getStyle('A'.\$filaTitulo.':'.\$this->ultimaColumna.\$filaTitulo)->getFill()
                                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('f2f2f2')
                            ;
                            \$styles = 
                            [
                                'borders' => 
                                [
                                    'allBorders' => 
                                    [
                                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                        'color' => ['argb' => 'FFB0B0B0'],
                                    ],
                                ],
                            ];
                            \$sheet->getStyle('A'.\$filaTitulo.':'.\$this->ultimaColumna.\$filaTitulo)->applyFromArray(\$styles);
                            \$sheet->getStyle('A'.\$filaTitulo.':'.\$this->ultimaColumna.\$filaTitulo)->getFont()->setBold(true)->setSize(11);
                        }

                        /** Se crea cada registro del informe */
                        /** --------------------------------- */

                        \$sheet->getStyle(\$columna.\$inicioRegistros)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                        if(\$alineacionCampo == 'center')
                        {
                            \$sheet->getStyle(\$columna.\$inicioRegistros)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                        }
                        if(\$alineacionCampo == 'left')
                        {
                            \$sheet->getStyle(\$columna.\$inicioRegistros)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                        }
                        if(\$alineacionCampo == 'right')
                        {
                            \$sheet->getStyle(\$columna.\$inicioRegistros)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                        }
                        \$sheet->setCellValue(\$columna.\$inicioRegistros, \$campo);

                        /** Se valida si el campo corresponde a una ruta */
                        /** -------------------------------------------- */

                        if(!empty(\$rutaCampo))
                        {
                            \$sheet->getCell(\$columna.\$inicioRegistros)->getHyperlink()->setUrl(\$rutaCampo);
                            \$sheet->getStyle(\$columna.\$inicioRegistros)->getFont()->getColor()->setARGB('FF007BFF');
                        }

                        /** Se asignan estilos a cada campo */
                        /** ------------------------------- */

                        \$styles = 
                        [
                            'borders' => 
                            [
                                'right' => 
                                [
                                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                    'color' => ['argb' => 'FFB0B0B0'],
                                ],
                                'bottom' => 
                                [
                                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                    'color' => ['argb' => ((\$inicioRegistros - \$filaInicioRegistros) == count(\$listRegistros))?'FFB0B0B0':'d1d4da'],
                                ],
                            ],
                        ];
                        \$sheet->getStyle('A'.\$inicioRegistros.':'.\$this->ultimaColumna.\$inicioRegistros)->applyFromArray(\$styles, false);
                        \$sheet->getRowDimension(\$inicioRegistros)->setRowHeight(20);

                        /** Se diseña la tabla de acuerdo a los totales configurados */
                        /** -------------------------------------------------------- */

                        if(\$indexRegistro == array_key_last(\$listRegistros))
                        {
                            if(array_key_exists(\$key, \$camposTotalizados))
                            {
                                \$finColspan = true;
                                \$total = \$camposTotalizados[\$key];
                                if(!empty(\$configuracionCampo))
                                {
                                    if(array_key_exists('tipoDato', \$configuracionCampo[0]) && \$configuracionCampo[0]['tipoDato'] == 'moneda')
                                    {
                                        \$total = number_format(\$total, 2, ',', '.');
                                    }

                                    if(array_key_exists('tipoDato', \$configuracionCampo[0]) && \$configuracionCampo[0]['tipoDato'] == 'numero')
                                    {
                                        \$total = number_format(\$total, 2, '.', '');
                                    }

                                    if(array_key_exists('alineacionCampo', \$configuracionCampo[0]) && array_key_exists(\$configuracionCampo[0]['alineacionCampo'], \$alineaciones))
                                    {
                                        \$alineacionCampo = \$alineaciones[\$configuracionCampo[0]['alineacionCampo']];
                                    }
                                }
                                \$tablaTotales['campo'.\$index] = [\$total, \$alineacionCampo];
                            }
                            else
                            {
                                if(!\$finColspan)
                                {
                                    \$tablaTotales['colspan'] = \$tablaTotales['colspan'] + 1;
                                }
                                else
                                {
                                    \$tablaTotales['campo'.\$index] = '';
                                }
                            }

                            /** Se obtienen los títulos de los totales generales */
                            /** ------------------------------------------------ */

                            if(array_key_exists(\$key, \$this->camposTotalizados))
                            {
                                if(!is_array(\$this->camposTotalizados[\$key]))
                                {
                                    \$this->camposTotalizados[\$key] = [\$titulo, \$this->camposTotalizados[\$key]];
                                }
                            }
                        }
                        \$index ++;
                        \$rutaCampo = '';
                    }
                    \$index = 1;
                    \$inicioRegistros ++;
                }

                /** Se crea la sección de totales */
                /** ----------------------------- */

                \$columnaInicioTotal = 1;
                \$columnaInicialTotal = '';
                if(!empty(\$camposTotalizados))
                {
                    \$sheet->getRowDimension(\$inicioRegistros)->setRowHeight(25);
                    \$sheet->getStyle('A'.\$inicioRegistros.':'.\$this->ultimaColumna.\$inicioRegistros)->getFont()->setBold(true)->setSize(11);
                    \$sheet->getStyle('A'.\$inicioRegistros.':'.\$this->ultimaColumna.\$inicioRegistros)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                    \$sheet->getStyle('A'.\$inicioRegistros.':'.\$this->ultimaColumna.\$inicioRegistros)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                    foreach(\$tablaTotales as \$key => \$campoTotal)
                    {
                        \$columnaInicialTotal = Coordinate::stringFromColumnIndex(\$columnaInicioTotal);
                        if(\$key == 'colspan' && \$campoTotal > 0)
                        {
                            \$columnaInicioTotal = \$campoTotal + 1;
                            \$sheet->setCellValue('A'.\$inicioRegistros, 'Total »');
                            \$columnaFinTotal = Coordinate::stringFromColumnIndex(\$campoTotal);
                            \$sheet->mergeCells('A'.\$inicioRegistros.':'.\$columnaFinTotal.\$inicioRegistros);
                        }
                        else
                        {
                            if(is_array(\$campoTotal)){\$campoTotal = \$campoTotal[0];}
                            \$sheet->setCellValue(\$columnaInicialTotal.\$inicioRegistros, \$campoTotal);
                            \$columnaInicioTotal ++;
                        }
                    }

                    /** Se aplican estilos a la cabecera del informe */
                    /** -------------------------------------------- */

                    \$sheet->getStyle('A'.\$inicioRegistros.':'.\$this->ultimaColumna.\$inicioRegistros)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('f2f2f2')
                    ;

                    \$styles = 
                    [
                        'borders' => 
                        [
                            'outline' => 
                            [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                'color' => ['argb' => 'FFB0B0B0'],
                            ]
                        ],
                    ];
                    \$sheet->getStyle('A'.\$inicioRegistros.':'.\$this->ultimaColumna.\$inicioRegistros)->applyFromArray(\$styles);
                    \$this->filaGeneral = \$inicioRegistros;
                    \$this->filaGeneral ++;
                }
            }

            public function crearTablaAgrupadaExcel(Request \$request, \$data, \$item, \$referencia, \$listAgrupada, \$titulo, \$camposAgrupacion, \$sheet, \$configuraciones)
            {
                /** 
                    * En esta función se crean las tablas del archivo excel, de acuerdo a los campos de agrupación configurados en el informe
                    * -----------------------------------------------------------------------------------------------------------------------
                    * @access public
                */
                
                if(\$referencia == 'registros')
                {
                    foreach(\$listAgrupada[array_key_first(\$listAgrupada)] as \$key => \$item)
                    {
                        if(\$key == 'referencia'){continue;}
                        \$nombreAgrupacion = explode('-', \$key);
                        if(count(\$nombreAgrupacion) > 1)
                        {
                            unset(\$nombreAgrupacion[0]);
                            \$nombreAgrupacion = implode('-', \$nombreAgrupacion);
                        }
                        else
                        {
                            \$nombreAgrupacion = \$key;
                        } 
                        
                        /** Se crea el título de agrupación con todos los campos relacionados */
                        /** ----------------------------------------------------------------- */
                        
                        \$sheet->getRowDimension(\$this->filaGeneral)->setRowHeight(30);
                        \$sheet->setCellValue('A'.\$this->filaGeneral, strip_tags(\$titulo));
                        \$sheet->mergeCells('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral);
                        \$sheet->getStyle('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

                        /** Se aplican estilos a la cabecera del informe */
                        /** -------------------------------------------- */

                        \$sheet->getStyle('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->getFill()
                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('f2f2f2')
                        ;
                        \$styles = 
                        [
                            'borders' => 
                            [
                                'allBorders' => 
                                [
                                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                    'color' => ['argb' => 'FFB0B0B0'],
                                ],
                            ],
                        ];
                        \$sheet->getStyle('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->getFont()->setBold(true)->setSize(11);
                        \$sheet->getStyle('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->applyFromArray(\$styles);
                        \$this->filaGeneral ++;

                        /** Se crea la tabla de detalles correspondiente a cada agrupación */
                        /** -------------------------------------------------------------- */

                        \$this->crearTablaRegistrosExcel(\$request, \$configuraciones, \$listAgrupada['registros'][\$item], true, \$sheet);

                        /** Se crea un separador */
                        /** -------------------- */

                        \$sheet->getRowDimension(\$this->filaGeneral)->setRowHeight(30);
                        \$sheet->mergeCells('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral);
                        \$this->filaGeneral ++;
                    }
                }
                else
                {
                    if(\$data['referencia'] != 'registros')
                    {
                        \$campo = array_filter(\$camposAgrupacion, fn(\$item) => \$item['nombre'] == \$referencia);
                        sort(\$campo);
                        \$tituloCampo = \$campo[0]['titulo'];

                        foreach(\$data[\$item] as \$key => \$itemAgrupacion)
                        {
                            \$nombreAgrupacion = explode('-', \$itemAgrupacion);
                            if(count(\$nombreAgrupacion) > 1)
                            {
                                unset(\$nombreAgrupacion[0]);
                                \$nombreAgrupacion = implode('-', \$nombreAgrupacion);
                            }
                            else
                            {
                                \$nombreAgrupacion = \$itemAgrupacion;
                            }
                            \$titulo .= '  |  '. \$tituloCampo.' » '.\$nombreAgrupacion;
                            \$this->crearTablaAgrupadaExcel(\$request, \$listAgrupada[\$data['referencia']], \$itemAgrupacion, \$data['referencia'], \$listAgrupada, \$titulo, \$camposAgrupacion, \$sheet, \$configuraciones);
                        }
                    }
                    else
                    {
                        foreach(\$data[\$item] as \$key => \$itemAgrupacion)
                        {
                            \$campo = array_filter(\$camposAgrupacion, fn(\$item) => \$item['nombre'] == \$referencia);
                            sort(\$campo);
                            \$tituloCampo = \$campo[0]['titulo'];
                            \$nombreAgrupacion = explode('-', \$key);
                            if(count(\$nombreAgrupacion) > 1)
                            {
                                unset(\$nombreAgrupacion[0]);
                                \$nombreAgrupacion = implode('-', \$nombreAgrupacion);
                            }
                            else
                            {
                                \$nombreAgrupacion = \$key;
                            } 
                            
                            /** Se crea el título de agrupación con todos los campos relacionados */
                            /** ----------------------------------------------------------------- */
                            
                            \$sheet->getRowDimension(\$this->filaGeneral)->setRowHeight(30);
                            \$tituloCampo = \$titulo.'  |  '.\$tituloCampo.' » '.\$nombreAgrupacion;
                            \$sheet->setCellValue('A'.\$this->filaGeneral, strip_tags(\$tituloCampo));
                            \$sheet->mergeCells('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral);
                            \$sheet->getStyle('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

                            /** Se aplican estilos a la cabecera del informe */
                            /** -------------------------------------------- */

                            \$sheet->getStyle('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->getFill()
                                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('f2f2f2')
                            ;
                            \$styles = 
                            [
                                'borders' => 
                                [
                                    'allBorders' => 
                                    [
                                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                        'color' => ['argb' => 'FFB0B0B0'],
                                    ],
                                ],
                            ];
                            \$sheet->getStyle('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->getFont()->setBold(true)->setSize(11);
                            \$sheet->getStyle('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral)->applyFromArray(\$styles);
                            \$this->filaGeneral ++;

                            /** Se crea la tabla de detalles correspondiente a cada agrupación */
                            /** -------------------------------------------------------------- */

                            \$this->crearTablaRegistrosExcel(\$request, \$configuraciones, \$listAgrupada['registros'][\$item.\$itemAgrupacion], true, \$sheet);

                            /** Se crea un separador */
                            /** -------------------- */

                            \$sheet->getRowDimension(\$this->filaGeneral)->setRowHeight(30);
                            \$sheet->mergeCells('A'.\$this->filaGeneral.':'.\$this->ultimaColumna.\$this->filaGeneral);
                            \$this->filaGeneral ++;
                        }
                    }
                }
            }

            /**
             * @Route("/Central/Reporteador/frameErrorInforme", name="central_reporteador_frame_error_informe")
            */
            public function frameErrorInforme(Request \$request)
            {
                /** 
                    * En esta función se genera la vista para visualizar los detalles de cualquier error ocurrido 
                    * en la descarga de un informe (excel, pdf).
                    * -------------------------------------------------------------------------------------------
                    * @access public
                */

                \$line = '';
                \$file = '';
                \$message = '';
                \$session = \$request->getSession();
                if(\$session->has('errorDescargaInforme'))
                {
                    \$errorDescargaInforme = \$session->get('errorDescargaInforme');
                    \$message = \$errorDescargaInforme['message'];
                    \$line = \$errorDescargaInforme['line'];
                    \$file = \$errorDescargaInforme['file'];
                    \$session->remove('errorDescargaInforme');
                }
                return \$this->render('Central/Reporteador/frameErrorInforme.html.twig',
                [
                    'file' => \$file,
                    'line' => \$line,
                    'message' => \$message
                ]);
            }
        }
        PHP;
        $this->fs->dumpFile($ruta, $plantilla);
    }

    public function crearPlantillaReporteadorTwig()
    {
        /** 
         * En esta función se crea la vista reporteador.html.twig en el path respectivo
         * ----------------------------------------------------------------------------
         * @access public
        */

        /** Definición de variables */
        /** ----------------------- */
        
        $row = '';
        $index = 1;
        $btnBuscar = '';
        $listCampos = [];
        $indexCampos = 1;
        $camposFiltros = '';
        $dimensionColumna = 0;
        $campos = $this->campos;
        $formularioFiltros = '';
        $dimensionesLabel = [0,0,0];
        $nombreControlador = 'central--reporteador';
        $ruta = 'src/Views/'.$this->modulo.'/Reporteador/reporteador.html.twig';
        $nombreControladorModulo = strtolower(str_replace('/', '--', $this->modulo));
        $this->archivosCreados[1] = ['» '.$ruta];

        /** Se ordena el posicionamiento de los campos */
        /** ------------------------------------------ */

        foreach($campos as $key => $campo)
        {
            $dataCampos[$key] = ['tipo' => $campo['tipo']];
            if($indexCampos == 3 || $index == count($campos))
            {
                $listCampos[] = $dataCampos;
                $dataCampos = [];
                $indexCampos = 0;
            }
            $indexCampos ++;
            $index ++;
        }

        /** Se establecen las dimensiones de cada campo, determinando el ancho de la columna y el padding derecho entre cada campo */
        /** ---------------------------------------------------------------------------------------------------------------------- */

        $indexCampos = 0;
        $indexCamposColumna = 0;
        foreach($listCampos as $index => $campos)
        {
            if($index == 0)
            {
                if(count($campos) == 3){$dimensionColumna = 4;}
                if(count($campos) == 2){$dimensionColumna = 6;}
                if(count($campos) == 1){$dimensionColumna = 12;}
            }

            foreach($campos as $key => $campo)
            {
                if($index > 0)
                {
                    $dimensionColumna = 4;
                    if($indexCamposColumna == count($campos) - 1){$dimensionColumna = 12 - (($indexCamposColumna) * 4);}
                }
                $dimensionesLabel[$indexCampos] = (strlen($key) > $dimensionesLabel[$indexCampos])?strlen(ucfirst($key)):$dimensionesLabel[$indexCampos];
                $listCampos[$index][$key]['padding'] = ($indexCampos == count($campos) - 1)?'':'pr-0';
                $listCampos[$index][$key]['col'] = $dimensionColumna;
                $indexCamposColumna ++;
                $indexCampos ++;
            }
            $indexCamposColumna = 0;
            $indexCampos = 0;
        }

        /** Se establece el ancho en pixeles para los label de cada campo */
        /** ------------------------------------------------------------- */

        $indexCampos = 0;
        foreach($listCampos as $index => $campos)
        {
            foreach($campos as $key => $campo)
            {
                $listCampos[$index][$key]['label'] = ($campo['tipo'] == 'boolean')?0:$dimensionesLabel[$indexCampos];
                $indexCampos ++;
            }
            $indexCampos = 0;
        }

        /** Se generan los campos del formulario */
        /** ------------------------------------ */

        $dataAction = '';
        foreach($listCampos as $index => $campos)
        {   
            $indexCampos = 0;
            $row = ($index > 0)?'mt-2':'';
            foreach($campos as $key => $campo)
            {
                $col = $campo['col'];
                $padding = $campo['padding'];

                $label = 27 + ($campo['label'] * 7);
                if($campo['tipo'] != 'relation')
                {
                    if($campo['tipo'] != 'boolean')
                    {
                        if($indexCampos == 0)
                        {
                            $camposFiltros .= 
                            <<<TWIG
                                                    <div class="col-$col $padding">
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    {{ form_label(formFiltros.$key,formFiltros.$key, {'label_attr' : {'class' : 'input-group-text font-weight-bold', 'style' : 'width:{$label}px'}}) }}
                                                                </div>
                                                                {{ form_widget(formFiltros.$key, {'attr' : {'class' : 'form-control'}})}}
                                                            </div>
                                                        </div>\n
                            TWIG;
                        }
                        else
                        {
                            $camposFiltros .= 
                            <<<TWIG
                                                        <div class="col-$col $padding">
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    {{ form_label(formFiltros.$key,formFiltros.$key, {'label_attr' : {'class' : 'input-group-text font-weight-bold', 'style' : 'width:{$label}px'}}) }}
                                                                </div>
                                                                {{ form_widget(formFiltros.$key, {'attr' : {'class' : 'form-control'}})}}
                                                            </div>
                                                        </div>\n
                            TWIG;
                        }
                    }
                    else
                    {
                        if($indexCampos == 0)
                        {
                            $camposFiltros .= 
                            <<<TWIG
                                                    <div class="col-$col $padding">
                                                            <div class="input-group">
                                                                {{ form_label(formFiltros.$key,formFiltros.$key, {'label_attr' : {'class' : 'form-control input-group-text font-weight-bold' }} ) }}
                                                                <div class="input-group-text d-flex justify-content-center" style="background: white; width:auto">
                                                                    <div class="custom-control custom-switch">
                                                                        {{ form_widget(formFiltros.$key, {'attr' : {'class' : 'custom-control-input'}}) }}
                                                                        <label class="custom-control-label" for="filtros_busqueda_$key"></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>\n
                            TWIG;
                        }
                        else
                        {
                            $camposFiltros .= 
                            <<<TWIG
                                                        <div class="col-$col $padding">
                                                            <div class="input-group">
                                                                {{ form_label(formFiltros.$key,formFiltros.$key, {'label_attr' : {'class' : 'form-control input-group-text font-weight-bold' }} ) }}
                                                                <div class="input-group-text d-flex justify-content-center" style="background: white; width:auto">
                                                                    <div class="custom-control custom-switch">
                                                                        {{ form_widget(formFiltros.$key, {'attr' : {'class' : 'custom-control-input'}}) }}
                                                                        <label class="custom-control-label" for="filtros_busqueda_$key"></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>\n
                            TWIG;
                        }
                    }
                }
                else
                {
                    if($key == 'informe'){$dataAction = "'data-action' : 'central--reporteador#seleccionarInforme'";}
                    if($indexCampos == 0)
                    {
                        $camposFiltros .= 
                        <<<TWIG
                                                <div class="col-$col $padding">
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                {{ form_label(formFiltros.$key,formFiltros.$key, {'label_attr' : {'class' : 'input-group-text font-weight-bold', 'style' : 'width:{$label}px'}}) }}
                                                            </div>
                                                            {{ form_widget(formFiltros.$key, {'attr' : 
                                                                {
                                                                    'data-size' : '10',
                                                                    'data-width' : '50%',
                                                                    'data-container' : 'body',
                                                                    'class' : 'form-control selectpicker',
                                                                    $dataAction
                                                                }
                                                            })}}
                                                        </div>
                                                    </div>\n
                        TWIG;
                    }
                    else
                    {
                        $camposFiltros .= 
                        <<<TWIG
                                                    <div class="col-$col $padding">
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                {{ form_label(formFiltros.$key,formFiltros.$key, {'label_attr' : {'class' : 'input-group-text font-weight-bold', 'style' : 'width:{$label}px'}}) }}
                                                            </div>
                                                            {{ form_widget(formFiltros.$key, {'attr' : 
                                                                {
                                                                    'data-size' : '10',
                                                                    'data-width' : '50%',
                                                                    'data-container' : 'body',
                                                                    'class' : 'form-control selectpicker'
                                                                }
                                                            })}}
                                                        </div>
                                                    </div>\n
                        TWIG;
                    }
                }
                $indexCampos ++;
            } 
            if($index == 0)
            {
                $formularioFiltros .= 
                <<<TWIG
                <div class="row $row">
                    $camposFiltros
                                        </div>\n
                TWIG;
            }
            else
            {
                $formularioFiltros .= 
                <<<TWIG
                                        <div class="row $row">
                    $camposFiltros
                                        </div>\n
                TWIG;
            }
            $camposFiltros = '';
        }

        $plantilla =
        <<<TWIG
        {% extends 'base.html.twig' %}
        {% block body %}
            {{ parent() }}
            <style>
                * {
                    font-size: 13px;
                }

                body {
                    margin: 0;
                    padding: 0;
                    height: 100vh;
                    position: relative;
                }

                .montserrat {
                    font-family: "Montserrat", serif;
                    font-optical-sizing: auto;
                    font-weight: 700;
                    font-style: normal;
                }

                .montserrat-text {
                    font-family: "Montserrat", serif;
                    font-optical-sizing: auto;
                    font-weight: 500;
                    font-style: normal;
                }

                .btn.dropdown-toggle.bs-placeholder.btn-light, .btn.dropdown-toggle.btn-light {
                    border: 1px solid #d0d4da;
                }

                .seccionInforme:hover {
                    color:#007BFF;
                }

                .seccionInforme:hover svg {
                    color:#007BFF;
                }

                .listado {
                    max-height:780px;
                }

                .listado::-webkit-scrollbar {
                    width: 5px;
                    height: 5px;
                }

                .listado::-webkit-scrollbar-thumb {
                    background-color: #dee2e6;
                    border-radius: 20px;
                }

                body::-webkit-scrollbar {
                    width: 5px;
                    height: 5px;
                }

                body::-webkit-scrollbar-thumb {
                    background-color: #dee2e6;
                    border-radius: 20px;
                }

                .listadoTabla::-webkit-scrollbar {
                    width: 5px;
                    height: 5px;
                }

                .listadoTabla::-webkit-scrollbar-thumb {
                    background-color: #dee2e6;
                    border-radius: 20px;
                }
                
                .paginas {
                    transition: all 0.5s ease;
                }

                .paginas:hover {
                    color: white;
                    background: #17A3B8 !important;
                }

                .logo {
                    animation: animarLogo 2s linear infinite;
                }
                
                .loader {
                    top: 2px;
                    left: 10px;
                    width: 40px;
                    height: 40px;
                    display: block;
                    position:absolute;
                    border-radius: 50%;
                    border: 6px solid #f3f3f3;
                    border-top: 6px solid #007BFF;
                    animation: spin 2s linear infinite, cambiarColorLogo 20s linear infinite;
                }

                @keyframes in-circle-swoop {
                    from {
                        clip-path: var(--circle-top-right-out);
                    }
                    to {
                        clip-path: var(--circle-bottom-right-in);
                    }
                }

                [transition-style="in:custom:circle-swoop"] {
                    --transition__duration: 1s;
                    animation-name: in-circle-swoop;
                }

                @keyframes out-circle-swoop {
                    from {
                        clip-path: var(--circle-bottom-right-in);
                    }
                    to {
                        clip-path: var(--circle-top-right-out);
                    }
                }

                [transition-style="out:custom:circle-swoop"] {
                    --transition__duration: 1s;
                    --transition__easing: cubic-bezier(.30, 1, .25, 1);
                    animation-name: out-circle-swoop;
                }

                @keyframes spin {
                    0% {transform: rotate(0deg)}
                    100% {transform: rotate(360deg)}
                }

                @keyframes animarLogo {
                    0% {transform:scale(0.2)}
                    50% {transform:scale(0.3)}
                    100% {transform:scale(0.2)}
                }

                @keyframes cambiarColorLogo {
                    0% {border-top: 6px solid #007BFF}
                    50% {border-top: 6px solid #28A745}
                    75% {border-top: 6px solid #DC3545}
                    100% {border-top: 6px solid #007BFF}
                }

                @media (max-height : 641px) {
                    .listado {
                        max-height:480px;
                    }
                }

                .ripple {
                    width: 1rem;
                }
                
                .ripple::before {
                    content: "";
                    display: grid;
                    grid-area: 1/1;
                    aspect-ratio: 1;
                    border-radius: 50%;
                    box-shadow: 0 0 0 0 #17a2b85e;
                    animation: rp 3s linear infinite;
                }

                .ripple::before {--s: 3s}

                @keyframes rp {
                    to {box-shadow: 0 0 0 1rem #0000}
                }

                .titulo:hover {
                    color:#007BFF;
                }

                .titulo:hover i {
                    color:#007BFF !important;
                }

                .titulo:hover svg {
                    color:#007BFF !important;
                }

                .buscar:focus {
                    border-color:#d0d4da;
                    box-shadow:none;
                }

                #btnBusquedaRapida:hover {
                    box-shadow:0px 0px 5px 1px #17A;
                }
                
                .menuReporteador:hover {
                    background:#17A !important;
                }

                .menuReporteador:hover * {
                    color:white;
                }

                .itemMenu:hover {
                    background: #d2d4da85;
                }

                .itemMenu:hover .flecha {
                    opacity:1 !important;
                }

                .itemMenu:hover span {
                    margin-left:0px !important;
                }

                .menuReporteadorError:hover {
                    background:#DC3545 !important;
                }
                
                .registroInfome:hover {
                    background:#dfe2e66e !important;
                }

                .cerrarError:hover {
                    box-shadow: 0px 0px 9px 0px;
                }
            </style>
            <div class="container-fluid pt-4" data-turbo="true" 
                {{ stimulus_controller(
                    {
                        '$nombreControlador' :
                        {
                            'urlGenerarInforme' : path('central_reporteador_generar_informe'),
                            'urlFrameErrorInforme' : path('central_reporteador_frame_error_informe'),
                            'urlDescargarInformePdf' : path('central_reporteador_descargar_informe_pdf'),
                            'urlDescargarInformeExcel' : path('central_reporteador_descargar_informe_excel')
                        },
                        '$nombreControladorModulo--reporteador' : {}
                    }
                )}}
            >
                <div class="col-12 animate__animated animate__fadeIn" style="display:flex; align-items:center; justify-content:center">
                    <div class="card shadow-lg p-0 mb-5 bg-white rounded" style="width:100%">
                        <div class="card-body" style="overflow:hidden">
                            <div class="list-group-item active font-weight-bold" style="border-radius:5px 5px 0px 0px">Informes</div>
                            {{ form_start(formFiltros, {'attr' : {'class' : 'mb-0', 'id' : 'filtrosInforme', 'data-action' : '$nombreControlador#generarInforme'}}) }}
                                <div class="list-group-item animate__animated animate__fadeIn animate__animated animate__fadeIn seccionFiltros"> 
                                    $formularioFiltros
                                </div>
                                <div class="list-group-item seccionFiltros">
                                    <button type="submit" class="btn btn-success" id="btnGenerarInforme"><i class="fas fa-search"></i> Generar informe</button>
                                    <button type="button" class="btn btn-info" data-action="$nombreControlador#limpiarCampos"><i class="fas fa-broom"></i> Limpiar</button>
                                    <a class="btn btn-danger" href="#" target="_top" id="btnRegresar"><i class="fas fa-caret-left"></i> Regresar</a>
                                </div>
                            {{ form_end(formFiltros) }}
                            <div class="list-group-item seccionInforme" style="border-top:none; display:flex; align-items:center; gap:5px; cursor:pointer" data-action="click->$nombreControlador#showSeccionFiltros">
                                <span class="montserrat" style="font-size:12px">Generación de informes</span>
                                <i class="fas fa-search"></i>
                            </div>
                            <div class="list-group-item">
                                <div id="frameErrorInforme" style=
                                "
                                    top:0; 
                                    left:0; 
                                    z-index:1; 
                                    width:100%; 
                                    height:100%; 
                                    display:none; 
                                    position:absolute;
                                    background:#FFFFFF99;
                                "></div>
                                <div class="list-group-item p-0" style="border:none">
                                    <div style="display:none" data-$nombreControlador-target="cargandoFiltros" id="cargandoFiltros">
                                        <div class="animate__animated animate__fade" style="position:absolute; width:100%; height:100%; background:white; z-index:2; left:0; top:0; opacity:0.6"></div>
                                        <div class="animate__animated animate__flipInX" style="position:absolute; z-index:3; width:100%; height:100%; left:0; top:0; display:flex; align-items:center; justify-content:center">
                                            <div style="background:white; width: 470px; display: flex; align-items: center; justify-content: center; padding: 8px; border-radius: 15px; border:1px solid #d1d4da;">
                                                <div style="position:relative;">
                                                    <div class="loader"></div>
                                                    <img src="{{ asset('Imgs/logo.png') }}" class="logo">
                                                </div>
                                                <b style="font-size:13px">Cargando información...</b>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="list-group-item listado" id="frameInforme" style="border:none; padding:0px; padding-right:2px; overflow-y:auto">
                                        <div class="text-danger" style="display:flex; align-items:center; justify-content:center; font-weight:bold; height:50px">¡No se encontraron registros para listar!</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>      
                </div>
            </div>
        {% endblock %}
        TWIG;
        $this->fs->dumpFile($ruta, $plantilla);
    }

    public function crearPlantillaFrameErrorInforme()
    {
        /** 
         * En esta función se crea la plantilla para reportar errores al generar
         * o descargar informes.
         * ---------------------------------------------------------------------
         * @access public
        */

        $ruta = 'src/Views/Central/Reporteador/frameErrorInforme.html.twig';
        $this->archivosCreados[4] = ["» $ruta"];
        $plantilla =
        <<<TWIG
        <div class="animate__animated animate__fadeIn" style="display:flex; padding: 40px 0px; align-items:center; justify-content:center; height:100%">
            <div style="display:flex; align-items:center; padding: 35px; border-radius:25px; gap: 15px; box-shadow: 9px 1px 17px 0px #E2E2E2; position:relative; overflow:hidden; background:white">
                <i 
                    data-action="click->central--reporteador#cerrarErrorInforme"
                    class="fas fa-times-circle cerrarError text-danger animate__animated animate__fadeInRight animate__delay-1s" 
                    style="position:absolute; right:18px; top:15px; font-size:15px; cursor:pointer; z-index:2; transition:all 0.5s ease; border-radius:50%"
                ></i>
                <img src="{{ asset('Imgs/fondo.jpg') }}" style=
                "
                    left: 0px;
                    width: 100%;
                    opacity: 0.1;
                    height: 400px;
                    position: absolute;
                ">
                <i class="fas fa-cog fa-spin" style=
                "
                    z-index: 1;
                    top: -28px;
                    left: -98px;
                    opacity: 0.6;
                    color: #e5e5e5;
                    font-size: 151px;
                    position: absolute;
                    --fa-animation-delay: 1s;
                    --fa-animation-duration: 15s;
                "></i>
                <div style="display:flex; align-items:center; justify-content:center; width:90px; height:90px; border-radius:50%; padding:9px; position:relative">
                    <img style="width:100%; height:100%; object-fit:contain;" src="{{ asset('Imgs/logoActualizado.png') }}">
                </div>
                <div style="border-left: 1px solid #e5e5e5; padding-left: 20px; position:relative">
                    <div class="montserrat" style="background: #DC3545; width: fit-content; gap:5px; display: flex; align-items: center; justify-content: center; padding: 4px 18px; color: white; border-radius: 16px; font-size: 13px; margin-bottom: 7px;">
                        <i class="fas fa-circle-exclamation" style="color:white; font-size:15px"></i>
                        <span style="font-size:11px">Oops! Algo salió mal</span>
                    </div>
                    <div class="montserrat-text" style="font-size:11px; color:#313131;">Se presentó el siguiente error al generar el informe</div>
                    <hr style="border-style:dashed">
                    {% if line != '' %}
                        <div style="display:flex; align-items:center; gap:5px">
                            <i class="fas fa-angle-double-right" style="font-size:8px"></i>
                            <span class="montserrat" style="font-size:11px">Línea: </span>
                            <span class="montserrat-text" style="font-size:11px; margin-right:2px">{{ line }}</span>
                        </div>
                    {% endif %}
                    {% if file != '' %}
                        <div style="display:flex; align-items:center; gap:5px; margin-top:2px">
                            <i class="fas fa-angle-double-right" style="font-size:8px"></i>
                            <span class="montserrat" style="font-size:11px">Archivo: </span>
                            <span class="montserrat-text" style="font-size:11px; margin-right:2px">{{ file }}</span>
                        </div>
                    {% endif %}
                    {% if message != '' %}
                        <div style="display:flex; align-items:center; gap:5px; margin-top:2px">
                            <i class="fas fa-angle-double-right" style="font-size:8px"></i>
                            <span class="montserrat" style="font-size:11px">Detalle: </span>
                            <span class="montserrat-text" style="font-size:11px; margin-right:2px">{{ message }}</span>
                        </div>
                    {% else %}
                        <div style="display:flex; align-items:center; gap:5px; margin-top:2px">
                            <i class="fas fa-angle-double-right" style="font-size:8px"></i>
                            <span class="montserrat" style="font-size:11px">Detalle: </span>
                            <span class="montserrat-text" style="font-size:11px; margin-right:2px">Por favor revise la funcionalidad implementada para la descarga del informe</span>
                        </div>
                    {% endif %}
                </div>
            </div>
        </div>
        TWIG;
        $this->fs->dumpFile($ruta, $plantilla);
    }
}
