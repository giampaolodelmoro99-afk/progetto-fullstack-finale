<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    actingAsAdmin();
});

function imageProduct(): Product
{
    return Product::factory()->for(Category::factory())->create(['name' => 'Cuffie Aria']);
}

function storedImage(ProductImage $image): string
{
    return Storage::disk('public')->get($image->path);
}

it('appends the uploaded images after the existing ones, with the product name as alt', function () {
    $product = imageProduct();
    ProductImage::factory()->for($product)->create(['path' => 'products/uno.webp', 'alt' => 'Uno', 'position' => 1]);
    ProductImage::factory()->for($product)->create(['path' => 'products/due.webp', 'alt' => 'Due', 'position' => 2]);

    $response = $this->post("/api/v1/admin/products/{$product->id}/images", [
        'images' => [
            UploadedFile::fake()->image('fronte.jpg', 300, 200),
            UploadedFile::fake()->image('lato.png', 300, 200),
            UploadedFile::fake()->image('retro.webp', 300, 200),
        ],
    ])->assertCreated();

    $images = $product->images()->get();
    expect($images->pluck('position')->all())->toBe([1, 2, 3, 4, 5])
        ->and($images->pluck('alt')->all())->toBe(['Uno', 'Due', 'Cuffie Aria', 'Cuffie Aria', 'Cuffie Aria']);
    $response->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0', ['id' => $images[0]->id, 'url' => '/storage/products/uno.webp', 'alt' => 'Uno', 'position' => 1])
        ->assertJsonPath('data.2.alt', 'Cuffie Aria')
        ->assertJsonPath('data.4.position', 5);
});

it('stores every image as webp with a random name, whatever the format sent', function () {
    $product = imageProduct();

    $this->post("/api/v1/admin/products/{$product->id}/images", [
        'images' => [
            UploadedFile::fake()->image('foto.jpg', 300, 200),
            UploadedFile::fake()->image('foto.png', 300, 200),
            UploadedFile::fake()->image('foto.webp', 300, 200),
        ],
    ])->assertCreated();

    $images = $product->images()->get();
    expect($images->pluck('path')->unique())->toHaveCount(3);
    $images->each(function (ProductImage $image) {
        expect($image->path)->toMatch('/^products\/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.webp$/')
            ->and(getimagesizefromstring(storedImage($image))['mime'])->toBe('image/webp');
    });
    expect(Storage::disk('public')->allFiles())->toHaveCount(3);
});

it('removes the exif data, gps position included, and applies the orientation', function () {
    $product = imageProduct();
    $photo = new UploadedFile(base_path('tests/fixtures/photo-with-gps.jpg'), 'foto.jpg', 'image/jpeg', null, true);
    $original = file_get_contents(base_path('tests/fixtures/photo-with-gps.jpg'));
    expect($original)->toContain('Exif')->toContain('2026:09:15');

    $this->post("/api/v1/admin/products/{$product->id}/images", ['images' => [$photo]])->assertCreated();

    $stored = storedImage($product->images()->sole());
    $size = getimagesizefromstring($stored);
    expect($stored)->not->toContain('Exif')->not->toContain('2026:09:15')
        ->and($size['mime'])->toBe('image/webp')
        ->and([$size[0], $size[1]])->toBe([20, 40]);
});

it('converts a palette png to webp and keeps its transparency', function () {
    $product = imageProduct();
    $palette = imagecreate(40, 20);
    $transparent = imagecolorallocate($palette, 255, 255, 255);
    imagecolortransparent($palette, $transparent);
    imagefilledrectangle($palette, 20, 0, 39, 19, imagecolorallocate($palette, 255, 0, 0));
    ob_start();
    imagepng($palette);
    $png = (string) ob_get_clean();
    expect(imageistruecolor(imagecreatefromstring($png)))->toBeFalse();

    $this->post("/api/v1/admin/products/{$product->id}/images", [
        'images' => [UploadedFile::fake()->createWithContent('logo.png', $png)],
    ])->assertCreated();

    $stored = storedImage($product->images()->sole());
    $webp = imagecreatefromstring($stored);
    expect(getimagesizefromstring($stored)['mime'])->toBe('image/webp')
        ->and((imagecolorat($webp, 5, 10) >> 24) & 0x7F)->toBe(127)
        ->and((imagecolorat($webp, 30, 10) >> 24) & 0x7F)->toBe(0);
});

