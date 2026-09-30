<?php

use Illuminate\Support\Facades\Schema;

/**
 * @return array<string, array{0: string, 1: bool, 2: string|null}>
 */
function actualColumns(string $table): array
{
    return collect(Schema::getColumns($table))
        ->mapWithKeys(fn (array $column) => [$column['name'] => [$column['type'], $column['nullable'], $column['default']]])
        ->all();
}

/**
 * @return array<string, array{0: list<string>, 1: bool}>
 */
function actualIndexes(string $table): array
{
    return collect(Schema::getIndexes($table))
        ->reject(fn (array $index) => $index['primary'])
        ->mapWithKeys(fn (array $index) => [$index['name'] => [$index['columns'], $index['unique']]])
        ->sortKeys()
        ->all();
}

/**
 * @return array<string, array{0: string, 1: string}>
 */
function actualForeignKeys(string $table): array
{
    return collect(Schema::getForeignKeys($table))
        ->mapWithKeys(fn (array $key) => [$key['columns'][0] => [$key['foreign_table'], $key['on_delete']]])
        ->sortKeys()
        ->all();
}

it('creates the tables with the columns of the er schema', function (string $table, array $columns) {
    expect(actualColumns($table))->toBe($columns);
})->with([
    'users' => ['users', [
        'id' => ['bigint unsigned', false, null],
        'name' => ['varchar(255)', false, null],
        'email' => ['varchar(255)', false, null],
        'email_verified_at' => ['timestamp', true, null],
        'password' => ['varchar(255)', false, null],
        'role' => ['varchar(20)', false, 'customer'],
        'password_changed_at' => ['timestamp', true, null],
        'two_factor_secret' => ['text', true, null],
        'two_factor_recovery_codes' => ['text', true, null],
        'two_factor_confirmed_at' => ['timestamp', true, null],
        'two_factor_last_used_step' => ['bigint unsigned', true, null],
        'remember_token' => ['varchar(100)', true, null],
        'created_at' => ['timestamp', true, null],
        'updated_at' => ['timestamp', true, null],
    ]],
    'addresses' => ['addresses', [
        'id' => ['bigint unsigned', false, null],
        'user_id' => ['bigint unsigned', false, null],
        'full_name' => ['varchar(255)', false, null],
        'street' => ['varchar(255)', false, null],
        'city' => ['varchar(100)', false, null],
        'postal_code' => ['char(5)', false, null],
        'province' => ['char(2)', false, null],
        'country' => ['char(2)', false, 'IT'],
        'phone' => ['varchar(20)', false, null],
        'is_default' => ['tinyint(1)', false, '0'],
        'created_at' => ['timestamp', true, null],
        'updated_at' => ['timestamp', true, null],
    ]],
    'categories' => ['categories', [
        'id' => ['bigint unsigned', false, null],
        'name' => ['varchar(50)', false, null],
        'slug' => ['varchar(255)', false, null],
        'description' => ['varchar(255)', true, null],
        'created_at' => ['timestamp', true, null],
        'updated_at' => ['timestamp', true, null],
    ]],
    'products' => ['products', [
        'id' => ['bigint unsigned', false, null],
        'category_id' => ['bigint unsigned', false, null],
        'name' => ['varchar(255)', false, null],
        'slug' => ['varchar(255)', false, null],
        'sku' => ['varchar(64)', false, null],
        'short_description' => ['varchar(160)', false, null],
        'description' => ['text', true, null],
        'specifications' => ['json', false, null],
        'price' => ['int unsigned', false, null],
        'stock' => ['int unsigned', false, '0'],
        'is_active' => ['tinyint(1)', false, '0'],
        'created_at' => ['timestamp', true, null],
        'updated_at' => ['timestamp', true, null],
        'deleted_at' => ['timestamp', true, null],
    ]],
    'product_images' => ['product_images', [
        'id' => ['bigint unsigned', false, null],
        'product_id' => ['bigint unsigned', false, null],
        'path' => ['varchar(255)', false, null],
        'alt' => ['varchar(255)', false, null],
        'position' => ['smallint unsigned', false, null],
        'created_at' => ['timestamp', true, null],
        'updated_at' => ['timestamp', true, null],
    ]],
    'carts' => ['carts', [
        'id' => ['bigint unsigned', false, null],
        'user_id' => ['bigint unsigned', false, null],
        'created_at' => ['timestamp', true, null],
        'updated_at' => ['timestamp', true, null],
    ]],
    'cart_items' => ['cart_items', [
        'id' => ['bigint unsigned', false, null],
        'cart_id' => ['bigint unsigned', false, null],
        'product_id' => ['bigint unsigned', false, null],
        'quantity' => ['smallint unsigned', false, null],
        'created_at' => ['timestamp', true, null],
        'updated_at' => ['timestamp', true, null],
    ]],
    'orders' => ['orders', [
        'id' => ['bigint unsigned', false, null],
        'user_id' => ['bigint unsigned', false, null],
        'number' => ['varchar(20)', true, null],
        'status' => ['varchar(20)', false, null],
        'subtotal' => ['bigint unsigned', false, null],
        'shipping_cost' => ['int unsigned', false, null],
        'total' => ['bigint unsigned', false, null],
        'shipping_address' => ['json', false, null],
        'terms_accepted_at' => ['timestamp', false, null],
        'paid_at' => ['timestamp', true, null],
        'shipped_at' => ['timestamp', true, null],
        'delivered_at' => ['timestamp', true, null],
        'cancelled_at' => ['timestamp', true, null],
        'created_at' => ['timestamp', true, null],
        'updated_at' => ['timestamp', true, null],
    ]],
    'order_items' => ['order_items', [
        'id' => ['bigint unsigned', false, null],
        'order_id' => ['bigint unsigned', false, null],
        'product_id' => ['bigint unsigned', true, null],
        'product_name' => ['varchar(255)', false, null],
        'product_sku' => ['varchar(64)', false, null],
        'unit_price' => ['int unsigned', false, null],
        'quantity' => ['smallint unsigned', false, null],
        'line_total' => ['bigint unsigned', false, null],
        'created_at' => ['timestamp', true, null],
        'updated_at' => ['timestamp', true, null],
    ]],
    'order_status_changes' => ['order_status_changes', [
        'id' => ['bigint unsigned', false, null],
        'order_id' => ['bigint unsigned', false, null],
        'from_status' => ['varchar(20)', true, null],
        'to_status' => ['varchar(20)', false, null],
        'changed_by' => ['bigint unsigned', true, null],
        'reason' => ['varchar(255)', true, null],
        'created_at' => ['timestamp', false, null],
    ]],
    'payments' => ['payments', [
        'id' => ['bigint unsigned', false, null],
        'order_id' => ['bigint unsigned', false, null],
        'provider' => ['varchar(32)', false, null],
        'provider_reference' => ['varchar(255)', true, null],
        'amount' => ['bigint unsigned', false, null],
        'status' => ['varchar(20)', false, null],
        'card_last_four' => ['char(4)', false, null],
        'created_at' => ['timestamp', true, null],
        'updated_at' => ['timestamp', true, null],
    ]],
    'email_change_requests' => ['email_change_requests', [
        'id' => ['bigint unsigned', false, null],
        'user_id' => ['bigint unsigned', false, null],
        'new_email' => ['varchar(255)', false, null],
        'token_hash' => ['char(64)', false, null],
        'cancel_token_hash' => ['char(64)', false, null],
        'expires_at' => ['timestamp', false, null],
        'confirmed_at' => ['timestamp', true, null],
        'cancelled_at' => ['timestamp', true, null],
        'created_at' => ['timestamp', true, null],
        'updated_at' => ['timestamp', true, null],
    ]],
    'security_events' => ['security_events', [
        'id' => ['bigint unsigned', false, null],
        'user_id' => ['bigint unsigned', false, null],
        'type' => ['varchar(50)', false, null],
        'ip_address' => ['varchar(45)', true, null],
        'user_agent' => ['text', true, null],
        'metadata' => ['json', true, null],
        'created_at' => ['timestamp', false, null],
    ]],
]);

