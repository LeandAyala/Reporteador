<?php

namespace App\Entity\Facturas;

use App\Entity\Productos\Producto;
use App\Entity\Usuarios\Usuario;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Facturas\FacturaRepository;

/**
 * @ORM\Table(name="facturas.factura")
 * @ORM\Entity(repositoryClass=FacturaRepository::class)
 */
class Factura
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $numero;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $fecha;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $valor;

    /**
     * @ORM\ManyToOne(targetEntity=Usuario::class, inversedBy="facturas")
     */
    private $usuario;

    /**
     * @ORM\ManyToOne(targetEntity=Usuario::class, inversedBy="facturasUC")
     */
    private $usuCrea;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $fechaCrea;

    /**
     * @ORM\ManyToOne(targetEntity=Producto::class, inversedBy="facturas")
     */
    private $producto;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $permiteActivar;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?int
    {
        return $this->numero;
    }

    public function setNumero(?int $numero): self
    {
        $this->numero = $numero;

        return $this;
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

    public function getValor(): ?float
    {
        return $this->valor;
    }

    public function setValor(?float $valor): self
    {
        $this->valor = $valor;

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

    public function getUsuCrea(): ?Usuario
    {
        return $this->usuCrea;
    }

    public function setUsuCrea(?Usuario $usuCrea): self
    {
        $this->usuCrea = $usuCrea;

        return $this;
    }

    public function getFechaCrea(): ?\DateTimeInterface
    {
        return $this->fechaCrea;
    }

    public function setFechaCrea(?\DateTimeInterface $fechaCrea): self
    {
        $this->fechaCrea = $fechaCrea;

        return $this;
    }

    public function getProducto(): ?Producto
    {
        return $this->producto;
    }

    public function setProducto(?Producto $producto): self
    {
        $this->producto = $producto;

        return $this;
    }

    public function isPermiteActivar(): ?bool
    {
        return $this->permiteActivar;
    }

    public function setPermiteActivar(?bool $permiteActivar): self
    {
        $this->permiteActivar = $permiteActivar;

        return $this;
    }
}
