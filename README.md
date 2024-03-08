# On Page ® PHP library

With this library you can easy query your data using an On Page ® API token.

## Installation

To install this library in your existing composer project, or update to the latest version, you can launch:

```
composer require onpage-dev/onpage-php:^v1.2
```

Of course, remember to include the composer autoload:

```php
<?php
require 'vendor/autoload.php';
```

## Usage

### Setup

To start, you need to connect to an On Page Schema (project) using the following function:

```php
$schema = \OnPage\Schema::fromToken('MY-API-TOKEN');
```

### Query your data

```php
// Retrieve all records of a resource (returns a laravel collection of \OnPage\Thing)
$products = $schema->query('products')->all();
foreach ($products as $prod) {
    // ...
}

// Get only the first item
$prod = $api->query('products')->first();
```

### Filters and deletions

```php
// Retrieve all records of a resource (returns a laravel collection of \OnPage\Thing)
// NOTE: system fields must be prefixed with the _ symbol
$api->query('products')
    ->where('_id', 42) // = is the default operator
    ->where('_created_at', '<', '2024-01-01 00:00:00')
    ->where('_updated_at', '>=', '2024-01-01 00:00:00')
    ->first();

// Other filters
$api->query('products')
    ->where('name', 'like', 'shoes') // you can specify a different operator
    ->where('category.name', 'Nike') // you can query relations
    ->where('dimension', '>', 10) // you get it
    ->whereIn('size', [42, 43, 44])
    ->all(); // returns a collection with all your records

// Join filters with the OR clause: get all products for the adidas or nike brands
$api->query('products')
    ->whereOneOf(function(\OnPage\QueryBuilder $q) {
        ->where('price', 'Nike')
        ->where('brand', 'Adidas')
    })
    ->all();

// Advanced filtering by relation
$api->query('products')

    // only retrieve products that have at least one associated category
    ->whereHas('category')

    // only retrieve products that have zero associated categories
    ->whereHas('category', null, '=', 0)

    // only retrieve products that have at least one variant with at least one color
    ->whereHas('variant.color')

    // Only get products that have at least one category that satisfies these 2 conditions:
    ->whereHas('category', function(\OnPage\QueryBuilder $q) {
      $q->where('is_online', true);
      $q->where('name', 'like', 'shoes');
    })

    ->all();

// You can just simply move data to trash the same way:
$api->query('products')
    ->where(...)
    ->delete();

// Or delete elements bypassing the trash:
$api->query('products')
    ->where(...)
    ->delete(forever: true);

// Filter by element status trash, any
$api->query('products')
    ->where(...)
    ->delete(forever: true);
```

### Get thing values

Use the val() function to get the first value in a field.
Use the values() function to get all values in a field as a collection.

```php
$cat = $api->query('categories')->first();
echo $cat->id; // item ID
echo $cat->created_at; // creation date e.g. 2022-01-01 23:33:00
echo $cat->updated_at; // date of last update to any of the fields e.g. 2022-01-01 23:33:00
echo $cat->order; // global order number (float)
echo $cat->val('name');
echo $cat->val('dimension');
echo $cat->val('description', 'zh'); // you can specify a language

// Or set the default language
$schema->lang = 'zh';
echo $cat->val('name'); // 再见

// The values function is useful for multivalue fields, it will return a laravel collection of values.
echo $cat->values('bullet_points')->implode('; ');
```

#### Files

For `image` and `file` fields, the returned value will be an instance of `\OnPage\File::class`.
To get a file or image url use the `->link()` function. The link will point to the original file.

```php
# original size
$product->file('specsheet')->name // icecream-spec.pdf
$product->file('specsheet')->token // R417C0YAM90RF
$product->file('specsheet')->link() // https://acme-inc.onpage.it/api/storage/R417C0YAM90RF?name=icecream-spec.pdf

// Force download (by default the browser will try to preview the file, e.g. pdf/images)
$product->file('specsheet')->link([ 'download' => true ])

// Customize file name
$product->file('specsheet')->link([ 'name' => 'my custom name.pdf' ])
```

To turn images into a thumbnail add an array of options as shown below:

```php
# maintain proportions width 200px
$product->file('cover_image')->link(['x' => 200])

# maintain proportions height 100px
$product->file('cover_image')->link(['y' => 100])

# crop image to width 200px and height 100px
$product->file('cover_image')->link(['x' => 200, 'y' => 100])

# maintain proportions and contain in a rectangle of width 200px and height 100px
$product->file('cover_image')->link(['x' => 200, 'y' => 100, 'contain' => true])

# convert the image to png (default thumbnail extension is png)
$product->file('cover_image')->link(['x' => 200, 'ext' => 'png'])
```

#### Other utilities

