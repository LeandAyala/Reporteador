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

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $estado;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $migracion;

    /** 
     * Se almacena el estado de la migración validando si esta se encuentra o no ejecutada
    */
    private $estadoMigracion;

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

    public function getEstado(): ?int
    {
        return $this->estado;
    }

    public function setEstado(?int $estado): self
    {
        $this->estado = $estado;

        return $this;
    }

    public function getMigracion(): ?string
    {
        return $this->migracion;
    }

    public function setMigracion(?string $migracion): self
    {
        $this->migracion = $migracion;

        return $this;
    }

    public function getEstadoMigracion(): ?int
    {
        /** 
         * En esta función se guarda el estado de cada migración
         * -----------------------------------------------------
         * @access public
        */

        return $this->estadoMigracion;
    }

    public function setEstadoMigracion(?int $estadoMigracion): self
    {
        /** 
         * En esta función se obtiene el estado de cada migración
         * ------------------------------------------------------
         * @access public
        */

        $this->estadoMigracion = $estadoMigracion;
        return $this;
    }
}
