<?php

namespace App\Entity\Central;

use App\Repository\Central\reportesRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="central.reportes")
 * @ORM\Entity(repositoryClass=reportesRepository::class)
 */
class reportes
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $tipo;

    /**
     * @ORM\Column(type="text")
     */
    private $modulo;

    /**
     * @ORM\Column(type="text")
     */
    private $nombre;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $sql;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $json = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipo(): ?int
    {
        return $this->tipo;
    }

    public function setTipo(int $tipo): self
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function getModulo(): ?string
    {
        return $this->modulo;
    }

    public function setModulo(string $modulo): self
    {
        $this->modulo = $modulo;

        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): self
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getSql(): ?string
    {
        return $this->sql;
    }

    public function setSql(?string $sql): self
    {
        $this->sql = $sql;

        return $this;
    }

    public function getJson(): ?array
    {
        return $this->json;
    }

    public function setJson(?array $json): self
    {
        $this->json = $json;

        return $this;
    }
}
