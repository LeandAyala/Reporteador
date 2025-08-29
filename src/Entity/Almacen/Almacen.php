<?php

namespace App\Entity\Almacen;

use App\Entity\Bodega\Bodega;
use App\Entity\GrupoContable\GrupoContable;
use App\Entity\Productos\Producto;
use App\Repository\Almacen\AlmacenRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="almacen.almacenes")
 * @ORM\Entity(repositoryClass=AlmacenRepository::class)
 */
class Almacen
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
     * @ORM\OneToMany(targetEntity=Bodega::class, mappedBy="almacen")
     */
    private $bodegas;

    /**
     * @ORM\OneToMany(targetEntity=GrupoContable::class, mappedBy="almacen")
     */
    private $grupoContables;

    /**
     * @ORM\OneToMany(targetEntity=Producto::class, mappedBy="almacen")
     */
    private $productos;

    public function __construct()
    {
        $this->bodegas = new ArrayCollection();
        $this->grupoContables = new ArrayCollection();
        $this->productos = new ArrayCollection();
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

    /**
     * @return Collection<int, Bodega>
     */
    public function getBodegas(): Collection
    {
        return $this->bodegas;
    }

    public function addBodega(Bodega $bodega): self
    {
        if (!$this->bodegas->contains($bodega)) {
            $this->bodegas[] = $bodega;
            $bodega->setAlmacen($this);
        }

        return $this;
    }

    public function removeBodega(Bodega $bodega): self
    {
        if ($this->bodegas->removeElement($bodega)) {
            // set the owning side to null (unless already changed)
            if ($bodega->getAlmacen() === $this) {
                $bodega->setAlmacen(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, GrupoContable>
     */
    public function getGrupoContables(): Collection
    {
        return $this->grupoContables;
    }

    public function addGrupoContable(GrupoContable $grupoContable): self
    {
        if (!$this->grupoContables->contains($grupoContable)) {
            $this->grupoContables[] = $grupoContable;
            $grupoContable->setAlmacen($this);
        }

        return $this;
    }

    public function removeGrupoContable(GrupoContable $grupoContable): self
    {
        if ($this->grupoContables->removeElement($grupoContable)) {
            // set the owning side to null (unless already changed)
            if ($grupoContable->getAlmacen() === $this) {
                $grupoContable->setAlmacen(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Producto>
     */
    public function getProductos(): Collection
    {
        return $this->productos;
    }

    public function addProducto(Producto $producto): self
    {
        if (!$this->productos->contains($producto)) {
            $this->productos[] = $producto;
            $producto->setAlmacen($this);
        }

        return $this;
    }

    public function removeProducto(Producto $producto): self
    {
        if ($this->productos->removeElement($producto)) {
            // set the owning side to null (unless already changed)
            if ($producto->getAlmacen() === $this) {
                $producto->setAlmacen(null);
            }
        }

        return $this;
    }
}
