<?php

namespace App\Repository\Central;

use App\Entity\Central\reportes;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<reportes>
 *
 * @method reportes|null find($id, $lockMode = null, $lockVersion = null)
 * @method reportes|null findOneBy(array $criteria, array $orderBy = null)
 * @method reportes[]    findAll()
 * @method reportes[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class reportesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, reportes::class);
    }

    public function add(reportes $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(reportes $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findInformes($modulo, $tipo)
    {
        /** 
         * En esta función se obtiene una lista de informes de acuerdo a los parámetros de búsqueda seleccionados
         * ------------------------------------------------------------------------------------------------------
         * @access public
        */

        $andTipo = !empty($tipo)?"and i.tipo = $tipo":'';
        return $this->createQueryBuilder('i')
            ->where("i.modulo = '$modulo' $andTipo")
            ->andWhere('i.estado = 1')
            ->orderBy('i.id', 'DESC')
            ->getQuery()->getResult()
        ;
    }

    public function findModulos()
    {
        /** 
         * En esta función se obtienen todos los módulos registrados en la tabla de central.reportes
         * -----------------------------------------------------------------------------------------
         * @access public
        */

        $listModulos = [];
        $modulos = $this->createQueryBuilder('i')
            ->select('i.modulo')
            ->groupBy('i.modulo')
            ->getQuery()->getResult()
        ;
        foreach($modulos as $modulo)
        {
            $listModulos[$modulo['modulo']] = $modulo['modulo'];
        }
        ksort($listModulos);
        return $listModulos;
    }
}
