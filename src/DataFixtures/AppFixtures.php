<?php
// src/DataFixtures/AppFixtures.php

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AppFixtures extends Fixture
{
    /**
     * @var string[]
     */
    private array $phoneBrands = ['Apple', 'Samsung', 'Xiaomi', 'Google', 'OnePlus'];

    public function load(ObjectManager $manager): void
    {
        // Initialize Faker with French locale for realistic local data
        $faker = Factory::create('fr_FR');

        // ==========================================
        // 1. PRODUCT FIXTURES (SMARTPHONES)
        // ==========================================
        for ($i = 1; $i <= 20; $i++) {
            $product = new Product();
            $brand = $faker->randomElement($this->phoneBrands);

            $product->setBrand($brand)
                ->setModel($faker->words(2, true))
                ->setDescription($faker->paragraph(3))
                ->setPrice($faker->randomFloat(2, 299, 1299)) // Price between 299€ and 1299€
                ->setStock($faker->numberBetween(5, 150))
                ->setColor($faker->safeColorName)
                ->setStorage($faker->randomElement(['128 Go', '256 Go', '512 Go']))
                ->setCreatedAt(new \DateTimeImmutable());

            $manager->persist($product);
        }

        // ==========================================
        // 2. CLIENT (B2B) AND LINKED USER FIXTURES
        // ==========================================
        // Pre-hashing password using bcrypt (will be wired with Security/JWT later)
        $hashedPassword = password_hash('password123', PASSWORD_BCRYPT);

        for ($c = 1; $c <= 5; $c++) {
            $client = new Client();
            $companyName = $faker->company;

            // Generate a clean slug-like username (e.g., "sfr-telecom")
            $username = strtolower(str_replace(' ', '-', $companyName));

            $client->setUsername($username)
                ->setRoles(['ROLE_USER'])
                ->setPassword($hashedPassword)
                ->setCompanyName($companyName);

            $manager->persist($client);

            // Generate between 10 and 20 end-users for each B2B Client
            $numberOfUsers = $faker->numberBetween(10, 20);
            for ($u = 1; $u <= $numberOfUsers; $u++) {
                $user = new User();
                $user->setFirstName($faker->firstName)
                    ->setLastName($faker->lastName)
                    ->setEmail($faker->unique()->safeEmail) // Ensure email uniqueness
                    ->setCreatedAt(new \DateTimeImmutable())
                    ->setClient($client); // Establish the ManyToOne relationship

                $manager->persist($user);
            }
        }

        // Save everything into Wamp MySQL database
        $manager->flush();
    }
}
