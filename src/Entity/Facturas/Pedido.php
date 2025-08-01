<?php

namespace App\Entity\Facturas;

use App\Entity\Usuarios\Usuario;
use App\Repository\Facturas\PedidoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="facturas.pedidos")
 * @ORM\Entity(repositoryClass=PedidoRepository::class)
 */
class Pedido
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $fecha;

    /**
     * @ORM\ManyToOne(targetEntity=Usuario::class, inversedBy="pedidos")
     */
    private $usuario;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $cantidadProductos;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $valor;

    /**
     * @ORM\ManyToOne(targetEntity=Cotizacion::class, inversedBy="pedidos")
     */
    private $cotizacion;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFecha(): ?\DateTimeInterface
    {
        return $this->fecha;
    }

    public function setFecha(?\DateTimeInterface $fecha): self
    {
        $this->fecha = $fecha;

        return $this;
    }

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function setUsuario(?Usuario $usuario): self
    {
        $this->usuario = $usuario;

        return $this;
    }

    public function getCantidadProductos(): ?float
    {
        return $this->cantidadProductos;
    }

    public function setCantidadProductos(?float $cantidadProductos): self
    {
        $this->cantidadProductos = $cantidadProductos;

        return $this;
    }

    public function getValor(): ?float
    {
        return $this->valor;
    }

    public function setValor(?float $valor): self
    {
        $this->valor = $valor;

        return $this;
    }

    public function getCotizacion(): ?Cotizacion
    {
        return $this->cotizacion;
    }

    public function setCotizacion(?Cotizacion $cotizacion): self
    {
        $this->cotizacion = $cotizacion;

        return $this;
    }
}
