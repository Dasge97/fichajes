<?php

namespace App\Modulo\Acceso\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rol')]
class Rol
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 60, unique: true)]
    private string $codigo;

    #[ORM\Column(length: 120)]
    private string $nombre;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $descripcion;

    #[ORM\Column]
    private bool $esSistema;

    public function __construct(string $id, string $codigo, string $nombre, ?string $descripcion = null, bool $esSistema = true)
    {
        $this->id = $id;
        $this->codigo = $codigo;
        $this->nombre = $nombre;
        $this->descripcion = $descripcion;
        $this->esSistema = $esSistema;
    }

    public function getId(): string { return $this->id; }
    public function getCodigo(): string { return $this->codigo; }
    public function getNombre(): string { return $this->nombre; }
}
