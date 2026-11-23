<?php

namespace Tests\Feature\Security;

use App\Enums\UserType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Who may do what: account management is for administrators only (without
 * revealing which account ids exist), privileges cannot be raised through
 * any endpoint, and catalogue deletes are for administrators.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function accountRequestsForAnUnknownId(): iterable
    {
        yield 'GET' => ['GET', '/api/user/999999'];
        yield 'PUT' => ['PUT', '/api/user/999999'];
        yield 'PATCH' => ['PATCH', '/api/user/999999'];
        yield 'DELETE' => ['DELETE', '/api/user/999999'];
    }

    #[DataProvider('accountRequestsForAnUnknownId')]
    public function test_non_admin_gets_403_not_404_when_probing_account_ids(string $method, string $uri): void
    {
        $this->actingAsUser();

        $this->json($method, $uri, ['name' => 'x'])->assertForbidden();
    }

    public function test_admin_receives_generic_404_for_missing_account(): void
    {
        $this->actingAsAdmin();

        $this->assertGenericNotFound($this->getJson('/api/user/999999'));
        $this->putJson('/api/user/999999', ['name' => 'x'])->assertNotFound();
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->actingAsAdmin();

        $this->deleteJson('/api/user/'.$admin->id)->assertForbidden();
        $this->assertModelExists($admin);
    }

    public function test_admin_cannot_change_own_type_but_can_change_others(): void
    {
        $admin = $this->actingAsAdmin();
        $user = User::factory()->create();

        $this->putJson('/api/user/'.$admin->id, ['type' => 'user'])
            ->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->assertSame(UserType::Admin, $admin->refresh()->type);

        $this->putJson('/api/user/'.$admin->id, ['type' => 'admin', 'name' => 'Still admin'])->assertOk();

        $this->putJson('/api/user/'.$user->id, ['type' => 'admin'])->assertOk();
        $this->assertSame(UserType::Admin, $user->refresh()->type);

        $this->putJson('/api/user/'.$user->id, ['type' => 'superuser'])
            ->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_regular_user_cannot_escalate_privileges_through_any_endpoint(): void
    {
        $user = $this->actingAsUser();

        $this->putJson('/api/profile', ['name' => 'Me', 'email' => $user->email, 'type' => 'admin'])->assertOk();
        $this->putJson('/api/user/'.$user->id, ['type' => 'admin'])->assertForbidden();
        $this->patchJson('/api/user/'.$user->id, ['type' => 'admin'])->assertForbidden();
        $this->postJson('/api/user', [
            'name' => 'Sock puppet', 'email' => 'puppet@example.com',
            'password' => 'Puppet-password-123!', 'type' => 'admin',
        ])->assertForbidden();

        $this->assertSame(UserType::User, $user->refresh()->type);
        $this->assertDatabaseMissing('users', ['email' => 'puppet@example.com']);
    }

    public function test_type_is_not_mass_assignable(): void
    {
        $user = new User([
            'name' => 'Mass', 'email' => 'mass@example.com',
            'password' => 'Mass-assign-123!', 'type' => 'admin',
        ]);

        $this->assertArrayNotHasKey('type', $user->getAttributes());
        $this->assertSame(['name', 'email', 'password'], $user->getFillable());
    }

    /**
     * @return iterable<string, array{class-string<Model>}>
     */
    public static function catalogueModels(): iterable
    {
        yield 'Category' => [Category::class];
        yield 'Tag' => [Tag::class];
        yield 'Product' => [Product::class];
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('catalogueModels')]
    public function test_catalogue_models_declare_fillable_attributes(string $model): void
    {
        $this->assertNotEmpty((new $model)->getFillable(), $model.' must declare $fillable');
        $this->assertNotSame([], (new $model)->getGuarded(), $model.' must not be fully unguarded');
    }

    public function test_serialized_users_never_expose_secrets(): void
    {
        $admin = $this->actingAsAdmin();
        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonMissingPath('data.data.0.password')
            ->assertJsonMissingPath('data.data.0.remember_token');
        $this->assertArrayNotHasKey('password', $admin->toArray());
        $this->assertArrayNotHasKey('remember_token', $admin->toArray());
    }

    public function test_passwords_are_hashed_exactly_once(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/user', [
            'name' => 'Hashed', 'email' => 'hashed@example.com',
            'password' => 'Hashed-once-123!', 'type' => 'user',
        ])->assertOk();

        $user = User::where('email', 'hashed@example.com')->firstOrFail();
        $this->assertTrue(password_verify('Hashed-once-123!', $user->password));
    }

    public function test_catalogue_deletes_are_admin_only_and_writes_are_open_to_users(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();
        $tag = Tag::factory()->create();

        $this->actingAsUser();
        $this->putJson('/api/category/'.$category->id, ['name' => 'Renamed'])->assertOk();
        $this->deleteJson('/api/category/'.$category->id)->assertForbidden();
        $this->deleteJson('/api/tag/'.$tag->id)->assertForbidden();
        $this->deleteJson('/api/product/'.$product->id)->assertForbidden();

        $this->actingAsAdmin();
        $this->deleteJson('/api/category/'.$category->id)->assertOk();
        $this->deleteJson('/api/tag/'.$tag->id)->assertOk();
        $this->deleteJson('/api/product/'.$product->id)->assertOk();
    }
}