it('creates the indexes of the er schema', function (string $table, array $indexes) {
    expect(actualIndexes($table))->toBe($indexes);
})->with([
    'users' => ['users', [
        'users_email_unique' => [['email'], true],
    ]],
    'addresses' => ['addresses', [
        'addresses_user_id_foreign' => [['user_id'], false],
    ]],
    'categories' => ['categories', [
        'categories_name_unique' => [['name'], true],
        'categories_slug_unique' => [['slug'], true],
    ]],
    'products' => ['products', [
        'products_category_id_foreign' => [['category_id'], false],
        'products_created_at_index' => [['created_at'], false],
        'products_is_active_index' => [['is_active'], false],
        'products_price_index' => [['price'], false],
        'products_sku_unique' => [['sku'], true],
        'products_slug_unique' => [['slug'], true],
        'products_stock_index' => [['stock'], false],
    ]],
    'product_images' => ['product_images', [
        'product_images_product_id_position_index' => [['product_id', 'position'], false],
    ]],
    'carts' => ['carts', [
        'carts_user_id_unique' => [['user_id'], true],
    ]],
    'cart_items' => ['cart_items', [
        'cart_items_cart_id_product_id_unique' => [['cart_id', 'product_id'], true],
        'cart_items_product_id_foreign' => [['product_id'], false],
    ]],
    'orders' => ['orders', [
        'orders_created_at_index' => [['created_at'], false],
        'orders_number_unique' => [['number'], true],
        'orders_paid_at_index' => [['paid_at'], false],
        'orders_status_paid_at_index' => [['status', 'paid_at'], false],
        'orders_user_id_foreign' => [['user_id'], false],
    ]],
    'order_items' => ['order_items', [
        'order_items_order_id_foreign' => [['order_id'], false],
        'order_items_product_id_foreign' => [['product_id'], false],
    ]],
    'order_status_changes' => ['order_status_changes', [
        'order_status_changes_changed_by_foreign' => [['changed_by'], false],
        'order_status_changes_order_id_created_at_index' => [['order_id', 'created_at'], false],
    ]],
    'payments' => ['payments', [
        'payments_order_id_foreign' => [['order_id'], false],
    ]],
    'email_change_requests' => ['email_change_requests', [
        'email_change_requests_cancel_token_hash_unique' => [['cancel_token_hash'], true],
        'email_change_requests_token_hash_unique' => [['token_hash'], true],
        'email_change_requests_user_id_foreign' => [['user_id'], false],
    ]],
    'security_events' => ['security_events', [
        'security_events_user_id_created_at_index' => [['user_id', 'created_at'], false],
    ]],
]);

