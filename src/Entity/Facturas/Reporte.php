<?php

namespace App\Entity\Facturas;

use App\Repository\Facturas\ReporteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="facturas.reportes")
 * @ORM\Entity(repositoryClass=ReporteRepository::class)
 */
class Reporte
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
    private $sql;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $json = [];

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