```php
// Speed things up by only loading some fields
$api->query('products')->loadFields(['title'])->all();

// You can also limit the fields on a related item
$api->query('products')
->with([ 'colors' ])
->loadRelationFields('colors', ['name', 'image']) // only load 2 fields for the "color" relation
->all();


// Get a mapping between two fields or a field and the thing ID
$api->query('products')->map('code');
// [ 'MYSKU100' => 1827, 'MYSKU101' => 1828, ... ]

$api->query('products')->map('code', 'title');
// [ 'MYSKU100' => 'Apples', 'MYSKU101' => 'Bananas', ... ]
```

### Get thing relations

```php
// You need to specify the relations using the "with" method
$cat = $api->query('categories')
    ->with('subcategories')
    ->first();
$subcategories = $cat->rel('subcategories');
foreach ($subcategories as $subcategory) {
    echo $subcategory->val('name');
}

// You can also preload nested subcategories
$cat = $api->query('categories')
    ->with('subcategories.articles.colors')
    ->first();

// Or you can pass the relations as an array
$products_with_colors = $api->query('products')
    ->with([ 'colors', 'categories' ])
    ->all();
foreach ($products_with_colors as $prod) {
    echo $prod->val('name');
    foreach ($prod->colors as $color) {
        echo $color->val('name');
    }
}

// If you need to filter the related items you want to download, you can do this:
$cat = $api->query('categories')
    ->with('subcategories.articles.colors')
    ->filterRelation('subcategories.articles', function(\OnPage\QueryBuilder $q) {
        $q->where('is_online', true);
    })
    ->first();

```

# Creating and updating things

To create or update a record, you need to create a Thing Editor.
There are two ways to get a Thing Editor:

1. Using the **Resource Writer**
2. Calling `->editor()` on a `Op\Thing`

## Using the Resource Writer (first method)

This class allows you to edit many records at once.
You can easily obtain the editor calling:

```php
$writer = $api->resource('categories')->writer();
```

Now that you have a **Resource Writer**, you can use it to create things:

```php
$editor = $writer->createThing();
$editor->set('name', 'Element 1');
$editor->setRel('category', [ 12345 ]); // array with category IDs
```

...and to update existing things:

```php
$editor = $writer->updateThing(736251); // The id of the element you want to update
$editor->set('description', 'Element 1 description');
```

Finally, you need to send the request to the On Page server:

```php
// this will create and update all the things as requested above
$writer->save();
```

## Updating a single item (second method)

```php
$product = $api->query('products')->where('name', 'Plastic Duck')->first();

$editor = $product->editor();
$editor->set('description', 'This yellow plastic duck will be your best friend');
$editor->set('description', '这只黄色塑料鸭将是你最好的朋友', 'zh'); // you can specify language

// Save all the edits at once using the save method
$editor->save();

```

## Limiting modified languages

By default, even if you update a single language, the writer will delete the data on other languages. If you only need to edit certain languages and maintain the current values for the others, you can specify which languages you are working on as follows:

```php
// Update the chinese description without deleting the english description:
$editor = $product->editor();
$editor->setLangs([ 'zh' ]);
$editor->set('description', '这只');
$editor->save();
```

## Updating translations

Just add the language code as the third argument to the `set` function:

```php
// Update the value in the default language
$editor->set('description', 'This yellow plastic duck will be your best friend');

// Specify another the language
$editor->set('description', '这只黄色塑料鸭将是你最好的朋友', 'zh');
```

## Updating files

You can upload files to On Page using the FileUpload class:

```php
$editor->set('image', new \OnPage\FileUpload('/path/to/bird.jpg')); // upload file
```

Or you can also upload a file using a public URL:

```php
$editor->set('image', 'https://mysite.com/bird_cover.jpg'); // specify file by url
```

## Updating multivalue fields

For multivalue fields you only need to replace `->set` with `->setValues` and pass an array of values as the second argument:

```php
$editor->setValues('bullet_points', [
    'Durable plastic',
    'Bright yellow color',
    'Compostable'
]);
```

## Updating relations

To update relations, you can use the `->setRel(relation_name, related_ids)`:

```php
$editor->setRel('features', [
    425790,
    547023,
    240289,
]);
```

### Get structure information

```php
// Retrieve info about the schema:
echo $schema->label;

// Retrieve a resource given its name or ID
$res = $schema->resource('products');
foreach ($res->fields() as $field) {
    echo "$field->getLabel()\n"; // Main image
    echo "$field->getLabel('zh')\n"; // "Main Image" but in Chinese
    echo "$field->name\n"; // "main_image"
    echo "$field->type\n"; // string|file|image|real|int|text|...
    echo "$field->unit\n"; // null|kg|...
    echo "$field->is_multiple\n"; // true|false
    echo "$field->is_translatable\n"; // true|false
}
```
