<?php

namespace App\Repository\Facturas;

use App\Entity\Facturas\Factura;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Factura>
 *
 * @method Factura|null find($id, $lockMode = null, $lockVersion = null)
 * @method Factura|null findOneBy(array $criteria, array $orderBy = null)
 * @method Factura[]    findAll()
 * @method Factura[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FacturaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Factura::class);
    }

    public function add(Factura $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
    
    public function remove(Factura $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return Factura[] Returns an array of Factura objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('f')
//            ->andWhere('f.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('f.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Factura
//    {
//        return $this->createQueryBuilder('f')
//            ->andWhere('f.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    public function findFactura($campos)
    {
        /**
         * En esta función se realiza la búsqueda de registros de acuerdo a los filtros seleccionados
         * ------------------------------------------------------------------------------------------
         * @access public
        */
        
        /** Se obtienen los campos del formulario de filtros */
        /** ------------------------------------------------ */

        $fecha = !empty($campos['fecha'])?$campos['fecha']:null;
		$numero = !empty($campos['numero'])?$campos['numero']:null;
		$permiteActivar = !empty($campos['permiteActivar'])?$campos['permiteActivar']:null;
		$producto = !empty($campos['producto'])?$campos['producto']:null;
		$usuario = !empty($campos['usuario'])?$campos['usuario']:null;
		$valor = !empty($campos['valor'])?$campos['valor']:null;
        $andFecha = !is_null($fecha)?"and r.fecha = '$fecha'":'';
		$andNumero = !is_null($numero)?"and r.numero = $numero":'';
		$andPermiteActivar = !is_null($permiteActivar)?'and r.permiteActivar = true':'and (r.permiteActivar = false or r.permiteActivar is null)';
		$andProducto = !is_null($producto)?"and r.producto = $producto":'';
		$andUsuario = !is_null($usuario)?"and r.usuario = $usuario":'';
		$andValor = !is_null($valor)?"and r.valor = $valor":'';

        /** Se realiza la búsqueda de registros */
        /** ----------------------------------- */

        return $this->createQueryBuilder('r')
            ->where("r.id > 0 $andFecha $andNumero $andPermiteActivar $andProducto $andUsuario $andValor")
            ->getQuery()->getResult()
        ;
    }
}