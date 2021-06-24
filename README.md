# On Page ® PHP library

With this library you can easy query your data using an On Page ® API token.

## Installation
composer config repositories.onpage vcs 'https://github.com/onpage-dev/laravel-plugin.git'
composer require onpage-dev/onpage-php:^v1

## Usage

### Setup
```php
$api = new \OnPage\Api('acme-inc', $api_token);
```

### Get structure information
```php
// Retrieve info about the schema:
echo $this->api->schema->label;

// Retrieve a resource given its name or ID
$res = $this->api->schema->res('products');
foreach ($res->fields() as $field) {
    echo "$field->label\n";
    echo "$field->name\n";
    echo "$field->type\n";
    echo "$field->is_multiple\n";
    echo "$field->is_translatable\n";
}
```

### Get data
```php
// Retrieve all records of a resource (returns a laravel collection of \OnPage\Thing)
$products = $api->query('products')->all();
foreach ($products as $prod) {
    // ...
}

// Get only the first product
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

