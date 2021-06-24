# On Page ® PHP library

With this library you can easy query your data using an On Page ® API token.

## Installation
```
composer config repositories.onpage vcs 'https://github.com/onpage-dev/onpage-php.git'
composer require onpage-dev/onpage-php:^v1
```

Of course, remember to include the composer autoload:
```php
<?php
require 'vendor/autoload.php';
```

## Usage

### Setup
```php
$api = new \OnPage\Api('acme-inc', $api_token);
```

### Get structure information
```php
// Retrieve info about the schema:
echo $api->schema->label;

// Retrieve a resource given its name or ID
$res = $api->schema->res('products');
foreach ($res->fields() as $field) {
    echo "$field->label\n";
    echo "$field->name\n";
    echo "$field->type\n";
    echo "$field->is_multiple\n";
    echo "$field->is_translatable\n";
}
```

### Query your data
```php
// Retrieve all records of a resource (returns a laravel collection of \OnPage\Thing)
$products = $api->query('products')->all();
foreach ($products as $prod) {
    // ...
}

// Get only the first item
$prod = $api->query('products')->first();
```

### Filters
```php
// Retrieve all records of a resource (returns a laravel collection of \OnPage\Thing)
$api->query('products')
    ->where('_id', 42) // = is the default operator
    ->first();

// Other filters
$api->query('products')
    ->where('name', 'like', 'shoes') // you can specify a different operator
    ->where('category.name', 'Nike') // you can query relations
    ->where('dimension', '>', 10) // you get it
    ->all();
```

### Get thing values
```php
$cat = $api->query('categories')->first();
echo $cat->val('name');
echo $cat->val('dimension');
echo $cat->val('description', 'fr'); // you can specify a language
```
#### Files
For `image` and `file` fields, the returned value will be an instance of `\OnPage\File::class`.
To get a file or image url use the `->link()` function. The link will point to the original file.

```php
# original size
$product->val('specsheet')->name // icecream-spec.pdf
$product->val('specsheet')->token // R417C0YAM90RF
$product->val('specsheet')->link() // https://acme-inc.onpage.it/api/storage/R417C0YAM90RF?name=icecream-spec.pdf
```

To turn images into a thumbnail add an array of options as shown below:
```php
# maintain proportions width 200px
$product->val('cover_image')->link(['x' => 200])

# maintain proportions height 100px
$product->val('cover_image')->link(['y' => 100])

# crop image to width 200px and height 100px
$product->val('cover_image')->link(['x' => 200, 'y' => 100])

# maintain proportions and contain in a rectangle of width 200px and height 100px 
$product->val('cover_image')->link(['x' => 200, 'y' => 100, 'contain' => true])

# convert the image to png (default is jpg)
$product->val('cover_image')->link(['x' => 200, 'format' => 'png'])
```

### Get thing relations
```php
$cat = $api->query('categories')->first();
$subcategories = $cat->rel('subcategories');
foreach ($subcategories as $subcategory) {
    echo $subcategory->val('name');
}
```

### Preload thing relations
```php
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
```