it('answers 422 for an image that passes the validation but cannot be decoded', function () {
    $product = imageProduct();
    ob_start();
    imagepng(imagecreatetruecolor(40, 20));
    $truncated = substr((string) ob_get_clean(), 0, 33);

    $this->post("/api/v1/admin/products/{$product->id}/images", [
        'images' => [UploadedFile::fake()->createWithContent('rotta.png', $truncated)],
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.images', ["Non è stato possibile leggere l'immagine rotta.png."]);

    expect($product->images()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('reduces the longest side to 2000 pixels keeping the proportions', function (int $width, int $height, array $expected) {
    $product = imageProduct();

    $this->post("/api/v1/admin/products/{$product->id}/images", [
        'images' => [UploadedFile::fake()->image('grande.jpg', $width, $height)],
    ])->assertCreated();

    $size = getimagesizefromstring(storedImage($product->images()->sole()));
    expect([$size[0], $size[1]])->toBe($expected);
})->with([
    'landscape of 3000 pixels' => [3000, 1500, [2000, 1000]],
    'portrait of 3000 pixels' => [1200, 3000, [800, 2000]],
    'exactly 2000 pixels' => [2000, 600, [2000, 600]],
    'small image' => [640, 480, [640, 480]],
]);

it('accepts an image of exactly 5 MB', function () {
    $product = imageProduct();

    $this->post("/api/v1/admin/products/{$product->id}/images", [
        'images' => [UploadedFile::fake()->image('foto.jpg', 100, 100)->size(5120)],
    ])->assertCreated();
});

it('rejects invalid uploads', function (string $case, string $field) {
    $product = imageProduct();

    $images = match ($case) {
        'gif' => [UploadedFile::fake()->image('animazione.gif', 100, 100)],
        'too heavy' => [UploadedFile::fake()->image('foto.jpg', 100, 100)->size(5121)],
        'pdf' => [UploadedFile::fake()->create('scheda.pdf', 100, 'application/pdf')],
        'too wide' => [UploadedFile::fake()->image('panorama.png', 8001, 1)],
        'too tall' => [UploadedFile::fake()->image('striscia.png', 1, 8001)],
        'eleven files' => array_map(fn (int $index) => UploadedFile::fake()->image("foto-{$index}.jpg", 10, 10), range(1, 11)),
        'no files' => [],
    };

    $this->post("/api/v1/admin/products/{$product->id}/images", ['images' => $images], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect($product->images()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'gif' => ['gif', 'images.0'],
    'file of 5121 KB' => ['too heavy', 'images.0'],
    'pdf' => ['pdf', 'images.0'],
    'width over 8000 pixels' => ['too wide', 'images.0'],
    'height over 8000 pixels' => ['too tall', 'images.0'],
    'more than 10 files' => ['eleven files', 'images'],
    'no files' => ['no files', 'images'],
]);

it('accepts exactly 10 files', function () {
    $product = imageProduct();

    $this->post("/api/v1/admin/products/{$product->id}/images", [
        'images' => array_map(fn (int $index) => UploadedFile::fake()->image("foto-{$index}.jpg", 10, 10), range(1, 10)),
    ])->assertCreated()->assertJsonCount(10, 'data');
});

it('answers 413 in italian to a body above the post size limit', function () {
    $product = imageProduct();

    $this->call('POST', "/api/v1/admin/products/{$product->id}/images", server: [
        'CONTENT_LENGTH' => (string) (64 * 1024 * 1024 + 1),
        'HTTP_ACCEPT' => 'application/json',
    ])
        ->assertStatus(413)
        ->assertExactJson(['message' => 'I file inviati superano la dimensione massima consentita.']);
});

it('removes the files already written when the transaction fails', function () {
    $product = imageProduct();
    ProductImage::creating(fn () => throw new RuntimeException('Insert non riuscito'));

    $this->post("/api/v1/admin/products/{$product->id}/images", [
        'images' => [UploadedFile::fake()->image('uno.jpg', 10, 10), UploadedFile::fake()->image('due.jpg', 10, 10)],
    ], ['Accept' => 'application/json'])->assertServerError();

    expect(Storage::disk('public')->allFiles())->toBe([])
        ->and(ProductImage::count())->toBe(0);
});

it('deletes an image with its file and compacts the positions', function () {
    $product = imageProduct();
    $disk = Storage::disk('public');
    foreach (['uno', 'due', 'tre'] as $name) {
        $disk->put("products/{$name}.webp", $name);
    }
    $first = ProductImage::factory()->for($product)->create(['path' => 'products/uno.webp', 'position' => 1]);
    $second = ProductImage::factory()->for($product)->create(['path' => 'products/due.webp', 'position' => 2]);
    $third = ProductImage::factory()->for($product)->create(['path' => 'products/tre.webp', 'position' => 3]);

    $this->deleteJson("/api/v1/admin/products/{$product->id}/images/{$first->id}")->assertNoContent();

    $this->assertModelMissing($first);
    $disk->assertMissing('products/uno.webp');
    $disk->assertExists('products/due.webp');
    $this->assertDatabaseHas('product_images', ['id' => $second->id, 'position' => 1]);
    $this->assertDatabaseHas('product_images', ['id' => $third->id, 'position' => 2]);
});

it('keeps the file when the deletion is rolled back', function () {
    $product = imageProduct();
    Storage::disk('public')->put('products/uno.webp', 'uno');
    $image = ProductImage::factory()->for($product)->create(['path' => 'products/uno.webp', 'position' => 1]);
    ProductImage::deleted(fn () => throw new RuntimeException('Delete non riuscito'));

    $this->deleteJson("/api/v1/admin/products/{$product->id}/images/{$image->id}")->assertServerError();

    $this->assertModelExists($image);
    Storage::disk('public')->assertExists('products/uno.webp');
});

it('answers 404 for an image of another product', function () {
    $product = imageProduct();
    $foreign = ProductImage::factory()->for(imageProduct())->create(['path' => 'products/altro.webp']);

    $this->deleteJson("/api/v1/admin/products/{$product->id}/images/{$foreign->id}")->assertNotFound();

    $this->assertModelExists($foreign);
});

it('answers 404 for the images of a deleted product', function () {
    $product = Product::factory()->for(Category::factory())->trashed()->create();

    $this->post("/api/v1/admin/products/{$product->id}/images", [
        'images' => [UploadedFile::fake()->image('foto.jpg', 10, 10)],
    ], ['Accept' => 'application/json'])->assertNotFound();
});
