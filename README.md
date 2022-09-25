# Foundation

## Justification

A long time ago, while I was creating one of my applications for Mac OS X , I wanted to give the application the ability to "speak" AppleScript, make it scriptable.

In order to understand a little bit more what I was about to do, I made myself of a couple of books, one *AppleScript: The Definitive Guide* has a chapter called *Calculation and Repetition* and it starts like this.

>"Computers are good at calculation and repetition which happen to be exactly the things humans are not good at. Humans are liable to calculate inaccurately, and repetitive activity can make them careless and bored. The whole idea of having a computer is to have it take over in these situations."

Now this statement is true most of the time and it's pretty basic stuff, right? Well, yes, in a sense, but somehow from there my perspective on software development changed.

The maxim became, if you find something repetitive and/or boring, make a program and let the program complete the task  for you, that way you will have more time to think about other more creative things. To be efficient before throwing any code to the canvas, do the abstraction, make the design, implement the layers (of abstraction) and avoid repeating yourself.

Years ago I got the opportunity to work on a project for iOS (long before Swift) that required building a web service, at that time I didn't have much experience doing web development but I had a lot of experience building desktop applications so I took the job, everything went fine, end of story, well not really, over the years, more and more opportunities to do web development arrived and I started to use more and more web service's frameworks and now suddenly, I wanted to automate some things.

One thing you can notice about these kind of frameworks is that (even if is not on purpose and regardless the language) most APIs look a like, witch is a good thing because implementing things from one framework (or/and language) to another is less problematic.

But the problem is, between projects that the only thing that really changes is the model of the business you're working with but the essence of the implementation is virtually the same and you end up (at some level) making the same service over and over.

I started building something very primitive to help me deal with the repetitive part, something that I internally called *The singularity*, an sketch of *[Sabatier's Service](https://github.com/dantesabatier/Service)*, a utility that helped me to generate the necessary code to create web services in a bit.

But I wanted something more abstract and effective to manage the data part of the framework, I wanted something I little more like *[Apple's Core Data](https://developer.apple.com/documentation/coredata)* (a wonderful, very complex and beautifully designed framework), but in order to use *[Sabatier's Core Data](https://github.com/dantesabatier/CoreData)* (yes, we already have it), I needed to implement my very own version of *[Apple's Foundation](https://developer.apple.com/documentation/foundation)* and, this friends is the reason of the existence of this framework.

## What is Foundation?

The Foundation framework defines a base layer of functionality that is required for almost all applications.
This implementation is (sort of) a mixture between the Objective C and Swift implementations.

### What is implemented?

I implemented a lot but not everything (Foundation is a big boy), I implemented only what is necessary (maybe a little more) to meet our goal.

Here is a list of the main areas:

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
