## PHP Library: Spam Doctor

Check your email content for spam possibilities before being spammed.
PHP Library to check if content contains spam words.

---
### Using the Library

#### Installation

Intall library in PHP project using composer
```
composer require tbetool/spam-doctor
```

#### Using Library
```
$spamDoctor = new SpamDoctor();

$spamDoctor->check($text_content);
```
#### `check()` parameters

1. **text_content**: Text content to check for spam. You can pass both plain text and html content
2. **is_html** *(boolean)*: if **text_content** is **HTML**, set second parameter to **true**. *Default: false*.


#### Get output
```php
$spamDoctor->getSpamItems();
```

returns array of spam keyword and their count of occurrences.
```php
[
   0 => [
     item => 'welcome',
     count => 4
   ]
]
```
---
```php
$spamDoctor->getSpamPositions();
```

returns array of positions of the spam items in ascending order.
```php
[
   0 => 4,
   1 => 12,
   2 => 25
]
```
---
```php
$spamDoctor->getHighlighted();
```

returns complete string with spam keywords highlighted in red color. If HTML content is provided to check, 
this will return only the text of the HTML content.  
To get HTML content highlighted, pass **true** as parameter.

---
```php
$spamDoctor->getHighlighted(true);
```

returns complete HTML content with spam keywords highlighted.

---
```php
$spamDoctor->getSpamDictionary();
```

returns array of complete list of words used to detect spam contents.

---
### Replace Rule
Library also support replace rule which can be used to replace spamming text
on the go. You can pass json rule in key-value pair where key in the spam text
is replaced by the corresponding value.

To set the replace rule, use following function before calling `check()`

```php
$replace_rule = [
    'o' => 0,
    'O' => 0,
    '*' => '_'
];

$replace_rule = json_encode($replace_rule);

$spamDoctor->setReplaceRule($replace_rule);
```
Above example will replace **o** and **O** (upper and lowercase of O) with **0** (zero)

You can also pass common letter to replace with if letter in `replace_rule` is not found by passing `*` key with 
appropriate value.

For example, in above example if a spam word contains **o** or **O* will be replaced with **0** otherwise will
add **_** (underscore) to random place in the word. 

---
### Self Learning

This library learns itself as it processes the spam contents. It generates a dictionary file
in **data** directory name **spam_data.txt**.

You can also teach the doctor by passing json data to the `teachDoctor()` function.

```php
$spamDoctor->teachDoctor($json_data);
```

**NOTE**: Json data can be upto 2-Dimensional array

---

### Exception Handling
_Ex:_
```
try {
    $spamDoctor->check($html_content, true);
} catch (Exception $exception) {
    echo $exception->getMessage();
}
```

---
### Bug Reporting

If you found any bug, create an [issue](https://github.com/TBETool/spam-doctor/issues/new).

---
### Support and Contribution

Something is missing? 
* `Fork` the repositroy
* Make your contribution
* make a `pull request`
