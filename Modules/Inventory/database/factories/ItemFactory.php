<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Inventory\Models\Item::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $furnitureTypes = ['Kursi', 'Meja', 'Lemari', 'Sofa', 'Rak', 'Tempat Tidur', 'Nakas', 'Meja Makan', 'Kursi Kantor', 'Meja Rias', 'Meja TV', 'Sofa Bed', 'Lemari Pakaian'];
        $materials = ['Kayu Jati', 'Mahoni', 'Rotan', 'Besi', 'Aluminium', 'Kaca', 'Multiplek', 'HPL', 'Bambu'];
        $colors = ['Coklat Tua', 'Putih', 'Hitam', 'Abu-abu', 'Natural', 'Coklat Muda', 'Walnut', 'Oak'];

        $name = $this->faker->randomElement($furnitureTypes) . ' ' . 
                $this->faker->randomElement($materials) . ' ' . 
                $this->faker->randomElement($colors);

        $purchase_price = $this->faker->numberBetween(100, 5000) * 1000;
        
        return [
            'code' => 'ITM-' . $this->faker->unique()->numerify('####'),
            'name' => $name,
            'description' => $this->faker->sentence(),
            'image' => 'items/image-placeholder.webp', // Placeholder bawaan
            'unit_id' => $this->faker->numberBetween(1, 5), // Asumsi unit ID 1-5 ada
            'type_id' => $this->faker->numberBetween(1, 3), // Asumsi type ID 1-3 ada
            'category_id' => $this->faker->numberBetween(1, 4), // Asumsi category ID 1-4 ada
            'sub_category_id' => null, // Optional
            'purchase_price' => $purchase_price,
            'selling_price' => $purchase_price + ($purchase_price * $this->faker->randomElement([0.1, 0.2, 0.3, 0.5])),
            'min_stock' => $this->faker->numberBetween(5, 20),
            'max_stock' => $this->faker->numberBetween(50, 200),
            'is_active' => $this->faker->boolean(90), // 90% aktif
            'requires_label' => $this->faker->boolean(30), // 30% butuh label
            'user_id' => 1, // Admin
        ];
    }
}