it('declares the delete behaviour of every foreign key', function (string $table, array $foreignKeys) {
    expect(actualForeignKeys($table))->toBe($foreignKeys);
})->with([
    'addresses' => ['addresses', ['user_id' => ['users', 'cascade']]],
    'products' => ['products', ['category_id' => ['categories', 'restrict']]],
    'product_images' => ['product_images', ['product_id' => ['products', 'cascade']]],
    'carts' => ['carts', ['user_id' => ['users', 'cascade']]],
    'cart_items' => ['cart_items', ['cart_id' => ['carts', 'cascade'], 'product_id' => ['products', 'cascade']]],
    'orders' => ['orders', ['user_id' => ['users', 'restrict']]],
    'order_items' => ['order_items', ['order_id' => ['orders', 'cascade'], 'product_id' => ['products', 'set null']]],
    'order_status_changes' => ['order_status_changes', ['changed_by' => ['users', 'restrict'], 'order_id' => ['orders', 'cascade']]],
    'payments' => ['payments', ['order_id' => ['orders', 'restrict']]],
    'email_change_requests' => ['email_change_requests', ['user_id' => ['users', 'cascade']]],
    'security_events' => ['security_events', ['user_id' => ['users', 'cascade']]],
]);

it('does not create the personal access tokens and passkeys tables', function () {
    expect(Schema::hasTable('personal_access_tokens'))->toBeFalse()
        ->and(Schema::hasTable('passkeys'))->toBeFalse();
});
