<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function makeProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Test Product',
            'description' => 'A product used in tests.',
            'price' => 19.99,
            'quantity' => 5,
        ], $attributes));
    }

    public function test_product_index_page_loads(): void
    {
        $this->get('/admin/products')->assertOk();
    }

    public function test_list_shows_existing_products(): void
    {
        $products = collect(['Keyboard', 'Monitor', 'Mouse'])
            ->map(fn (string $name) => $this->makeProduct(['name' => $name]));

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords($products)
            ->assertCanRenderTableColumn('name')
            ->assertCanRenderTableColumn('price')
            ->assertCanRenderTableColumn('quantity')
            ->assertCanRenderTableColumn('created_at');
    }

    public function test_products_can_be_searched_by_name(): void
    {
        $keyboard = $this->makeProduct(['name' => 'Keyboard']);
        $monitor = $this->makeProduct(['name' => 'Monitor']);

        Livewire::test(ListProducts::class)
            ->searchTable('Keyboard')
            ->assertCanSeeTableRecords([$keyboard])
            ->assertCanNotSeeTableRecords([$monitor]);
    }

    public function test_products_can_be_sorted_by_price(): void
    {
        $cheap = $this->makeProduct(['name' => 'Cheap', 'price' => 5.00]);
        $pricey = $this->makeProduct(['name' => 'Pricey', 'price' => 500.00]);

        Livewire::test(ListProducts::class)
            ->sortTable('price')
            ->assertCanSeeTableRecords([$cheap, $pricey], inOrder: true)
            ->sortTable('price', 'desc')
            ->assertCanSeeTableRecords([$pricey, $cheap], inOrder: true);
    }

    public function test_a_product_can_be_created(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Desk Lamp',
                'description' => 'Adjustable LED lamp.',
                'price' => 42.50,
                'quantity' => 12,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', [
            'name' => 'Desk Lamp',
            'description' => 'Adjustable LED lamp.',
            'price' => 42.50,
            'quantity' => 12,
        ]);
    }

    public function test_a_product_can_be_created_without_a_description(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Notebook',
                'price' => 3.25,
                'quantity' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', [
            'name' => 'Notebook',
            'description' => null,
        ]);
    }

    public function test_required_fields_are_validated_on_create(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => null,
                'price' => null,
                'quantity' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'price' => 'required',
                'quantity' => 'required',
            ]);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_quantity_may_not_be_negative(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Bad Quantity',
                'price' => 10.00,
                'quantity' => -1,
            ])
            ->call('create')
            ->assertHasFormErrors(['quantity' => 'min']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_price_must_be_numeric(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Bad Price',
                'price' => 'not-a-number',
                'quantity' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['price' => 'numeric']);
    }

    public function test_a_product_can_be_viewed(): void
    {
        $product = $this->makeProduct(['name' => 'Viewable']);

        $this->get("/admin/products/{$product->getKey()}")->assertOk();

        Livewire::test(ViewProduct::class, ['record' => $product->getRouteKey()])
            ->assertSchemaStateSet([
                'name' => 'Viewable',
                'price' => '19.99',
                'quantity' => 5,
            ]);
    }

    public function test_edit_page_loads_existing_data(): void
    {
        $product = $this->makeProduct(['name' => 'Editable']);

        $this->get("/admin/products/{$product->getKey()}/edit")->assertOk();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSchemaStateSet([
                'name' => 'Editable',
                'price' => '19.99',
                'quantity' => 5,
            ]);
    }

    public function test_a_product_can_be_edited(): void
    {
        $product = $this->makeProduct(['name' => 'Before']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm([
                'name' => 'After',
                'price' => 99.95,
                'quantity' => 3,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'name' => 'After',
            'price' => 99.95,
            'quantity' => 3,
        ]);
    }

    public function test_a_product_can_be_deleted(): void
    {
        $product = $this->makeProduct(['name' => 'Deletable']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertDatabaseMissing('products', ['id' => $product->getKey()]);
    }
}
