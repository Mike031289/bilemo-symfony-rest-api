<?php
//src/Entity/Product.php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "La marque du téléphone ne peut pas être vide.")]
    #[Assert\Length(max: 255, maxMessage: "La marque ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $brand = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le modèle du téléphone ne peut pas être vide.")]
    #[Assert\Length(max: 255, maxMessage: "Le modèle ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $model = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La description du produit est obligatoire.")]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 0)]
    #[Assert\NotBlank(message: "Le prix est obligatoire.")]
    #[Assert\Positive(message: "Le prix doit être un montant supérieur à 0.")]
    private ?string $price = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "Le stock ne peut pas être nul.")]
    #[Assert\PositiveOrZero(message: "Le stock ne peut pas être négatif.")]
    private ?int $stock = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de création est obligatoire.")]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "La couleur est obligatoire.")]
    #[Assert\Length(max: 255, maxMessage: "La couleur ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $color = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "La capacité de stockage est obligatoire.")]
    #[Assert\Length(max: 255, maxMessage: "La capacité de stockage ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $storage = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(string $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getStorage(): ?string
    {
        return $this->storage;
    }

    public function setStorage(string $storage): static
    {
        $this->storage = $storage;

        return $this;
    }
}
