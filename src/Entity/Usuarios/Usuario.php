<?php

namespace App\Entity\Usuarios;

use App\Entity\Facturas\Factura;
use App\Entity\Facturas\Pedido;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use App\Repository\Usuarios\UsuarioRepository;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Table(name="usuarios.usuario")
 * @ORM\Entity(repositoryClass=UsuarioRepository::class)
 */
class Usuario
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
     * @ORM\Column(type="text", nullable=true)
     */
    private $direccion;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $telefono;

    /**
     * @ORM\OneToMany(targetEntity=Factura::class, mappedBy="usuario")
     */
    private $facturas;

    /**
     * @ORM\OneToMany(targetEntity=Factura::class, mappedBy="usuCrea")
     */
    private $facturasUC;

    /**
     * @ORM\OneToMany(targetEntity=Pedido::class, mappedBy="usuario")
     */
    private $pedidos;

    public function __construct()
    {
        $this->facturas = new ArrayCollection();
        $this->facturasUC = new ArrayCollection();
        $this->pedidos = new ArrayCollection();
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

    public function getDireccion(): ?string
    {
        return $this->direccion;
    }

    public function setDireccion(?string $direccion): self
    {
        $this->direccion = $direccion;

        return $this;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function setTelefono(?string $telefono): self
    {
        $this->telefono = $telefono;

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
            $factura->setUsuario($this);
        }

        return $this;
    }

    public function removeFactura(Factura $factura): self
    {
        if ($this->facturas->removeElement($factura)) {
            // set the owning side to null (unless already changed)
            if ($factura->getUsuario() === $this) {
                $factura->setUsuario(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Factura>
     */
    public function getFacturasUC(): Collection
    {
        return $this->facturasUC;
    }

    public function addFacturasUC(Factura $facturasUC): self
    {
        if (!$this->facturasUC->contains($facturasUC)) {
            $this->facturasUC[] = $facturasUC;
            $facturasUC->setUsuCrea($this);
        }

        return $this;
    }

    public function removeFacturasUC(Factura $facturasUC): self
    {
        if ($this->facturasUC->removeElement($facturasUC)) {
            // set the owning side to null (unless already changed)
            if ($facturasUC->getUsuCrea() === $this) {
                $facturasUC->setUsuCrea(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Pedido>
     */
    public function getPedidos(): Collection
    {
        return $this->pedidos;
    }

    public function addPedido(Pedido $pedido): self
    {
        if (!$this->pedidos->contains($pedido)) {
            $this->pedidos[] = $pedido;
            $pedido->setUsuario($this);
        }

        return $this;
    }

    public function removePedido(Pedido $pedido): self
    {
        if ($this->pedidos->removeElement($pedido)) {
            // set the owning side to null (unless already changed)
            if ($pedido->getUsuario() === $this) {
                $pedido->setUsuario(null);
            }
        }

        return $this;
    }
}
