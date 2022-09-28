# Foundation

## What is Foundation?

The Foundation framework defines a base layer of functionality that is required for almost all applications.

### What is implemented?

- Collections (array, set, dictionary, slice, range, flattened sequence, etc.) and sort descriptor.

```php
<?php

/** @var Set<ManagedObject> $result */
$result = new Set();
$result = $result->sorted([new SortDescriptor('name')]);
```

- The (profoundly beautiful) expressions and predicates, this is somewhat similar to using the relational model to filter collections using a code-enriched pseudo language based on mathematical logic, predicate logic or first-order logic.

```php
<?php

/** @var Set<ManagedObject> $result */
$result = new Set();
//normally you don't define predicates this complex but (if you need to), you can
if (!($predicate = Predicate::format("((%K BETWEEN \$DATES) && (SOME addresses.city.name BEGINSWITH[cd] %s) && (NONE addresses.street CONTAINS[cd] %s) && (10%3 >= 1) && (deposits.amount.value.@sum < 1.1*3.6) && (3+3.1 < 0.2**10) && (2-1.1 < 1001/11.1) && ({999.6, 1001}[1] > savings.value) && (SUBQUERY(addresses, \$address, \$address.street ENDSWITH[cd] %s).@count = %i) && (1 IN {0, 1, 2, 3, 5, 8} UNION {2, 4, 6, 10}) && (%K < TERNARY(%K MATCHES[c] %s, 30, 40)) && (FUNCTION(%s, 'validate', \$ID) != false) && (%s = %s))", new ArrayClass(['creationDate', 'Ángeles', 'Melrose', 'street', 1, 'age', 'name', 'jane', new Validator(), true, Expression::expressionForBlock(fn() => true)])))) {
    fatal_error("Oops, something went wrong");
}
$predicate = $predicate->withSubstitutionVariables(new Dictionary([
    "\$DATES" => new ArrayClass([Date::distantPast(), Date::distantFuture()]),
    "\$ID" => $person->hash()
]));
$result = $result->filtered($predicate);
```

- File system, a more efficient way to read, write and iterate through folders and document no matter what OS you are on (Windows, Linux, Mac), in the example we use FileManager to iterate over the contents of a folder.

```php
<?php

$fileManager = FileManager::default();
$urls = $fileManager->contentsOfDirectory($this->contentsURL, null, DirectoryEnumerationOptions::skipsHiddenFiles);
foreach ($urls as $url) {
    //...
}
```

- The url loading system, url request, url response, url session, data, download, upload tasks, etc.

```php
<?php

$url = new URL('https://...');
$request = new URLRequest($url);
$request->httpMethod = HTTPRequestMethod::post;
$request->setValueForHttpHeaderField('application/json', 'Content-Type');
$request->setValueForHttpHeaderField('key=SECRET', 'Authorization');
$request->httpBody = json_encode(['notification' => ['title' => 'Lorem Ipsum', 'body' => "Lorem ipsum dolor sit amet."], 'to' => 'KEY']);
$task = URLSession::shared()->dataTaskWithRequest($request, function (?string $data, ?URLResponse $response, ?Error $error): void {
    if ($error) {
        fatal_error("Failed to post notification: $error");
    }
    if ($response instanceof HTTPURLResponse) {
        debuglog(sprintf("%s %s", $response->statusCode, HTTPURLResponse::localizedString($response->statusCode)));
    }
    //do something with data
});
$task->resume();
```

- KVC (Key-Value Coding), a mechanism that enables objects that implement the interface to provide indirect access to their properties.

```php
<?php

$person = new Person();
$names = $person->valueForKeyPath('addresses.city.name');
// addresses is a to-many relationship (of Person), city is a to-one relationship (of Address) and name is an attribute (of City), then for instance the result would be ['Veracruz', 'Ciudad de México']
```

- KVO (Key-Value Observing), a mechanism that allows objects to be notified of property changes specific to other objects.

```php
<?php

//...
$operation->observe('isFinished', KeyValueObservingOptions::new, function (Operation $operation): void {
    if ($operation->isFinished) {
        //implementation continues
    }
});
```

- Notifications (Notification and Notification Center), register to receive notifications from other objects.

```php
<?php

NotificationCenter::default()->addObserverForName('NotificationName', $obj, function (Notification $notification): void {
    //do something useful
});
```

- Lots of additions to the PHP standard library and much more.

```php
<?php

if (string_is_equal('publicación', 'Publicacion', CompareOptions::caseInsensitive | CompareOptions::diacriticInsensitive)) {
    //implementation continues
}
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
