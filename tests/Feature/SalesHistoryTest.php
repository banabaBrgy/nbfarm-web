<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_history_starts_with_every_recorded_sale(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->seedSales();

        $response = $this->actingAs($owner)->get(route('owner.sales.history'));

        $response->assertOk();
        $response->assertSee('Sales History');
        $response->assertSee('All recorded sales');
        $response->assertSee('Today');
        $response->assertSee('This month');
        $response->assertSee('All previous months');
        $response->assertSee('Custom range');
        $response->assertSee('North Store');
        $response->assertSee('South Store');
        $response->assertSee('West Store');
        $response->assertSee('East Store');
        $response->assertSee('₱750.00');
    }

    public function test_date_filters_limit_the_sales_list(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->seedSales();

        $this->actingAs($manager)
            ->get(route('manager.sales.history', ['range' => 'today']))
            ->assertOk()
            ->assertSee('North Store')
            ->assertDontSee('South Store')
            ->assertSee('₱100.00');

        $this->actingAs($manager)
            ->get(route('manager.sales.history', ['range' => 'month']))
            ->assertOk()
            ->assertSee('North Store')
            ->assertSee('South Store')
            ->assertDontSee('West Store')
            ->assertSee('₱300.00');

        $this->actingAs($manager)
            ->get(route('manager.sales.history', ['range' => 'previous']))
            ->assertOk()
            ->assertSee('West Store')
            ->assertSee('East Store')
            ->assertDontSee('North Store')
            ->assertSee('₱450.00');

        $this->actingAs($manager)
            ->get(route('manager.sales.history', ['range' => '2026-09']))
            ->assertOk()
            ->assertSee('West Store')
            ->assertDontSee('East Store')
            ->assertSee('₱400.00');

        $this->actingAs($manager)
            ->get(route('manager.sales.history', [
                'range' => 'custom',
                'from' => '2026-10-01',
                'to' => '2026-10-02',
            ]))
            ->assertOk()
            ->assertSee('South Store')
            ->assertDontSee('North Store')
            ->assertSee('₱200.00');
    }

    public function test_dashboard_card_links_to_the_full_history(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $owner = User::factory()->create(['role' => 'owner']);
        $this->seedSales();

        $managerPage = $this->actingAs($manager)->get(route('manager.dashboard'));
        $ownerPage = $this->actingAs($owner)->get(route('owner.dashboard'));

        $managerPage->assertOk();
        $ownerPage->assertOk();

        $managerPage->assertSee('This Month\'s Sales', false);
        $ownerPage->assertSee('This Month\'s Sales', false);
        $managerPage->assertSee('₱300.00', false);
        $ownerPage->assertSee('₱300.00', false);
        $managerPage->assertSee(route('manager.sales.history'), false);
        $ownerPage->assertSee(route('owner.sales.history'), false);
        $this->assertStringNotContainsString(
            'sales/history?range=month',
            $managerPage->getContent()
        );
    }

    private function seedSales(): void
    {
        Transaction::query()->insert([
            $this->sale('North Store', '2026-10-08', 100),
            $this->sale('South Store', '2026-10-02', 200),
            $this->sale('West Store', '2026-09-15', 400),
            $this->sale('East Store', '2026-08-01', 50),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sale(string $customer, string $date, float $price): array
    {
        return [
            'customer' => $customer,
            'transaction_date' => $date,
            'quantity' => 1,
            'price' => $price,
            'status' => 'Paid',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
