<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class MakeBasicCrudCommand extends Command
{   
    private $fs;
    private $em;
    private $rutas;
    private $campos;
    private $archivosCreados;
    protected static $defaultName = 'app:make:basic-crud';
    protected static $defaultDescription = 'Genera un CRUD básico, personalizado con estilos estandarizados de CC3';

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->rutas = [];
        $this->campos = [];
        parent::__construct();
        $this->archivosCreados = [];
        $this->fs = new Filesystem();

    }

    protected function configure(): void
    {
        $this->addArgument('entidad', InputArgument::REQUIRED, 'Nombre de la entidad');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** 
         * En esta función se genera el CRUD básico de una entidad específica, aplicando
         * estilos personalizados de CC3. Para ello, se crean los siguientes archivos:
         * -----------------------------------------------------------------------------
         *   » NuevoEntidadType.php
         *   » EntidadController.php
         *   » entidad_controller.js
         *   » listaEntidad.html.twig
         *   » frameListaEntidad.html.twig
         *   » FiltrosBusquedaEntidadType.php
         * -----------------------------------------------------------------------------
         * @access public
        */

        /** Definición de variables */
        /** ----------------------- */

        $camposSeleccionados = ['formulario' => [], 'filtros' => []];
        $entidad = $input->getArgument('entidad');
        $io = new SymfonyStyle($input, $output);
        $clase = "App\Entity\\$entidad";
        $camposRelacion = [];

        /** Se valida si la clase existe */
        /** ---------------------------- */

        if(class_exists($clase)) 
        {
            /** Se obtienen los campos de la entidad, omitiendo: 'id', 'usuCrea', 'fechaCrea', 'usuMod', 'fechaMod', 'usuElimina', 'fechaElimina' */
            /** --------------------------------------------------------------------------------------------------------------------------------- */

            $nombreEntidad = explode('\\', $entidad);
            $metadata = $this->em->getClassMetadata($clase);
            $nombreEntidadMinuscula = strtolower($nombreEntidad[0]);
            $rutaRepositorio = 'src/Repository/'. str_replace("\\", '/', $entidad).'Repository.php';
            $campos = array_merge($metadata->getFieldNames(), array_keys($metadata->getAssociationMappings()));
            foreach($metadata->getAssociationMappings() as $key => $campo){$camposRelacion[$key] = $campo['targetEntity'];}
            $nombreEntidadRuta = (count($nombreEntidad) > 1)?strtolower($nombreEntidad[array_key_last($nombreEntidad)]):strtolower($entidad);
            $campos = array_filter($campos, fn($item) => !in_array($item, ['id', 'usuCrea', 'fechaCrea', 'usuMod', 'fechaMod', 'usuElimina', 'fechaElimina']));
            $nombreEntidadRutaCapitalize = ucfirst($nombreEntidadRuta);
            $camposSeleccionados['entidades'] = $camposRelacion;
            sort($campos);

            /** Se valida la ejecución del comando */
            /** ---------------------------------- */

            $confirmarEjecucion = $io->confirm("► Se sobrescribirán los archivos de rutas, controladores, formularios y plantillas twig asociados a la entidad $nombreEntidadRutaCapitalize.\n  ¿Está seguro de continuar?");
            if(!$confirmarEjecucion){return Command::SUCCESS;}

            /** Se valida si existe el archivo de repositorio asociado a la entidad respectiva */
            /** ------------------------------------------------------------------------------ */

            if(!file_exists($rutaRepositorio))
            {
                $io->error("No existe un repositorio para la entidad $nombreEntidadRutaCapitalize");
                return Command::FAILURE;
            }

            /** Se valida si existe el archivo de rutas asociado al módulo respectivo. Si es así, se agregan las rutas requeridas en el CRUD básico */
            /** ----------------------------------------------------------------------------------------------------------------------------------- */

            if(file_exists("src/Routing/$nombreEntidad[0].yaml"))
            {
                /** Se valida si las rutas existen */
                /** ------------------------------ */

                $rutas =
                <<<YAML
                # Rutas del CRUD básico 
                # =====================

                {$nombreEntidadMinuscula}_listado_$nombreEntidadRuta:
                    path:      /$nombreEntidad[0]/Listado$nombreEntidadRutaCapitalize
                    defaults:   {_controller: App\Controller\\{$entidad}Controller::listar{$nombreEntidadRutaCapitalize}}

                {$nombreEntidadMinuscula}_frame_lista_$nombreEntidadRuta:
                    path:      /$nombreEntidad[0]/frameLista$nombreEntidadRutaCapitalize
                    defaults:   {_controller: App\Controller\\{$entidad}Controller::frameLista{$nombreEntidadRutaCapitalize}}
                
                {$nombreEntidadMinuscula}_frame_nuevo_$nombreEntidadRuta:
                    path:      /$nombreEntidad[0]/frameNuevo$nombreEntidadRutaCapitalize
                    defaults:   {_controller: App\Controller\\{$entidad}Controller::frameNuevo{$nombreEntidadRutaCapitalize}}

                {$nombreEntidadMinuscula}_guardar_$nombreEntidadRuta:
                    path:      /$nombreEntidad[0]/guardar$nombreEntidadRutaCapitalize
                    defaults:   {_controller: App\Controller\\{$entidad}Controller::guardar{$nombreEntidadRutaCapitalize}}

                {$nombreEntidadMinuscula}_eliminar_$nombreEntidadRuta:
                    path:      /$nombreEntidad[0]/eliminar$nombreEntidadRutaCapitalize
                    defaults:   {_controller: App\Controller\\{$entidad}Controller::eliminar{$nombreEntidadRutaCapitalize}}
                YAML;
                file_put_contents("src/Routing/$nombreEntidad[0].yaml", $rutas);

                /** Se guardan las rutas agregadas */
                /** ------------------------------ */

                $this->rutas['guardar'] = "{$nombreEntidadMinuscula}_guardar_$nombreEntidadRuta";
                $this->rutas['listado'] = "{$nombreEntidadMinuscula}_listado_$nombreEntidadRuta";
                $this->rutas['eliminar'] = "{$nombreEntidadMinuscula}_eliminar_$nombreEntidadRuta";
                $this->rutas['frameLista'] = "{$nombreEntidadMinuscula}_frame_lista_$nombreEntidadRuta";
                $this->rutas['frameNuevo'] = "{$nombreEntidadMinuscula}_frame_nuevo_$nombreEntidadRuta";
            }
            else
            {
                $io->error("No existe el archivo de rutas $nombreEntidad[0].yaml");
                return Command::FAILURE;
            }

            /** Se presenta la lista de campos y se capturan las opciones seleccionadas del formulario principal */
            /** ------------------------------------------------------------------------------------------------ */
            
            $output->writeln('');
            $campos[count($campos)] = 'Todo';
            $listaCamposFormularioPrincipal = new ChoiceQuestion("<fg=#28A745> ► Seleccione los campos que desea incluir en el formulario principal </>\n<fg=#28A745>   ------------------------------------------------------------------ </>", $campos);
            $listaCamposFormularioPrincipal->setMultiselect(true);
            $camposSeleccionadosFormularioPrincipal = $this->getHelper('question')->ask($input, $output, $listaCamposFormularioPrincipal);
            if(!empty(array_filter($camposSeleccionadosFormularioPrincipal, fn($item) => $item == $campos[count($campos) - 1])))
            {
                $camposSeleccionadosFormularioPrincipal = array_filter($campos, fn($item) => $item != $campos[count($campos) - 1]);
            }
            $output->writeln('');
            $io->horizontalTable(['Campos seleccionados'], array_map(fn($item) => [$item], $camposSeleccionadosFormularioPrincipal));
            $output->writeln('');
            
            /** Se presenta la lista de campos y se capturan las opciones seleccionadas para los filtros de búsqueda */
            /** ---------------------------------------------------------------------------------------------------- */

            $listaCamposFiltrosBusqueda = new ChoiceQuestion("<fg=#28A745> ► Seleccione los campos que desea incluir en los filtros de búsqueda </>\n<fg=#28A745>   ------------------------------------------------------------------ </>", $campos);
            $listaCamposFiltrosBusqueda->setMultiselect(true);
            $camposSeleccionadosFiltrosBusqueda = $this->getHelper('question')->ask($input, $output, $listaCamposFiltrosBusqueda);
            if(!empty(array_filter($camposSeleccionadosFiltrosBusqueda, fn($item) => $item == $campos[count($campos) - 1])))
            {
                $camposSeleccionadosFiltrosBusqueda = array_filter($campos, fn($item) => $item != $campos[count($campos) - 1]);
            }
            $output->writeln('');
            $io->horizontalTable(['Campos seleccionados'], array_map(fn($item) => [$item], $camposSeleccionadosFiltrosBusqueda));
            $output->writeln('');

            /** Se obtiene el tipo de dato de los campos seleccionados */
            /** ------------------------------------------------------ */

            foreach($camposSeleccionadosFormularioPrincipal as $campo)
            {
                $camposSeleccionados['formulario'][$campo] = !empty($metadata->getTypeOfField($campo))?$metadata->getTypeOfField($campo):'relation';
            }

            foreach($camposSeleccionadosFiltrosBusqueda as $campo)
            {
                $camposSeleccionados['filtros'][$campo] = !empty($metadata->getTypeOfField($campo))?$metadata->getTypeOfField($campo):'relation';
            }
            $this->campos = $camposSeleccionados;

            /** Se crean los distintos archivos del CRUD */
            /** ---------------------------------------- */

            $this->crearPlantillaFormularioPrincipalType($entidad, $camposSeleccionados);
            $this->crearPlantillaFiltrosBusquedaType($entidad, $camposSeleccionados);
            $this->crearPlantillaControllerStimulus($entidad, $camposSeleccionados);
            $this->crearPlantillaFrameListaTwig($entidad, $camposSeleccionados);
            $this->crearPlantillaFrameNuevoTwig($entidad, $camposSeleccionados);
            $this->crearPlantillaController($entidad, $camposSeleccionados);
            $this->crearPlantillaListaTwig($entidad, $camposSeleccionados);
            $this->crearFuncionFiltrosBusqueda($entidad, $rutaRepositorio);

            ksort($this->archivosCreados);
            $io->success("El CRUD básico se ha creado con éxito. Se agregaron los siguientes archivos:");
            $io->table(["\t    Archivos creados/actualizados"], $this->archivosCreados);
            return Command::SUCCESS;
        }
        else
        {
            $io->error("La entidad $clase no existe");
            return Command::FAILURE;
        }
    }

    public function crearPlantillaFiltrosBusquedaType($entidad, $camposSeleccionados)
    {
        /** 
         * En esta función se crea el archivo FiltrosBusquedaEntidadType.php en el path correspondiente
         * --------------------------------------------------------------------------------------------
         * @access public
        */ 

        /** Definición de variables */
        /** ----------------------- */

        $camposFiltros = '';
        $entidadesImportadas = '';
        $repositoriosImportados = '';
        $nombreEntidad = explode('\\', $entidad);
        $namespace = (count($nombreEntidad) > 1)?"App\Form\\".$nombreEntidad[0]:'App\Form';
        $nombreRuta = (count($nombreEntidad) > 1)?"src/Form/".$nombreEntidad[0].'/':'src/Form/';
        $nombreEntidad = (count($nombreEntidad) > 1)?$nombreEntidad[array_key_last($nombreEntidad)]:$entidad;
        $ruta = "{$nombreRuta}FiltrosBusqueda{$nombreEntidad}Type.php";
        $this->archivosCreados[5] = ['» '.$ruta];

        /** Se generan los campos del formulario */
        /** ------------------------------------ */

        foreach($camposSeleccionados['filtros'] as $key => $campo)
        {
            if(!array_key_exists($key, $camposSeleccionados['entidades']))
            {
                if(in_array($campo, ['date', 'datetime']))
                {
                    $camposFiltros .= 
                    <<<PHP
                                ->add('$key', DateType::class, ['widget' => 'single_text', 'data' => new \DateTime('now', new \DateTimeZone('America/Bogota')), 'required' => false])\n
                    PHP;
                }
                elseif($campo == 'boolean')
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
                                ->add('$key', TextType::class, ['required' => false])\n
                    PHP;
                }
            }
            else
            {
                $nombreClase = explode("\\", $camposSeleccionados['entidades'][$key]);
                $entidadesImportadas .= 'use '.$camposSeleccionados['entidades'][$key].";\n";
                $choiceLabel = property_exists($camposSeleccionados['entidades'][$key], 'nombre')?'nombre':'id';
                $repositoriosImportados .= 'use '.str_replace('Entity', 'Repository', $camposSeleccionados['entidades'][$key])."Repository;\n";
                $camposFiltros .= 
                <<<PHP
                            ->add('$key', EntityType::class, 
                                [
                                    'required' => false,
                                    'choice_value' => 'id', 
                                    'choice_label' => '$choiceLabel',
                                    'placeholder' => 'Seleccione', 
                                    'class' => {$nombreClase[count($nombreClase) - 1]}::class,  
                                ]
                            )\n
                PHP;
            }
        }

        $plantilla =
        <<<PHP
        <?php

        namespace $namespace;
        
        $entidadesImportadas
        $repositoriosImportados
        use Doctrine\ORM\EntityRepository;
        use Symfony\Component\Form\AbstractType;
        use Symfony\Component\Form\FormBuilderInterface;
        use Symfony\Bridge\Doctrine\Form\Type\EntityType;
        use Symfony\Component\OptionsResolver\OptionsResolver;
        use Symfony\Component\Form\Extension\Core\Type\DateType;
        use Symfony\Component\Form\Extension\Core\Type\TextType;
        use Symfony\Component\Form\Extension\Core\Type\HiddenType;
        use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
        
        class FiltrosBusqueda{$nombreEntidad}Type extends AbstractType
        {
            public function buildForm(FormBuilderInterface \$builder, array \$options)
            {

                \$builder\n$camposFiltros
                ;
            }
        
            public function configureOptions(OptionsResolver \$resolver)
            {
                \$resolver->setDefaults([]);
            }
        }
        PHP;
        $this->fs->dumpFile($ruta, $plantilla);
    }

    public function crearPlantillaFormularioPrincipalType($entidad, $camposSeleccionados)
    {
        /** 
         * En esta función se crea el archivo NuevoEntidadType.php en el path correspondiente
         * ----------------------------------------------------------------------------------
         * @access public
        */ 

        /** Definición de variables */
        /** ----------------------- */

        $repositoriosImportados = '';
        $camposFormularioPrincipal = '';
        $nombreEntidad = explode('\\', $entidad);
        $entidadesImportadas = "use App\Entity\\$entidad;\n";
        $namespace = (count($nombreEntidad) > 1)?"App\Form\\".$nombreEntidad[0]:'App\Form';
        $nombreRuta = (count($nombreEntidad) > 1)?"src/Form/".$nombreEntidad[0].'/':'src/Form/';
        $nombreEntidad = (count($nombreEntidad) > 1)?$nombreEntidad[array_key_last($nombreEntidad)]:$entidad;
        $ruta = "{$nombreRuta}Nuevo{$nombreEntidad}Type.php";
        $this->archivosCreados[0] = ['» '.$ruta];

        /** Se generan los campos del formulario */
        /** ------------------------------------ */

        foreach($camposSeleccionados['formulario'] as $key => $campo)
        {
            if(!array_key_exists($key, $camposSeleccionados['entidades']))
            {
                if(in_array($campo, ['date', 'datetime']))
                {
                    $camposFormularioPrincipal .= 
                    <<<PHP
                                ->add('$key', DateType::class, ['widget' => 'single_text', 'data' => new \DateTime('now', new \DateTimeZone('America/Bogota')), 'required' => true])\n
                    PHP;
                }
                elseif($campo == 'boolean')
                {
                    $camposFormularioPrincipal .= 
                    <<<PHP
                                ->add('$key', CheckboxType::class, ['required' => false])\n
                    PHP;
                }
                else
                {
                    $camposFormularioPrincipal .= 
                    <<<PHP
                                ->add('$key', TextType::class, ['required' => true])\n
                    PHP;
                }
            }
            else
            {
                $nombreClase = explode("\\", $camposSeleccionados['entidades'][$key]);
                $entidadesImportadas .= 'use '.$camposSeleccionados['entidades'][$key].";\n";
                $choiceLabel = property_exists($camposSeleccionados['entidades'][$key], 'nombre')?'nombre':'id';
                $repositoriosImportados .= 'use '.str_replace('Entity', 'Repository', $camposSeleccionados['entidades'][$key])."Repository;\n";
                $camposFormularioPrincipal .= 
                <<<PHP
                            ->add('$key', EntityType::class, 
                                [
                                    'required' => true,
                                    'choice_value' => 'id', 
                                    'choice_label' => '$choiceLabel',
                                    'placeholder' => 'Seleccione', 
                                    'class' => {$nombreClase[count($nombreClase) - 1]}::class,  
                                ]
                            )\n
                PHP;
            }
        }

        $plantilla =
        <<<PHP
        <?php

        namespace $namespace;
        
        $entidadesImportadas
        $repositoriosImportados
        use Doctrine\ORM\EntityRepository;
        use Symfony\Component\Form\AbstractType;
        use Symfony\Component\Form\FormBuilderInterface;
        use Symfony\Bridge\Doctrine\Form\Type\EntityType;
        use Symfony\Component\OptionsResolver\OptionsResolver;
        use Symfony\Component\Form\Extension\Core\Type\DateType;
        use Symfony\Component\Form\Extension\Core\Type\TextType;
        use Symfony\Component\Form\Extension\Core\Type\HiddenType;
        use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
        
        class Nuevo{$nombreEntidad}Type extends AbstractType
        {
            public function buildForm(FormBuilderInterface \$builder, array \$options)
            {

                \$id = \$options['id'];
                \$builder\n$camposFormularioPrincipal
                    ->add('idRegistro', HiddenType::class, ['data' => \$id, 'mapped' => false])
                ;
            }
        
            public function configureOptions(OptionsResolver \$resolver)
            {
                \$resolver->setDefaults(['data_class' => $nombreEntidad::class, 'id' => null]);
            }
        }
        PHP;
        $this->fs->dumpFile($ruta, $plantilla);
    }

    public function crearPlantillaController($entidad, $camposSeleccionados)
    {
        /** 
         * En esta función se crea el archivo EntidadController.php en el path correspondiente
         * -----------------------------------------------------------------------------------
         * @access public
        */ 

        /** Definición de variables */
        /** ----------------------- */

        $camposFiltros = '';
        $entidadesImportadas = '';
        $nombreEntidadExplode = explode('\\', $entidad);
        $namespace = (count($nombreEntidadExplode) > 1)?"App\Controller\\".$nombreEntidadExplode[0]:'App\Controller';
        $nombreRuta = (count($nombreEntidadExplode) > 1)?"src/Controller/".$nombreEntidadExplode[0].'/':'src/Controller/';
        $nombreEntidad = (count($nombreEntidadExplode) > 1)?$nombreEntidadExplode[array_key_last($nombreEntidadExplode)]:$entidad;
        foreach(array_flip($camposSeleccionados['entidades']) as $key => $campo){$entidadesImportadas .= 'use '.$camposSeleccionados['entidades'][$campo].";\n";}
        $useFormularioPrincipal = (count($nombreEntidadExplode) > 1)?"App\Form\\".$nombreEntidadExplode[0].'\Nuevo'.$nombreEntidad.'Type':'App\Form\Nuevo'.$entidad.'Type';
        $useFiltrosBusqueda = (count($nombreEntidadExplode) > 1)?"App\Form\\".$nombreEntidadExplode[0].'\FiltrosBusqueda'.$nombreEntidad.'Type':'App\Form\FiltrosBusqueda'.$nombreEntidad.'Type';
        $nombreFiltrosBusqueda = 'FiltrosBusqueda'.$nombreEntidad.'Type';
        $nombreFormularioPrincipal = 'Nuevo'.$nombreEntidad.'Type';
        $ruta = "{$nombreRuta}{$nombreEntidad}Controller.php";
        $nombreEntidadMinuscula = strtolower($nombreEntidad);
        $this->archivosCreados[2] = ['» '.$ruta];

        $plantilla =
        <<<PHP
        <?php

        namespace $namespace;
        
        $entidadesImportadas
        use App\Entity\\$entidad;
        use $useFiltrosBusqueda;
        use $useFormularioPrincipal;
        use Doctrine\ORM\EntityManagerInterface;
        use Symfony\Component\HttpFoundation\Request;
        use Symfony\Component\HttpFoundation\Response;
        use Symfony\Component\Routing\Annotation\Route;
        use Doctrine\DBAL\Exception\ConstraintViolationException;
        use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

        class {$nombreEntidad}Controller extends AbstractController
        {
            private \$em;
            public function __construct(EntityManagerInterface \$em)
            {
                \$this->em = \$em;
            }

            public function listar{$nombreEntidad}()
            {
                /**
                 * En esta función se carga la vista que contiene los filtros de búsqueda y el listado de registros de la tabla respectiva
                 * -----------------------------------------------------------------------------------------------------------------------
                 * @access public
                */

                \$bd = \$this->em;
                \$formFiltros = \$this->createForm($nombreFiltrosBusqueda::class, null);
                return \$this->render('$nombreEntidadExplode[0]\lista{$nombreEntidad}.html.twig',
                [
                    'formFiltros' => \$formFiltros->createView()
                ]);
            }

            public function frameLista{$nombreEntidad}(Request \$request)
            {
                /**
                 * En esta función se listan todos los registros de la tabla respectiva
                 * --------------------------------------------------------------------
                 * @access public
                */

                \$bd = \$this->em;
                \$filtrosBusqueda = \$request->request->get('filtros_busqueda_$nombreEntidadMinuscula');
                \$listRegistros = \$bd->getRepository($nombreEntidad::class)->find{$nombreEntidad}(\$filtrosBusqueda);
                return \$this->render('$nombreEntidadExplode[0]\\frameLista{$nombreEntidad}.html.twig',
                [
                    'listRegistros' => \$listRegistros
                ]);
            }

            public function frameNuevo{$nombreEntidad}(Request \$request)
            {
                /** 
                 * En esta función se genera el formulario para Crear/Editar registros de la entidad correspondiente 
                 * -------------------------------------------------------------------------------------------------
                 * @access public
                */

                \$bd = \$this->em;
                \$id = (\$request->request->has('id'))?\$request->request->get('id'):0;
                \$registro = (\$id > 0)?\$bd->getRepository($nombreEntidad::class)->find(\$id):new $nombreEntidad(); 
                \$formularioPrincipal = \$this->createForm($nombreFormularioPrincipal::class, \$registro, ['id' => \$id]);
                return \$this->render('$nombreEntidadExplode[0]\\frameNuevo{$nombreEntidad}.html.twig', 
                [
                    'formularioPrincipal' => \$formularioPrincipal->createView()
                ]);
            }

            public function guardar{$nombreEntidad}(Request \$request)
            {
                /** 
                 * En esta función se guardar/edita un registro
                 * --------------------------------------------
                 * @access public
                */

                /** Definición de variables */
                /** ----------------------- */

                \$message = '';
                \$bd = \$this->em;
                \$status = 'success';
                \$form = \$request->request->get('nuevo_$nombreEntidadMinuscula');
                \$registro = !empty(\$form['idRegistro'])?\$bd->getRepository($nombreEntidad::class)->find(\$form['idRegistro']):new $nombreEntidad();
                \$formularioPrincipal = \$this->createForm($nombreFormularioPrincipal::class, \$registro, ['id' => 0]);

                /** Se guarda/edita el registro */
                /** --------------------------- */

                \$idRegistro = \$form['idRegistro'];
                \$formularioPrincipal->handleRequest(\$request);
                try
                {
                    \$idRegistro = \$registro->getId();
                    \$bd->persist(\$registro);
                    \$bd->flush();
                }
                catch(\Exception \$e)
                {
                    \$status = 'error';
                    \$message = '¡Ocurrión un error al guardar el registro. '.\$e->getMessage().'!';
                }
                return new Response(json_encode(
                [
                    'status' => \$status, 
                    'message' => \$message,
                    'idRegistro' => \$idRegistro
                ]));
            }

            public function eliminar{$nombreEntidad}(Request \$request)
            {
                /** 
                 * En esta función se efectúa la eliminación de un registro
                 * --------------------------------------------------------
                 * @access public
                */

                /** Definición de variables */
                /** ----------------------- */

                \$message = '';
                \$bd = \$this->em;
                \$status = 'success';
                \$id = \$request->request->get('id');
                \$registro = \$bd->getRepository($nombreEntidad::class)->find(\$id);

                /** Se guarda/edita el registro */
                /** --------------------------- */

                if(!empty(\$registro))
                {
                    try
                    {
                        \$bd->remove(\$registro);
                        \$bd->flush();
                    }
                    catch(\ConstraintViolationException \$e)
                    {
                        \$status = 'error';
                        \$message = '¡El registro no se puede eliminar porque se encuentra asociado!';
                    }
                }
                return new Response(json_encode(
                [
                    'status' => \$status, 
                    'message' => \$message
                ]));
            }
        }
        PHP;
        $this->fs->dumpFile($ruta, $plantilla);
    }

    public function crearPlantillaControllerStimulus($entidad, $camposSeleccionados)
    {
        /** 
         * En esta función se crea el archivo entidad_controller.js en el path correspondiente
         * -----------------------------------------------------------------------------------
         * @access public
        */ 

        /** Definición de variables */
        /** ----------------------- */
        
        $camposFiltros = '';
        $nombreEntidadExplode = explode('\\', $entidad);
        $nombreRuta = "assets/controllers/".strtolower($nombreEntidadExplode[0]).'/';
        $nombreEntidad = (count($nombreEntidadExplode) > 1)?$nombreEntidadExplode[array_key_last($nombreEntidadExplode)]:$entidad;
        $this->rutas['controladorJS'] = (count($nombreEntidadExplode) > 1)?strtolower($nombreEntidadExplode[0]).'--'.strtolower($nombreEntidadExplode[array_key_last($nombreEntidadExplode)]).'s':strtolower($nombreEntidad).'--'.strtolower($nombreEntidad).'s';
        $ruta = $nombreRuta.strtolower($nombreEntidad).'s_controller.js';
        $nombreEntidadMinuscula = strtolower($nombreEntidad);
        $this->archivosCreados[6] = ['» '.$ruta];

        /** Se generan el controller de stimulus */
        /** ------------------------------------ */

        $plantilla =
        <<<STIMULUS
        import mensajes from '../central/mensajes';
        import { Controller } from "@hotwired/stimulus";

        export default class extends Controller 
        {
            mensaje = new mensajes();

            static values = 
            {
                'urlGuardar$nombreEntidad' : String,
                'urlEliminar$nombreEntidad' : String,
                'urlFrameNuevo$nombreEntidad' : String,
                'urlActualizarLista$nombreEntidad' : String
            };

            static targets = 
            [
                'formFiltros', 'cargandoFrameNuevo$nombreEntidad', 'frameNuevo$nombreEntidad', 'formularioPrincipal', 'frameLista$nombreEntidad',
                'cargandoFiltros', 'totalRegistrosHidden'
            ];

            connect()
            {
                var self = this;
                console.log('connect');
                this.actualizarLista$nombreEntidad(null, 1);
                \$('.selectpicker').selectpicker('refresh');
                \$('#btnRegresar').on('click', function(){\$(this).html('<i class="fas fa-spinner fa-spin"></i> Regresando')});
            }

            formatearCampo(event)
            {
                /** En esta función se formatea el valor ingresado en los inputs que reciben valores numéricos */
                /** ------------------------------------------------------------------------------------------ */

                new Cleave(event.currentTarget, { numeral: true, numeralPositiveOnly: true, numeralDecimalScale: 2, numeralDecimalMark: ',', delimiter: '.' });
            }

            async showModalNuevo$nombreEntidad(event)
            {
                /** En esta función se hace visible el modal de Nuevo/Editar registro cargando la información correspondiente */
                /** --------------------------------------------------------------------------------------------------------- */
                
                let self = this;
                event.preventDefault();
                let form = new FormData();
                let id = event.currentTarget.dataset.id;
                $('#modalNuevo$nombreEntidad').modal('show');
                this.cargandoFrameNuevo{$nombreEntidad}Target.style.display = '';

                if(id == 0)
                {
                    $('#tituloModalNuevo').text('Nuevo $nombreEntidad');
                    $('#iconoModalNuevo').removeClass('fa-edit').addClass('fa-external-link-alt');
                    $('#btnNuevo$nombreEntidad').html('<i class="fas fa-spinner fa-spin"></i> Cargando');
                }
                else
                {
                    $('#tituloModalNuevo').text('Editar $nombreEntidad');
                    $('#opc'+id).html('<i class="fas fa-spinner fa-spin text-primary"></i>');
                    $('#iconoModalNuevo').removeClass('fa-external-link-alt').addClass('fa-edit');
                    form.append('id', id);
                }

                /** Se carga el formulario para crear/editar registros */
                /** -------------------------------------------------- */

                let consulta = await fetch(this.urlFrameNuevo{$nombreEntidad}Value, {'method' : 'POST', 'body' : form});
                this.frameNuevo{$nombreEntidad}Target.innerHTML = await consulta.text();
                if(id == 0)
                {
                    $('#btnNuevo$nombreEntidad').html('<i class="fas fa-external-link-alt"></i> Nuevo');
                }
                else
                {
                    $('#opc'+id).html('<i class="fas fa-cog text-primary"></i>');
                }
                $('.camposNumericos').each(function(){if($(this).val() != ''){\$(this).val(self.numberFormat($(this).val()))}});
                $('.selectpicker').selectpicker('refresh');
            }

            async guardar$nombreEntidad(event)
            {
                /** En esta función se envía el formulario para crear/editar registros */
                /** ------------------------------------------------------------------ */

                event.preventDefault();
                $('#btnGuardarFactura').html('<i class="fas fa-spinner fa-spin"></i> Guardando').prop('disabled', true);
                $('.camposNumericos').each(function(){if($(this).val() != ''){\$(this).val($(this).val().replaceAll('.','').replace(',','.'))}});

                /** Se envía el formulario para guardar/editar el registro */
                /** ------------------------------------------------------ */

                let form = new FormData(this.formularioPrincipalTarget);
                let consulta = await fetch(this.urlGuardar{$nombreEntidad}Value, {'method' : 'POST', 'body' : form});
                let result = await consulta.json();

                /** Se valida si el registro/edición fue exitoso */
                /** -------------------------------------------- */

                if(result.status == 'success')
                {
                    this.mensaje.mostrarMensaje('¡El registro se ha guardado con éxito!', 1);
                    $('#modalNuevo$nombreEntidad').modal('hide');
                    await this.actualizarLista$nombreEntidad();
                }
                else
                {
                    this.mensaje.mostrarMensaje(result.message, 2);
                }
                $('#btnGuardarFactura').html('<i class="fas fa-save"></i> Guardar').prop('disabled', false);
            }

            async actualizarLista$nombreEntidad(event = null, opc = 0)
            {
                /** En esta función se actualiza la lista de registros de acuerdo a los filtros de búsqueda seleccionados */
                /** ----------------------------------------------------------------------------------------------------- */

                let form = new FormData(this.formFiltrosTarget);
                if(opc == 0){\$('#cargandoFiltros').css('display', '');}
                let consulta = await fetch(this.urlActualizarLista{$nombreEntidad}Value, {'method' : 'POST', 'body' : form});
                this.frameLista{$nombreEntidad}Target.innerHTML = await consulta.text();
                $('#cargandoFiltros').css('display', 'none');

                /** Se actualiza el total de registros */
                /** ---------------------------------- */

                $('#totalRegistros').removeClass('animate__flipInX').addClass('animate__flipOutX');
                let intervaloRegistros = setInterval(() =>
                {
                    if(this.targets.find('totalRegistrosHidden') != undefined)
                    {
                        clearInterval(intervaloRegistros);
                        \$('#totalRegistros').css('display', (parseFloat(\$('#totalRegistrosHidden').val()) == 0)?'none':'').text(`Total registros: \${\$('#totalRegistrosHidden').val()}`).removeClass('animate__flipOutX').addClass('animate__flipInX');
                    }
                }, 1000);
            }

            async eliminar$nombreEntidad(event)
            {
                /** En esta función se hace la eliminación de un registro */
                /** ----------------------------------------------------- */

                event.preventDefault();
                let form = new FormData();
                let id = event.currentTarget.dataset.id;
                form.append('id', event.currentTarget.dataset.id);
                $('#opc'+id).html('<i class="fas fa-spinner fa-spin text-danger"></i>');

                /** Se realiza la eliminación del registro */
                /** -------------------------------------- */

                let consulta = await fetch(this.urlEliminar{$nombreEntidad}Value, {'method' : 'POST', 'body' : form});
                let result = await consulta.json();

                /** Se valida si la eliminación fue exitosa */
                /** --------------------------------------- */

                if(result.status == 'success')
                {
                    this.mensaje.mostrarMensaje('¡El registro se ha eliminado con éxito!', 1);
                    await this.actualizarLista$nombreEntidad();
                }
                else
                {
                    this.mensaje.mostrarMensaje(result.message, 2);
                    $('#opc'+id).html('<i class="fas fa-cog text-primary"></i>');
                }
            }

            showOpciones()
            {
                /** En esta función se ajusta el top de las opciones Editar/Eliminar de cada registro */
                /** --------------------------------------------------------------------------------- */

                $('.dropdown-menu').each(function()
                {
                    setTimeout(() => 
                    {
                        if(\$(this).hasClass('show')){\$('.dropdown-menu.show').css('top', '4px')}
                    });
                });
            }

            numberFormat(valor) 
            {
                /** En esta función se formatea un valor numérico */
                /** --------------------------------------------- */

                valor = parseFloat(valor).toFixed(2).toString().replace('.', ',');
                while(true) 
                {
                    let valorFormato = valor.replace(/(\d)(\d{3})($|,|\.)/g, '$1.$2$3');
                    if(valor == valorFormato){break;}
                    valor = valorFormato;
                }
                return valor;
            }
        }
        STIMULUS;
        $this->fs->dumpFile($ruta, $plantilla);
    }

    public function crearPlantillaListaTwig($entidad, $camposSeleccionados)
    {
        /** 
         * En esta función se crea el archivo listaEntidad.html.twig en el path correspondiente
         * ------------------------------------------------------------------------------------
         * @access public
        */ 

        /** Definición de variables */
        /** ----------------------- */
        
        $row = '';
        $index = 1;
        $btnNuevo = '';
        $listCampos = [];
        $indexCampos = 1;
        $camposFiltros = '';
        $dimensionColumna = 0;
        $formularioFiltros = '';
        $dimensionesLabel = [0,0,0];
        $rutaGuardar = $this->rutas['guardar'];
        $rutaEliminar = $this->rutas['eliminar'];
        $nombreEntidad = explode('\\', $entidad);
        $rutaFrameLista = $this->rutas['frameLista'];
        $rutaFrameNuevo = $this->rutas['frameNuevo'];
        $nombreControlador = $this->rutas['controladorJS'];
        $nombreRuta = (count($nombreEntidad) > 1)?"src/Views/".$nombreEntidad[0].'/':'src/Views/';
        $nombreEntidad = (count($nombreEntidad) > 1)?$nombreEntidad[array_key_last($nombreEntidad)]:$entidad;
        $ruta = "{$nombreRuta}lista{$nombreEntidad}.html.twig";
        $this->archivosCreados[1] = ['» '.$ruta];

        /** Se ordena el posicionamiento de los campos */
        /** ------------------------------------------ */

        foreach($camposSeleccionados['filtros'] as $key => $campo)
        {
            $dataCampos[$key] = ['tipo' => $campo];
            if($indexCampos == 3 || $index == count($camposSeleccionados['filtros']))
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

        foreach($listCampos as $index => $campos)
        {   
            $indexCampos = 0;
            $row = ($index > 0)?'mt-2':'';
            foreach($campos as $key => $campo)
            {
                if(($index == count($listCampos) - 1) && ($indexCampos == count($campos) - 1))
                {
                    $btnNuevo = '<button class="btn btn-primary" type="button" style="margin-left:10px" data-id="0" data-action="'.$nombreControlador.'#showModalNuevo'.$nombreEntidad.'" id="btnNuevo'.$nombreEntidad.'"><i class="fas fa-external-link-alt"></i> Nuevo</button>';
                }
                $col = $campo['col'];
                $padding = $campo['padding'];
                $label = 27 + ($campo['label'] * 7);
                $dataAction = in_array($campo['tipo'], ['date', 'datetime', 'string', 'text', 'integer', 'float'])?'blur->'.$nombreControlador.'#actualizarLista'.$nombreEntidad:$nombreControlador.'#actualizarLista'.$nombreEntidad;
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
                                                                {{ form_widget(formFiltros.$key, {'attr' : {'class' : 'form-control', 'data-action' : '$dataAction'}})}}
                                                                $btnNuevo
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
                                                                {{ form_widget(formFiltros.$key, {'attr' : {'class' : 'form-control', 'data-action' : '$dataAction'}})}}
                                                                $btnNuevo
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
                                                                        {{ form_widget(formFiltros.$key, {'attr' : {'class' : 'custom-control-input', 'data-action' : '$dataAction'}}) }}
                                                                        <label class="custom-control-label" for="filtros_busqueda_$key"></label>
                                                                    </div>
                                                                </div>
                                                                $btnNuevo
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
                                                                        {{ form_widget(formFiltros.$key, {'attr' : {'class' : 'custom-control-input', 'data-action' : '$dataAction'}}) }}
                                                                        <label class="custom-control-label" for="filtros_busqueda_$key"></label>
                                                                    </div>
                                                                </div>
                                                                $btnNuevo
                                                            </div>
                                                        </div>\n
                            TWIG;
                        }
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
                                                            <div class="input-group-prepend">
                                                                {{ form_label(formFiltros.$key,formFiltros.$key, {'label_attr' : {'class' : 'input-group-text font-weight-bold', 'style' : 'width:{$label}px'}}) }}
                                                            </div>
                                                            {{ form_widget(formFiltros.$key, {'attr' : 
                                                                {
                                                                    'data-size':'10',
                                                                    'data-width':'50%',
                                                                    'data-action' : '$dataAction',
                                                                    'class' : 'form-control selectpicker'
                                                                }
                                                            })}}
                                                            $btnNuevo
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
                                                                    'data-size':'10',
                                                                    'data-width':'50%',
                                                                    'data-action' : '$dataAction',
                                                                    'class' : 'form-control selectpicker'
                                                                }
                                                            })}}
                                                            $btnNuevo
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
                font-size:13px;
            }

            .listado::-webkit-scrollbar {
                width: 5px;
            }

            .listado::-webkit-scrollbar-thumb {
                background-color: #dee2e6;
                border-radius: 20px;
            }

            body {
                overflow-x: hidden;
            }

            .text {
                font-size:13px;
            }

            .filter-option-inner {
                font-size:13px;
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

            .btn dropdown-toggle.bs-placeholder.btn-light {
                border: 1px solid #d0d4da;
            }

            .btn.dropdown-toggle.btn-light {
                border: 1px solid #d0d4da;
            }
        </style>
        <div class="container-fluid" data-turbo="true" 
            {{ stimulus_controller('$nombreControlador', 
                {
                    'urlGuardar$nombreEntidad' : path('$rutaGuardar'),
                    'urlEliminar$nombreEntidad' : path('$rutaEliminar'),
                    'urlFrameNuevo$nombreEntidad' : path('$rutaFrameNuevo'),
                    'urlActualizarLista$nombreEntidad' : path('$rutaFrameLista')
                }
            )}}
        >
            <div class="col-12 animate__animated animate__fadeIn" style="display:flex; align-items:center; justify-content:center">
                <div class="card shadow-lg mb-5 bg-white rounded" style="width:85%">
                    <div class="card-body">
                        <div class="list-group-item active font-weight-bold" style="font-size:13px">Listado de {{ '{$nombreEntidad}'|lower() }}<span class="animate__animated animate__flipInX" style="float:right; display:none; font-size:13px" id="totalRegistros">Total registros: 0</span></div>
                        <div class="list-group-item">
                            {{ form_start(formFiltros, {'attr' : {'class' : 'mb-0', 'data-$nombreControlador-target' : 'formFiltros'}}) }}
                                $formularioFiltros
                            {{ form_end(formFiltros) }}
                        </div>
                        <div class="list-group-item" style="overflow:hidden">
                            <div style="display:none" data-$nombreControlador-target="cargandoFiltros" id="cargandoFiltros">
                                <div class="animate__animated animate__fade" style="position:absolute; width:100%; height:100%; background:white; z-index:2; margin-left:-18px; margin-top:-10px; opacity:0.6"></div>
                                <div class="animate__animated animate__flipInX" style="position:absolute; z-index:3; width:100%; height:100%; margin-left:-18px; margin-top:-10px; display:flex; align-items:center; justify-content:center">
                                    <div style="background:white; width: 470px; display: flex; align-items: center; justify-content: center; padding: 8px; border-radius: 15px; border:1px solid #d1d4da;">
                                        <div style="position:relative;">
                                            <div class="loader"></div>
                                            <img src="{{ asset('Imgs/logo.png') }}" class="logo">
                                        </div>
                                        <b style="font-size:13px">Cargando información...</b>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item p-0" style="border:none" data-$nombreControlador-target="frameLista$nombreEntidad">
                                <div class="animate__animated animate__flipInX" style="display:flex; align-items:center; justify-content:center; height:60px">
                                    <div style="position:relative;">
                                        <div class="loader"></div>
                                        <img src="{{ asset('Imgs/logo.png') }}" class="logo">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <a href="#" target="_top" class="btn btn-danger" id="btnRegresar"><i class="fas fa-caret-left"></i> Regresar</a>
                        </div>
                    </div>
                </div>
            </div>

            {# Modal para crear nuevos registros #}
            {# --------------------------------- #}

            <div id="modalNuevo$nombreEntidad" class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header btn-primary font-weight-bold" style="display:flex; align-items:center">
                            <div style="display:flex; align-items:center; gap:5px;">
                                <i id="iconoModalNuevo" class="fas" style="font-size:12px; margin-top:1px"></i>
                                <span id="tituloModalNuevo"></span>
                            </div>
                            <button type="button" class="close" onclick="$('#modalNuevo$nombreEntidad').modal('hide')" style="display:flex; align-items:center"><i class="fas fa-times" style="color:white; font-size:12px"></i></button>
                        </div>
                        <div class="modal-body">
                            <div class="list-group-item pr-0 pl-0" style="border:none">
                                <turbo-frame id="frameNuevo$nombreEntidad" src="{{ path('$rutaFrameNuevo') }}" data-$nombreControlador-target="frameNuevo$nombreEntidad">
                                    <div style="height:40px">
                                        <div data-$nombreControlador-target="cargandoFrameNuevo$nombreEntidad">
                                            <div style="position:absolute; width:100%; height:100%; background:white; z-index:2; opacity:0.6"></div>
                                            <div class="animate__animated animate__fadeIn" style="position:absolute; z-index:3; width:100%; height:100%; display:flex; align-items:center; justify-content:center">
                                                <div style="background:white; width: 470px; display: flex; align-items: center; justify-content: center; padding: 8px; border-radius: 15px; border:1px solid #d1d4da;">
                                                    <div style="position:relative;">
                                                        <div class="loader"></div>
                                                        <img src="{{ asset('Imgs/logo.png') }}" class="logo">
                                                    </div>
                                                    <b style="font-size:13px">Cargando información...</b>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="container-fluid" style="margin-top:20px;">
                                        <hr>
                                        <button type="button" class="btn btn-success" disabled><i class="fas fa-save"></i> Guardar</button>
                                        <button type="button" class="btn btn-danger" disabled><i class="fas fa-ban"></i> Cancelar</button>
                                    </div>
                                </turbo-frame>
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

    public function crearPlantillaFrameListaTwig($entidad, $camposSeleccionados)
    {
        /** 
         * En esta función se crea el archivo frameListaEntidad.html.twig en el path correspondiente
         * -----------------------------------------------------------------------------------------
         * @access public
        */ 

        /** Definición de variables */
        /** ----------------------- */

        $index = 0;
        $valorCampo = '';
        $camposTabla = '';
        $titulosTabla = '';
        $campos = $this->campos['formulario'];
        $nombreEntidadExplode = explode('\\', $entidad);
        $nombreControlador = $this->rutas['controladorJS'];
        $nombreRuta = (count($nombreEntidadExplode) > 1)?"src/Views/".$nombreEntidadExplode[0].'/':'src/Views/';
        $nombreEntidad = (count($nombreEntidadExplode) > 1)?$nombreEntidadExplode[array_key_last($nombreEntidadExplode)]:$entidad;
        $ruta = "{$nombreRuta}frameLista{$nombreEntidad}.html.twig";
        $this->archivosCreados[4] = ['» '.$ruta];

        /** Se generan los titulos y los campos que se visualizarán en la tabla */
        /** ------------------------------------------------------------------- */

        foreach($campos as $key => $campo)
        {
            if($campo != 'boolean')
            {
                $titulo = ucfirst($key);
                if($campo == 'relation'){$valorCampo = '{{ (registro.'.$key.'.nombre is defined) ? registro.'.$key.'.nombre|default("-----") : registro.id }}';}
                if($campo == 'float'){$valorCampo = '{{ registro.'.$key.'|number_format(2, ",", ".") }}';}
                if(in_array($campo, ['integer', 'text', 'string'])){$valorCampo = '{{ registro.'.$key.' }}';}
                if(in_array($campo, ['date', 'datetime'])){$valorCampo = '{{ registro.'.$key.'|date("Y-m-d") }}';}
                if($index == 0)
                {
                    $titulosTabla .= 
                    <<<TWIG
                    <th style="text-align:center; font-size:13px">$titulo</th>\n
                    TWIG;

                    $camposTabla .=
                    <<<TWIG
                    <td style="padding-left:7px; font-size:13px">$valorCampo</td>\n
                    TWIG;
                }
                else
                {
                    $titulosTabla .= 
                    <<<TWIG
                                            <th style="text-align:center; font-size:13px">$titulo</th>\n
                    TWIG;

                    $camposTabla .=
                    <<<TWIG
                                                <td style="padding-left:7px; font-size:13px">$valorCampo</td>\n
                    TWIG;
                }
                $index ++;
            }
        }

        $plantilla =
        <<<TWIG
        <div class="row animate__animated animate__fadeIn">
            <div class="col-12">
                {% if listRegistros|length > 0 %}
                    <div class="listado" style="padding:1px; max-height:514px; overflow-y:auto; overflow-x:hidden">
                        <table class="table table-sm table-bordered table-hover animate__animated animate__fadeIn" style="margin-bottom:10px">
                            <tr class="bg-light text-primary">
                                <td width="40px"></td>
                                $titulosTabla
                            </tr>
                            {% for registro in listRegistros %}
                                <tr>
                                    <td style="text-align:center">
                                        <div class="dropdown dropleft" id="opciones{{ registro.id }}">
                                            <a role="button" id="opc{{ registro.id }}" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="cursor:pointer" data-action="$nombreControlador#showOpciones">
                                                <i class="fas fa-cog text-primary"></i>
                                            </a>
                                            <div class="dropdown-menu animate__animated animate__fadeIn" aria-labelledby="opc{{ registro.id }}">
                                                <a 
                                                    href="#"
                                                    data-id="{{ registro.id }}"
                                                    class="dropdown-item font-weight-bold text-success" 
                                                    data-action="$nombreControlador#showModalNuevo$nombreEntidad"
                                                >
                                                    <i class="fas fa-edit"></i>  Editar
                                                </a>
                                                <a 
                                                    href="#" 
                                                    data-id="{{ registro.id }}"  
                                                    class="dropdown-item font-weight-bold text-danger" 
                                                    data-action="$nombreControlador#eliminar$nombreEntidad"
                                                >
                                                    <i class="fas fa-times-circle"></i>  Eliminar
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    $camposTabla
                                </tr>
                            {% endfor %}
                        </table>
                    </div>
                {% else %}
                    <div 
                        class="text-danger text-center font-weight-bold animate__animated animate__fadeIn" 
                        style="opacity:0.8; height:60px; display:flex; align-items:center; justify-content:center; font-size:13px"
                    >
                        ¡No se encontraron registros para listar!
                    </div>
                {% endif %}
                <input type="hidden" id="totalRegistrosHidden" data-$nombreControlador-target="totalRegistrosHidden" value="{{ listRegistros|length }}">
            </div>
        </div>
        TWIG;
        $this->fs->dumpFile($ruta, $plantilla);
    }

    public function crearPlantillaFrameNuevoTwig($entidad, $camposSeleccionados)
    {
        /** 
         * En esta función se crea el archivo frameNuevoEntidad.html.twig en el path correspondiente
         * -----------------------------------------------------------------------------------------
         * @access public
        */ 

        /** Definición de variables */
        /** ----------------------- */
        
        $row = '';
        $index = 1;
        $listCampos = [];
        $indexCampos = 1;
        $camposFiltros = '';
        $dimensionColumna = 0;
        $formularioPrincipal = '';
        $dimensionesLabel = [0,0,0];
        $nombreEntidad = explode('\\', $entidad);
        $rutaFrameNuevo = $this->rutas['frameNuevo'];
        $nombreControlador = $this->rutas['controladorJS'];
        $nombreRuta = (count($nombreEntidad) > 1)?"src/Views/".$nombreEntidad[0].'/':'src/Views/';
        $nombreEntidad = (count($nombreEntidad) > 1)?$nombreEntidad[array_key_last($nombreEntidad)]:$entidad;
        $ruta = "{$nombreRuta}frameNuevo{$nombreEntidad}.html.twig";
        $this->archivosCreados[3] = ['» '.$ruta];

        /** Se ordena el posicionamiento de los campos */
        /** ------------------------------------------ */

        foreach($camposSeleccionados['formulario'] as $key => $campo)
        {
            $dataCampos[$key] = ['tipo' => $campo];
            if($indexCampos == 3 || $index == count($camposSeleccionados['formulario']))
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

        foreach($listCampos as $index => $campos)
        {   
            $indexCampos = 0;
            $row = ($index > 0)?'mt-2':'';
            foreach($campos as $key => $campo)
            {
                $col = $campo['col'];
                $padding = $campo['padding'];
                $label = 27 + ($campo['label'] * 7);
                $dataAction = in_array($campo['tipo'], ['float', 'integer'])?$nombreControlador.'#formatearCampo':'';
                if($campo['tipo'] != 'relation')
                {
                    $claseNumero = in_array($campo['tipo'], ['float', 'integer'])?'camposNumericos':'';
                    $onKeypress = ($campo['tipo'] == 'integer')?",'onkeypress' : 'return event.charCode >= 48 && event.charCode <= 57'":'';
                    if($campo['tipo'] != 'boolean')
                    {
                        if($indexCampos == 0)
                        {
                            $camposFiltros .= 
                            <<<TWIG
                                    <div class="col-$col $padding">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    {{ form_label(formularioPrincipal.$key,formularioPrincipal.$key, {'label_attr' : {'class' : 'input-group-text font-weight-bold', 'style' : 'width:{$label}px'}}) }}
                                                </div>
                                                {{ form_widget(formularioPrincipal.$key, {'attr' : {'class' : 'form-control $claseNumero', 'data-action' : '$dataAction' $onKeypress}})}}
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
                                                    {{ form_label(formularioPrincipal.$key,formularioPrincipal.$key, {'label_attr' : {'class' : 'input-group-text font-weight-bold', 'style' : 'width:{$label}px'}}) }}
                                                </div>
                                                {{ form_widget(formularioPrincipal.$key, {'attr' : {'class' : 'form-control $claseNumero', 'data-action' : '$dataAction' $onKeypress}})}}
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
                                                {{ form_label(formularioPrincipal.$key,formularioPrincipal.$key, {'label_attr' : {'class' : 'form-control input-group-text font-weight-bold' }} ) }}
                                                <div class="input-group-text d-flex justify-content-center" style="background: white; width:auto">
                                                    <div class="custom-control custom-switch">
                                                        {{ form_widget(formularioPrincipal.$key, {'attr' : {'class' : 'custom-control-input', 'data-action' : '$dataAction'}}) }}
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
                                                {{ form_label(formularioPrincipal.$key,formularioPrincipal.$key, {'label_attr' : {'class' : 'form-control input-group-text font-weight-bold' }} ) }}
                                                <div class="input-group-text d-flex justify-content-center" style="background: white; width:auto">
                                                    <div class="custom-control custom-switch">
                                                        {{ form_widget(formularioPrincipal.$key, {'attr' : {'class' : 'custom-control-input', 'data-action' : '$dataAction'}}) }}
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
                    if($indexCampos == 0)
                    {
                        $camposFiltros .= 
                        <<<TWIG
                                <div class="col-$col $padding">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                {{ form_label(formularioPrincipal.$key,formularioPrincipal.$key, {'label_attr' : {'class' : 'input-group-text font-weight-bold', 'style' : 'width:{$label}px'}}) }}
                                            </div>
                                            {{ form_widget(formularioPrincipal.$key, {'attr' : 
                                                {
                                                    'data-size':'10',
                                                    'data-width':'50%',
                                                    'data-action' : '$dataAction',
                                                    'class' : 'form-control selectpicker'
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
                                                {{ form_label(formularioPrincipal.$key,formularioPrincipal.$key, {'label_attr' : {'class' : 'input-group-text font-weight-bold', 'style' : 'width:{$label}px'}}) }}
                                            </div>
                                            {{ form_widget(formularioPrincipal.$key, {'attr' : 
                                                {
                                                    'data-size':'10',
                                                    'data-width':'50%',
                                                    'data-action' : '$dataAction',
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
                $formularioPrincipal .= 
                <<<TWIG
                <div class="row $row">
                    $camposFiltros
                        </div>\n
                TWIG;
            }
            else
            {
                $formularioPrincipal .= 
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
        <turbo-frame id="frameNuevo$nombreEntidad" class="animate__animated animate__fadeIn">
            <div style="display:none" data-$nombreControlador-target="cargandoFrameNuevo$nombreEntidad">
                <div style="position:absolute; width:100%; height:100%; background:white; z-index:2; opacity:0.6"></div>
                <div class="animate__animated animate__flipInX" style="position:absolute; z-index:3; width:100%; height:100%; display:flex; align-items:center; justify-content:center">
                    <div style="background:white; width: 470px; display: flex; align-items: center; justify-content: center; padding: 8px; border-radius: 15px; border:1px solid #d1d4da;">
                        <div style="position:relative;">
                            <div class="loader"></div>
                            <img src="{{ asset('Imgs/logo.png') }}" class="logo">
                        </div>
                        <b style="font-size:13px">Cargando información...</b>
                    </div>
                </div>
            </div>
            {{ form_start(formularioPrincipal, {'attr' : {'class' : 'mb-0', 'data-$nombreControlador-target' : 'formularioPrincipal', 'data-action' : '$nombreControlador#guardar$nombreEntidad'}}) }}
                <div class="container-fluid">
                        $formularioPrincipal
                </div>
                <div class="container-fluid" style="margin-top:20px;">
                    <hr>
                    <button type="submit" class="btn btn-success" id="btnGuardar$nombreEntidad"><i class="fas fa-save"></i> Guardar</button>
                    <button type="button" class="btn btn-danger" onclick="$('#modalNuevo$nombreEntidad').modal('hide')"><i class="fas fa-ban"></i> Cancelar</button>
                </div>
            {{ form_end(formularioPrincipal) }}
        </turbo-frame>
        TWIG;
        $this->fs->dumpFile($ruta, $plantilla);
    }

    public function crearFuncionFiltrosBusqueda($entidad, $rutaRepositorio)
    {
        /**  
         * En esta función, se agrega al final del archivo Repository.php correspondiente, el método que permitirá 
         * efectuar la búsqueda de registros de acuerdo a los parámetros seleccionados en el formulario de filtros
         * -------------------------------------------------------------------------------------------------------
         * @access public
        */

        /** Definición de variables */
        /** ----------------------- */

        $index = 0;
        $variable = '';
        $variables = [];
        $lineasEliminar = [];
        $nombreVariables = [];
        $contenidoFuncion = '';
        $variablesCondicion = [];
        $espaciosFuncion = "\n\n";
        $campos = $this->campos['filtros'];
        $nombreEntidadExplode = explode('\\', $entidad);
        $repositorio = explode("\n", trim(file_get_contents($rutaRepositorio)));
        $nombreEntidad = (count($nombreEntidadExplode) > 1)?$nombreEntidadExplode[array_key_last($nombreEntidadExplode)]:$entidad;

        if(strpos(file_get_contents($rutaRepositorio), "function find$nombreEntidad") !== false)
        {
            $espaciosFuncion = "\n";
            foreach($repositorio as $index => $linea)
            {
                if(strpos($linea, "function find$nombreEntidad") !== false)
                {
                    $lineasEliminar[] = $index;
                }
                else
                {
                    if(count($lineasEliminar) > 0)
                    {
                        $lineasEliminar[] = $index;
                        if($index < (count($repositorio) - 1))
                        {
                            if(strpos($repositorio[$index + 1], 'public function') !== false || ($index + 1) == (count($repositorio) - 1))
                            {
                                break;
                            }
                        }
                    }
                }
            }
            $repositorio = array_filter($repositorio, fn($key) => !in_array($key, $lineasEliminar), ARRAY_FILTER_USE_KEY);
        }

        /** Se agregan los campos de los filtros de búsqueda */
        /** ------------------------------------------------ */

        $index = 0;
        foreach($campos as $key => $campo)
        {
            $variable = ucfirst($key);
            $variablesCondicion[] = "\$and$variable";
            if($index == 0)
            {
                $nombreVariables[] = "\$$key = !empty(\$campos["."'".$key."']".")?\$campos['".$key."']:null;"; 
            }
            else
            {
                $nombreVariables[] = "\t\t\$$key = !empty(\$campos["."'".$key."']".")?\$campos['".$key."']:null;"; 
            }
            
            if($campo != 'boolean')
            {
                if($index == 0)
                {
                    if(in_array($campo, ['date', 'datetime', 'string', 'text']))
                    {
                        $variables[] = <<<PHP
                        \$and$variable = !is_null(\$$key)?"and r.$key = '\$$key'":'';
                        PHP;
                    }
                    else
                    {
                        $variables[] = 
                        <<<PHP
                        \$and$variable = !is_null(\$$key)?"and r.$key = \$$key":'';
                        PHP;
                    }
                }
                else
                {
                    if(in_array($campo, ['date', 'datetime', 'string', 'text']))
                {
                    $variables[] = 
                    <<<PHP
                    \t\t\$and$variable = !is_null(\$$key)?"and r.$key = '\$$key'":'';
                    PHP;
                }
                else
                {
                    $variables[] = 
                    <<<PHP
                    \t\t\$and$variable = !is_null(\$$key)?"and r.$key = \$$key":'';
                    PHP;
                }
                }
            }
            else
            {
                if($index == 0)
                {
                    $variables[] = 
                    <<<PHP
                    \$and$variable = !is_null(\$$key)?'and r.$key = true':'and (r.$key = false or r.$key is null)';
                    PHP;
                }
                else
                {
                    $variables[] = 
                    <<<PHP
                    \t\t\$and$variable = !is_null(\$$key)?'and r.$key = true':'and (r.$key = false or r.$key is null)';
                    PHP;
                }
            }
            $index ++;
        }
        $variables = implode("\n", $variables);
        $nombreVariables = implode("\n", $nombreVariables);
        $variablesCondicion = implode(' ', $variablesCondicion);
        $funcion = 
        <<<PHP
            public function find{$nombreEntidad}(\$campos)
            {
                /**
                 * En esta función se realiza la búsqueda de registros de acuerdo a los filtros seleccionados
                 * ------------------------------------------------------------------------------------------
                 * @access public
                */
                
                /** Se obtienen los campos del formulario de filtros */
                /** ------------------------------------------------ */

                $nombreVariables
                $variables

                /** Se realiza la búsqueda de registros */
                /** ----------------------------------- */

                return \$this->createQueryBuilder('r')
                    ->where("r.id > 0 $variablesCondicion")
                    ->getQuery()->getResult()
                ;
            }
        PHP;
        unset($repositorio[array_key_last($repositorio)]);
        file_put_contents($rutaRepositorio, implode("\n", $repositorio)."$espaciosFuncion$funcion\n}");
    }
}
