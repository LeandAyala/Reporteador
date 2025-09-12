<?php

namespace App\Entity\Central;

use App\Repository\Central\companiaRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="central.compania")
 * @ORM\Entity(repositoryClass=companiaRepository::class)
 */
class compania
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
    private $nit;

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
    private $telefonos;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $logocompania;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNit(): ?string
    {
        return $this->nit;
    }

    public function setNit(?string $nit): self
    {
        $this->nit = $nit;

        return $this;
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

    public function getTelefonos(): ?string
    {
        return $this->telefonos;
    }

    public function setTelefonos(?string $telefonos): self
    {
        $this->telefonos = $telefonos;

        return $this;
    }

    public function getLogocompania(): ?string
    {
        return $this->logocompania;
    }

    public function setLogocompania(?string $logocompania): self
    {
        $this->logocompania = $logocompania;

        return $this;
    }
}
