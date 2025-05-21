<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['income', 'expense'];
        $type = $this->faker->randomElement($types);

        return [
            'account_id' => Account::factory(),
            'category_id' => Category::inRandomOrder()->first() ?? null,
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'type' => $type,
            'description' => $this->faker->sentence(),
            'transaction_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'is_reconciled' => $this->faker->boolean(70),
            'reference' => $this->faker->optional(0.7)->bothify('REF-####'),
        ];
    }

    /**
     * Indicate that the transaction is an income.
     *
     * @return static
     */
    public function income()
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'income',
            ];
        });
    }

    /**
     * Indicate that the transaction is an expense.
     *
     * @return static
     */
    public function expense()
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'expense',
            ];
        });
    }

    /**
     * Indicate that the transaction is reconciled.
     *
     * @return static
     */
    public function reconciled()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_reconciled' => true,
            ];
        });
    }

    /**
     * Indicate that the transaction is not reconciled.
     *
     * @return static
     */
    public function notReconciled()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_reconciled' => false,
            ];
        });
    }
}
