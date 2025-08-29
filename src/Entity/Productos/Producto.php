<?php

namespace App\Entity\Productos;

use App\Entity\Almacen\Almacen;
use App\Entity\Facturas\Factura;
use App\Entity\GrupoContable\GrupoContable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Productos\ProductoRepository;

/**
 * @ORM\Table(name="productos.producto")
 * @ORM\Entity(repositoryClass=ProductoRepository::class)
 */
class Producto
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $nombre;

    /**
     * @ORM\Column(type="integer")
     */
    private $estado;

    /**
     * @ORM\OneToMany(targetEntity=Factura::class, mappedBy="producto")
     */
    private $facturas;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $precio;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $codigo;

    /**
     * @ORM\ManyToOne(targetEntity=Almacen::class, inversedBy="productos")
     */
    private $almacen;

    /**
     * @ORM\ManyToOne(targetEntity=GrupoContable::class, inversedBy="productos")
     */
    private $grupoContable;

    public function __construct()
    {
        $this->facturas = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): self
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getEstado(): ?int
    {
        return $this->estado;
    }

    public function setEstado(int $estado): self
    {
        $this->estado = $estado;

        return $this;
    }

    /**
     * @return Collection<int, Factura>
     */
    public function getFacturas(): Collection
    {
        return $this->facturas;
    }

    public function addFactura(Factura $factura): self
    {
        if (!$this->facturas->contains($factura)) {
            $this->facturas[] = $factura;
            $factura->setProducto($this);
        }

        return $this;
    }

    public function removeFactura(Factura $factura): self
    {
        if ($this->facturas->removeElement($factura)) {
            // set the owning side to null (unless already changed)
            if ($factura->getProducto() === $this) {
                $factura->setProducto(null);
            }
        }

        return $this;
    }

    public function getPrecio(): ?float
    {
        return $this->precio;
    }

    public function setPrecio(?float $precio): self
    {
        $this->precio = $precio;

        return $this;
    }

    public function getCodigo(): ?string
    {
        return $this->codigo;
    }

    public function setCodigo(?string $codigo): self
    {
        $this->codigo = $codigo;

        return $this;
    }

    public function getAlmacen(): ?Almacen
    {
        return $this->almacen;
    }

    public function setAlmacen(?Almacen $almacen): self
    {
        $this->almacen = $almacen;

        return $this;
    }

    public function getGrupoContable(): ?GrupoContable
    {
        return $this->grupoContable;
    }

    public function setGrupoContable(?GrupoContable $grupoContable): self
    {
        $this->grupoContable = $grupoContable;

        return $this;
    }
}
