<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_gets_403_not_404_when_probing_account_ids(): void
    {
        $this->actingAs(User::factory()->create(), 'api');

        foreach (['getJson', 'deleteJson'] as $method) {
            $this->{$method}('/api/user/999999')->assertForbidden();
        }
        $this->putJson('/api/user/999999', ['name' => 'x'])->assertForbidden();
        $this->patchJson('/api/user/999999', ['name' => 'x'])->assertForbidden();
    }

    public function test_admin_receives_generic_404_for_missing_account(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');

        $this->getJson('/api/user/999999')->assertNotFound()
            ->assertExactJson(['message' => 'Not Found.']);
        $this->putJson('/api/user/999999', ['name' => 'x'])->assertNotFound();
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'api')->deleteJson('/api/user/'.$admin->id)->assertForbidden();
        $this->assertModelExists($admin);
    }

    public function test_admin_cannot_change_own_type_but_can_change_others(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin, 'api')->putJson('/api/user/'.$admin->id, ['type' => 'user'])
            ->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->assertSame('admin', $admin->fresh()->type);

        $this->putJson('/api/user/'.$admin->id, ['type' => 'admin', 'name' => 'Still admin'])->assertOk();

        $this->putJson('/api/user/'.$user->id, ['type' => 'admin'])->assertOk();
        $this->assertSame('admin', $user->fresh()->type);

        $this->putJson('/api/user/'.$user->id, ['type' => 'superuser'])
            ->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_regular_user_cannot_escalate_privileges_through_any_endpoint(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $this->putJson('/api/profile', ['name' => 'Me', 'email' => $user->email, 'type' => 'admin'])->assertOk();
        $this->putJson('/api/user/'.$user->id, ['type' => 'admin'])->assertForbidden();
        $this->patchJson('/api/user/'.$user->id, ['type' => 'admin'])->assertForbidden();
        $this->postJson('/api/user', [
            'name' => 'Sock puppet', 'email' => 'puppet@example.com',
            'password' => 'Puppet-password-123!', 'type' => 'admin',
        ])->assertForbidden();

        $this->assertSame('user', $user->fresh()->type);
        $this->assertDatabaseMissing('users', ['email' => 'puppet@example.com']);
    }

    public function test_type_is_not_mass_assignable(): void
    {
        $user = new User([
            'name' => 'Mass', 'email' => 'mass@example.com',
            'password' => 'Mass-assign-123!', 'type' => 'admin',
        ]);

        $this->assertNull($user->type);
        $this->assertSame(['name', 'email', 'password'], $user->getFillable());
        foreach ([Category::class, Tag::class, Product::class] as $model) {
            $this->assertNotEmpty((new $model)->getFillable(), $model.' must declare $fillable');
            $this->assertNotSame([], (new $model)->getGuarded(), $model.' must not be fully unguarded');
        }
    }

    public function test_serialized_users_never_expose_secrets(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'api')->getJson('/api/user')
            ->assertOk()
            ->assertJsonMissingPath('data.data.0.password')
            ->assertJsonMissingPath('data.data.0.remember_token');
        $this->assertArrayNotHasKey('password', $admin->toArray());
        $this->assertArrayNotHasKey('remember_token', $admin->toArray());
    }

    public function test_passwords_are_hashed_exactly_once(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'api')->postJson('/api/user', [
            'name' => 'Hashed', 'email' => 'hashed@example.com',
            'password' => 'Hashed-once-123!', 'type' => 'user',
        ])->assertOk();

        $user = User::where('email', 'hashed@example.com')->firstOrFail();
        $this->assertTrue(password_verify('Hashed-once-123!', $user->password));
    }

    public function test_catalogue_deletes_are_admin_only_and_writes_are_open_to_users(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();
        $tag = Tag::create(['name' => 'Tag']);

        $this->actingAs($user, 'api');
        $this->putJson('/api/category/'.$category->id, ['name' => 'Renamed'])->assertOk();
        $this->deleteJson('/api/category/'.$category->id)->assertForbidden();
        $this->deleteJson('/api/tag/'.$tag->id)->assertForbidden();
        $this->deleteJson('/api/product/'.$product->id)->assertForbidden();

        $this->actingAs(User::factory()->admin()->create(), 'api');
        $this->deleteJson('/api/category/'.$category->id)->assertOk();
        $this->deleteJson('/api/tag/'.$tag->id)->assertOk();
        $this->deleteJson('/api/product/'.$product->id)->assertOk();
    }
}
